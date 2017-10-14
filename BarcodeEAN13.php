<?php

/**
 * Class BarcodeEAN13
 * Create an EAN13 Barcode image.
 *
 * https://www.barcodingfonts.com/pdf/EAN.pdf
 * https://www.stimulsoft.com/en/documentation/online/user-manual/index.html?report_internals_barcodes_linear_barcodes_ean_upc_based_ean-13.htm
 */
class BarcodeEAN13
{
    public $font;
    
    public $number;
    
    public $scale;
    
    /**
     *  Value beetwen
     *  A value between 0 and 127.
     *  0 indicates completely opaque while
     *  127 indicates completely transparent.
     *  
     * @var int $alpha
     */
    public $alpha = 127;
    
    private $key;
    
    private $bars;
    
    private $image;
    
    private $width;
    
    private $height;
    
    public static $PARITY_KEY = array(
        0 => "000000", 1 => "001011", 2 => "001101", 3 => "001110",
        4 => "010011", 5 => "011001", 6 => "011100", 7 => "010101",
        8 => "010110", 9 => "011010"
    );
    
    public static $LEFT_PARITY = array(
        // Odd Encoding
        0 => array(
            0 => "0001101", 1 => "0011001", 2 => "0010011", 3 => "0111101",
            4 => "0100011", 5 => "0110001", 6 => "0101111", 7 => "0111011",
            8 => "0110111", 9 => "0001011"
        ),
        // Even Encoding
        1 => array (
            0 => "0100111", 1 => "0110011", 2 => "0011011", 3 => "0100001",
            4 => "0011101", 5 => "0111001", 6 => "0000101", 7 => "0010001",
            8 => "0001001", 9 => "0010111"
        )
    );
    
    public static $RIGHT_PARITY = array(
        0 => "1110010", 1 => "1100110", 2 => "1101100", 3 => "1000010",
        4 => "1011100", 5 => "1001110", 6 => "1010000", 7 => "1000100",
        8 => "1001000", 9 => "1110100"
    );
    
    public static $GUARD = array(
        'start' => "101", 'middle' => "01010", 'end' => "101"
    );
    
    /**
     * @param string $ean
     * @return number
     */
    public static function checksum($ean) {
        $even=true;
        $esum=0;
        $osum=0;
        
        for ($i = strlen($ean)-1; $i >= 0; $i--) {
            if ($even) {
            	$esum += $ean[$i];
            }
            else {
            	$osum += $ean[$i];
            }
            $even = !$even;
        }
        return (10-((3*$esum+$osum)%10))%10;
    }
    
    /**
     * @param string $number
     * @return string
     */
    private function prepareNumber($number) {
    	$numberLength = strlen($number);
    	
    	if($numberLength > 13) {
    		throw new Exception('Number as string length over 13 signs can not be shoting by automat.');
    	}
    	
    	if(!preg_match('/[0-9]/', $number)) {
    		throw new Exception('Number have non-digits signs.');
    	}
    	
    	if($numberLength < 12) {
    		$needZeroLength = 12 - $numberLength;
    		do {
    			$number = '0'.$number;
    		} while($needZeroLength--);
    	}
    	
    	return $number;
    }
    
    /**
     * @param int $scale
     * @return int
     */
    private function prepareScale($scale) {
    	$scale = intval($scale);
    	if($scale < 2) {
    		$scale = 2;
    	}
    	elseif ($scale > 12) {
    		$scale = 12;
    	}
    	
    	return $scale;
    }
    
    /**
     * Create the EAN13 barcode
     * @param string	$number		- is the max 13 digit barcode to be displayed.
     * @param int		$scale		- is the scale of the image in integers. The scale will not go lower than 2 or greater than 12
     * @param string	$fontpath	- 
     * @throws Exception
     */
    public function __construct($number, $fontpath, $scale=2)
    {
    	$this->number = $this->prepareNumber($number);
    	$this->scale = $this->prepareScale($scale);
    	
        /* Get the parity key, which is based on the first digit. */
    	$this->key = self::$PARITY_KEY[substr($this->number, 0, 1)];
        $this->font = $fontpath;
        
        /* The checksum (13th digit) can be calculated or supplied */
        if (strlen($this->number) === 12) {
        	$this->number .= self::checksum($this->number);
        }
        $this->bars = $this->_encode();
        $this->_createImage();
        $this->_drawBars();
        $this->_drawText();
    }
    
    public function __destruct()
    {
        imagedestroy($this->image);
    }
    
    /**
     * The following incantations use the parity key (based off the
     * first digit of the unencoded number) to encode the first six
     * digits of the barcode. The last 6 use the same parity.
     *
     * So, if the key is 010101, the first digit (of the first six
     * digits) uses odd parity encoding. The second uses even. The
     * third uses odd, and so on.
     */
    protected function _encode()
    {
        $barcode[] = self::$GUARD['start'];
        for($i=1; $i<=strlen($this->number)-1; $i++)
        {
            if($i < 7) {
            	$barcode[] = self::$LEFT_PARITY[$this->key[$i-1]][substr($this->number, $i, 1)];
            }
            else {
            	$barcode[] = self::$RIGHT_PARITY[substr($this->number, $i, 1)];
            }
            if($i == 6) {
            	$barcode[] = self::$GUARD['middle'];
            }
        }
        $barcode[] = self::$GUARD['end'];
        return $barcode;
    }
    /**
     * Create the image.
     *
     * The Height is 60 times the scale and the width is simply
     * 180% of the height.
     */
    protected function _createImage()
    {
        $this->height = $this->scale * 60;
        $this->width  = 1.8 * $this->height;
        $this->image = imagecreate($this->width, $this->height);
        imagecolorallocatealpha($this->image, 0xFF, 0xFF, 0xFF, $this->alpha);
    }
    
    /**
     * Draw the actual bars themselves.
     *
     * We have defined some constants. MAX is the y-value for the maximum
     * height a bar should go. FLOOR is the y-value for the minimum height.
     *
     * The differences in margin for MAX and FLOOR are because most of the
     * barcode doesn't extend to the bottom, only the guards do.
     *
     * WIDTH is the actual width of the bars.
     *
     * X is the starting position of the bars, which is a fifth of the way
     * into the image.
     *
     * To draw the bars, we translate a binary string into bars:
     *
     * 10111001 - bar, empty, bar, bar, bar, empty, empty, bar
     */
    protected function _drawBars()
    {
        $bar_color = imagecolorallocate($this->image, 0x00, 0x00, 0x00);
        $MAX   = $this->height * 0.025;
        $FLOOR = $this->height * 0.825;
        $WIDTH = $this->scale;

        $x = ($this->height * 0.2) - $WIDTH;
        foreach($this->bars as $bar) {
            $tall = 0;
            if(strlen($bar)==3 || strlen($bar)==5) {
            	$tall = ($this->height * 0.15);
            }
            for($i = 1; $i <= strlen($bar); $i++) {
                if(substr($bar, $i-1, 1)==='1') {
                	imagefilledrectangle($this->image, $x, $MAX, $x + $WIDTH, $FLOOR + $tall, $bar_color);
                }
                $x += $WIDTH;
            }
        }
    }
    /**
     * Draw the text:
     *
     * The first digit is left of the first guard. The kerning
     * is how much space is in between the individual characters.
     *
     * We add kerning after the first character to skip over the
     * first guard. Then we do it again after the 6th character
     * to skip over the second guard.
     *
     * We don't need to skip over the last guard.
     *
     * The fontsize is 7 times the scale.
     * X is the start point, which is .05 a way into the image
     */
    protected function _drawText()
    {
        $x = $this->width * 0.05;
        $y = $this->height * 0.96;
        $text_color = imagecolorallocate($this->image, 0x00, 0x00, 0x00);
        $fontsize = $this->scale * 7;
        $kerning = $fontsize * 1;
        for($i=0; $i<strlen($this->number); $i++)
        {
            imagettftext($this->image, $fontsize, 0, $x, $y, $text_color, $this->font, $this->number[$i]);
            if($i==0 || $i==6) {
            	$x += $kerning*0.5;
            }
            $x += $kerning;
        }
    }
    /**
     * Return the barcode's image by reference.
     */
    public function &image()
    {
        return $this->image;
    }
    /**
     * Send the headers and display the barcode.
     */
    public function display($filename=null)
    {
        if(empty($filename)) {
            header("Content-Type: image/png;");
            $filename = null;
        }
        else {
        	$dirPath = dirname($filename);
        	if(!file_exists($dirPath)) {
        		throw new Exception('No such directory ['.$dirPath.']');
        	}
        }
        imagepng($this->image, $filename);
    }
}

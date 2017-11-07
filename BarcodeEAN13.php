<?php
/**
 * Class \Barcode\Ean13
 *
 * @author      Wojciech Niewiadomski <wojtek.niewiadomski@bold.net.pl>
 * @package     Barcode
 */
namespace Barcode;

/**
 * Class Ean13
 * @package Barcode
 */
class Ean13
{
    public $font;

    public $number;

    /** @var float $scale */
    public $scale;

    /**
     *  Value beetwen
     *  A value between 0 and 127.
     *  0 indicates completely opaque while
     *  127 indicates completely transparent.
     *
     * @var int $alpha
     */
    public $alpha = 0;

    protected $key;

    protected $bars;

    protected $image;

    protected $width;

    protected $height;

    protected static $parityMatrix = [
        'key' => [
            0 => "000000", 1 => "001011", 2 => "001101", 3 => "001110",
            4 => "010011", 5 => "011001", 6 => "011100", 7 => "010101",
            8 => "010110", 9 => "011010"
        ],
        'left' => [
            [
                0 => "0001101", 1 => "0011001", 2 => "0010011", 3 => "0111101",
                4 => "0100011", 5 => "0110001", 6 => "0101111", 7 => "0111011",
                8 => "0110111", 9 => "0001011"
            ],
            [
                0 => "0100111", 1 => "0110011", 2 => "0011011", 3 => "0100001",
                4 => "0011101", 5 => "0111001", 6 => "0000101", 7 => "0010001",
                8 => "0001001", 9 => "0010111"
            ]
        ],
        'right' => [
            0 => "1110010", 1 => "1100110", 2 => "1101100", 3 => "1000010",
            4 => "1011100", 5 => "1001110", 6 => "1010000", 7 => "1000100",
            8 => "1001000", 9 => "1110100"
        ],
        'guards' => [
            'start' => "101",
            'middle' => "01010",
            'end' => "101"
        ]
    ];

    /**
     * @param string $ean
     * @return number
     */
    public function checksum($ean)
    {
        $even = true;
        $esum = 0;
        $osum = 0;

        for ($i = strlen($ean) - 1; $i >= 0; $i--) {
            if ($even) {
                $esum += $ean[$i];
            } else {
                $osum += $ean[$i];
            }
            $even = !$even;
        }
        return (10 - ((3 * $esum + $osum) % 10)) % 10;
    }

    /**
     * @param string $number
     * @return string
     * @throws BarcodeException
     */
    protected function prepareNumber($number)
    {
        $numberLength = strlen($number);

        if ($numberLength > 13) {
            throw new BarcodeException('Number as string length over 13 signs can not be shorting by automat.');
        }

        if (!preg_match('/[0-9]/', $number)) {
            throw new BarcodeException('Number have non-digits signs.');
        }

        if ($numberLength < 12) {
            $needZeroLength = 12 - $numberLength;
            do {
                $number = '0' . $number;
            } while ($needZeroLength--);
        }

        return $number;
    }

    /**
     * @param float $scale
     * @return float
     */
    protected function prepareScale($scale)
    {
        $scale = floatval($scale);
        if ($scale < 2) {
            $scale = 2;
        } elseif ($scale > 12) {
            $scale = 12;
        }

        return $scale;
    }

    /**
     * @param string $fontPath
     * @return string mixed
     * @throws BarcodeException
     */
    protected function prepareFont($fontPath)
    {
        if (!file_exists($fontPath)) {
            throw new BarcodeException(sprintf('Font file [%s] doesn\'t exists.', $fontPath));
        }
        return $fontPath;
    }

    /**
     * Create the EAN13 barcode
     * @param string $number - is the max 13 digit barcode to be displayed.
     * @param int $scale - is the scale of the image in integers. The scale will not go lower than 2 or greater than 12
     * @param string $fontpath
     * @throws BarcodeException
     */
    public function __construct($number, $fontpath, $scale = 2)
    {
        $this->number = $this->prepareNumber($number);
        $this->scale = $this->prepareScale($scale);

        $this->height = $this->scale * 60;
        $this->width = 1.8 * $this->height;

        /* Get the parity key, which is based on the first digit. */
        $this->key = static::$parityMatrix['key'][substr($this->number, 0, 1)];
        $this->font = $this->prepareFont($fontpath);

        /* The checksum (13th digit) can be calculated or supplied */
        if (strlen($this->number) === 12) {
            $this->number .= $this->checksum($this->number);
        }
    }

    /**
     * @param int $dimmWidth
     * @param int $dimmHeight
     */
    public function setDimmensions($dimmWidth, $dimmHeight)
    {
        $dimmWidth = intval($dimmWidth);
        $dimmHeight = intval($dimmHeight);

        if ($dimmHeight > 0) {
            $this->height = $dimmHeight;
        }
        if ($dimmWidth > 0) {
            $this->width = $dimmWidth;
        }
        $this->scale = $this->prepareScale($this->width/(1.8 * 60));
    }

    public function __destruct()
    {
        imagedestroy($this->image);
    }

    /**
     * @return array
     */
    protected function encode()
    {
        $barcode[] = static::$parityMatrix['guards']['start'];
        for ($i = 1; $i <= strlen($this->number) - 1; $i++) {
            if ($i < 7) {
                $barcode[] = static::$parityMatrix['left'][$this->key[$i - 1]][substr($this->number, $i, 1)];
            } else {
                $barcode[] = static::$parityMatrix['right'][substr($this->number, $i, 1)];
            }
            if ($i == 6) {
                $barcode[] = static::$parityMatrix['guards']['middle'];
            }
        }
        $barcode[] = static::$parityMatrix['guards']['end'];
        return $barcode;
    }

    /**
     *
     */
    protected function createImage()
    {
        $this->image = imagecreate($this->width, $this->height);
        imagecolorallocatealpha($this->image, 0xFF, 0xFF, 0xFF, $this->alpha);
    }

    /**
     * Draw the actual bars themselves.
     * 10111001 - bar, empty, bar, bar, bar, empty, empty, bar
     */
    protected function drawBars()
    {
        $barColor = imagecolorallocate($this->image, 0x00, 0x00, 0x00);
        $maxVerticalPos = $this->height * 0.025;
        $floorVerticalPos = $this->height * 0.825;
        $barWidth = $this->scale;

        $coordX = ($this->width * 0.11) - $barWidth;
        foreach ($this->bars as $bar) {
            $tall = 0;
            if (strlen($bar) == 3 || strlen($bar) == 5) {
                $tall = ($this->height * 0.15);
            }
            for ($i = 1; $i <= strlen($bar); $i++) {
                if (substr($bar, $i - 1, 1) === '1') {
                    imagefilledrectangle($this->image, $coordX, $maxVerticalPos, $coordX + $barWidth, $floorVerticalPos + $tall, $barColor);
                }
                $coordX += $barWidth;
            }
        }
    }

    /**
     * Draw text
     */
    protected function drawText()
    {
        $coordX = $this->width * 0.04;
        $coordY = $this->height * 0.95;
        $textColor = imagecolorallocate($this->image, 0x00, 0x00, 0x00);
        $fontsize = $this->scale * 7;
        $kerning = $fontsize * 1;
        for ($i = 0; $i < strlen($this->number); $i++) {
            imagettftext($this->image, $fontsize, 0, $coordX, $coordY, $textColor, $this->font, $this->number[$i]);
            if ($i == 0 || $i == 6) {
                $coordX += $kerning * 0.5;
            }
            $coordX += $kerning;
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
     * Creating completed barcode image
     */
    public function create()
    {
        $this->bars = $this->encode();
        $this->createImage();
        $this->drawBars();
        $this->drawText();
    }

    /**
     * @param string $filename
     * @throws BarcodeException
     */
    public function saveFile($filename)
    {
        $dirPath = dirname($filename);
        if (!file_exists($dirPath)) {
            throw new BarcodeException('No such directory [' . $dirPath . ']');
        }
        imagepng($this->image, $filename);
    }
}


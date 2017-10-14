<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
<?php
function rand13() {
	$rand_a = rand(0, 999999);
	$rand_b = rand(0, 9999999);
	return sprintf('%d%d', ($rand_a > 0) ? $rand_a : '', $rand_b);
}


error_reporting(E_ALL);
ini_set('display_errors', 1);
    include_once 'BarcodeEAN13.php';

    $fontFilePath = dirname(__FILE__) . '/FreeSansBold.ttf';
    $value = rand13();
    $value = '605589605589';

    try {
    	var_dump(strlen($value), $value);
    	$barcode = new BarcodeEAN13($value, $fontFilePath, 6);
        $barcode->display('images/barcode_'.$value.'.png');
    }
    catch(Exception $e) {
        var_dump(get_class($e), $e->getMessage());
    }
?>
</body>
</html>

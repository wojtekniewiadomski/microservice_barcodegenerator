<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
    include_once 'ean13.php';

    $value = rand(100000000000, 1000000000000);
    $value = '0000000123456';

    try {
        var_dump($value);
        $barcode = new BarcodeEAN13((string)$value, 6);
        $barcode->display('images/barcode_'.$value.'.png');
    }
    catch(Exception $e) {
        var_dump(get_class($e), $e->getMessage());
    }
?>
</body>
</html>

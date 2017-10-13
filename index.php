<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
<?php
    include_once 'ean13.php';

    $value = rand(10000, 100000);

    $barcode = new BarcodeEAN13($value, 6);

?>
</body>
</html>

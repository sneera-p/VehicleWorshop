<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

<?php
    $names = ["Tharanga", "Geeth"]
?>

<?php foreach ($names as $name): ?>
    <h2><?= $name ?></h2>
<?php endforeach; ?>

</body>
</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js"></script>
</head>

<body>

    <?php
        $names = ["Tharanga", "Geeth", "Risinu", "Senira"];
    ?>

    <?php foreach ($names as $name): ?>
        <h2><?= $name ?></h2>
    <?php endforeach; ?>

</body>

</html>

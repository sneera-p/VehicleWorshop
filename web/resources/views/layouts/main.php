<?php
/**
 * @var string $title
 * @var string $csrf
 * @var string $content
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="assets/index.css">
    <script src="assets/index.js"></script>
</head>
<body>
    <h1>Hello Main</h1>
    <main>
        <?= $content ?>
    </main>
</body>
</html>

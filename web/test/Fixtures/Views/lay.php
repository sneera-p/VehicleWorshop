<?php
/**
 * @var string $title
 * @var string $csrf
 * @var string $content
 */
?>
<title><?= $title ?></title>
<meta name="csrf-token" content="<?= $csrf ?>">
<main><?= $content ?></main>
<?= isset($name) ? 'leaked' : 'isolated' ?>

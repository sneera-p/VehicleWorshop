<?php
/**
 * @var string $message
 */
?>

<h1>Hello Sailor!</h1>

<p x-get='/dummy/greeting' x-trigger='sse dummy:event' x-swap='afterbegin'>
</p>

<div x-get='/dummy/hello' x-trigger='click delay:2s' x-swap='innerHTML'>
    Click Me
</div>

<!-- <?php var_dump($_SERVER) ?> -->
<!-- <?php var_dump($_REQUEST) ?> -->

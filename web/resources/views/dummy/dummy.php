<?php
/**
 * @var string $message
 */
?>

<h1>Hello Sailor!</h1>

<div x-get='/dummy/hello' x-trigger='click delay:800ms' x-swap='innerHTML'>
    Click Me
</div>

<p x-get='/dummy/greeting' x-trigger='sse dummy:event' x-swap='afterbegin'>
</p>

<!-- <?php var_dump($_SERVER) ?> -->
<!-- <?php var_dump($_REQUEST) ?> -->

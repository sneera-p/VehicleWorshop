<?php
/**
 * @var string $message
 */
$now = (new DateTime())->format('Y-m-d H:i:s');
?>
<span>
    time: <em><?= $now ?></em> &nbsp;
    msg: <i><?= htmlspecialchars($message) ?></i>
</span><br>

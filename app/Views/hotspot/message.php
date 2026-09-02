<?php
/** @var string $heading */
/** @var string $message */
/** @var string|null $actionUrl */
/** @var string|null $actionLabel */
?>
<h1><?= e($heading) ?></h1>
<?= $message ?>
<?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
<a class="button full" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?></a>
<?php endif; ?>

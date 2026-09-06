<?php
/** @var array<int,array<string,mixed>> $themes */
?>

<div class="heading">
    <div>
        <h1>Portal Themes</h1>
        <p class="muted mb-0">
            Installed hotspot portal themes. Themes are stored in <span class="code">portal-themes/&lt;theme&gt;</span> and may contain HTML, CSS, JavaScript and assets.
        </p>
    </div>
</div>

<section class="panel">
    <?php if (!$themes): ?>
        <div class="text-body-secondary">No portal themes are installed.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($themes as $theme): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <h2 class="h5 mb-1"><?= e($theme['name']) ?></h2>
                                    <div class="small text-body-secondary font-monospace"><?= e($theme['slug']) ?></div>
                                </div>
                                <span class="badge rounded-pill <?= $theme['enabled'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $theme['enabled'] ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </div>
                            <p class="text-body-secondary mt-3 mb-2 flex-grow-1">
                                <?= e($theme['description'] ?: 'No description.') ?>
                            </p>
                            <div class="small text-body-secondary">Version <?= e($theme['version'] ?: '—') ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

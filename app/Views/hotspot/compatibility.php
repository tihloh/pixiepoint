<?php
/** @var array $context */
/** @var array $vendos */
$context = $context ?? [];
$vendos = $vendos ?? [];
?>
<div class="portal">
<section class="card portal-card border-0 shadow-lg"><div class="card-body p-3 p-sm-4"><div class="compat" id="compat-app">
<div class="brand"><span class="brandmark">P</span><span>PixiePoint</span></div><h1>Connect to Wi-Fi</h1><p class="muted">Insert coins or enter an existing voucher.</p>
<div id="compat-alert" class="alert" hidden></div>
<div class="field" id="compat-topup-slot"><label for="compat-vendo">Coin slot</label><select id="compat-vendo">
<?php foreach ($vendos as $i => $vendo): ?><option value="<?= e($vendo['id']) ?>" data-base-url="<?= e($vendo['baseUrl']) ?>" data-password-mode="<?= e($vendo['passwordMode']) ?>" data-charging="<?= $vendo['chargingEnabled'] ? '1':'0' ?>" data-eload="<?= $vendo['eloadEnabled'] ? '1':'0' ?>" <?= $i===0?'selected':'' ?>><?= e($vendo['name']) ?></option><?php endforeach; ?>
</select><small id="compat-health" class="compat-status"><?= $vendos ? 'Checking the local coin slot…' : 'No coin slot available for this hotspot' ?></small></div>
<form id="compat-voucher-form" class="compat-voucher"><div class="field"><label for="compat-voucher">Voucher</label><div class="input-group"><input id="compat-voucher" class="form-control" autocomplete="one-time-code" autocapitalize="characters" required><button class="button" id="compat-connect" type="submit">Connect</button></div></div></form>
<button class="button full" id="compat-topup" type="button" disabled>Insert coin</button><button class="button secondary full" id="compat-rates" type="button" disabled>View rates</button>
<div class="compat-tools"><button class="button secondary" id="compat-charging" type="button" hidden>Phone charging</button><button class="button secondary" id="compat-eload" type="button" hidden>Buy e-load</button></div>
<div id="compat-transaction" class="compat-transaction" hidden><small>Your voucher</small><strong id="compat-code">—</strong><div class="context"><div><small>Coin total</small><span id="compat-amount">₱0</span></div><div><small>Time</small><span id="compat-time">—</span></div></div><div class="progress my-3" role="progressbar" aria-label="Coin slot timer"><div id="compat-progress-bar" class="progress-bar" style="width:100%"></div></div><div id="compat-countdown" class="small text-body-secondary mb-2">Waiting…</div><p id="compat-progress" class="muted">Waiting for coins…</p><div class="actions"><button class="button" id="compat-finish" type="button" disabled>Done &amp; connect</button><button class="button secondary" id="compat-cancel" type="button">Cancel</button></div></div>
<div id="compat-rate-list" class="compat-rate-list" hidden></div><div id="compat-charger-list" class="compat-rate-list" hidden></div><div id="compat-eload-panel" class="compat-rate-list" hidden><p class="muted">E-load compatibility is enabled for this vendo. Product retrieval and payment stay in the hosted portal.</p><div id="compat-eload-products">Loading products…</div></div>
<noscript><div class="alert">JavaScript is required to communicate with the local coin slot.</div></noscript>
<script>window.PIXIEPOINT_VENDOS=<?= json_encode($vendos, JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
</div></div></section></div>

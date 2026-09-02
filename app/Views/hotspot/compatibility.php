<div class="compat" id="compat-app">
    <div class="brand"><span class="brandmark">P</span><span>PixiePoint</span></div>
    <h1>Connect to Wi-Fi</h1>
    <p class="muted">Insert coins or enter an existing voucher.</p>

    <div id="compat-alert" class="alert" hidden></div>

    <div class="field">
        <label for="compat-vendo">Coin slot</label>
        <select id="compat-vendo"></select>
        <small id="compat-health" class="compat-status">Connecting to the local vendo…</small>
    </div>

    <button class="button full" id="compat-topup" type="button" disabled>Insert coin</button>
    <button class="button secondary full" id="compat-rates" type="button" disabled>View rates</button>

    <div class="compat-tools">
        <button class="button secondary" id="compat-extend-toggle" type="button" disabled>Extend voucher</button>
        <button class="button secondary" id="compat-charging" type="button" hidden>Phone charging</button>
        <button class="button secondary" id="compat-eload" type="button" hidden>Buy e-load</button>
    </div>

    <form id="compat-extend-form" class="compat-inline" hidden>
        <div class="field"><label for="compat-extend-code">Voucher to extend</label><input id="compat-extend-code" autocomplete="one-time-code" autocapitalize="characters" required></div>
        <button class="button full" type="submit">Insert coins to extend</button>
    </form>

    <div id="compat-transaction" class="compat-transaction" hidden>
        <small>Your voucher</small>
        <strong id="compat-code">—</strong>
        <div class="context">
            <div><small>Coin total</small><span id="compat-amount">₱0</span></div>
            <div><small>Time</small><span id="compat-time">—</span></div>
        </div>
        <p id="compat-progress" class="muted">Waiting for coins…</p>
        <div class="actions">
            <button class="button" id="compat-finish" type="button">Done &amp; connect</button>
            <button class="button secondary" id="compat-cancel" type="button">Cancel</button>
        </div>
    </div>

    <form id="compat-convert-form" class="compat-inline" hidden>
        <div class="field"><label for="compat-convert-code">Convert time into another voucher</label><input id="compat-convert-code" autocomplete="one-time-code" autocapitalize="characters" required></div>
        <button class="button full" type="submit">Convert voucher</button>
    </form>

    <form id="compat-voucher-form" class="compat-voucher">
        <div class="field"><label for="compat-voucher">Have a voucher?</label><input id="compat-voucher" autocomplete="one-time-code" autocapitalize="characters" required></div>
        <button class="button full" type="submit">Connect</button>
    </form>

    <div id="compat-rate-list" class="compat-rate-list" hidden></div>
    <div id="compat-charger-list" class="compat-rate-list" hidden></div>
    <div id="compat-eload-panel" class="compat-rate-list" hidden>
        <p class="muted">E-load compatibility is enabled for this vendo. Product retrieval and payment stay in the hosted portal.</p>
        <div id="compat-eload-products">Loading products…</div>
    </div>

    <noscript><div class="alert">JavaScript is required to communicate with the local coin slot.</div></noscript>
</div>
<script src="/assets/juanfi-compat.js" defer></script>

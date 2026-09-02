<div class="portal">
    <section class="card portal-card border-0 shadow-lg">
        <div class="card-body p-3 p-sm-4">
            <div class="compat" id="compat-app">
                <div class="brand">
                    <span class="logo">P</span>
                    <div>
                        <strong>PixiePoint Wi-Fi</strong>
                        <div class="small text-body-secondary">Secure hotspot access</div>
                    </div>
                </div>

                <h1>Connect to Wi-Fi</h1>
                <p class="muted mb-4">Use an existing voucher, or create/add internet time by inserting coins at an available PisoWiFi coin slot.</p>

                <div id="compat-alert" class="alert" hidden></div>

                <section class="border rounded-3 p-3 mb-3" id="compat-topup-slot">
                    <div class="d-flex gap-2 align-items-start mb-3">
                        <span class="badge rounded-pill text-bg-primary">1</span>
                        <div>
                            <strong class="d-block">Select a coin slot</strong>
                            <small class="text-body-secondary">Choose the PisoWiFi vendo you are physically using. Only vendos assigned to this hotspot are shown.</small>
                        </div>
                    </div>
                    <div class="field mb-0">
                        <label for="compat-vendo">Available coin slot</label>
                        <select id="compat-vendo" aria-describedby="compat-health">
                            <option>Looking for available coin slots…</option>
                        </select>
                        <small id="compat-health" class="compat-status">Loading available coin slots…</small>
                    </div>
                </section>

                <section class="border rounded-3 p-3 mb-3">
                    <div class="d-flex gap-2 align-items-start mb-3">
                        <span class="badge rounded-pill text-bg-primary">2</span>
                        <div>
                            <strong class="d-block">Enter your voucher</strong>
                            <small class="text-body-secondary">Already have internet time? Enter your voucher and connect immediately. You can also use this voucher when adding more time with coins.</small>
                        </div>
                    </div>
                    <form id="compat-voucher-form" class="m-0 p-0 border-0">
                        <div class="field mb-0">
                            <label for="compat-voucher">Voucher code</label>
                            <div class="input-group">
                                <input id="compat-voucher" class="form-control" autocomplete="one-time-code" autocapitalize="characters" placeholder="Enter voucher code" required>
                                <button class="button" id="compat-connect" type="submit">Connect</button>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="border rounded-3 p-3 mb-3">
                    <div class="d-flex gap-2 align-items-start mb-3">
                        <span class="badge rounded-pill text-bg-primary">3</span>
                        <div>
                            <strong class="d-block">Insert coins for internet time</strong>
                            <small class="text-body-secondary">Press Insert coin first. When the selected vendo is ready, insert your coins. PixiePoint will show the amount received and the internet time added.</small>
                        </div>
                    </div>
                    <button class="button full" id="compat-topup" type="button" disabled>Insert coin</button>
                    <button class="button secondary full" id="compat-rates" type="button" disabled>View internet rates</button>
                </section>

                <div class="compat-tools">
                    <button class="button secondary" id="compat-charging" type="button" hidden>Phone charging</button>
                    <button class="button secondary" id="compat-eload" type="button" hidden>Buy e-load</button>
                </div>

                <div id="compat-transaction" class="compat-transaction" hidden>
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <small class="text-body-secondary">Active voucher</small>
                            <strong id="compat-code">—</strong>
                        </div>
                        <div id="compat-countdown" class="small text-body-secondary">Waiting…</div>
                    </div>
                    <div class="context">
                        <div><small>Coins inserted</small><span id="compat-amount">₱0</span></div>
                        <div><small>Internet time</small><span id="compat-time">—</span></div>
                    </div>
                    <div class="progress my-3" role="progressbar" aria-label="Coin slot timer">
                        <div id="compat-progress-bar" class="progress-bar" style="width:100%"></div>
                    </div>
                    <p id="compat-progress" class="muted">Waiting for coins…</p>
                    <div class="actions">
                        <button class="button" id="compat-finish" type="button" disabled>Done &amp; connect</button>
                        <button class="button secondary" id="compat-cancel" type="button">Cancel</button>
                    </div>
                    <small class="d-block text-body-secondary mt-2">After a coin is accepted, press Done &amp; connect or wait for the coin timer to finish.</small>
                </div>

                <div id="compat-rate-list" class="compat-rate-list" hidden></div>
                <div id="compat-charger-list" class="compat-rate-list" hidden></div>
                <div id="compat-eload-panel" class="compat-rate-list" hidden>
                    <p class="muted">E-load is available on this vendo. Select a product to continue.</p>
                    <div id="compat-eload-products">Loading products…</div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <strong class="d-block mb-1">Need help?</strong>
                    <small class="text-body-secondary">Voucher login works even without a coin slot. Coin insertion, rates, charging and e-load require an online vendo assigned to this hotspot. If no coin slot appears, use a valid voucher or contact the hotspot operator.</small>
                </div>

                <noscript><div class="alert">JavaScript is required to communicate with the local coin slot.</div></noscript>
            </div>
        </div>
    </section>
</div>

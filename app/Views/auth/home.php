<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>

<div class="row g-4 align-items-center auth-login-layout">
    <div class="col-lg-8">
        <div class="pe-lg-5 py-3">
            <span class="badge mb-3">PixiePoint Wi-Fi</span>
            <h1 class="display-6 fw-semibold mb-3">Welcome to PixiePoint</h1>
            <p class="lead text-body-secondary mb-3">
                Manage your PisoWiFi routers, hotspot stations, vouchers, users, sessions, and portal experience from one place.
            </p>
            <p class="muted mb-0">
                Sign in with your PixiePoint account to open your dashboard and access the tools available to you.
            </p>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border rounded-4 shadow-sm auth-login-card">
            <div class="card-body p-4">
                <div class="mb-4">
                    <h2 class="h4 mb-1">Sign in</h2>
                    <p class="text-body-secondary mb-0">Use your PixiePoint account credentials.</p>
                </div>

                <?= $error ?>

                <?php if ($googleEnabled): ?>
                    <div class="mb-3">
                        <?php require dirname(__DIR__) . '/partials/google-button.php'; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/login" class="auth-form">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

                    <div class="field">
                        <label for="login-email">Email address</label>
                        <input
                            id="login-email"
                            name="email"
                            type="email"
                            autocomplete="username"
                            placeholder="you@example.com"
                            required
                            autofocus
                        >
                    </div>

                    <div class="field">
                        <label for="login-password">Password</label>
                        <input
                            id="login-password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <button class="button full" type="submit">Sign in</button>
                </form>

                <p class="muted auth-footer mb-0 mt-3">
                    No account yet? <a href="/register">Create a member account</a>
                </p>
            </div>
        </div>
    </div>
</div>

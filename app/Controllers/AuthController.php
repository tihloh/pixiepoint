<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\GoogleOAuth;
use PixiePoint\App\Services\View;
use Throwable;
use Tihloh\Prefab\Input\Input;
use Tihloh\Prefab\Users\Services\UserManager;

final class AuthController
{
    public function __construct(
        private UserManager $users,
        private AuthContext $auth,
        private GoogleOAuth $google,
        private View $view,
    ) {}

    public function setup(): never
    {
        if ($this->hasUsers()) redirect('/login');
        $error = '';

        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'email' => 'trim|lowercase|required|email|max:254',
                'password' => 'required|string|min:12|max:255',
            ]);

            if ($result->fails()) {
                $error = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                try {
                    $this->users->create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                        'active' => true,
                        'platform_role' => 'platform_owner',
                        'points' => 0,
                    ], $this->requestContext());
                    redirect('/login');
                } catch (Throwable) {
                    $error = '<div class="alert">The platform owner account could not be created.</div>';
                }
            }
        }

        $this->view->page('Initial setup', $this->view->portalCard('<h1>Create platform owner</h1><p class="muted">This one-time account owns the centralized PixiePoint service. All other users register through the normal account flow.</p>' . $error . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Email</label><input name="email" type="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="12" required></div><button class="button full">Create platform owner</button></form>'));
    }

    public function register(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        $error = '';

        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'email' => 'trim|lowercase|required|email|max:254',
                'password' => 'required|string|min:8|max:255',
            ]);

            if ($result->fails()) {
                $error = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                try {
                    $this->users->create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                        'active' => true,
                        'platform_role' => 'user',
                        'points' => 0,
                    ], $this->requestContext());

                    $attempt = $this->auth->auth()->attempt($data['email'], $data['password'], $this->requestContext());
                    if ($attempt->success) {
                        session_regenerate_id(true);
                        redirect('/dashboard');
                    }
                    redirect('/login');
                } catch (Throwable) {
                    $error = '<div class="alert">An account with that email already exists. Try logging in instead.</div>';
                }
            }
        }

        $this->view->page('Create account', $this->view->portalCard('<h1>Create your account</h1><p class="muted">Registration is optional for basic PisoWiFi access. An account unlocks points, saved devices, history and better support.</p>' . $error . $this->googleButton() . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" autocomplete="name" required></div><div class="field"><label>Email</label><input name="email" type="email" autocomplete="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="8" autocomplete="new-password" required></div><button class="button full">Create free account</button></form><p class="muted auth-footer">Already registered? <a href="/login">Log in</a></p>'));
    }

    public function login(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        $error = '';

        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'email' => 'trim|lowercase|required|email|max:254',
                'password' => 'required|string|max:255',
            ]);

            if ($result->fails()) {
                $error = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                $attempt = $this->auth->auth()->attempt($data['email'], $data['password'], $this->requestContext());
                if ($attempt->success) {
                    session_regenerate_id(true);
                    redirect('/dashboard');
                }
                $error = '<div class="alert">The email or password is incorrect.</div>';
            }
        }

        $this->view->page('Log in', $this->view->portalCard('<h1>Welcome back</h1><p class="muted">One PixiePoint account for Wi-Fi rewards, devices, history, support and authorized management features.</p>' . $error . $this->googleButton() . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Email</label><input name="email" type="email" autocomplete="username" required autofocus></div><div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div><button class="button full">Log in</button></form><p class="muted auth-footer">No account? <a href="/register">Register free</a></p>'));
    }

    public function logout(): never
    {
        if ($this->auth->auth()->check()) $this->auth->auth()->logout($this->requestContext());
        session_regenerate_id(true);
        redirect('/login');
    }

    public function googleStart(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        try {
            header('Location: ' . $this->google->authorizationUrl(), true, 302);
            exit;
        } catch (Throwable $e) {
            $this->view->page('Google sign-in', $this->view->portalCard('<h1>Google sign-in unavailable</h1><div class="alert">' . e($e->getMessage()) . '</div><a class="button full" href="/login">Back to login</a>'));
        }
    }

    public function googleCallback(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if (isset($_GET['error'])) {
            $this->view->page('Google sign-in', $this->view->portalCard('<h1>Sign-in cancelled</h1><p class="muted">Google sign-in was cancelled or could not be completed.</p><a class="button full" href="/login">Back to login</a>'));
        }
        try {
            $id = $this->google->complete((string)($_GET['code'] ?? ''), (string)($_GET['state'] ?? ''));
            $this->google->establishSession($id);
            redirect('/dashboard');
        } catch (Throwable $e) {
            $this->view->page('Google sign-in', $this->view->portalCard('<h1>Could not sign in</h1><div class="alert">' . e($e->getMessage()) . '</div><a class="button full" href="/login">Try again</a>'));
        }
    }

    private function hasUsers(): bool
    {
        return $this->users->all(1) !== [];
    }

    private function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function requestContext(): array
    {
        return [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
    }

    private function errors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) foreach ((array)$fieldErrors as $message) $messages[] = e($message);
        return '<div class="alert">' . implode('<br>', $messages ?: ['Please check the form.']) . '</div>';
    }

    private function googleButton(): string
    {
        return $this->google->enabled()
            ? '<a class="button google full" href="/auth/google"><span class="google-mark">G</span>Continue with Google</a><div class="auth-divider">or</div>'
            : '';
    }
}

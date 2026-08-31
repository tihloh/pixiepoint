<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDOException;
use PixiePoint\App\Http\Request;
use PixiePoint\App\Models\User;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\GoogleOAuth;
use PixiePoint\App\Services\View;
use Throwable;

final class AuthController
{
    public function __construct(
        private User $users,
        private AuthContext $auth,
        private GoogleOAuth $google,
        private View $view,
    ) {}

    public function setup(Request $request): never
    {
        if ($this->users->count() > 0) redirect('/login');
        $error = '';
        if ($request->method === 'POST') {
            require_csrf();
            $name = trim((string)$request->input('name', ''));
            $email = strtolower(trim((string)$request->input('email', '')));
            $password = (string)$request->input('password', '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                $error = '<div class="alert">Use a valid name and email plus a password of at least 12 characters.</div>';
            } else {
                $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), 'platform_owner');
                redirect('/login');
            }
        }
        $this->view->page('Initial setup', $this->view->portalCard('<h1>Create platform owner</h1><p class="muted">This one-time account owns the centralized PixiePoint service. All other users register through the normal account flow.</p>' . $error . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Email</label><input name="email" type="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="12" required></div><button class="button full">Create platform owner</button></form>'));
    }

    public function register(Request $request): never
    {
        if ($this->users->count() === 0) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        $error = '';
        if ($request->method === 'POST') {
            require_csrf();
            $name = trim((string)$request->input('name', ''));
            $email = strtolower(trim((string)$request->input('email', '')));
            $password = (string)$request->input('password', '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                $error = '<div class="alert">Use a valid name and email plus a password of at least 8 characters.</div>';
            } else {
                try {
                    $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
                    $result = $this->auth->auth()->attempt($email, $password);
                    if ($result->success) {
                        session_regenerate_id(true);
                        redirect('/dashboard');
                    }
                    redirect('/login');
                } catch (PDOException) {
                    $error = '<div class="alert">An account with that email already exists. Try logging in instead.</div>';
                }
            }
        }
        $this->view->page('Create account', $this->view->portalCard('<h1>Create your account</h1><p class="muted">Registration is optional for basic PisoWiFi access. An account unlocks points, saved devices, history and better support.</p>' . $error . $this->googleButton() . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" autocomplete="name" required></div><div class="field"><label>Email</label><input name="email" type="email" autocomplete="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="8" autocomplete="new-password" required></div><button class="button full">Create free account</button></form><p class="muted auth-footer">Already registered? <a href="/login">Log in</a></p>'));
    }

    public function login(Request $request): never
    {
        if ($this->users->count() === 0) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        $error = '';
        if ($request->method === 'POST') {
            require_csrf();
            $result = $this->auth->auth()->attempt(
                strtolower(trim((string)$request->input('email', ''))),
                (string)$request->input('password', ''),
                ['ip_address' => $request->server['REMOTE_ADDR'] ?? null, 'user_agent' => $request->server['HTTP_USER_AGENT'] ?? null],
            );
            if ($result->success) {
                session_regenerate_id(true);
                redirect('/dashboard');
            }
            $error = '<div class="alert">The email or password is incorrect.</div>';
        }
        $this->view->page('Log in', $this->view->portalCard('<h1>Welcome back</h1><p class="muted">One PixiePoint account for Wi-Fi rewards, devices, history, support and authorized management features.</p>' . $error . $this->googleButton() . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Email</label><input name="email" type="email" autocomplete="username" required autofocus></div><div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div><button class="button full">Log in</button></form><p class="muted auth-footer">No account? <a href="/register">Register free</a></p>'));
    }

    public function logout(Request $request): never
    {
        if ($this->auth->auth()->check()) {
            $this->auth->auth()->logout(['ip_address' => $request->server['REMOTE_ADDR'] ?? null, 'user_agent' => $request->server['HTTP_USER_AGENT'] ?? null]);
        }
        session_regenerate_id(true);
        redirect('/login');
    }

    public function googleStart(Request $request): never
    {
        if ($this->users->count() === 0) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        try {
            header('Location: ' . $this->google->authorizationUrl(), true, 302);
            exit;
        } catch (Throwable $e) {
            $this->view->page('Google sign-in', $this->view->portalCard('<h1>Google sign-in unavailable</h1><div class="alert">' . e($e->getMessage()) . '</div><a class="button full" href="/login">Back to login</a>'));
        }
    }

    public function googleCallback(Request $request): never
    {
        if ($this->users->count() === 0) redirect('/setup');
        if (isset($request->query['error'])) {
            $this->view->page('Google sign-in', $this->view->portalCard('<h1>Sign-in cancelled</h1><p class="muted">Google sign-in was cancelled or could not be completed.</p><a class="button full" href="/login">Back to login</a>'));
        }
        try {
            $id = $this->google->complete((string)($request->query['code'] ?? ''), (string)($request->query['state'] ?? ''));
            $this->google->establishSession($id);
            redirect('/dashboard');
        } catch (Throwable $e) {
            $this->view->page('Google sign-in', $this->view->portalCard('<h1>Could not sign in</h1><div class="alert">' . e($e->getMessage()) . '</div><a class="button full" href="/login">Try again</a>'));
        }
    }

    private function googleButton(): string
    {
        return $this->google->enabled()
            ? '<a class="button google full" href="/auth/google"><span class="google-mark">G</span>Continue with Google</a><div class="auth-divider">or</div>'
            : '';
    }
}

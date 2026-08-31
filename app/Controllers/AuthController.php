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

    public function home(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');

        $error = (string)($_SESSION['login_error'] ?? '');
        unset($_SESSION['login_error']);

        $body = '<h1>Welcome to PixiePoint</h1>'
            . '<p class="muted">Sign in to your PixiePoint account for Wi-Fi rewards, saved devices, session history and support. Registration is optional for basic hotspot access.</p>'
            . $error
            . $this->googleButton()
            . '<form method="post" action="/login" class="auth-form">'
            . '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'
            . '<div class="field"><label>Email</label><input name="email" type="email" autocomplete="username" required autofocus></div>'
            . '<div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div>'
            . '<button class="button full">Log in</button>'
            . '</form>'
            . '<p class="muted auth-footer">No account? <a href="/register">Create a free account</a></p>';

        $this->view->page('PixiePoint', $this->view->portalCard($body));
    }

    public function setup(): never
    {
        if ($this->hasUsers()) redirect('/');
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
                    redirect('/');
                } catch (Throwable) {
                    $error = '<div class="alert">The platform owner account could not be created.</div>';
                }
            }
        }

        $this->view->page('Initial setup', $this->view->portalCard('<h1>Create platform owner</h1><p class="muted">This one-time account owns the centralized PixiePoint service. All other users register through the normal account flow.</p>' . $error . '<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Email</label><input name="email" type="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="12" required></div><button class="button full">Create platform owner</button></form>'));
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
                    redirect('/');
                } catch (Throwable) {
                    $error = '<div class="alert">An account with that email already exists. Try logging in instead.</div>';
                }
            }
        }

        $this->view->page('Create account', $this->view->portalCard('<h1>Create your account</h1><p class="muted">Registration is optional for basic PisoWiFi access. An account unlocks points, saved devices, history and better support.</p>' . $error . $this->googleButton() . '<form method="post" class="auth-form"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" autocomplete="name" required></div><div class="field"><label>Email</label><input name="email" type="email" autocomplete="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="8" autocomplete="new-password" required></div><button class="button full">Create free account</button></form><p class="muted auth-footer">Already registered? <a href="/">Log in</a></p>'));
    }

    public function login(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        if (!$this->isPost()) redirect('/');

        require_csrf();
        $result = Input::fromRequest()->process([
            'email' => 'trim|lowercase|required|email|max:254',
            'password' => 'required|string|max:255',
        ]);

        if ($result->fails()) {
            $_SESSION['login_error'] = $this->errors($result->errors());
            redirect('/');
        }

        $data = $result->validated();
        $attempt = $this->auth->auth()->attempt($data['email'], $data['password'], $this->requestContext());
        if ($attempt->success) {
            session_regenerate_id(true);
            redirect('/dashboard');
        }

        $_SESSION['login_error'] = '<div class="alert">The email or password is incorrect.</div>';
        redirect('/');
    }

    public function logout(): never
    {
        if ($this->auth->auth()->check()) $this->auth->auth()->logout($this->requestContext());
        session_regenerate_id(true);
        redirect('/');
    }

    public function googleStart(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if ($this->auth->auth()->check()) redirect('/dashboard');
        try {
            header('Location: ' . $this->google->authorizationUrl(), true, 302);
            exit;
        } catch (Throwable $e) {
            $_SESSION['login_error'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            redirect('/');
        }
    }

    public function googleCallback(): never
    {
        if (!$this->hasUsers()) redirect('/setup');
        if (isset($_GET['error'])) {
            $_SESSION['login_error'] = '<div class="alert">Google sign-in was cancelled or could not be completed.</div>';
            redirect('/');
        }
        try {
            $id = $this->google->complete((string)($_GET['code'] ?? ''), (string)($_GET['state'] ?? ''));
            $this->google->establishSession($id);
            redirect('/dashboard');
        } catch (Throwable $e) {
            $_SESSION['login_error'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            redirect('/');
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
        if (!$this->google->enabled()) return '';

        $googleIcon = '<svg class="google-icon" viewBox="0 0 18 18" aria-hidden="true" focusable="false">'
            . '<path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.716v2.258h2.909c1.702-1.567 2.684-3.875 2.684-6.615Z"/>'
            . '<path fill="#34A853" d="M9 18c2.43 0 4.468-.806 5.956-2.18l-2.91-2.258c-.805.54-1.834.859-3.046.859-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z"/>'
            . '<path fill="#FBBC05" d="M3.963 10.707A5.41 5.41 0 0 1 3.682 9c0-.592.102-1.167.281-1.707V4.961H.956A9 9 0 0 0 0 9c0 1.453.347 2.828.956 4.039l3.007-2.332Z"/>'
            . '<path fill="#EA4335" d="M9 3.579c1.322 0 2.507.455 3.44 1.346l2.581-2.582C13.464.892 11.426 0 9 0A9 9 0 0 0 .956 4.961l3.007 2.332C4.672 5.164 6.656 3.579 9 3.579Z"/>'
            . '</svg>';

        return '<a class="google-button" href="/auth/google">'
            . $googleIcon
            . '<span>Continue with Google</span>'
            . '</a>'
            . '<div class="auth-divider"><span>or</span></div>';
    }
}

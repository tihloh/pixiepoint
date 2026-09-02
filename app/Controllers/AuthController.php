<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
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
        private PDO $db,
    ) {
    }

    public function home(): never
    {
        if (!$this->hasUsers()) {
            redirect('/setup');
        }
        if ($this->auth->auth()->check()) {
            redirect('/dashboard');
        }

        $error = (string) ($_SESSION['login_error'] ?? '');
        unset($_SESSION['login_error']);

        $this->portal('PixiePoint', 'auth/home', [
            'error' => $error,
            'googleEnabled' => $this->google->enabled(),
            'csrf' => csrf_token(),
        ]);
    }

    public function setup(): never
    {
        if ($this->hasUsers()) {
            redirect('/');
        }
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

        $this->portal('Initial setup', 'auth/setup', [
            'error' => $error,
            'csrf' => csrf_token(),
        ]);
    }

    public function register(): never
    {
        if (!$this->hasUsers()) {
            redirect('/setup');
        }
        if ($this->auth->auth()->check()) {
            redirect('/dashboard');
        }
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
                        'platform_role' => 'member',
                        'points' => 0,
                    ], $this->requestContext());

                    $attempt = $this->auth->auth()->attempt(
                        $data['email'],
                        $data['password'],
                        $this->requestContext(),
                    );

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

        $this->portal('Create account', 'auth/register', [
            'error' => $error,
            'googleEnabled' => $this->google->enabled(),
            'csrf' => csrf_token(),
        ]);
    }

    public function login(): never
    {
        if (!$this->hasUsers()) {
            redirect('/setup');
        }
        if ($this->auth->auth()->check()) {
            redirect('/dashboard');
        }
        if (!$this->isPost()) {
            redirect('/');
        }

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
        $attempt = $this->auth->auth()->attempt(
            $data['email'],
            $data['password'],
            $this->requestContext(),
        );

        if (!$attempt->success) {
            $_SESSION['login_error'] = '<div class="alert">Invalid email or password.</div>';
            redirect('/');
        }

        session_regenerate_id(true);
        redirect('/dashboard');
    }

    public function logout(): never
    {
        $this->auth->auth()->logout($this->requestContext());
        session_regenerate_id(true);
        redirect('/');
    }

    public function googleStart(): never
    {
        if (!$this->google->enabled()) {
            $_SESSION['login_error'] = '<div class="alert">Google sign-in is not configured.</div>';
            redirect('/');
        }

        redirect($this->google->authorizationUrl());
    }

    public function googleCallback(): never
    {
        try {
            $userId = $this->google->complete(
                (string) ($_GET['code'] ?? ''),
                (string) ($_GET['state'] ?? ''),
            );
            $this->google->establishSession($userId);
            redirect('/dashboard');
        } catch (Throwable $e) {
            $_SESSION['login_error'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            redirect('/');
        }
    }

    /**
     * Setup only needs to know whether the application has at least one user.
     * Prefab UserManager intentionally has no count() API, so use the mapped
     * application users table directly for this application-level check.
     */
    private function hasUsers(): bool
    {
        return (bool) $this->db
            ->query('SELECT 1 FROM users LIMIT 1')
            ->fetchColumn();
    }

    private function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Public account screens use the same centered portal card as the captive
     * portal so their width and spacing remain constrained on large displays.
     */
    private function portal(string $title, string $view, array $data): never
    {
        $body = $this->view->render($view, $data);

        $this->view->page(
            $title,
            $this->view->portalCard($body),
        );
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

        foreach ($errors as $fieldErrors) {
            foreach ((array) $fieldErrors as $message) {
                $messages[] = e($message);
            }
        }

        return '<div class="alert">'
            . implode('<br>', $messages ?: ['Please check the form.'])
            . '</div>';
    }
}

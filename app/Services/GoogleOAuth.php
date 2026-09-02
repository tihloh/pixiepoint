<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use RuntimeException;
use Tihloh\Prefab\Users\Services\UserManager;

final class GoogleOAuth
{
    public function __construct(
        private PDO $db,
        private array $config,
        private UserManager $users,
    ) {
    }

    public function enabled(): bool
    {
        return trim((string) ($this->config['google_client_id'] ?? '')) !== ''
            && trim((string) ($this->config['google_client_secret'] ?? '')) !== '';
    }

    public function authorizationUrl(): string
    {
        $this->ensureEnabled();
        $state = bin2hex(random_bytes(24));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_verifier'] = $verifier;

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->config['google_client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function complete(string $code, string $state): int
    {
        $this->ensureEnabled();
        $expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
        $verifier = (string) ($_SESSION['google_oauth_verifier'] ?? '');
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_verifier']);

        if ($code === '' || $state === '' || $expectedState === '' || !hash_equals($expectedState, $state) || $verifier === '') {
            throw new RuntimeException('Google sign-in session expired. Please try again.');
        }

        $token = $this->requestJson('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->config['google_client_id'],
            'client_secret' => $this->config['google_client_secret'],
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);
        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Google did not return an access token.');
        }

        $profile = $this->requestJson('https://openidconnect.googleapis.com/v1/userinfo', null, ['Authorization: Bearer ' . $accessToken]);
        $sub = trim((string) ($profile['sub'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $verified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        $name = trim((string) ($profile['name'] ?? ''));
        $picture = substr(trim((string) ($profile['picture'] ?? '')), 0, 1000);

        if ($sub === '' || $email === '' || !$verified || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google did not provide a verified email address.');
        }

        $id = $this->idByGoogleSubject($sub);
        $user = $id !== null ? $this->users->find($id) : $this->users->findByEmail($email);
        $context = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'source' => 'google-oauth',
        ];

        if ($user) {
            if (!$user->active) {
                throw new RuntimeException('This PixiePoint account is disabled.');
            }
            $update = [
                'google_sub' => $sub,
                'avatar_url' => $picture !== '' ? $picture : null,
            ];
            if (($user->name ?? '') === '') {
                $update['name'] = $name !== '' ? $name : $email;
            }
            $this->users->update($user->id, $update, $context);

            return (int) $user->id;
        }

        $created = $this->users->create([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'active' => true,
            'password_hash' => null,
            'google_sub' => $sub,
            'avatar_url' => $picture !== '' ? $picture : null,
            'platform_role' => 'member',
            'points' => 0,
        ], $context);

        return (int) $created->data->id;
    }

    public function establishSession(int $userId): void
    {
        $_SESSION['auth:user_id'] = $userId;
        session_regenerate_id(true);
    }

    private function idByGoogleSubject(string $sub): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE google_sub=? LIMIT 1');
        $stmt->execute([$sub]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function redirectUri(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/auth/google/callback';
    }

    private function ensureEnabled(): void
    {
        if (!$this->enabled()) {
            throw new RuntimeException('Google sign-in is not configured.');
        }
    }

    private function requestJson(string $url, ?array $form = null, array $headers = []): array
    {
        $headers[] = 'Accept: application/json';
        $options = [
            'http' => [
                'method' => $form === null ? 'GET' : 'POST',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ];

        if ($form !== null) {
            $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
            $options['http']['content'] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        }

        $body = @file_get_contents($url, false, stream_context_create($options));
        $data = is_string($body) ? json_decode($body, true) : null;

        if (!is_array($data) || isset($data['error'])) {
            throw new RuntimeException('Google sign-in could not be completed.');
        }

        return $data;
    }
}

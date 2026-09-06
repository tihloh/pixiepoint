<?php

declare(strict_types=1);

namespace PixiePoint\App\Portal\Adapters;

use PixiePoint\App\Portal\ThemeContext;

final class MikroTikAdapter implements PlatformAdapter
{
    public function id(): string
    {
        return 'mikrotik';
    }

    public function capabilities(ThemeContext $context): array
    {
        return $context->features;
    }

    public function transform(string $html, ThemeContext $context): string
    {
        $isLogin = str_contains($html, 'id="pp-login-card"');
        $origin = 'https://hs.portalx.win';
        $nonce = (string) time();

        $contextData = [
            'mac' => (string) ($_GET['mac'] ?? $context->value('context.mac') ?? ''),
            'ip' => (string) ($_GET['client_ip'] ?? $context->value('context.ip') ?? ''),
            'username' => (string) ($_GET['username'] ?? ''),
            'routerIdentity' => (string) ($context->value('context.routerIdentity') ?? ''),
            'interfaceName' => (string) ($context->value('context.interfaceName') ?? ''),
            'serverAddress' => (string) ($context->value('context.serverAddress') ?? ''),
            'sessionTimeLeft' => (string) ($_GET['session_time_left'] ?? ''),
            'loginUrl' => $this->loginUrl((string) ($_GET['server_address'] ?? $context->value('context.serverAddress') ?? '')),
            'originalUrl' => (string) ($_GET['original_url'] ?? ''),
            'error' => (string) ($_GET['error'] ?? ''),
        ];

        $script = '';
        if ($isLogin) {
            $script .= '<script>window.PIXIEPOINT_CONTEXT=' . $this->json($contextData) . ';';
            $script .= 'window.PIXIEPOINT_CHAP=' . $this->json([
                'id' => (string) ($_GET['chap_id'] ?? ''),
                'challenge' => (string) ($_GET['chap_challenge'] ?? ''),
            ]) . ';';
            $script .= 'window.PIXIEPOINT_BOOTSTRAP=true;window.PIXIEPOINT_SERVER_RENDERED=true;</script>';
        } else {
            $session = [
                'username' => (string) ($_GET['username'] ?? ''),
                'mac' => $contextData['mac'],
                'ip' => $contextData['ip'],
                'routerIdentity' => $contextData['routerIdentity'],
                'serverAddress' => $contextData['serverAddress'],
                'interfaceName' => $contextData['interfaceName'],
                'sessionTimeLeft' => $contextData['sessionTimeLeft'],
                'bytesIn' => (string) ($_GET['bytes_in'] ?? ''),
                'bytesOut' => (string) ($_GET['bytes_out'] ?? ''),
                'remainBytesTotal' => (string) ($_GET['remain_bytes_total'] ?? ''),
                'refreshUrl' => (string) ($_GET['refresh_url'] ?? ''),
                'logoutUrl' => (string) ($_GET['logout_url'] ?? ''),
                'loginUrl' => $contextData['loginUrl'],
            ];
            $script .= '<script>window.PIXIEPOINT_SESSION=' . $this->json($session) . ';window.PIXIEPOINT_SERVER_RENDERED=true;</script>';
        }

        $script .= '<script src="' . $origin . '/assets/hotspot-loader.js?v=' . e($nonce) . '"></script>';

        return str_replace('</body>', $script . '</body>', $html);
    }

    private function loginUrl(string $serverAddress): string
    {
        $serverAddress = trim($serverAddress);
        if ($serverAddress === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $serverAddress)) {
            return rtrim($serverAddress, '/') . '/login';
        }

        return 'http://' . rtrim($serverAddress, '/') . '/login';
    }

    private function json(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) ?: '{}';
    }
}

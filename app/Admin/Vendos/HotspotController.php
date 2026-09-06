<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PixiePoint\App\Portal\Adapters\PlatformAdapter;
use PixiePoint\App\Portal\ThemeEngine;
use PixiePoint\App\Services\PortalThemeManager;
use PixiePoint\App\Services\View;

final class HotspotController
{
    public function __construct(
        private Api $api,
        private View $view,
        private PortalThemeManager $themes,
        private ThemeEngine $themeEngine,
        private PlatformAdapter $portalAdapter,
    ) {
    }

    public function portal(): never
    {
        $context = $this->hotspotContext();
        $vendos = $this->api->forHotspot($context['routerIdentity'], $context['serverAddress'], $context['ip'], $context['interfaceName']);
        $theme = $this->themes->resolveByRouterIdentity($context['routerIdentity'], isset($vendos[0]['id']) ? (int) $vendos[0]['id'] : null);

        $options = $this->vendoOptions($vendos);
        $debug = [];
        $raw = [
            'router_identity' => $context['routerIdentity'],
            'server_address' => $context['serverAddress'],
            'client_ip' => $context['ip'],
            'interface' => $context['interfaceName'],
        ];
        if ($this->api->hasDebugTarget($context['routerIdentity'])) {
            $debug = [
                'raw' => $raw,
                'processed' => $context,
                'validationErrors' => [],
                'matching' => $this->api->debugForHotspot($context['routerIdentity'], $context['serverAddress'], $context['ip'], $context['interfaceName']),
            ];
        }

        $this->headers('text/html; charset=utf-8');
        echo $this->themeEngine->render($theme, 'login.html', $this->portalAdapter, [
            'portal' => [
                'name' => (string) ($vendos[0]['name'] ?? 'PixiePoint'),
                'vendo_options' => $options,
                'debug' => $debug ? '1' : '',
            ],
            'context' => $context,
        ], [
            'voucher_login' => true,
            'member_login' => true,
            'coin_slot' => !empty($vendos),
            'points' => true,
        ]);
        exit;
    }

    public function status(): never
    {
        $context = $this->hotspotContext();
        $vendos = $this->api->forHotspot($context['routerIdentity'], $context['serverAddress'], $context['ip'], $context['interfaceName']);
        $theme = $this->themes->resolveByRouterIdentity($context['routerIdentity'], isset($vendos[0]['id']) ? (int) $vendos[0]['id'] : null);

        $this->headers('text/html; charset=utf-8');
        echo $this->themeEngine->render($theme, 'status.html', $this->portalAdapter, [
            'portal' => [
                'name' => (string) ($vendos[0]['name'] ?? 'PixiePoint'),
                'vendo_options' => $this->vendoOptions($vendos),
            ],
        ], [
            'coin_slot' => !empty($vendos),
        ]);
        exit;
    }

    public function index(): never
    {
        $raw = [
            'router_identity' => (string) ($_GET['router_identity'] ?? ''),
            'server_address' => (string) ($_GET['server_address'] ?? ''),
            'client_ip' => (string) ($_GET['client_ip'] ?? ''),
            'interface' => (string) ($_GET['interface'] ?? ''),
        ];
        [$d, $errors] = $this->validateQuery($raw);
        if ($errors) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => $errors, 'vendos' => []]);
            exit;
        }
        $this->headers('application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'vendos' => $this->api->forHotspot($d['router_identity'], $d['server_address'], $d['client_ip'], $d['interface'])], JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<int,array<string,mixed>> $vendos */
    private function vendoOptions(array $vendos): string
    {
        $options = '';
        foreach ($vendos as $index => $vendo) {
            $options .= '<option value="' . e($vendo['id']) . '"'
                . ' data-base-url="' . e($vendo['baseUrl']) . '"'
                . ' data-password-mode="' . e($vendo['passwordMode']) . '"'
                . ' data-charging="' . ($vendo['chargingEnabled'] ? '1' : '0') . '"'
                . ' data-eload="' . ($vendo['eloadEnabled'] ? '1' : '0') . '"'
                . ($index === 0 ? ' selected' : '')
                . '>' . e($vendo['name']) . '</option>';
        }
        return $options;
    }

    /** @return array{routerIdentity:string,serverAddress:string,ip:string,interfaceName:string} */
    private function hotspotContext(): array
    {
        $raw = [
            'router_identity' => (string) ($_GET['router_identity'] ?? ''),
            'server_address' => (string) ($_GET['server_address'] ?? ''),
            'client_ip' => (string) ($_GET['client_ip'] ?? ''),
            'interface' => (string) ($_GET['interface'] ?? ''),
        ];
        [$data] = $this->validateQuery($raw);
        return [
            'routerIdentity' => $data['router_identity'],
            'serverAddress' => $data['server_address'],
            'ip' => $data['client_ip'],
            'interfaceName' => $data['interface'],
        ];
    }

    private function headers(string $contentType): void
    {
        header('Content-Type: ' . $contentType);
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store');
    }

    /** @return array{0:array{router_identity:string,server_address:string,client_ip:string,interface:string},1:array<string,array<int,string>>} */
    private function validateQuery(array $raw): array
    {
        $data = [];
        $errors = [];
        foreach (['router_identity' => 160, 'server_address' => 45, 'client_ip' => 45, 'interface' => 128] as $key => $max) {
            $value = trim((string) ($raw[$key] ?? ''));
            if (strlen($value) > $max) {
                $errors[$key] = ['The ' . $key . ' field is too long.'];
                $value = substr($value, 0, $max);
            }
            $data[$key] = $value;
        }
        if ($data['router_identity'] === '') {
            $errors['router_identity'] = ['The router identity field is required.'];
        }
        return [$data, $errors];
    }
}

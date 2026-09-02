<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use PDO;
use PDOException;
use PixiePoint\App\Admin\Shared\RouterAccess;
use Tihloh\Prefab\Logs\Services\LogManager;

/**
 * Registers a MikroTik to the account whose API key is used by RouterOS.
 *
 * The response is itself a RouterOS script. That lets the one registration
 * command fetch and import the result without parsing an as-value map, which
 * keeps the flow compatible with older RouterOS versions.
 */
final class RegistrationController
{
    public function __construct(
        private readonly PDO $db,
        private readonly LogManager $logs,
    ) {
    }

    public function register(string $key): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        $key = strtolower(trim($key));
        $identity = trim((string) ($_SERVER['HTTP_X_PIXIEPOINT_IDENTITY'] ?? ''));
        $hardwareId = trim((string) ($_SERVER['HTTP_X_PIXIEPOINT_SERIAL'] ?? ''));

        if (!preg_match('/^[a-f0-9]{48}$/', $key)) {
            $this->fail('Invalid account API key.');
        }

        if ($identity === '' || strlen($identity) > 160) {
            $this->fail('RouterOS identity is missing or invalid.');
        }

        if ($hardwareId === '' || strlen($hardwareId) > 128) {
            $this->fail('Router hardware serial is unavailable.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, active FROM users WHERE account_api_key=? LIMIT 1',
        );
        $stmt->execute([$key]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['active']) {
            $this->fail('Account API key is not active.');
        }

        $userId = (int) $user['id'];
        $access = new RouterAccess($this->db);

        $this->db->beginTransaction();

        try {
            $identityCheck = $this->db->prepare(
                'SELECT id FROM routers WHERE identity=? LIMIT 1 FOR UPDATE',
            );
            $identityCheck->execute([$identity]);

            if ($identityCheck->fetchColumn()) {
                $this->db->rollBack();
                $this->fail('Router identity already registered.');
            }

            $hardwareCheck = $this->db->prepare(
                'SELECT id FROM routers WHERE hardware_id=? LIMIT 1 FOR UPDATE',
            );
            $hardwareCheck->execute([$hardwareId]);

            if ($hardwareCheck->fetchColumn()) {
                $this->db->rollBack();
                $this->fail('This MikroTik hardware is already registered.');
            }

            $agentKey = bin2hex(random_bytes(24));
            $stmt = $this->db->prepare(
                'INSERT INTO routers(name,identity,hardware_id,api_key,enabled) '
                . 'VALUES(?,?,?,?,1)',
            );
            $stmt->execute([
                $identity,
                $identity,
                $hardwareId,
                $agentKey,
            ]);

            $routerId = (int) $this->db->lastInsertId();
            $access->addOwner($routerId, $userId);

            // This role is descriptive only. Router/team permissions remain
            // scoped through router_members; it is not a platform-wide bypass.
            $stmt = $this->db->prepare(
                "UPDATE users SET platform_role='pisowifi_owner' "
                . "WHERE id=? AND platform_role='member'",
            );
            $stmt->execute([$userId]);

            $this->db->commit();

            $this->logs->record([
                'action' => 'router.registered',
                'subject_type' => 'router',
                'subject_id' => $routerId,
                'actor_id' => $userId,
                'message' => 'MikroTik router was registered from RouterOS.',
                'metadata' => [
                    'identity' => $identity,
                    'hardware_id' => $hardwareId,
                    'source' => 'routeros-terminal',
                ],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            $this->success($agentKey);
        } catch (PDOException) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->fail('Router identity or hardware is already registered.');
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->fail('Router could not be registered.');
        }
    }

    /**
     * Returns the post-registration installer as RouterOS commands.
     */
    private function success(string $agentKey): never
    {
        $installUrl = 'https://hs.portalx.win/api/router/install/' . $agentKey;

        echo ':put "PixiePoint router registered";', "\n";
        echo '/tool fetch url="', $installUrl,
            '" mode=https dst-path="PixiePointAgent.rsc";', "\n";
        echo '/system scheduler remove [find name="pixiepoint-agent"];', "\n";
        echo '/system script remove [find name="pixiepoint-agent"];', "\n";
        echo '/system script add name="pixiepoint-agent" ',
            'source=[/file get [find name="PixiePointAgent.rsc"] contents] ',
            'policy=read,write,test;', "\n";
        echo '/system scheduler add name="pixiepoint-agent" interval=5s ',
            'start-time=startup on-event="/system script run pixiepoint-agent" ',
            'policy=read,write,test;', "\n";
        echo '/file remove [find name="PixiePointAgent.rsc"];', "\n";
        echo '/system script run pixiepoint-agent;', "\n";
        echo ':put "SUCCESS - PixiePoint Agent installed";', "\n";
        exit;
    }

    /**
     * Errors are also valid RouterOS so importing the fetched response prints a
     * useful message instead of producing a second syntax error.
     */
    private function fail(string $message): never
    {
        $message = str_replace(["\r", "\n", '"'], ['', ' ', "'"], $message);
        echo ':put "ERROR - ', $message, '";', "\n";
        exit;
    }
}

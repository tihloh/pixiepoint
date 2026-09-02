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
 * Registration deliberately requires values read by the RouterOS command from
 * the router itself. The server also enforces unique RouterOS identity and
 * hardware ID constraints so a router can only be claimed once.
 */
final class RegistrationController
{
    public function __construct(
        private readonly PDO $db,
        private readonly LogManager $logs,
    ) {
    }

    public function register(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        $key = strtolower(trim((string) ($_GET['key'] ?? '')));
        $identity = trim((string) (
            $_SERVER['HTTP_X_PIXIEPOINT_IDENTITY']
            ?? $_GET['identity']
            ?? ''
        ));
        $hardwareId = trim((string) (
            $_SERVER['HTTP_X_PIXIEPOINT_SERIAL']
            ?? $_GET['serial']
            ?? ''
        ));

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

            $stmt = $this->db->prepare(
                'INSERT INTO routers(name,identity,hardware_id,api_key,enabled) '
                . 'VALUES(?,?,?,?,1)',
            );
            $stmt->execute([
                $identity,
                $identity,
                $hardwareId,
                bin2hex(random_bytes(24)),
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

            echo "SUCCESS\n";
            echo 'Router registered: ' . $identity . "\n";
            echo 'Open PixiePoint > Routers to continue setup.';
            exit;
        } catch (PDOException) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // The database unique constraints are the final race-safe guard.
            $this->fail('Router identity or hardware is already registered.');
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->fail('Router could not be registered.');
        }
    }

    private function fail(string $message): never
    {
        echo "ERROR\n", $message;
        exit;
    }
}

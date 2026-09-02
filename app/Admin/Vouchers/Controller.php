<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vouchers;

use PixiePoint\App\Admin\Shared\FeatureController;
use Throwable;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);
        if ($this->isPost()) {
            require_csrf();
            $action = (string) ($_POST['action'] ?? 'create');
            $result = Input::fromRequest()->process([
                'code' => 'trim|uppercase|null_if_empty|nullable|string|max:128',
                'label' => 'trim|null_if_empty|nullable|string|max:255',
                'duration_minutes' => 'required|integer|min:1|max:525600',
                'data_limit_mb' => 'null_if_empty|nullable|integer|min:1',
                'max_devices' => 'required|integer|min:1|max:1000',
                'max_uses' => 'required|integer|min:1|max:1000000',
                'expires_at' => 'trim|null_if_empty|nullable|string|max:32',
                'enabled' => 'default:0|integer|min:0|max:1',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();

                try {
                    if ($action === 'update') {
                        $id = max(0, (int) ($_POST['id'] ?? 0));
                        if ($id < 1) {
                            throw new \RuntimeException('Voucher not found.');
                        }
                        $code = $data['code'] ?? '';
                        if ($code === '') {
                            throw new \RuntimeException('Voucher code is required when editing.');
                        }
                        $stmt = $this->db->prepare('UPDATE vouchers SET code=?,label=?,duration_minutes=?,data_limit_mb=?,max_devices=?,max_uses=?,expires_at=?,enabled=? WHERE id=?');
                        $stmt->execute([
                            $code, $data['label'] ?? null, $data['duration_minutes'], $data['data_limit_mb'] ?? null,
                            $data['max_devices'], $data['max_uses'], $data['expires_at'] ?? null, (int) ($data['enabled'] ?? 0), $id,
                        ]);
                        $this->audit('voucher.updated', 'voucher', $id, 'PisoWiFi voucher was updated.', ['code' => $code]);
                        $message = '<div class="alert ok">Voucher <span class="code">' . e($code) . '</span> updated.</div>';
                    } else {
                        $code = $data['code'] ?? substr(strtoupper(bin2hex(random_bytes(5))), 0, 10);
                        $password = bin2hex(random_bytes(8));
                        $stmt = $this->db->prepare('INSERT INTO vouchers(code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?)');
                        $stmt->execute([$code, $password, $data['label'] ?? null, $data['duration_minutes'], $data['data_limit_mb'] ?? null, $data['max_devices'], $data['max_uses'], $data['expires_at'] ?? null]);
                        $id = (int) $this->db->lastInsertId();
                        $this->audit('voucher.created', 'voucher', $id, 'PisoWiFi voucher was created.', ['code' => $code]);
                        $message = '<div class="alert ok">Voucher <span class="code">' . e($code) . '</span> created.</div>';
                    }
                } catch (Throwable $e) {
                    $message = '<div class="alert">' . e($e->getMessage() ?: 'That voucher code already exists or the voucher could not be saved.') . '</div>';
                }
            }
            $_SESSION['admin_flash'] = $message;
            redirect('/admin/vouchers');
        }

        $this->page('Vouchers', __DIR__ . '/views/index.php', [
            'message' => $message,
            'vouchers' => $this->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100')->fetchAll(),
            'csrf' => csrf_token(),
        ]);
    }
}

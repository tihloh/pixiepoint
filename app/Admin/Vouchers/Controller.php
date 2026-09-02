<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vouchers;

use Throwable;
use PixiePoint\App\Admin\Shared\FeatureController;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $message = '';
        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'code' => 'trim|uppercase|null_if_empty|nullable|string|max:128',
                'label' => 'trim|null_if_empty|nullable|string|max:255',
                'duration_minutes' => 'required|integer|min:1|max:525600',
                'data_limit_mb' => 'null_if_empty|nullable|integer|min:1',
                'max_devices' => 'required|integer|min:1|max:1000',
                'max_uses' => 'required|integer|min:1|max:1000000',
                'expires_at' => 'trim|null_if_empty|nullable|string|max:32',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                $code = $data['code'] ?? substr(strtoupper(bin2hex(random_bytes(5))), 0, 10);
                $password = bin2hex(random_bytes(8));
                try {
                    $stmt = $this->db->prepare('INSERT INTO vouchers(code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?)');
                    $stmt->execute([$code, $password, $data['label'] ?? null, $data['duration_minutes'], $data['data_limit_mb'] ?? null, $data['max_devices'], $data['max_uses'], $data['expires_at'] ?? null]);
                    $id = (int)$this->db->lastInsertId();
                    $this->audit('voucher.created', 'voucher', $id, 'PisoWiFi voucher was created.', ['code' => $code]);
                    $message = '<div class="alert ok">Voucher <span class="code">' . e($code) . '</span> created.</div>';
                } catch (Throwable) {
                    $message = '<div class="alert">That voucher code already exists or the voucher could not be saved.</div>';
                }
            }
        }

        $this->page('Vouchers', __DIR__ . '/views/index.php', [
            'message' => $message,
            'vouchers' => $this->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100')->fetchAll(),
            'csrf' => csrf_token(),
        ]);
    }
}

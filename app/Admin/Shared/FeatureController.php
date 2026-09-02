<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Shared;

use PDO;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Logs\Services\LogManager;

abstract class FeatureController
{
    public function __construct(
        protected PDO $db,
        protected AuthContext $auth,
        protected View $view,
        protected LogManager $logs,
    ) {}

    protected function page(string $title, string $viewFile, array $data = []): never
    {
        $this->view->page($title, $this->view->renderFile($viewFile, $data), true, $this->auth->navigation());
    }

    protected function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function audit(string $action, string $subjectType, int|string|null $subjectId, string $message, array $metadata = []): void
    {
        $this->logs->record([
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'actor_id' => $this->auth->auth()->id(),
            'message' => $message,
            'metadata' => $metadata,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    protected function errors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) {
            foreach ((array)$fieldErrors as $message) $messages[] = e($message);
        }
        return '<div class="alert">' . implode('<br>', $messages ?: ['Please check the form.']) . '</div>';
    }
}

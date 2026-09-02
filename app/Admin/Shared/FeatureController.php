<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Shared;

use PDO;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Logs\Services\LogManager;

/**
 * Shared helpers for feature-local admin controllers.
 *
 * Feature controllers keep their own business logic while inheriting the small
 * amount of common rendering, auditing, and validation plumbing used by admin
 * pages.
 */
abstract class FeatureController
{
    public function __construct(
        protected PDO $db,
        protected AuthContext $auth,
        protected View $view,
        protected LogManager $logs,
    ) {
    }

    /**
     * Renders a feature-local admin view inside the management layout.
     */
    protected function page(
        string $title,
        string $viewFile,
        array $data = [],
    ): never {
        $content = $this->view->renderFile($viewFile, $data);

        $this->view->page(
            $title,
            $content,
            true,
            $this->auth->navigation(),
        );
    }

    protected function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Writes a structured Prefab audit event using the current account as actor.
     */
    protected function audit(
        string $action,
        string $subjectType,
        int|string|null $subjectId,
        string $message,
        array $metadata = [],
    ): void {
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

    /**
     * Converts Prefab Input validation errors into one compact alert.
     */
    protected function errors(array $errors): string
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

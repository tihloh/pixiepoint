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
        // MikroTik-specific output transformations will be added here.
        // Keeping this step in the adapter lets the theme remain platform-neutral.
        return $html;
    }
}

<?php

declare(strict_types=1);

namespace PixiePoint\App\Portal\Adapters;

use PixiePoint\App\Portal\ThemeContext;

interface PlatformAdapter
{
    public function id(): string;

    /** @return array<string,bool> */
    public function capabilities(ThemeContext $context): array;

    public function transform(string $html, ThemeContext $context): string;
}

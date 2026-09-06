<?php

declare(strict_types=1);

namespace PixiePoint\App\Portal;

final class ThemeContext
{
    /** @param array<string,mixed> $data @param array<string,bool> $features */
    public function __construct(
        public readonly array $data = [],
        public readonly array $features = [],
    ) {
    }

    public function value(string $key): mixed
    {
        $value = $this->data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }
}

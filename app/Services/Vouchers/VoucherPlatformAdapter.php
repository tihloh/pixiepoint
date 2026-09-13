<?php

declare(strict_types=1);

namespace PixiePoint\App\Services\Vouchers;

interface VoucherPlatformAdapter
{
    public function key(): string;
    public function label(): string;
    public function filename(array $batch): string;
    public function render(array $router, ?array $station, array $batch, array $vouchers): string;
}

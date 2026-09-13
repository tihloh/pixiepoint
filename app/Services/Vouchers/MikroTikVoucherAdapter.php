<?php

declare(strict_types=1);

namespace PixiePoint\App\Services\Vouchers;

final class MikroTikVoucherAdapter implements VoucherPlatformAdapter
{
    public function key(): string{return 'mikrotik';}
    public function label(): string{return 'MikroTik RouterOS script';}

    public function filename(array $batch): string
    {
        return 'pixiepoint-vouchers-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $batch['batch_key']) . '.rsc';
    }

    public function render(array $router, ?array $station, array $batch, array $vouchers): string
    {
        $lines = [
            '# PixiePoint voucher batch ' . $batch['batch_key'],
            '# Router: ' . $router['name'] . ' (' . $router['identity'] . ')',
            '# Hotspot Station: ' . ($station['name'] ?? 'All stations on selected router'),
            '# Promo: ' . ($batch['promo_name'] ?: 'None'),
            '# Generated: ' . $batch['created_at'],
            ':local ppExisting',
        ];
        $profile = trim((string) ($batch['platform_profile'] ?? ''));
        foreach($vouchers as $voucher){
            $code=$this->quote((string)$voucher['code']);$password=$this->quote((string)$voucher['password']);
            $comment=max(1,(int)$voucher['duration_minutes']) . 'm,0,0,' . ($station['name']??'Voucher');
            $command='/ip hotspot user add name=' . $code . ' password=' . $password;
            if($profile!=='')$command.=' profile=' . $this->quote($profile);
            $command.=' limit-uptime=' . max(1,(int)$voucher['duration_minutes']) . 'm';
            if(!empty($voucher['data_limit_mb']))$command.=' limit-bytes-total=' . ((int)$voucher['data_limit_mb']*1048576);
            $command.=' comment=' . $this->quote($comment);
            $lines[]=':set ppExisting [/ip hotspot user find where name=' . $code . ']';
            $lines[]=':if ([:len $ppExisting] = 0) do={' . $command . '}';
        }
        $lines[]='# End PixiePoint voucher batch';
        return implode("\n",$lines) . "\n";
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\','"'],['\\\\','\\"'],$value) . '"';
    }
}

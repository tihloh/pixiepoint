<?php

declare(strict_types=1);

namespace PixiePoint\App\Services\Vouchers;

use InvalidArgumentException;

final class VoucherEngine
{
    /** @var array<string,VoucherPlatformAdapter> */
    private array $adapters=[];

    public function __construct(VoucherPlatformAdapter ...$adapters)
    {
        foreach($adapters as $adapter)$this->adapters[$adapter->key()]=$adapter;
    }

    public function platforms(): array
    {
        $items=[];foreach($this->adapters as $adapter)$items[$adapter->key()]=$adapter->label();return $items;
    }

    public function adapter(string $platform): VoucherPlatformAdapter
    {
        if(!isset($this->adapters[$platform]))throw new InvalidArgumentException('Unsupported voucher platform.');
        return $this->adapters[$platform];
    }

    public function codes(int $quantity,string $prefix,int $length,array $reserved=[]): array
    {
        $alphabet='23456789ABCDEFGHJKLMNPQRSTUVWXYZ';$prefix=strtoupper(preg_replace('/[^A-Z0-9_-]/i','',$prefix));
        if($quantity<1||$quantity>1000)throw new InvalidArgumentException('Quantity must be between 1 and 1,000.');
        if($length<4||$length>32)throw new InvalidArgumentException('Random code length must be between 4 and 32.');
        $seen=array_fill_keys(array_map('strtoupper',$reserved),true);$codes=[];
        while(count($codes)<$quantity){$suffix='';for($i=0;$i<$length;$i++)$suffix.=$alphabet[random_int(0,strlen($alphabet)-1)];$code=$prefix.$suffix;if(isset($seen[$code]))continue;$seen[$code]=true;$codes[]=$code;}
        return $codes;
    }
}

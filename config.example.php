<?php

$accountingKey=trim((string)(getenv('ACCOUNTING_KEY')?:''));
$vendoGatewayKey=trim((string)(getenv('VENDO_GATEWAY_MASTER_KEY')?:''));
if(strlen($vendoGatewayKey)<32&&strlen($accountingKey)>=32)$vendoGatewayKey=hash_hmac('sha256','pixiepoint.vendo-gateway',$accountingKey);
if(strlen($vendoGatewayKey)<32){
    $secretFile=__DIR__.'/data/secrets/vendo_gateway_master_key';
    if(is_file($secretFile))$vendoGatewayKey=trim((string)file_get_contents($secretFile));
    if(strlen($vendoGatewayKey)<32){
        $secretDir=dirname($secretFile);
        if((is_dir($secretDir)||@mkdir($secretDir,0770,true))&&is_writable($secretDir)){
            $generated=bin2hex(random_bytes(32));
            if(@file_put_contents($secretFile,$generated,LOCK_EX)!==false){@chmod($secretFile,0600);$vendoGatewayKey=$generated;}
        }
    }
}

return [
    'app_name' => getenv('APP_NAME') ?: 'PixiePoint Wi-Fi',
    'base_url' => getenv('APP_URL') ?: 'https://hs.portalx.win',
    'database_dsn' => getenv('DB_DSN') ?: 'mysql:host=mariadb;port=3306;dbname=pixiepoint;charset=utf8mb4',
    'database_user' => getenv('DB_USER') ?: 'pixiepoint',
    'database_password' => getenv('DB_PASS') ?: '',
    'session_name' => getenv('SESSION_NAME') ?: 'pixiepoint_session',
    'cookie_secure' => filter_var(getenv('COOKIE_SECURE') ?: 'true', FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Manila',
    'accounting_key' => $accountingKey,
    'vendo_gateway_master_key' => $vendoGatewayKey,
    'points_pesos_per_point' => max(1, (int) (getenv('POINTS_PESOS_PER_POINT') ?: 5)),
    'points_exclude_sales_at_or_above' => max(0, (int) (getenv('POINTS_EXCLUDE_SALES_AT_OR_ABOVE') ?: 50)),
    'google_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
];

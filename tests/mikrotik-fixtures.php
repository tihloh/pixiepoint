<?php
declare(strict_types=1);
require __DIR__.'/../app/Portal/ThemeContext.php';
require __DIR__.'/../app/Portal/Adapters/PlatformAdapter.php';
require __DIR__.'/../app/Portal/Adapters/MikroTikAdapter.php';
function e(mixed $value): string {return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');}
$adapter=new PixiePoint\App\Portal\Adapters\MikroTikAdapter();
$cases=[];$sessions=[];
foreach(['default','light','neon','sky'] as $theme){
    $template=file_get_contents(__DIR__.'/../app/Portal/Themes/'.$theme.'/portal.html');
    preg_match_all('/<script\b[^>]*>.*?<\/script>/is',$template,$scripts);
    $bootstrap=implode('',array_filter($scripts[0],fn($s)=>str_contains($s,'window.PIXIEPOINT_CONTEXT')));
    $statusBootstrap=implode('',array_filter($scripts[0],fn($s)=>str_contains($s,'window.PIXIEPOINT_SESSION')));
    $statusContext=['state'=>'status','loginUrl'=>'http://10.0.0.1/login','logoutUrl'=>'http://10.0.0.1/logout','sessionTimeLeft'=>'125','username'=>'member','bytesIn'=>'123'];
    $statusHtml='<html><body><section id="pp-status-card"><form id="pp-disconnect-form"></form></section>'.$statusBootstrap.'</body></html>';
    $logoutHtml='<html><body><section id="pp-login-card"><form id="compat-voucher-form"></form></section></body></html>';
    $sessions[]=['status'=>$adapter->transform($statusHtml,new PixiePoint\App\Portal\ThemeContext(['context'=>$statusContext])),'logout'=>$adapter->transform($logoutHtml,new PixiePoint\App\Portal\ThemeContext(['context'=>array_replace($statusContext,['state'=>'logout'])]))];
    foreach(['blank','voucher'] as $mode)foreach([false,true] as $chap){
        $context=['loginUrl'=>'http://10.0.0.1/login','originalUrl'=>'https://example.com/a?q=$1&b=two words','originalUrlEsc'=>rawurlencode('https://example.com/a?q=$1&b=two words'),'chapId'=>$chap?'\000':'','chapChallenge'=>$chap?'\000\011\040\177\200\377\134\061':'','trial'=>'yes','macEsc'=>'AA%3ABB%3ACC%3ADD%3AEE%3AFF','mac'=>'AA:BB:CC:DD:EE:FF','ip'=>'10.0.0.2','passwordMode'=>$mode];
        $html='<html><body><section id="pp-login-card"><form id="compat-voucher-form"><input id="compat-voucher"></form><button id="pp-trial-start">Trial</button><div id="pp-device-info"></div></section>'.$bootstrap.'</body></html>';
        $cases[]=['theme'=>$theme,'mode'=>$mode,'chap'=>$chap,'context'=>$context,'html'=>$adapter->transform($html,new PixiePoint\App\Portal\ThemeContext(['context'=>$context]))];
    }
}

require __DIR__.'/../app/Admin/Vendos/HotspotController.php';
$reflection=new ReflectionClass(PixiePoint\App\Admin\Vendos\HotspotController::class);
$controller=$reflection->newInstanceWithoutConstructor();
$_SESSION=['hotspot'=>['chap_id'=>'old','chap_challenge'=>'old','error'=>'old','trial'=>'yes']];
$_GET=['login_url'=>'http://10.0.0.1/login','chap_id'=>'','chap_challenge'=>'','trial'=>''];
$fresh=$reflection->getMethod('hotspotContext')->invoke($controller);
foreach(['chapId','chapChallenge','error','trial'] as $key)if($fresh[$key]!=='')throw new RuntimeException('Stale '.$key);
echo json_encode(['logins'=>$cases,'sessions'=>$sessions],JSON_THROW_ON_ERROR);


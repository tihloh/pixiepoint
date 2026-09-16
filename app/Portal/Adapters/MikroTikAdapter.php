<?php

declare(strict_types=1);

namespace PixiePoint\App\Portal\Adapters;

use PixiePoint\App\Portal\ThemeContext;

final class MikroTikAdapter implements PlatformAdapter
{
    public function id(): string{return 'mikrotik';}
    public function capabilities(ThemeContext $context): array{return $context->features;}

    public function transform(string $html,ThemeContext $context): string
    {
        $isLogin=preg_match('/\bid\s*=\s*(["\'])pp-login-card\1/i',$html)===1;
        $isStatus=preg_match('/\bid\s*=\s*(["\'])pp-status-card\1/i',$html)===1;
        if(!$isLogin&&!$isStatus)return $html;

        // Router pages fetch this HTML without changing the browser URL. Use the
        // resolved platform context, never location.search, to initialize themes.
        $platform=(array)($context->value('context')??[]);
        $json=json_encode($platform,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        $html=preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is',static function(array $m)use($json):string{
            if(!str_contains($m[0],'URLSearchParams(location.search)'))return $m[0];
            $login=str_contains($m[0],'window.PIXIEPOINT_CONTEXT');
            $status=str_contains($m[0],'window.PIXIEPOINT_SESSION');
            if(!$login&&!$status)return $m[0];
            return '<script>(function(){var context='.$json.';window.'.($login?'PIXIEPOINT_CONTEXT':'PIXIEPOINT_SESSION').'=context;'
                .'window.PIXIEPOINT_BOOTSTRAP=true;window.PIXIEPOINT_CHAP={id:context.chapId||"",challenge:context.chapChallenge||""};'
                .'window.PIXIEPOINT_VENDOS=Array.from(document.querySelectorAll("#compat-vendo option,#pp-vendo option")).map(function(o){return{id:o.value,name:o.textContent.trim(),baseUrl:o.dataset.baseUrl||"",passwordMode:o.dataset.passwordMode||"blank",chargingEnabled:o.dataset.charging==="1",eloadEnabled:o.dataset.eload==="1"};});'
                .'var alert=document.getElementById("compat-alert");if(alert&&context.error){alert.textContent=context.error;alert.hidden=false;}'
                .'})();</script>';
        },$html)??$html;

        if(($platform['state']??'')==='logout'){
            // A logout response has no fresh CHAP challenge. Obtain a login page first.
            return preg_replace('/<section\\b[^>]*id="pp-login-card"[^>]*>.*?<\\/section>/is',
                '<section id="pp-login-card" class="portal-card p-4 shadow"><h1>Session ended</h1><a class="btn btn-primary" href="'.e((string)($platform['loginUrl']??'')).'">Connect again</a></section>',$html,1)??$html;
        }

        if($isStatus){
            $html=str_replace('/assets/session-status.js','/assets/session-portal.js',$html);
            $logoutUrl=(string)($context->value('context.logoutUrl')??'');
            if($logoutUrl!==''){
                $html=preg_replace_callback('/<form\b[^>]*\bid\s*=\s*(["\'])pp-disconnect-form\1[^>]*>/i',static function(array $m)use($logoutUrl):string{
                    $tag=preg_replace('/\s+action\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[0])??$m[0];
                    return rtrim(substr($tag,0,-1)).' action="'.e($logoutUrl).'">';
                },$html,1)??$html;
                $html=preg_replace_callback('/<form\b[^>]*\bid\s*=\s*(["\'])pp-end-session-form\1[^>]*>/i',static function(array $m)use($logoutUrl):string{
                    $tag=preg_replace('/\s+action\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[0])??$m[0];
                    return rtrim(substr($tag,0,-1)).' action="'.e($logoutUrl).'">';
                },$html,1)??$html;
            }
            return $html;
        }

        $loginUrl=(string)($context->value('context.loginUrl')??'');
        $destination=(string)($context->value('context.originalUrl')??'');
        $destinationEsc=(string)($context->value('context.originalUrlEsc')??'');
        $chapId=(string)($context->value('context.chapId')??'');
        $chapChallenge=(string)($context->value('context.chapChallenge')??'');
        $trial=strtolower(trim((string)($context->value('context.trial')??'')));
        $macEsc=(string)($context->value('context.macEsc')??'');
        $hasChap=$chapId!==''&&$chapChallenge!=='';
        $voucherPassword=($context->value('context.passwordMode')==='voucher');

        $html=preg_replace_callback('/<form\b[^>]*\bid\s*=\s*(["\'])compat-voucher-form\1[^>]*>/i',static function(array $m)use($loginUrl,$destination):string{
            $tag=preg_replace('/\s+(?:name|method|action|onsubmit)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[0])??$m[0];
            $tag=rtrim(substr($tag,0,-1)).' name="login" method="post" action="'.e($loginUrl).'" onsubmit="return doLogin()">';
            return $tag.'<input type="hidden" name="password" value=""><input type="hidden" name="dst" value="'.e($destination).'"><input type="hidden" name="popup" value="true">';
        },$html,1)??$html;
        $html=preg_replace_callback('/<input\b[^>]*\bid\s*=\s*(["\'])compat-voucher\1[^>]*>/i',static function(array $m):string{$tag=preg_replace('/\s+name\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[0])??$m[0];return rtrim(substr($tag,0,-1)).' name="username">';},$html,1)??$html;

        if($trial==='yes'&&$loginUrl!==''&&$macEsc!==''){$trialUrl=$loginUrl.'?dst='.($destinationEsc!==''?$destinationEsc:rawurlencode($destination)).'&username=T-'.$macEsc;$html=preg_replace('/<button\b[^>]*\bid\s*=\s*(["\'])pp-trial-start\1[^>]*>.*?<\/button>/is','<a id="pp-trial-start" class="btn btn-outline-success" href="'.e($trialUrl).'">Free trial</a>',$html,1)??$html;}else{$html=preg_replace('/<button\b[^>]*\bid\s*=\s*(["\'])pp-trial-start\1[^>]*>.*?<\/button>/is','',$html,1)??$html;}

        $routerLogin='<div id="pp-mikrotik-login" class="mb-3"><button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#pp-mikrotik-login-panel" aria-expanded="false" aria-controls="pp-mikrotik-login-panel">MikroTik user login</button><div class="collapse mt-3" id="pp-mikrotik-login-panel"><div class="border rounded-3 p-3"><div class="mb-3"><label for="pp-mikrotik-username" class="form-label">Username</label><input id="pp-mikrotik-username" class="form-control" name="username" form="pp-mikrotik-login-form" autocomplete="username" required></div><div class="mb-3"><label for="pp-mikrotik-password" class="form-label">Password</label><input id="pp-mikrotik-password" class="form-control" name="password" form="pp-mikrotik-login-form" type="password" autocomplete="current-password"></div><form id="pp-mikrotik-login-form" name="routerLogin" method="post" action="'.e($loginUrl).'"'.($hasChap?' onsubmit="return doRouterLogin()"':'').'><input type="hidden" name="dst" value="'.e($destination).'"><input type="hidden" name="popup" value="true"><button class="btn btn-primary w-100" type="submit">Login to HotSpot</button></form></div></div></div>';
        $html=preg_match('/<div\s+id=(["\'])pp-device-info\1/i',$html)?(preg_replace_callback('/<div\s+id=(["\'])pp-device-info\1/i',static fn(array $m):string=>$routerLogin.$m[0],$html,1)??$html):$html;
        $passwordExpression=$hasChap?'hexMD5('.$this->chapLiteral($chapId).'+password+'.$this->chapLiteral($chapChallenge).')':'password';
        $native='<form name="sendin" action="'.e($loginUrl).'" method="post" style="display:none"><input type="hidden" name="username"><input type="hidden" name="password"><input type="hidden" name="dst" value="'.e($destination).'"><input type="hidden" name="popup" value="true"></form><script src="/assets/md5.js"></script><script>function ppChap(username,password){var form=document.forms.namedItem("sendin");form.elements.namedItem("username").value=username;form.elements.namedItem("password").value='.$passwordExpression.';form.submit();return false;}function doLogin(){var username=document.getElementById("compat-voucher").value;return ppChap(username,'.($voucherPassword?'username':'""').');}function doRouterLogin(){return ppChap(document.getElementById("pp-mikrotik-username").value,document.getElementById("pp-mikrotik-password").value);}</script>';
        return preg_match('/<\/body\s*>/i',$html)?(preg_replace_callback('/<\/body\s*>/i',static fn():string=>$native.'</body>',$html,1)??$html):$html.$native;
    }

    private function chapLiteral(string $value): string
    {
        $bytes=preg_replace_callback('/\\\\([0-7]{3})/',static fn(array $m):string=>chr(octdec($m[1])),$value)??$value;$encoded='';for($i=0,$length=strlen($bytes);$i<$length;$i++)$encoded.=sprintf('\\x%02x',ord($bytes[$i]));return "'".$encoded."'";
    }
}

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
        if(!$isLogin)return $html;

        $loginUrl=(string)($context->value('context.loginUrl')??'');
        $destination=(string)($context->value('context.originalUrl')??'');
        $chapId=(string)($context->value('context.chapId')??'');
        $chapChallenge=(string)($context->value('context.chapChallenge')??'');
        $hasChap=$chapId!==''&&$chapChallenge!=='';

        $html=preg_replace_callback('/<form\b[^>]*\bid\s*=\s*(["\'])compat-voucher-form\1[^>]*>/i',static function(array $m)use($loginUrl,$destination,$hasChap):string{
            $tag=preg_replace('/\s+(?:name|method|action|onsubmit)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[0])??$m[0];
            $tag=rtrim(substr($tag,0,-1)).' name="login" method="post" action="'.e($loginUrl).'"'.($hasChap?' onsubmit="return doLogin()"':'').'>';
            return $tag.'<input type="hidden" name="password" value=""><input type="hidden" name="dst" value="'.e($destination).'"><input type="hidden" name="popup" value="true">';
        },$html,1)??$html;

        $html=preg_replace_callback('/<input\b[^>]*\bid\s*=\s*(["\'])compat-voucher\1[^>]*>/i',static function(array $m):string{
            $tag=preg_replace('/\s+name\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[0])??$m[0];
            return rtrim(substr($tag,0,-1)).' name="username">';
        },$html,1)??$html;

        $routerLogin='<div id="pp-mikrotik-login" class="mb-3">'
            .'<button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#pp-mikrotik-login-panel" aria-expanded="false" aria-controls="pp-mikrotik-login-panel">MikroTik user login</button>'
            .'<div class="collapse mt-3" id="pp-mikrotik-login-panel"><div class="border rounded-3 p-3">'
            .'<div class="mb-3"><label for="pp-mikrotik-username" class="form-label">Username</label><input id="pp-mikrotik-username" class="form-control" name="username" form="pp-mikrotik-login-form" autocomplete="username" required></div>'
            .'<div class="mb-3"><label for="pp-mikrotik-password" class="form-label">Password</label><input id="pp-mikrotik-password" class="form-control" name="password" form="pp-mikrotik-login-form" type="password" autocomplete="current-password"></div>'
            .'<form id="pp-mikrotik-login-form" name="routerLogin" method="post" action="'.e($loginUrl).'"'.($hasChap?' onsubmit="return doRouterLogin()"':'').'><input type="hidden" name="dst" value="'.e($destination).'"><input type="hidden" name="popup" value="true"><button class="btn btn-primary w-100" type="submit">Login to HotSpot</button></form>'
            .'</div></div></div>';
        $html=preg_match('/<div\s+id=(["\'])pp-device-info\1/i',$html)?(preg_replace('/<div\s+id=(["\'])pp-device-info\1/i',$routerLogin.'<div id=$1pp-device-info$1',$html,1)??$html):$html;

        if(!$hasChap)return $html;

        $native='<form name="sendin" action="'.e($loginUrl).'" method="post" style="display:none">'
            .'<input type="hidden" name="username">'
            .'<input type="hidden" name="password">'
            .'<input type="hidden" name="dst" value="'.e($destination).'">'
            .'<input type="hidden" name="popup" value="true">'
            .'</form>'
            .'<script src="/assets/md5.js"></script>'
            .'<script>function ppChap(username,password){document.sendin.username.value=username;document.sendin.password.value=hexMD5('.$this->chapLiteral($chapId).'+password+'.$this->chapLiteral($chapChallenge).');document.sendin.submit();return false;}function doLogin(){return ppChap(document.login.username.value,"");}function doRouterLogin(){return ppChap(document.routerLogin.username.value,document.routerLogin.password.value);}</script>';

        return preg_match('/<\/body\s*>/i',$html)?(preg_replace('/<\/body\s*>/i',$native.'</body>',$html,1)??$html):$html.$native;
    }

    private function chapLiteral(string $value): string
    {
        $bytes=preg_replace_callback('/\\\\([0-7]{3})/',static fn(array $m):string=>chr(octdec($m[1])),$value)??$value;
        $encoded='';for($i=0,$length=strlen($bytes);$i<$length;$i++)$encoded.=sprintf('\\%03o',ord($bytes[$i]));
        return "'".$encoded."'";
    }
}

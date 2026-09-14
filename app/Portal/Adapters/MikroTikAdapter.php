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

        if(!$hasChap)return $html;

        $native='<form name="sendin" action="'.e($loginUrl).'" method="post" style="display:none">'
            .'<input type="hidden" name="username">'
            .'<input type="hidden" name="password">'
            .'<input type="hidden" name="dst" value="'.e($destination).'">'
            .'<input type="hidden" name="popup" value="true">'
            .'</form>'
            .'<script src="/assets/md5.js"></script>'
            .'<script>function doLogin(){document.sendin.username.value=document.login.username.value;document.sendin.password.value=hexMD5('.$this->chapLiteral($chapId).'+'.$this->chapLiteral($chapChallenge).');document.sendin.submit();return false;}</script>';

        return preg_match('/<\/body\s*>/i',$html)?(preg_replace('/<\/body\s*>/i',$native.'</body>',$html,1)??$html):$html.$native;
    }

    private function chapLiteral(string $value): string
    {
        $bytes=preg_replace_callback('/\\\\([0-7]{3})/',static fn(array $m):string=>chr(octdec($m[1])),$value)??$value;
        $encoded='';for($i=0,$length=strlen($bytes);$i<$length;$i++)$encoded.=sprintf('\\%03o',ord($bytes[$i]));
        return "'".$encoded."'";
    }
}

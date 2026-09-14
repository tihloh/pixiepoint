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
        $isLogin=str_contains($html,'id="pp-login-card"');
        $data=[
            'mac'=>(string)($context->value('context.mac')??''),
            'ip'=>(string)($context->value('context.ip')??''),
            'username'=>(string)($context->value('context.username')??''),
            'routerIdentity'=>(string)($context->value('context.routerIdentity')??''),
            'interfaceName'=>(string)($context->value('context.interfaceName')??''),
            'serverAddress'=>(string)($context->value('context.serverAddress')??''),
            'loginUrl'=>(string)($context->value('context.loginUrl')??''),
            'originalUrl'=>(string)($context->value('context.originalUrl')??''),
            'error'=>(string)($context->value('context.error')??''),
        ];

        if($isLogin){
            $csrf=(string)($context->value('portal.csrf')??'');
            $html=preg_replace('/<form\s+id="compat-voucher-form"([^>]*)>/i','<form id="compat-voucher-form"$1 method="post" action="/hotspot/authenticate"><input type="hidden" name="_csrf" value="'.e($csrf).'">',$html,1)??$html;
            $html=preg_replace('/<input\s+id="compat-voucher"(?![^>]*\bname=)([^>]*)>/i','<input id="compat-voucher" name="voucher"$1>',$html,1)??$html;
            $script='<script>window.PIXIEPOINT_CONTEXT='.$this->json($data).';window.PIXIEPOINT_CHAP='.$this->json(['id'=>(string)($context->value('context.chapId')??''),'challenge'=>(string)($context->value('context.chapChallenge')??'')]).';window.PIXIEPOINT_SERVER_RENDERED=true;</script>';
        }else{
            $script='<script>window.PIXIEPOINT_SESSION='.$this->json([
                'username'=>$data['username'],'mac'=>$data['mac'],'ip'=>$data['ip'],'routerIdentity'=>$data['routerIdentity'],'serverAddress'=>$data['serverAddress'],'interfaceName'=>$data['interfaceName'],
                'sessionTimeLeft'=>(string)($context->value('context.sessionTimeLeft')??''),'bytesIn'=>(string)($context->value('context.bytesIn')??''),'bytesOut'=>(string)($context->value('context.bytesOut')??''),
                'remainBytesTotal'=>(string)($context->value('context.remainBytesTotal')??''),'refreshUrl'=>(string)($context->value('context.refreshUrl')??''),'logoutUrl'=>(string)($context->value('context.logoutUrl')??''),'loginUrl'=>$data['loginUrl'],
            ]).';window.PIXIEPOINT_SERVER_RENDERED=true;</script>';
        }

        return preg_match('/<\/body\s*>/i',$html)?(preg_replace('/<\/body\s*>/i',$script.'</body>',$html,1)??$html):$html.$script;
    }

    private function json(array $value): string
    {
        return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?:'{}';
    }
}

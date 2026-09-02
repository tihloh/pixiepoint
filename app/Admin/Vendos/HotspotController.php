<?php

declare(strict_types=1);
namespace PixiePoint\App\Admin\Vendos;
use PixiePoint\App\Services\View; use Tihloh\Prefab\Input\Input;
final class HotspotController
{
 public function __construct(private Api $api,private View $view){}
 public function portal():never
 {
  header('Content-Type: text/html; charset=utf-8');header('Access-Control-Allow-Origin: *');header('Cache-Control: no-store');
  $result=Input::fromRequest()->process(['router_identity'=>'trim|required|string|max:160','server_address'=>'trim|null_if_empty|nullable|string|max:45','client_ip'=>'trim|null_if_empty|nullable|string|max:45','interface'=>'trim|null_if_empty|nullable|string|max:128']);$data=$result->fails()?[]:$result->validated();
  $context=['routerIdentity'=>(string)($data['router_identity']??''),'serverAddress'=>(string)($data['server_address']??''),'ip'=>(string)($data['client_ip']??''),'interfaceName'=>(string)($data['interface']??'')];
  $vendos=$context['routerIdentity']===''?[]:$this->api->forHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName']);
  echo $this->view->render('hotspot/compatibility',['context'=>$context,'vendos'=>$vendos]);exit;
 }
 /** Legacy JSON endpoint retained for integrations; the captive portal no longer uses it. */
 public function index():never
 {
  header('Content-Type: application/json; charset=utf-8');header('Access-Control-Allow-Origin: *');header('Cache-Control: no-store');
  $result=Input::fromRequest()->process(['router_identity'=>'trim|required|string|max:160','server_address'=>'trim|null_if_empty|nullable|string|max:45','client_ip'=>'trim|null_if_empty|nullable|string|max:45','interface'=>'trim|null_if_empty|nullable|string|max:128']);if($result->fails()){http_response_code(422);echo json_encode(['ok'=>false,'vendos'=>[]]);exit;}$d=$result->validated();
  echo json_encode(['ok'=>true,'vendos'=>$this->api->forHotspot((string)$d['router_identity'],(string)($d['server_address']??''),(string)($d['client_ip']??''),(string)($d['interface']??''))],JSON_UNESCAPED_SLASHES);exit;
 }
}

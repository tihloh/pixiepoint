<?php

declare(strict_types=1);
namespace PixiePoint\App\Admin\Vendos;
use PixiePoint\App\Services\View;
final class HotspotController
{
 public function __construct(private Api $api,private View $view){}
 public function portal():never
 {
  header('Content-Type: text/html; charset=utf-8');header('Access-Control-Allow-Origin: *');header('Cache-Control: no-store');
  $raw=['router_identity'=>(string)($_GET['router_identity']??''),'server_address'=>(string)($_GET['server_address']??''),'client_ip'=>(string)($_GET['client_ip']??''),'interface'=>(string)($_GET['interface']??'')];
  [$data,$errors]=$this->validateQuery($raw);
  $context=['routerIdentity'=>$data['router_identity'],'serverAddress'=>$data['server_address'],'ip'=>$data['client_ip'],'interfaceName'=>$data['interface']];
  $vendos=$context['routerIdentity']===''?[]:$this->api->forHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName']);
  $debug=[];
  if($this->api->debugEnabled()){
   $matching=$context['routerIdentity']===''?['input'=>$context,'candidateCount'=>0,'selectedCount'=>0,'selectedIds'=>[],'candidates'=>[]]:$this->api->debugForHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName']);
   $debug=['raw'=>$raw,'processed'=>$context,'validationErrors'=>$errors,'matching'=>$matching];
  }
  echo $this->view->render('hotspot/compatibility',['context'=>$context,'vendos'=>$vendos,'debug'=>$debug]);exit;
 }
 /** Legacy JSON endpoint retained for integrations; the captive portal no longer uses it. */
 public function index():never
 {
  header('Content-Type: application/json; charset=utf-8');header('Access-Control-Allow-Origin: *');header('Cache-Control: no-store');
  $raw=['router_identity'=>(string)($_GET['router_identity']??''),'server_address'=>(string)($_GET['server_address']??''),'client_ip'=>(string)($_GET['client_ip']??''),'interface'=>(string)($_GET['interface']??'')];[$d,$errors]=$this->validateQuery($raw);
  if($errors){http_response_code(422);echo json_encode(['ok'=>false,'errors'=>$errors,'vendos'=>[]]);exit;}
  echo json_encode(['ok'=>true,'vendos'=>$this->api->forHotspot($d['router_identity'],$d['server_address'],$d['client_ip'],$d['interface'])],JSON_UNESCAPED_SLASHES);exit;
 }
 /** @return array{0:array{router_identity:string,server_address:string,client_ip:string,interface:string},1:array<string,array<int,string>>} */
 private function validateQuery(array $raw):array
 {
  $data=[];$errors=[];
  foreach(['router_identity'=>160,'server_address'=>45,'client_ip'=>45,'interface'=>128] as $key=>$max){$value=trim((string)($raw[$key]??''));if(strlen($value)>$max){$errors[$key]=['The '.$key.' field is too long.'];$value=substr($value,0,$max);}$data[$key]=$value;}
  if($data['router_identity']==='')$errors['router_identity']=['The router identity field is required.'];
  return [$data,$errors];
 }
}

<?php

declare(strict_types=1);

namespace PixiePoint\App\Api;

use InvalidArgumentException;
use JsonException;
use PDO;
use PixiePoint\App\Services\VendoGatewayBridge;
use Tihloh\VendoGateway\Database\Migrator;
use Tihloh\VendoGateway\Gateway;
use Tihloh\VendoGateway\GatewayFactory;
use Tihloh\VendoGateway\Http\CommandEndpoint;
use Tihloh\VendoGateway\Http\ConfigEndpoint;
use Tihloh\VendoGateway\Http\DeviceAuth;
use Tihloh\VendoGateway\Http\DiscoveryEndpoint;
use Tihloh\VendoGateway\Http\EventEndpoint;
use Tihloh\VendoGateway\Http\FirmwareEndpoint;
use Tihloh\VendoGateway\Http\HeartbeatEndpoint;
use Tihloh\VendoGateway\Http\PairingEndpoint;
use Tihloh\VendoGateway\Http\StateEndpoint;
use Tihloh\VendoGateway\Http\SyncEndpoint;

final class VendoGatewayController
{
    private ?Gateway $gateway=null;
    private ?DeviceAuth $deviceAuth=null;
    private VendoGatewayBridge $bridge;

    public function __construct(private PDO $db,private array $config)
    {
        (new Migrator($this->db))->migrate();$this->bridge=new VendoGatewayBridge($this->db);
        $key=trim((string)($this->config['vendo_gateway_master_key']??''));if(strlen($key)>=32){$this->gateway=GatewayFactory::pdo($this->db,$key);$this->deviceAuth=new DeviceAuth(GatewayFactory::authenticator($this->db,$key));$this->bridge->register($this->gateway);}
    }

    public function discover(): never{$this->json((new DiscoveryEndpoint((string)($this->config['app_name']??'PixiePoint'),'/vendo/v1'))->handle());}

    public function pair(): never
    {
        $gateway=$this->gateway();
        $this->run(function() use($gateway): array{
            $result=(new PairingEndpoint($gateway->pairings))->enroll($this->body());$context=is_array($result['context']??null)?$result['context']:[];$deviceId=(string)($result['device_id']??'');$stationId=max(0,(int)($context['station_id']??$context['vendo_id']??0));$routerId=max(0,(int)($context['router_id']??0));$name=trim((string)($context['vendo_name']??'Vendo'))?:'Vendo';
            if($routerId<1&&$stationId>0)$routerId=$this->bridge->routerIdForStation($stationId);
            if($deviceId!==''&&$routerId>0)$this->bridge->registerVendo($deviceId,$routerId,$name,$stationId>0?$stationId:null);
            unset($result['context']);return$result;
        });
    }

    public function pairingAck(string $id): never{$gateway=$this->gateway();$this->run(function() use($gateway,$id): array{$payload=$this->body();$code=trim((string)($payload['setup_code']??''));return(new PairingEndpoint($gateway->pairings))->acknowledge($id,$code);});}
    public function heartbeat(): never{$gateway=$this->gateway();$raw=$this->raw();$this->run(fn()=>(new HeartbeatEndpoint(GatewayFactory::authenticator($this->db,$this->masterKey()),$gateway->heartbeats))->handle($this->headers(),$raw,'POST','/vendo/v1/heartbeat',$_SERVER['REMOTE_ADDR']??null));}
    public function sync(): never{$gateway=$this->gateway();$raw=$this->raw();$this->run(fn()=>(new SyncEndpoint($this->deviceAuth(),$gateway->configs,$gateway->commands,$gateway->states,$gateway->firmware))->handle($this->headers(),$raw,'POST','/vendo/v1/sync'));}
    public function events(): never{$gateway=$this->gateway();$raw=$this->raw();$this->run(fn()=>(new EventEndpoint($this->deviceAuth(),$gateway->events))->handle($this->headers(),$raw,'POST','/vendo/v1/events'));}
    public function commands(): never{$gateway=$this->gateway();$limit=max(1,min(50,(int)($_GET['limit']??10)));$this->run(fn()=>(new CommandEndpoint($this->deviceAuth(),$gateway->commands))->poll($this->headers(),'','GET','/vendo/v1/commands',$limit));}
    public function commandAck(string $id): never{$gateway=$this->gateway();$raw=$this->raw();$this->run(fn()=>(new CommandEndpoint($this->deviceAuth(),$gateway->commands))->acknowledge($this->headers(),$raw,'POST','/vendo/v1/commands/'.$id.'/ack',$id));}
    public function config(): never{$gateway=$this->gateway();$revision=isset($_GET['revision'])?(string)$_GET['revision']:null;$this->run(fn()=>(new ConfigEndpoint($this->deviceAuth(),$gateway->configs,$gateway->commands,$gateway->states,$gateway->firmware))->pull($this->headers(),'','GET','/vendo/v1/config',$revision));}
    public function state(): never{$gateway=$this->gateway();$raw=$this->raw();$this->run(fn()=>(new StateEndpoint($this->deviceAuth(),$gateway->states))->report($this->headers(),$raw,'POST','/vendo/v1/state'));}
    public function firmware(): never{$gateway=$this->gateway();$raw=$this->raw();$this->run(fn()=>(new FirmwareEndpoint($this->deviceAuth(),$gateway->firmware))->check($this->headers(),$raw,'POST','/vendo/v1/firmware/check'));}

    private function gateway(): Gateway{if(!$this->gateway)$this->json(['ok'=>false,'error'=>'vendo_gateway_not_configured'],503);return$this->gateway;}
    private function deviceAuth(): DeviceAuth{if(!$this->deviceAuth)$this->json(['ok'=>false,'error'=>'vendo_gateway_not_configured'],503);return$this->deviceAuth;}
    private function masterKey(): string{return(string)($this->config['vendo_gateway_master_key']??'');}
    private function raw(): string{return(string)(file_get_contents('php://input')?:'');}
    private function body(): array{$raw=$this->raw();if($raw==='')return[];$body=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);return is_array($body)?$body:[];}
    private function headers(): array{$headers=function_exists('getallheaders')?getallheaders():[];foreach($_SERVER as $key=>$value){if(!str_starts_with($key,'HTTP_'))continue;$name=str_replace(' ','-',ucwords(strtolower(str_replace('_',' ',substr($key,5)))));$headers[$name]=(string)$value;}return$headers;}
    private function run(callable $callback): never{try{$this->json($callback());}catch(JsonException $e){$this->json(['ok'=>false,'error'=>'invalid_json','message'=>$e->getMessage()],422);}catch(InvalidArgumentException $e){$this->json(['ok'=>false,'error'=>'invalid_request','message'=>$e->getMessage()],422);}catch(\RuntimeException $e){$this->json(['ok'=>false,'error'=>'request_rejected','message'=>$e->getMessage()],400);}catch(\Throwable){$this->json(['ok'=>false,'error'=>'vendo_gateway_error'],500);}}
    private function json(array $payload,int $status=200): never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($payload,JSON_UNESCAPED_SLASHES);exit;}
}

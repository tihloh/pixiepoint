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
        (new Migrator($this->db))->migrate();
        $this->bridge=new VendoGatewayBridge($this->db);
        $key=trim((string)($this->config['vendo_gateway_master_key']??''));
        if(strlen($key)>=32){
            $this->gateway=GatewayFactory::pdo($this->db,$key);
            $this->deviceAuth=new DeviceAuth(GatewayFactory::authenticator($this->db,$key));
            $this->bridge->register($this->gateway);
            $this->cleanupPairings();
        }
    }

    public function discover(): never{
        $this->json((new DiscoveryEndpoint((string)($this->config['app_name']??'PixiePoint'),'/vendo/v1'))->handle());
    }

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

    public function pairingAck(string $id): never
    {
        $gateway=$this->gateway();
        $this->run(function() use($gateway,$id): array{
                $payload=$this->body();$code=trim((string)($payload['setup_code']??''));$result=(new PairingEndpoint($gateway->pairings))->acknowledge($id,$code);
                if(!method_exists($gateway->pairings,'cleanupExpired')){
                    $stmt=$this->db->prepare('DELETE FROM vg_pairings WHERE pairing_id=? AND status="completed" AND issued_device_secret_encrypted IS NULL');$stmt->execute([$id]);
                }
                return$result;
            });
    }
    public function heartbeat(): never{
        $gateway=$this->gateway();
        $raw=$this->raw();
        $this->run(fn()=>(new HeartbeatEndpoint($this->deviceAuth(),$gateway->heartbeats))->handle($this->headers(),$raw,'POST','/vendo/v1/heartbeat',$_SERVER['REMOTE_ADDR']??null));
    }
    public function sync(): never{
        $gateway=$this->gateway();
        $auth=$this->deviceAuth();
        $raw=$this->raw();
        $headers=$this->headers();
        $this->run(function() use($gateway,$auth,$raw,$headers): array{
            $device=$auth->authenticate($headers,$raw,'POST','/vendo/v1/sync');
            $response=(new SyncEndpoint($auth,$gateway->configs,$gateway->commands,$gateway->states,$gateway->firmware,$gateway->events,$gateway->heartbeats))->handle($headers,$raw,'POST','/vendo/v1/sync',$_SERVER['REMOTE_ADDR']??null);
            $session=$this->bridge->activeCoinSession($device->deviceId);
            $response['coin_session']=$session?[
                'active'=>true,
                'session_id'=>$session['session_id'],
                'coin_count'=>$session['coin_count'],
                'credits'=>$session['credits'],
                'last_sequence'=>$session['last_sequence'],
                'last_coin_credits'=>$session['last_coin_credits']
            ]:['active'=>false];
            return$response;
        });
    }
    public function events(): never{
        $gateway=$this->gateway();
        $raw=$this->raw();
        $this->run(fn()=>(new EventEndpoint($this->deviceAuth(),$gateway->events))->handle($this->headers(),$raw,'POST','/vendo/v1/events'));
    }
    public function commands(): never{
        $gateway=$this->gateway();
        $limit=max(1,min(50,(int)($_GET['limit']??10)));
        $this->run(fn()=>(new CommandEndpoint($this->deviceAuth(),$gateway->commands))->poll($this->headers(),'','GET','/vendo/v1/commands',$limit));
    }
    public function commandAck(string $id): never{
        $gateway=$this->gateway();
        $raw=$this->raw();
        $this->run(fn()=>(new CommandEndpoint($this->deviceAuth(),$gateway->commands))->acknowledge($this->headers(),$raw,'POST','/vendo/v1/commands/'.$id.'/ack',$id));
    }
    public function config(): never{
        $gateway=$this->gateway();
        $revision=isset($_GET['revision'])?(string)$_GET['revision']:null;
        $this->run(fn()=>(new ConfigEndpoint($this->deviceAuth(),$gateway->configs,$gateway->commands,$gateway->states,$gateway->firmware))->pull($this->headers(),'','GET','/vendo/v1/config',$revision));
    }
    public function state(): never{
        $gateway=$this->gateway();
        $raw=$this->raw();
        $this->run(fn()=>(new StateEndpoint($this->deviceAuth(),$gateway->states))->report($this->headers(),$raw,'POST','/vendo/v1/state'));
    }
    public function firmware(): never{
        $gateway=$this->gateway();
        $raw=$this->raw();
        $this->run(fn()=>(new FirmwareEndpoint($this->deviceAuth(),$gateway->firmware))->check($this->headers(),$raw,'POST','/vendo/v1/firmware/check'));
    }

    public function firmwareDownload(string $version,string $target): never
    {
        $version=ltrim(trim($version),'vV');
        $target=strtolower(trim($target));
        if(!preg_match('/^\d+\.\d+\.\d+(?:\+[a-zA-Z0-9.-]+)?$/',$version)||!in_array($target,['esp8266','esp32'],true)){
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Firmware not found.';
            exit;
        }

        $name='vendogate-'.$target.'.bin';
        $cacheDir=sys_get_temp_dir().'/pixiepoint-vendo-firmware';
        if(!is_dir($cacheDir)&&!@mkdir($cacheDir,0770,true)&&!is_dir($cacheDir)){
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Firmware cache unavailable.';
            exit;
        }

        $cache=$cacheDir.'/v'.$version.'-'.$name;
        if(!is_file($cache)||filesize($cache)<1){
            $lock=fopen($cache.'.lock','c');
            if(!$lock||!flock($lock,LOCK_EX)){
                if($lock)fclose($lock);
                http_response_code(503);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Firmware cache busy.';
                exit;
            }
            clearstatcache(true,$cache);
            if(!is_file($cache)||filesize($cache)<1){
                if(!function_exists('curl_init')){
                    flock($lock,LOCK_UN);
                    fclose($lock);
                    http_response_code(503);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Firmware transport unavailable.';
                    exit;
                }

                $tmp=$cache.'.tmp.'.bin2hex(random_bytes(4));
                $out=fopen($tmp,'wb');
                if(!$out){
                    flock($lock,LOCK_UN);
                    fclose($lock);
                    http_response_code(503);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Firmware cache unavailable.';
                    exit;
                }

                $url='https://github.com/tihloh/vendogate-firmware-releases/releases/download/v'.rawurlencode($version).'/'.$name;
                $ch=curl_init($url);
                curl_setopt_array($ch,[
                    CURLOPT_FILE=>$out,
                    CURLOPT_FOLLOWLOCATION=>true,
                    CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                    CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
                    CURLOPT_CONNECTTIMEOUT=>5,
                    CURLOPT_TIMEOUT=>120,
                    CURLOPT_USERAGENT=>'PixiePoint-VendoFirmware/1',
                    CURLOPT_FAILONERROR=>false,
                ]);
                $ok=curl_exec($ch);
                $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                fclose($out);

                if(!$ok||$code!==200||!is_file($tmp)||filesize($tmp)<1){
                    @unlink($tmp);
                    flock($lock,LOCK_UN);
                    fclose($lock);
                    http_response_code(502);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Firmware upstream unavailable.';
                    exit;
                }

                if(!@rename($tmp,$cache)){
                    @unlink($tmp);
                    flock($lock,LOCK_UN);
                    fclose($lock);
                    http_response_code(503);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Firmware cache unavailable.';
                    exit;
                }
            }
            flock($lock,LOCK_UN);
            fclose($lock);
        }

        clearstatcache(true,$cache);
        $size=(int)filesize($cache);
        $start=0;
        $end=$size-1;
        $status=200;
        $range=trim((string)($_SERVER['HTTP_RANGE']??''));

        if($range!==''){
            if(!preg_match('/^bytes=(\d+)-(\d*)$/',$range,$m)){
                http_response_code(416);
                header('Content-Range: bytes */'.$size);
                exit;
            }
            $start=(int)$m[1];
            $end=$m[2]!==''?(int)$m[2]:$end;
            if($start<0||$start>=$size||$end<$start){
                http_response_code(416);
                header('Content-Range: bytes */'.$size);
                exit;
            }
            if($end>=$size)$end=$size-1;
            $status=206;
        }

        $length=$end-$start+1;
        http_response_code($status);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=86400, immutable');
        header('Content-Length: '.$length);
        if($status===206)header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);

        $fh=fopen($cache,'rb');
        if(!$fh)exit;
        if($start>0)fseek($fh,$start);
        $remaining=$length;
        while($remaining>0&&!feof($fh)){
            $chunk=fread($fh,min(65536,$remaining));
            if($chunk===false||$chunk==='')break;
            echo $chunk;
            $remaining-=strlen($chunk);
            @ob_flush();
            flush();
        }
        fclose($fh);
        exit;
    }

    private function cleanupPairings(): void
    {
        if(!$this->gateway)return;
        if(method_exists($this->gateway->pairings,'cleanupExpired')){
            $this->gateway->pairings->cleanupExpired();
            return;
        }
        $this->db->exec("DELETE FROM vg_pairings WHERE status='pending' AND expires_at<=UTC_TIMESTAMP()");
    }
    private function gateway(): Gateway{
        if(!$this->gateway)$this->json(['ok'=>false,'error'=>'vendo_gateway_not_configured'],503);
        return$this->gateway;
    }
    private function deviceAuth(): DeviceAuth{
        if(!$this->deviceAuth)$this->json(['ok'=>false,'error'=>'vendo_gateway_not_configured'],503);
        return$this->deviceAuth;
    }
    private function raw(): string{
        return(string)(file_get_contents('php://input')?:'');
    }
    private function body(): array{
        $raw=$this->raw();
        if($raw==='')return[];
        $body=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);
        return is_array($body)?$body:[];
    }
    private function headers(): array{
        $headers=function_exists('getallheaders')?getallheaders():[];
        foreach($_SERVER as $key=>$value){
            if(!str_starts_with($key,'HTTP_'))continue;
            $name=str_replace(' ','-',ucwords(strtolower(str_replace('_',' ',substr($key,5)))));
            $headers[$name]=(string)$value;
        }
        if(isset($_SERVER['HTTP_AUTHORIZATION']))$headers['Authorization']=(string)$_SERVER['HTTP_AUTHORIZATION'];
        elseif(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))$headers['Authorization']=(string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        return$headers;
    }
    private function run(callable $callback): never{
        try{
            $this->json($callback());
        }
        catch(JsonException $e){
            $this->json(['ok'=>false,'error'=>'invalid_json','message'=>$e->getMessage()],422);
        }
        catch(InvalidArgumentException $e){
            $this->json(['ok'=>false,'error'=>'invalid_request','message'=>$e->getMessage()],422);
        }
        catch(\RuntimeException $e){
            $this->json(['ok'=>false,'error'=>'request_rejected','message'=>$e->getMessage()],400);
        }
        catch(\Throwable $e){
            error_log('[VendoGateway] '.$e::class.': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
            $this->json(['ok'=>false,'error'=>'vendo_gateway_error'],500);
        }
    }
    private function json(array $payload,int $status=200): never{
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload,JSON_UNESCAPED_SLASHES);
        exit;
    }
}

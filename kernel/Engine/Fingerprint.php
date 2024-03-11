<?php
namespace Manomite\Engine;
use Detection\MobileDetect;
use \DeviceDetector\{
    DeviceDetector,
    Parser\Device\AbstractDeviceParser as DV
};
use \Manomite\Protect\PostFilter;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__."/../../autoload.php";

class Fingerprint
{
    protected $adapter;
    protected $filter;
    protected $cacheKey;
    protected $cachettl;

    public function __construct()
    {
        $this->adapter = new CacheAdapter();//7 days cache
        $this->filter = new PostFilter;
        $this->cacheKey = 'mon_00003748342345473548364893904_';
        $this->cachettl =  604800;
    }

    public function scan()
    {
        $geo = new Geo;
        $ip = $geo->getIpAddress();
        $this->cacheKey = $this->cacheKey.'_'.$ip;
        $cache = $this->adapter->getCache($this->cacheKey);
        if (!$this->filter->nothing($cache)) {
            return json_decode($cache, true);
        } else {
            $geo = new Geo;
            $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'none';
            //Get device info
            DV::setVersionTruncation(DV::VERSION_TRUNCATION_NONE);
            $userAgent = $this->filter->strip($agent);
            $dd = new DeviceDetector($userAgent);
            $dd->parse();
            $browsers = $dd->getClient();
            $osy = $dd->getOs();

            $bname = isset($browsers['name']) ? $browsers['name'] : 'N/A';
            $bversion = isset($browsers['version']) ? $browsers['version'] : 'N/A';
            $osname = isset($osy['name']) ? $osy['name'] : 'N/A';
            $osversion = isset($osy['version']) ? $osy['version'] : 'N/A';

            $browser = $bname.' '.$bversion;
            
            $device = $dd->getDeviceName();
            $os = $osname.' '.$osversion;

            //Organize
            $device_data = array(
            'browser'     => $this->filter->strip($browser),
            'os'          => $this->filter->strip($os),
            'ip'          => $this->filter->strip($ip),
            'device'      => $this->filter->strip($device)
        );
            $this->adapter->cache(json_encode($device_data), $this->cacheKey, $this->cachettl);
            return $device_data;
        }
    }

    public function detect_device(){
        $detect = new MobileDetect();
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'none';
        $detect->setUserAgent($agent);
        $deviceType = ($detect->isMobile() ? ($detect->isTablet() ? 'tablet' : 'phone') : 'computer');
        return $deviceType;
    }

    public function codeGenerate($software = APP_NAME, $dynamic = false, $date = null)
    {
        $payload = $this->scan();
        $new_payload = array(
            'browser'     => $payload['browser'],
            'os'          => $payload['os'],
            'ip'          => $payload['ip'],
            'device'      => $payload['device'],
        );
        if(empty( $new_payload)){
            return false;
        }
        $hostname = APP_DOMAIN ?: 'http://127.0.0.1';
        $key = json_encode($software.$hostname.json_encode($new_payload).$date);
        $key = hash('sha512', ($key));
        $key_formated = '';
        for ($i= 0; $i < strlen($key); $i++) {
            if ($i>0 && $i % 5 == 0) {
                $key_formated .= strtoupper(substr($key, $i, 5)) . '-';
            }
        }
        if ($dynamic) {
            return $this->parse(trim($key_formated, '-'));
        }
        return trim($key_formated, '-');
    }
        
    private function parse($key_string)
    {
        $basechar = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        //characters in each segment, max 5 segments
        $segment_chars = 4;
        //number of segment in the key
        $num_segments = 4;

        $segment = '';
        for ($i = 0; $i < $num_segments; $i++) {
            $segment = '';
            for ($j = 0; $j < $segment_chars; $j++) {
                $segment .= $basechar[ rand(0, strlen($basechar)-1) ];
            }
            $key_string .= $segment;
            if ($i < ($num_segments - 1)) {
                $key_string .= '-';
            }
        }
        return $key_string;
    }

}
<?php
namespace Manomite\Engine;
//This PHP class uses the PHP Webservice of http://www.geoplugin.com/ to geolocate IP addresses
require_once __DIR__.'/../../autoload.php';
class GeoPlugin {

    protected $others = null;
    //the geoPlugin server
    /*
    supported languages:
    de
    en
    es
    fr
    ja
    pt-BR
    ru
    zh-CN
    */
    private $cache_key;
    private $cache;
    private $ip;

    public function __construct( string $ip = null) {
        $this->cache = new CacheAdapter();
        $this->cache_key = $ip.'client1.0.7';
        
        if (is_null($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $this->ip = $ip;
    }

    public function locate(){
        $cache = $this->cache->getCache($this->cache_key);
        if($cache !== null){
            return json_decode($cache, true);
        }
        $ip = $this->ip;
        if (empty($this->ip) || $this->ip === '::1' || $this->ip !== '127.0.0.1') {
            return array(); //Cannot geolocate localhost or empty ip
        }
        $data = array();
        $host = "http://www.geoplugin.net/php.gp?ip={$ip}";
        $response = $this->fetch( $host );
        $data = unserialize( $response);
        if (isset($data) AND $data !== false AND count($data) > 5) {
            //set the geoPlugin publics
            $data = array(
            'ip' => $this->ip,
            'city' => $data['geoplugin_city'],
            'region' => $data['geoplugin_region'],
            'regionCode' => $data['geoplugin_regionCode'],
            'regionName' => $data['geoplugin_regionName'],
            'dmaCode' => $data['geoplugin_dmaCode'],
            'countryCode' => $data['geoplugin_countryCode'],
            'countryName' => $data['geoplugin_countryName'],
            'inEU' => $data['geoplugin_inEU'],
            'continentCode' => $data['geoplugin_continentCode'],
            'continentName' => $data['geoplugin_continentName'],
            'latitude' => $data['geoplugin_latitude'],
            'longitude' => $data['geoplugin_longitude'],
            'locationAccuracyRadius' => $data['geoplugin_locationAccuracyRadius'],
            'timezone' => $data['geoplugin_timezone'],
            'currencyCode' => $data['geoplugin_currencyCode'],
            'currencySymbol' => $data['geoplugin_currencySymbol'],
            'currencyConverter' => $data['geoplugin_currencyConverter']
        );
    
            $this->others = $data;
            $this->cache->cache(json_encode($this->others), $this->cache_key, 3600);
        }
        return $this->others;
    }

    protected function fetch( $host ) {

        if ( function_exists( 'curl_init' ) ) {

            //use cURL to fetch data
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $host );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_USERAGENT, 'geoPlugin PHP Class v1.1' );
            $response = curl_exec( $ch );
            curl_close ( $ch );

        } else if ( ini_get( 'allow_url_fopen' ) ) {

            //fall back to fopen()
            $response = file_get_contents( $host );

        } else {

            trigger_error ( 'geoPlugin class Error: Cannot retrieve data. Either compile PHP with cURL support or enable allow_url_fopen in php.ini ', E_USER_ERROR );
            return;

        }

        return $response;
    }

    public function convert( int $amount, int $float = 2, bool $symbol = true ) {

        //easily convert amounts to geolocated currency.
        if ( !is_numeric( $this->others['currencyConverter'] ) || $this->others['currencyConverter'] == 0 ) {
            trigger_error( 'geoPlugin class Notice: currencyConverter has no value.', E_USER_NOTICE );
            return $amount;
        }
        if ( !is_numeric( $amount ) ) {
            trigger_error ( 'geoPlugin class Warning: The amount passed to geoPlugin::convert is not numeric.', E_USER_WARNING );
            return $amount;
        }
        if ( $symbol === true ) {
            return $this->others['currencySymbol'] . round( ( $amount * $this->others['currencyConverter'] ), $float );
        } else {
            return round( ( $amount * $this->others['currencyConverter'] ), $float );
        }
    }

    public function nearby( int $radius = 10, int $limit = null ) {

        if ( !is_numeric( $this->others['latitude'] ) || !is_numeric( $this->others['longitude'] ) ) {
            trigger_error ( 'geoPlugin class Warning: Incorrect latitude or longitude values.', E_USER_NOTICE );
            return array( array() );
        }

        $host = 'http://www.geoplugin.net/extras/nearby.gp?lat=' . $this->others['latitude'] . '&long=' . $this->others['longitude'] . "&radius={$radius}";

        if ( is_numeric( $limit ) )
        $host .= "&limit={$limit}";

        return unserialize( $this->fetch( $host ) );

    }

}

?>

<?php

//New User Account Register Controller
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
use Manomite\{
    Exception\ManomiteException as ex,
    Route\Route,
    Protect\PostFilter,
    Mouth\SimpleRoute,
    Engine\CacheAdapter,
};

include_once __DIR__ . '/../../autoload.php';
$security = new PostFilter();
$route = new Route();

if ($security->strip($_SERVER['REQUEST_METHOD']) === 'POST') {
    if (isset($_POST)) {
        $request = $security->inputPost('request');
        $fingerprint = $security->inputPost('fingerprint');
        if ($security->nothing($request)) {
            exit(json_encode(array('status' => 400, 'error' => (new ex('addon-general', 3, 'Invalid request.'))->return()), JSON_PRETTY_PRINT));
        } elseif ($security->nothing($fingerprint)) {
            exit(json_encode(array('status' => 400, 'error' => (new ex('addon-general', 3, 'Failed security checks'))->return()), JSON_PRETTY_PRINT));
        }

        $simpleRoute = new SimpleRoute();
        //All entire routes are placed here
        $allowedRoutes = [
            '/getCountryList'
        ];
        $simpleRoute->registeredRoute = $allowedRoutes;
        $request = '/' . $request;

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $simpleRoute->route('/getCountryList', function () {
            try {
                //get bank lists
                $cache = new CacheAdapter();
                $cf = $cache->getCache('countryListv3');
                if ($cf !== null || !empty($cf)) {
                    exit(json_encode(array('status' => 200, 'country' => json_decode($cf)), JSON_PRETTY_PRINT));
                }
                $bb = array();
                $countries = json_decode(file_get_contents(SYSTEM_DIR.'/files/countries/countryList.json'), true);

                foreach ($countries as $key => $value) {
                    $bb[] = array('id' => $value, 'title' => $value);
                }
                //cache request
                $cache->cache(json_encode($bb), 'countryListv3', 86400);
                exit(json_encode(array('status' => 200, 'country' => $bb), JSON_PRETTY_PRINT));
            } catch (\Throwable $e) {
                (new ex('getCountryList', 3, $e->getMessage()))->return();
                exit(json_encode(array('status' => 200, 'country' => array('English')), JSON_PRETTY_PRINT));
            }
        });

        //DO NOT TOUCH
        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $simpleRoute->dispatch($request);
        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    } else {
        exit(json_encode(array('status' => 400, 'error' => (new ex('addon-general', 3, REQUEST_ERROR))->return()), JSON_PRETTY_PRINT));
    }
} else {
    exit(json_encode(array('status' => 400, 'error' => (new ex('addon-general', 3, REQUEST_ERROR))->return()), JSON_PRETTY_PRINT));
}
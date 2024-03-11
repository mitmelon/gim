<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
use Manomite\{
    Protect\PostFilter,
    Exception\ManomiteException as ex,
};

include_once __DIR__ . '/../../../autoload.php';
$security = new PostFilter();

if ($security->strip($_SERVER['REQUEST_METHOD']) === 'POST') {
    if (isset($_POST)) {
        try {

            $code = $security->inputPost('code');
            $fingerprint = $security->inputPost('fingerprint');

            if (!empty($code) and !empty($fingerprint)) {

                //rate limiter
                $rateLimiter = new \Manomite\Engine\RateLimit;
                $status = $rateLimiter->limit();
                if ($status === true) {
                    //Ask convex if this user is known or loggedIn
                    $convex = new Manomite\Services\Convex\Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));
                    $obj = new \stdClass();
                    $obj->sessionId = $code;
                    $response = $convex->query('login:isLoggedIn', $obj);
                    if (isset($response['status']) and isset($response['value'])) {
                        if ($response['status'] === 'success' and $response['value'] === false) {
                            //Then this user is not loggedIn or not known.
                            //Redirect user to login page
                            exit(json_encode(array('status' => 200, 'url' => 'login'), JSON_PRETTY_PRINT));
                        }
                        //User session is still active
                        exit(json_encode(array('status' => 200, 'url' => 'index'), JSON_PRETTY_PRINT));
                    }
                    exit(json_encode(array('status' => 200, 'url' => 'login'), JSON_PRETTY_PRINT));
                }
            }
            exit(json_encode(array('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            new ex('tokenHandler', 5, $e->getMessage());
            exit(json_encode(array('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
        }
    } else {
        exit(json_encode(array('status' => 400, 'error' => (new ex('tokenHandler', 3, LANG->get('POST_ERROR')))->return()), JSON_PRETTY_PRINT));
    }
} else {
    exit(json_encode(array('status' => 400, 'error' => (new ex('tokenHandler', 3, LANG->get('REQUEST_ERROR')))->return()), JSON_PRETTY_PRINT));
}
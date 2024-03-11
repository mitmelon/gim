<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
use Slim\Csrf\Guard;
use Slim\Psr7\Factory\ResponseFactory;
use Manomite\{
    Protect\PostFilter,
    Exception\ManomiteException as ex,
    Route\Route,
    Engine\Fingerprint,
    Mouth\Auth,
    Engine\Network,
    Database\DB
};

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../../../autoload.php';
$security = new PostFilter();

if ($security->strip($_SERVER['REQUEST_METHOD']) === 'POST') {
    if (isset($_POST)) {
        try {

            $email = $security->inputPost('email');
            $password = $security->inputPost('password');
            $fingerprint = $security->inputPost('fingerprint');
            $request = $security->inputPost('request');
            $csrf_name = $security->inputPost('csrf_name');
            $csrf_value = $security->inputPost('csrf_value');

            if ($security->nothing($email)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, 'Please enter your email address'))->return()), JSON_PRETTY_PRINT));
            } elseif ($security->nothing($password)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, 'Please provide your password'))->return()), JSON_PRETTY_PRINT));
            } elseif ($security->nothing($fingerprint)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 6, LANG->get('FINGERPRINT_BLANK')))->return()), JSON_PRETTY_PRINT));
            } else {
                $module = new Auth();

                $responseFactory = new ResponseFactory(); // Note that you will need to import
                $guard = new Guard($responseFactory);

                // Validate csrf tokens
                if ($guard->validateToken($csrf_name, $csrf_value) === false) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 6, LANG->get('EXTERNAL_REJECTED')))->return()), JSON_PRETTY_PRINT));
                }

                $net = new Network();
                $db = new DB('tmp/auth');
                //Get device info
                $login_device = array_merge(array('fingerprint' => $fingerprint), (new Fingerprint())->scan());

                //Send request to login module
                $login = $module->login($email, $password, $login_device);
                if ($login['status']) {
                    if (!$security->nothing($request)) {
                        //validate request if its same domain
                        $d = $net->get_domain_from_url($request);
                        $app_domain = $net->get_domain_from_url(APP_DOMAIN);
                        if ($d === $app_domain) {
                            exit(json_encode(array('status' => 200, 'url' => str_replace('#38;#38;', '', $request)), JSON_PRETTY_PRINT));
                        } else {
                            //Block redirection attack
                            exit(json_encode(array('status' => 200, 'url' => 'index'), JSON_PRETTY_PRINT));
                        }
                    } else {
                        //Load home page
                        exit(json_encode(array('status' => 200, 'url' => 'index'), JSON_PRETTY_PRINT));
                    }
                } else {
                    if (isset($login['error'])) {
                        exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, $login['error']))->return()), JSON_PRETTY_PRINT));
                    } elseif (isset($login['locked'])) {
                        exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, LANG->get('LOCK_INFO')))->return()), JSON_PRETTY_PRINT));
                    } elseif (isset($login['review'])) {
                        exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, LANG->get('ACCOUNT_IN_REVIEW')))->return()), JSON_PRETTY_PRINT));
                    }
                    exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, LANG->get('INVALID_LOGIN')))->return()), JSON_PRETTY_PRINT));
                }
            }
        } catch (\Exception $e) {
            new ex('loginLog', 5, $e->getMessage()); // Dont ever display this type of juice error to the public. Just log it.
            exit(json_encode(array('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
        }
    } else {
        exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, LANG->get('POST_ERROR')))->return()), JSON_PRETTY_PRINT));
    }
} else {
    exit(json_encode(array('status' => 400, 'error' => (new ex('loginLog', 3, LANG->get('REQUEST_ERROR')))->return()), JSON_PRETTY_PRINT));
}

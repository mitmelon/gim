<?php
namespace Manomite\Mouth;
use \Manomite\{
    Protect\Secret,
    Protect\PostFilter as Provider,
    Engine\DateHelper,
    Route\Route,
    Engine\Fingerprint,
    Services\Convex\Convex
};
use \HtaccessFirewall\{
    HtaccessFirewall,
    Host\IP
};
//Auth Model
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__.'/../../autoload.php';

class Auth
{
    private $sec;
    private $date;
    private $route;
    private $turnel;
    private $filter;
    
    public function __construct()
    {
        $this->sec = new Provider;
        $this->date = new DateHelper;
        $this->route = new Route;
    }

    public function loggedin()
    {
        if (isset($_SESSION[LOGIN_SESSION]) and !empty($_SESSION[LOGIN_SESSION])) {

            $session =  $this->sec->strip($_SESSION[LOGIN_SESSION]);
            $convex = new Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));
            $obj = new \stdClass();
            $obj->sessionId = $session;
            //Check if user has active session
            $response = $convex->mutation('login:isLoggedIn', $obj);
            if (isset($response['status']) and isset($response['value'])) {
                if ($response['status'] === 'success' and $response['value'] !== false) {
                    //Get user informations
                    $responseData = $convex->mutation('user:getUserData', $obj);
                    if (isset($responseData['status']) and isset($responseData['value'])) {
                        $userData = $responseData['value'];
                        return array("status" => true, "userData" => $userData, "session" => $session);
                    }
                }
            }
        } else {
            session_destroy();
            unset($_SESSION[LOGIN_SESSION]);
            unset($_SESSION['request_generator']);
            unset($_SESSION['payload_date']);
        }
    }

    public function login($email, $password, array $device)
    {
        //Include rate limit
        $rateLimiter = new \Manomite\Engine\RateLimit;
        $status = $rateLimiter->limit();
        if ($status === true) {

            $email = $this->sec->strip($email);
            $password = $this->sec->strip($password);
            $device = $this->sec->filterArray($device);

            $fingerprint = $device['fingerprint'];
            $browser = $device['browser'];
            $os = $device['os'];
            $ip = $device['ip'];
            $dev = $device['device'];

            $convex = new Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));
            $obj = new \stdClass();
            $obj->email = $email;
            $obj->password = $password;
            $obj->fingerprint = $fingerprint;
            $obj->browser = $browser;
            $obj->os = $os;
            $obj->ip = $ip;
            $obj->device = $dev;

            $response = $convex->mutation('login:login', $obj);
            if (isset($response['status']) and isset($response['value'])) {
                if ($response['status'] === 'success' and $response['value'] !== false) {
                    //Always regenerate ID for new session
                    session_regenerate_id();
                    //set the session from here for high performance load
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION[LOGIN_SESSION] = $response['value']['token'];
                    return array("status" => true, "user" => $this->sec->strip($response['value']['userData']['authToken']), "security" => false, "failed" => false);
                } else {
                    return array("status" => false, "failed" => false);
                }
            } else {
                if (intval($response['value']['userData']['status']) === 0) {
                    return array("status" => false, 'review' => true, "failed" => false);
                }
                return array("status" => false, 'locked' => true, "failed" => false);
            }
        }
        return array("status" => false, "failed" => false);
    }

    public function destroy_session()
    {
        unset($_SESSION[LOGIN_SESSION]);
        unset($_SESSION['request_generator']);
        unset($_SESSION['payload_date']);
        @session_destroy();
    }

    public function destroy_session_security($user)
    {
        (new Secret())->verify_session_setter('manomite_login_pass', $user, 300, true);
        (new Secret())->verify_session_setter('security_type', $user, 300, true);
    }

    public function register($name, $email, $country, $pass, array $identity)
    {
        //Filter inputs
        $name = $this->sec->strip($name);
        $email = $this->sec->strip($email);
        $country = $this->sec->strip($country);
        $pass = $this->sec->strip($pass);
        $device   = $this->sec->filterArray($identity);

        //Send Details to model
        $convex = new Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));
        $obj = new \stdClass();
        $obj->name = $name;
        $obj->email = $email;
        $obj->country = $country;
        $obj->fingerprint = $device['fingerprint'];
        $obj->password = $pass;
        $response = $convex->mutation('login:register', $obj);
        if (isset($response['status']) and isset($response['value'])) {
            if ($response['status'] === 'success' and $response['value'] !== false) {
                if(isset($response['value']['authToken']) and !empty($response['value']['authToken']) and isset($response['value']['code']) and !empty($response['value']['code'])){
                    return array( 'status' => true, 'key' => $response['value']['authToken'], 'code' => $response['value']['code'], 'userDoc' => $response['value']['userDocId'], 'veriDoc' => $response['value']['verifyId']);
                } else {
                    //Delete the registered data so the user can retry.
                    if(isset($response['value']['userDocId']) and !empty($response['value']['userDocId'])){
                        $obj = new \stdClass();
                        $obj->id = $response['value']['userDocId'];
                        $convex->mutation('login:deleteFromAuthUser', $obj);
                    }

                    if(isset($response['value']['verifyId']) and !empty($response['value']['verifyId'])){
                        $obj = new \stdClass();
                        $obj->id = $response['value']['verifyId'];
                        $convex->mutation('login:deleteFromVerifications', $obj);
                    }
                    
                    return array( 'status' => false, 'error' => 'Token is null. Please try again later.');
                }
            }
        }
        $error = isset($response['errorMessage']) ? $response['errorMessage'] : (isset($response['errorData']) ? $response['errorData'] : LANG->get('TECHNICAL_ERROR'));
        return array( 'status' => false, 'error' => $error);
    }

    public function firewall_block($ip){
        $firewall = new HtaccessFirewall(__DIR__.'/../../.htaccess');
        $host = IP::fromString($ip);
        $firewall->deny($host);
    }

    public function firewall_unblock($ip){
        $firewall = new HtaccessFirewall(__DIR__.'/../../.htaccess');
        $host = IP::fromString($ip);
        $firewall->undeny($host);
    }

    public function generateTmpAuth($fingerprint){
        $payload = (new Fingerprint())->codeGenerate($fingerprint, false, date('d-m-Y'));
        $code = hash('sha256', $payload);
        return $this->session_setter($fingerprint, $code, 86400);
    }

    public function session_setter($session_name, $code, $time = 3600)
    {
        if (!isset($_SESSION[$session_name])) {
            session_regenerate_id();
            $_SESSION[$session_name] = $code;
            $_SESSION[$session_name . '_time'] = time() + $time;
            return $this->sec->strip($_SESSION[$session_name]);
        }
        return $this->sec->strip($_SESSION[$session_name]);
    }

    public function verify_session_setter($code, $session_name, int $expire, $unset = false)
    {
        $current = $this->sec->strip($_SESSION[$session_name]);
        $time = $this->sec->strip($_SESSION[$session_name . '_time']);
        $code = $this->sec->strip($code);
        if (!$this->sec->nothing($code) and !$this->sec->nothing($time) and !$this->sec->nothing($current)) {
            $token_age = abs((int) $time - time());
            if ($expire >= $token_age) {
                if ($code === $current) {
                    // Validated, Done!
                    if ($unset === true) {
                        unset($_SESSION[$session_name]);
                        unset($_SESSION[$session_name . '_time']);
                        session_destroy();
                    }
                    return true;
                }
            } else {
                unset($_SESSION[$session_name]);
                unset($_SESSION[$session_name . '_time']);
                session_destroy();
                return false;
            }
        } else {
            return false;
        }
    }

    public function session_retrieve($session_name, int $expire, $unset = false)
    {
        if (isset($_SESSION[$session_name])) {
            $current = $this->sec->strip($_SESSION[$session_name]);
            $time = $this->sec->strip($_SESSION[$session_name . '_time']);
            if (!$this->sec->nothing($time) and !$this->sec->nothing($current)) {
                $token_age = abs((int) $time - time());
                if ($expire >= $token_age) {
                    // Validated, Done!
                    if ($unset === true) {
                        unset($_SESSION[$session_name]);
                        unset($_SESSION[$session_name . '_time']);
                        session_destroy();
                    }
                    return $_SESSION[$session_name];
                } else {
                    unset($_SESSION[$session_name]);
                    unset($_SESSION[$session_name . '_time']);
                    session_destroy();
                    return false;
                }
            } else {
                return false;
            }
        }
        return false;
    }

}
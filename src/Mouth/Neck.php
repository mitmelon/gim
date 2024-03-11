<?php
namespace Manomite\Mouth;
use Manomite\{
    Exception\ManomiteException as ex,
    Route\Route,
    Engine\DateHelper,
    Protect\Secret,
    Nerves\FileGrabber,
    Protect\PostFilter,
    Database\DB,
    Services\Convex\Convex,
    Engine\CacheAdapter,
};

use \Slim\Csrf\Guard;
use \Slim\Psr7\Factory\ResponseFactory;

set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../autoload.php';

class Neck
{
    private $route;
    private $filter;
    private $date;
    private $turnel;
    private $fileGrabber;
    private $convex;
    private $auth;

    public function __construct()
    {
        $this->route = new Route();
        $this->filter = new PostFilter();
        $this->date = new DateHelper();
        $this->fileGrabber = new FileGrabber;
        $this->convex = new Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));
    }

    public function csrf_inject(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $responseFactory = new ResponseFactory();
        $guard = new Guard($responseFactory);

        $csrfNameKey = $guard->getTokenNameKey();
        $csrfValueKey = $guard->getTokenValueKey();
        $keyPair = $guard->generateToken();
        return '<input type="hidden" name="' . $csrfNameKey . '" value="' . $keyPair['csrf_name'] . '">
                <input type="hidden" name="' . $csrfValueKey . '" value="' . $keyPair['csrf_value'] . '">';
    }

    public function validate_csrf($csrf_name, $csrf_value){
        $responseFactory = new ResponseFactory();
        $guard = new Guard($responseFactory);
        return $guard->validateToken($csrf_name, $csrf_value);
    }

    public function getAccountHeader()
    {
        $logo = $this->fileGrabber->getImage(SYSTEM_DIR . '/assets/logo/logo-auth.png', 'assets/logo', true, null);
        $general = array(
            'app_name' => APP_NAME,
            'logo' => $logo,
            'logo_white' => $logo,
            'favicon' => $logo,
            'provider-support' => PROVIDER_SUPPORT_URL,
            'copyrights' => COPYRIGHTS,
        );

        $auth_handler = new Auth();

        $this->auth =  $auth_handler->loggedin();
        if (isset($this->auth['status']) && $this->auth['status'] !== false) {
            //Get user image if have one;
            $imageUrl = SYSTEM_DIR.'/assets/profile.png';
            $adapter = new CacheAdapter();
            $cache = $adapter->getCache('user_image_'.$this->auth['session']);
            if($cache !== null){
                $imageUrl = $cache;
            } else {
                $obj = new \stdClass();
                $obj->sessionId = $this->auth['session'];
                $response = $this->convex->mutation('user:getUserImage', $obj);
                if (isset($response['status']) and isset($response['value'])) {
                    if ($response['status'] === 'success' and $response['value'] !== false) {
                        $imageUrl = $response['value'];
                    }
                }
            }
            $imageUrl = $this->fileGrabber->getImage(null, 'resource/profile', true, null, $imageUrl);

            $profile = $this->auth['userData'];
            if (!isset($profile['authToken'])) {
                //destroy session
                session_destroy();
                unset($_SESSION[LOGIN_SESSION]);
                unset($_SESSION['request_generator']);
                unset($_SESSION['payload_date']);
                return false;
            }
            return array_merge($general, array(
                'status' => 200,
                "data_name" => $profile['name'],
                "data_email" => $profile['email'],
                "data_image" => $imageUrl,
                "profile" => json_encode($profile),
            )
            );
        } else {
            $url = base64_encode($this->filter->getUrl());
            return array_merge($general, array(
                'status' => 400,
                'url' => $url,

            )
            );
        }
    }

    public function gate()
    {
        $getHeader = $this->getAccountHeader();
        
        if ($getHeader['status'] === 400 || $getHeader === false) {
            $code = (new Secret())->session_retrieve('login_security_check', 600);
            $db = new DB('tmp/auth');
            if ($code !== false) {
                //Get code info
                if ($db->has($code)) {
                    header('Location: security');
                }
            }
            $content = array_merge($getHeader, array(
                'title' => APP_NAME . ' | Login',
                'appName' => APP_NAME,
                'requestData' => $getHeader['url'],
                'root' => APP_DOMAIN . '/',
                'csrf_root' => $this->csrf_inject()
            )
            );
            $template = BODY_TEMPLATE_FOLDER . '/auth/login.html';
            exit($this->view($template, $content, BODY_TEMPLATE_FOLDER . '/auth/header.html', BODY_TEMPLATE_FOLDER . '/auth/footer.html'));
        }
        return $getHeader;
    }
   
    public function index()
    {
        $header = $this->gate();
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $profile = json_decode($header['profile'], true);
        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $content = array_merge(
            $header,
            array(
                'title' => $profile['name'] . ' | ' . APP_NAME,
                'root' => '',
                'app_name' => APP_NAME,
            )
        );

        $template = BODY_TEMPLATE_FOLDER . '/app/body.html';
        return $this->view($template, $content, BODY_TEMPLATE_FOLDER . '/app/header.html', BODY_TEMPLATE_FOLDER . '/app/footer.html');
    }

    public function register()
    {
        $getHeader = $this->getAccountHeader();
        
        if ($getHeader['status'] === 400 || $getHeader === false) {
            $content = array(
                'title' => APP_NAME . ' | Open Account',
                'root' => '',
                'app_name' => $getHeader['app_name'],
                'logo' => $getHeader['logo'],
                'logo-white' => $getHeader['logo_white'],
                'favicon' => $getHeader['favicon'],
                'csrf_root' => $this->csrf_inject(),
            );
            $template = BODY_TEMPLATE_FOLDER . '/auth/register.html';
            exit($this->view($template, $content, BODY_TEMPLATE_FOLDER . '/auth/header.html', BODY_TEMPLATE_FOLDER . '/auth/footer.html'));
        }
        $this->gate();
    }

    public function email_verify($value)
    {
        
        if (isset($value['id']) and !empty($value['id'])) {
            $id = $this->filter->strip($value['id']);
            try {

                //Decrypt data
                $code = (new Secret($id))->decrypt();
                $code = json_decode($code, true);

                $obj = new \stdClass();
                $obj->code = $code['code'];
                $obj->authToken = $code['key'];
                $response = $this->convex->query('login:verificationStatus', $obj);
                if (isset($response['status']) and isset($response['value'])) {
                    if ($response['status'] === 'success' and $response['value'] === true) {

                        //activate account
                        $obj = new \stdClass();
                        $obj->id = $code['userDoc'];
                        $this->convex->mutation('login:activateUserAccount', $obj);

                        //Delete verifications
                        $obj = new \stdClass();
                        $obj->id = $code['veriDoc'];
                        $this->convex->mutation('login:deleteFromVerifications', $obj);

                        //account verified - show nice message using the error function
                        return $this->error('Congratulations!, your account has been activated. You can now login to your account.', '200');
                    }
                }
            } catch (\Throwable $e) {
                new ex('Neck', 5, $e->getMessage());
                return $this->error(LANG->get('TECHNICAL_ERROR'), '500');
            }
        }
        return $this->error('Email could not be verified or already verified', '404');
    }

    public function error($message, $code = '500')
    {
        if (empty($message)) {
            $message = 'Sorry this page you are looking for does not exist. You might be looking for what does not exist in the universe. Please use the button below to find your way out.';
        }
        $header = $this->getAccountHeader();

        $content = array(
            'title' => $code . ' | ' . APP_NAME,
            'error' => $code,
            'message' => $message,
            'root' => APP_DOMAIN . '/',
            'app_name' => $header['app_name'],
            'logo' => $header['logo'],
            'favicon' => $header['favicon'],
        );
        $template = BODY_TEMPLATE_FOLDER . '/error.html';
        exit($this->single($template, $content));
    }
    
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function view($body, $content, $head, $footer)
    {
        return $this->route->display(
            $head,
            $footer,
            $body,
            $content
        );
    }

    private function single($body, $content)
    {
        return $this->route->single(
            $body,
            $content
        );
    }

    //////////////////////////////////////////////////////ADMIN ROUTE//////////////////////////////////////////////////////////////////


}
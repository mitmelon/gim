<?php
//New User Account Register Controller
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
use Slim\Csrf\Guard;
use Slim\Psr7\Factory\ResponseFactory;
use Manomite\{
    Protect\PostFilter,
    Exception\ManomiteException as ex,
    Protect\Secret,
    Route\Route,
    Engine\Fingerprint,
    Mouth\Auth,
    Engine\Email,
    Nerves\FileGrabber,
};

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../../../autoload.php';
$security = new PostFilter;

try {
    if ($security->strip($_SERVER['REQUEST_METHOD']) === 'POST') {
        if (isset($_POST)) {

            $name = $security->inputPost('name');
            $email = $security->inputPost('email');
            $pass = $security->inputPost('password');
            $fingerprint = $security->inputPost('fingerprint');
            $country = $security->inputPost('country');
            $csrf_name = $security->inputPost('csrf_name');
            $csrf_value = $security->inputPost('csrf_value');

            if ($security->nothing($name)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, 'Please provide your full name'))->return()), JSON_PRETTY_PRINT));
            } elseif (!$security->validate_name($name)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, 'This is not your full name. Please provide your full name'))->return()), JSON_PRETTY_PRINT));
            } elseif ($security->nothing($email)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, 'Please provide your email address'))->return()), JSON_PRETTY_PRINT));
            } elseif (!$security->validate_email($email)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, 'Please provide a valid email address'))->return()), JSON_PRETTY_PRINT));
            } elseif ($security->nothing($pass)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, 'Please provide your password'))->return()), JSON_PRETTY_PRINT));
            } elseif ($security->nothing($fingerprint)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 6, LANG->get('EXTERNAL_REJECTED')))->return()), JSON_PRETTY_PRINT));
            } elseif ($security->nothing($country)) {
                exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 6, 'Please select your country'))->return()), JSON_PRETTY_PRINT));
            } else {

                $responseFactory = new ResponseFactory(); // Note that you will need to import
                $guard = new Guard($responseFactory);

                // Validate csrf retrieved tokens
                if ($guard->validateToken($csrf_name, $csrf_value) === false) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 6, LANG->get('EXTERNAL_REJECTED')))->return()), JSON_PRETTY_PRINT));
                }

                $mail = MAIL_DRIVER;
                $identity = array_merge(array('fingerprint' => $fingerprint), (new Fingerprint())->scan());
                //Account info
                $module = new Auth;
                $return = $module->register($name, $email, $country, $pass, $identity);
                if ($return['status']) {

                    $code = (new Secret(json_encode($return)))->encrypt();
                    //Call email Template
                    $fileGrabber = new FileGrabber;
                    $logo = $fileGrabber->getImage(SYSTEM_DIR . '/assets/logo/logo-auth.png', 'assets/logo', true, null);
                    $subject = 'Hello ' . $name . '! Please confirm your email address.';
                    $message = (new Route)->textRender('<b>Hello {#name#}!</b>. Please use the link below to complete your email verification.<br><br>.  {#url#}.<br><br>You have just 10 minutes to do this before the link expires.', array(
                        'name' => $name,
                        'url' => APP_DOMAIN . '/email/verify/' . $code,
                    )
                    );
                    ( new Email($subject, array($email), $message, '', CONFIG->get('smtp_reply_email')) )->$mail();
                    exit(json_encode(array('status' => 200, 'message' => 'Thank you for registering with ' . APP_NAME . '. Please check your email inbox or spam inbox to complete your registrations. Please note that it might take some couple of seconds for mail to arrive. Kindly wait.'), JSON_PRETTY_PRINT));
                   
                } else {
                    exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, $return['error']))->return()), JSON_PRETTY_PRINT));
                }
            }
        } else {
            exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, LANG->get('POST_ERROR')))->return()), JSON_PRETTY_PRINT));
        }
    } else {
        exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, LANG->get('REQUEST_ERROR')))->return()), JSON_PRETTY_PRINT));
    }
} catch (\Throwable $e) {
    new ex('newUserLog', 5, $e);
    exit(json_encode(array('status' => 400, 'error' => (new ex('newUserLog', 3, LANG->get('TECHNICAL_ERROR')))->return()), JSON_PRETTY_PRINT));
}
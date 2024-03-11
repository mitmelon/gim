<?php
/**
 * Index entry [API REQUEST ONLY]
 * @url    https://eyemail.manomite.net
 * @docs   https://eyemail.manomite.net/docs
 *
 * @version    1.0.0
 * @author     Manomite Limited
 *
 * @copyright  2024 Manomite Limited
 * @license    https://github.com/mitmelon/eye-mail/blob/master/LICENSE
 */
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Manomite\Mouth\Neck;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/autoload.php';

////////////////////////////////////////////////////////////////INDEX ERROR LOGGER//////////////////////////////////////////////////////
error_reporting(E_ALL);
ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', SYSTEM_DIR . '/index_errors.log');
ini_set('log_errors_max_len', 1024);
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], (strlen('/manomite/gim')));


try {

    $app = AppFactory::create();

    $app->get('/', function (Request $request, Response $response, $args) {
        $neck = new Neck();       
        $html = $neck->index();
        $response->getBody()->write($html);
       
        return $response;
    });


   



    $app->get('/index', function (Request $request, Response $response, $args) {
        $neck = new Neck();       
        $html = $neck->index();
        $response->getBody()->write($html);
        return $response;
    });
    $app->get('/register', function (Request $request, Response $response, $args) {
        $neck = new Neck();       
        $html = $neck->register();
        $response->getBody()->write($html);
        return $response;
    });

    $app->get('/email/verify/{id}', function (Request $request, Response $response, $args) {
        $neck = new Neck();       
        $html = $neck->email_verify($args);
        $response->getBody()->write($html);
        return $response;
    });

    $app->get('/test', function (Request $request, Response $response, $args) {
        $neck = new Neck();       
        $html = $neck->test();
        $response->getBody()->write($html);
        return $response;
    });

   
  
    $app->run();
} catch (Throwable $e) {
    http_response_code(700);
    exit($e);
}
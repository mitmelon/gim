<?php
namespace Manomite\Services\Convex;
/**
 * Convex PHP SDK Implementations
 *
 * @link https://convex.dev/ 
 * @author Manomite Limited <manomitehq@gmail.com>
 * @version 1.0.0
 */
use \Curl\Curl;

class Convex {
    protected $transport;
    protected $endpoint;

    public function __construct($token, $url){
		
        $this->endpoint = strtolower("{$url}/api");
        $this->transport = new Curl();
        $this->transport->setHeader('Authorization', "Convex {$token}");
        $this->transport->setHeader('Content-Type', 'application/json');
    }

    private function post($path, array $data){
        try {
            return $this->response($this->transport->post($this->endpoint.$path, json_encode($data)));
        } catch(\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    private function get($path, array $data){
        try {
            return $this->response($this->transport->get($this->endpoint.$path, $data));
        } catch(\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function mutation(string $path, object $args, string $format = 'json' ){ 
        $response = $this->post('/mutation', [
            'path' => $path,
            'args' => $args,
            'format' => $format,
        ]);
        return $response;
    }

    public function action(string $path, object $args, string $format = 'json' ){ 
        $response = $this->post('/action', [
            'path' => $path,
            'args' => $args,
            'format' => $format,
        ]);
        return $response;
    }

    public function query(string $path, object $args, string $format = 'json' ){ 
        $response = $this->post('/query', [
            'path' => $path,
            'args' => $args,
            'format' => $format,
        ]);
        return $response;
    }

    private function error($message){
        throw new \Exception($message);
    }

    private function response($response){
        return json_decode(json_encode($response), true);
    }
}
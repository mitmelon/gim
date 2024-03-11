<?php
namespace Manomite\Mouth;
require_once __DIR__."/../../autoload.php";

class SimpleRoute
{

    private $routes = [];
    public array $registeredRoute = [];
    
    public function route($action, $callback)
    {
        if ($this->validateRoute($action)) {
            $action = trim($action, '/');
            $this->routes[$action] = $callback;
        }
    }

    public function dispatch($action)
    {
        if ($this->validateRoute($action)) {
            $action = trim($action, '/');
            $callback = $this->routes[$action];
            echo call_user_func($callback);
        }
    }

    private function validateRoute($action)
    {
        foreach ($this->registeredRoute as $r) {
            if ($r === $action) {
                return true;
            }
        }
        return false;
    }
}
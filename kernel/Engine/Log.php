<?php
namespace Manomite\Engine;

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Monolog\Handler\FirePHPHandler;
use \Monolog\Level;

require_once __DIR__."/../../autoload.php";
class Log
{
    private $file;
    private $timestamp;
    
    public function __construct(string $filename = null)
    {
        $this->file = $filename;
    }
    
    public function putLog(string $insert)
    {
        file_put_contents($this->file, date('d-m-Y-g:ia')." ".$insert.PHP_EOL, FILE_APPEND);
    }
    
    public function getLog()
    {
        $content = @file_get_contents($this->file);
        return $content;
    }

    public function showLogger($path, $name, $level, ...$message)
    {
        switch ($level) {
            case '0':
                $code = array('warning', Level::Warning);
                break;
            case '1':
                $code = array('info', Level::Info);
                break;
            case '2':
                $code = array('notice', Level::Notice);
                break;
            case '3':
                $code = array('error', Level::Error);
                break;
            case '4':
                $code = array('critical', Level::Critical);
                break;
            case '5':
                $code = array('emergency', Level::Emergency);
                break;
            case '6':
                $code = array('alert', Level::Alert);
                break;
            default:
                $code = array('debug', Level::Debug);
        }
        $log = new Logger($name);
        $firephp = new FirePHPHandler();
        $log->pushHandler(new StreamHandler($path, $code[1]));
        $log->pushHandler($firephp);
        $code = isset($code[0]) ? $code[0] : '';
        if(empty($code) || empty($message[1]) || $message[1] === NULL){
            return null;
        }
        return $log->$code($message[0], isset($message[1]) ? $message[1] : '');
    }
}
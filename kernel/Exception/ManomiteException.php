<?php
namespace Manomite\Exception;
use Manomite\Engine\{
    Fingerprint,
    Log
};

class ManomiteException
{
    /**
    * Creates a new exception.
    *
    * @param string     $save       The file to log exception errors to
    * @param int        $mode       Mode 1 - 6 (1-Debug, 2-Notice, 3-Error, 4-Critical, 5-Emergency, 6-Alert )
    * @param string     $message    Exception message
    */
    private $message;
    public function __construct($save, $mode, $message)
    {
        $device_data = (new Fingerprint)->scan();
        $log = new Log();
        $dir = SYSTEM_DIR.'/errors';
        if(!is_dir($dir)){
            mkdir($dir, 0600, true);
        }
		if($mode === 5){
            $this->reportIssue($save, $message, $device_data);
        }
        $log->showLogger($dir.'/'.$save.'.log', $save, $mode, $message, $device_data);
        //Free log space
        $this->message = $message;
    }
    //No output
    public function return()
    {
        return $this->message;
    }
    //Use catch to grab exceptions
    public function throw()
    {
        throw new \Exception($this->message);
    }
    //Output and exit
    public function exit()
    {
        exit($this->message);
    }
    //Source log
    private function reportIssue($save, $message, $device){

        $log = new Log();
        $payload = array(
            'file' => $save,
            'message' => $message,
            'device' => $device
        );
        $dir = SYSTEM_DIR.'/errors';
        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }
        $log->showLogger($dir.'/'.$save.'.log', $save, 3, $message, $payload);
    }
}
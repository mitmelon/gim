<?php
namespace Manomite\Engine\Platform;
// Load libs
require_once dirname(__FILE__).'/init.php';

class Health {

  protected $linfo;

  public function __construct($timezone = 'Africa/Lagos'){
    try {
      $this->linfo = new \Linfo;
      $this->linfo->timezone = $timezone;
      $this->linfo->scan();
    } catch (\LinfoFatalException $e) {
      throw new \Exception($e->getMessage()."\n");
    }
  }

  public function toArray(){
    return $this->linfo->getInfo();
  }

  public function toGui(){
    return $this->linfo->output();
  }
}
<?php
namespace Lukiman\Cores\Mail\Engine;

use \Lukiman\Cores\Interfaces\Mail as iMail;

abstract class Base implements iMail {
    protected array $config = [];
    //protected array $config = [];
    public function __construct(array $config) {
		if (empty($config)) $config = Loader::Config('Mail');
        $this->config = $config;
		return $this;
    }

    abstract public function simpleSend(String $to, String $from, String $subject, String $message) : bool;

    abstract public static function allowSingleton() : bool;

}
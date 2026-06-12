<?php
namespace Lukiman\Cores\Mail\Engine;

use Lukiman\Cores\Loader;
use \Lukiman\Cores\Interfaces\Mail as iMail;

abstract class Base implements iMail {
    protected array $config = [];
    //protected array $config = [];
    public function __construct(array $config) {
		if (empty($config)) $config = Loader::Config('Mail');
        $this->config = $config;
		return $this;
    }

    public function getConfig(?String $key = null, mixed $default = null) : mixed {
        if ($key === null) return $this->config;
        return $this->config[$key] ?? $default;
    }

    abstract public function simpleSend(String $to, String $from, String $subject, String $message) : bool;

    abstract public static function allowSingleton() : bool;

}

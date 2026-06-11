<?php
namespace Lukiman\Cores\Mail;

use \Lukiman\Cores\Interfaces\Mail as iMail;
use Lukiman\Cores\Loader;

class Factory {
	private static String $path = '\\Lukiman\\Cores\\Mail\\Engine\\';

	public static function instantiate(?array $config = null) : iMail {
		if (empty($config)) $config = Loader::Config('Mail');
		$class = static::$path . ucfirst(strtolower($config['engine']));
		return new $class($config);
	}

	public static function allowSingleton() : bool {
		$class = static::$path . ucfirst(strtolower($config['engine']));
		return $class::allowSingleton();
	}
}

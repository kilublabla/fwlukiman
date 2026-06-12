<?php
namespace Lukiman\Cores\Interfaces;

interface Mail {
	public function simpleSend(String $to, String $from, String $subject, String $message) : bool;

	public function getConfig(?String $key = null, mixed $default = null) : mixed;

	public static function allowSingleton() : bool;
}

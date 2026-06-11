<?php
namespace Lukiman\Cores\Interfaces;

interface Mail {
	public function simpleSend(String $to, String $from, String $subject, String $message) : bool;

	public static function allowSingleton() : bool;
}

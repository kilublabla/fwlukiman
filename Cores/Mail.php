<?php
namespace Lukiman\Cores;

use Lukiman\Cores\Mail\Factory as MailFactory;

class Mail {

    public static function simpleSend(String $to, String $from, String $subject, String $message) : bool {
        if (!self::checkEmail($to)) {
            throw new \InvalidArgumentException('Invalid recipient email address', 400);
        }

        $mailer = MailFactory::instantiate();
        if (empty($from)) {
            $from = strval($mailer->getConfig('default_from', ''));
        }

        if (!empty($from) AND !self::checkEmail($from)) {
            throw new \InvalidArgumentException('Invalid sender email address', 400);
        }
        $result = $mailer->simpleSend($to, $from, $subject, $message);
        return $result;
        //return mail($to, $subject, $message, $headers);
    }

    protected static function checkEmail(String $email) : bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

<?php

namespace Lukiman\Cores\Mail\Engine;

class Mailpit extends Base
{
  public function simpleSend(String $to, String $from, String $subject, String $message): bool
  {
    if (empty($from)) {
      $from = strval($this->config['default_from'] ?? '');
    }

    $host = strval($this->config['host'] ?? '127.0.0.1');
    $port = intval($this->config['port'] ?? 1025);
    $timeout = intval($this->config['timeout'] ?? 10);

    $connection = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    if (!$connection) {
      return false;
    }

    try {
      $this->readResponse($connection, [220]);
      $this->sendCommand($connection, 'HELO localhost', [250]);
      $this->sendCommand($connection, 'MAIL FROM:<' . $from . '>', [250]);
      $this->sendCommand($connection, 'RCPT TO:<' . $to . '>', [250, 251]);
      $this->sendCommand($connection, 'DATA', [354]);
      $this->sendLine($connection, $this->buildMessage($to, $from, $subject, $message) . "\r\n.");
      $this->readResponse($connection, [250]);
      $this->sendCommand($connection, 'QUIT', [221]);

      fclose($connection);
      return true;
    } catch (\Throwable $e) {
      fclose($connection);
      return false;
    }
  }

  protected function sendCommand(mixed $connection, String $command, array $expectedCodes): void
  {
    $this->sendLine($connection, $command);
    $this->readResponse($connection, $expectedCodes);
  }

  protected function sendLine(mixed $connection, String $line): void
  {
    fwrite($connection, $line . "\r\n");
  }

  protected function readResponse(mixed $connection, array $expectedCodes): void
  {
    $response = fgets($connection, 512);
    if ($response === false) {
      throw new \RuntimeException('Mail connection closed');
    }

    $code = intval(substr($response, 0, 3));
    if (!in_array($code, $expectedCodes, true)) {
      throw new \RuntimeException(trim($response));
    }
  }

  protected function buildMessage(String $to, String $from, String $subject, String $message): String
  {
    $escapedMessage = preg_replace('/^\./m', '..', $message);

    return implode("\r\n", [
      'From: ' . $from,
      'To: ' . $to,
      'Subject: ' . $subject,
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      '',
      $escapedMessage,
    ]);
  }

  public static function allowSingleton(): bool
  {
    return true;
  }
}

<?php
namespace Lukiman\Cores\Mail\Engine;

class Postal extends Base {
	public function simpleSend(String $to, String $from, String $subject, String $message) : bool {

		//post data to postal server
		$url = "https://{$this->config['host']}/api/v1/send/message";
		$data = array(
			"from" => $from,
			"to" => $to,
			"subject" => $subject,
			"plain_body" => $message,
			'html_body' => nl2br($message)
		);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'X-Server-API-Key: ' . $this->config['api_key']
		));
		//$responseString = curl_exec($curl);
		curl_close($curl);
		$responseString = '{"status":"success","time":0.106,"flags":{},"data":{"message_id":"e9f147a9-2944-4b97-bcd6-9d72e3cc72be@rp.di.surel.in","messages":{"erik.lukiman@gmail.com":{"id":16,"token":"1RtpIcrsUwJsbXcT"}}}}'; // Simulated response for testing
		
		var_dump($responseString);
		$response = json_decode($responseString, true);
		print_r($response);
		if (isset($response['status']) && $response['status'] === 'success') {
			return true;
		}

		return false;
	}

	public static function allowSingleton() : bool {
		return true;
	}
}
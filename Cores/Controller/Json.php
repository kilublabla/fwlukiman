<?php
namespace Lukiman\Cores\Controller;
use \Lukiman\Cores\Controller;
use \Lukiman\Cores\Request;

class Json extends Controller {
	protected $_error = 0;
	protected int $_errorCode = 0;
	protected $_errorMessage = '';

	public function execute($action = 'Index', ?array $params = null, ?Request $request = null) : mixed {
		try {
			parent::execute($action, $params, $request);
		} catch (\Exception $e) {
			$this->setError(true)->setErrorCode((int) $e->getCode())->setErrorMessage($e->getMessage());
			//return '';//$e->getMessage();
		}

		$this->addHeaders(array(
			// 'Access-Control-Allow-Origin' 		=> (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') ),
			'Access-Control-Allow-Credentials'	=> 'true',
			'Content-type'						=> 'application/json',
		));

		$result = $this->getResult();
		if (!empty($result) AND !is_array($result)) $result = array('result' => $result);
		if (empty($result)) $result = [];
		if ( $this->_error OR (is_array($result) AND !isset($result['status'])) ) $result['status'] = array(
			'error'		=> $this->_error,
			'errorCode'	=> $this->_errorCode,
			'message'	=> $this->_errorMessage,
		);

		if (!empty($result)) {
			$caller = $this->request->getGetVars('callback');
			if (!empty($caller)) {
				if (empty($caller) OR ($caller == '?')) $caller = 'FrameworkCallback';
				// if (!headers_sent()) header('Content-type: text/javascript');
				return $caller . '(' . json_encode($result) . ');';
			} else return json_encode($result);
		}
		return json_encode('');
	}

	protected function setError(mixed $data) : self {
		$this->_error = $data;
		return $this;
	}

	protected function setErrorCode(int $data) : self {
		$this->_errorCode = $data;
		return $this;
	}

	protected function setErrorMessage(array|String $data) : self {
		$this->_errorMessage = $data;
		return $this;
	}

	protected function getError() : array {
		return array(
			'error'		=> $this->_error,
			'errorCode'	=> $this->_errorCode,
			'message'	=> $this->_errorMessage,
		);
	}

}

<?php

namespace Lukiman\Cores;

use Psr\Http\Message\UriInterface;

class Request {
    protected $params;
    protected $action;
    protected $body;
    /**
    * @var \Psr\Http\Message\ServerRequestInterface
    * */
	protected $request;
	protected $post;
	protected $get;
	protected $files;
	protected $headers;

    public function __construct (?\Psr\Http\Message\ServerRequestInterface $request = null) {

		if (is_null($request)) {
			$psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
			$creator = new \Nyholm\Psr7Server\ServerRequestCreator(
				$psr17Factory, // ServerRequestFactory
				$psr17Factory, // UriFactory
				$psr17Factory, // UploadedFileFactory
				$psr17Factory  // StreamFactory
			);

			$this->request = $creator->fromGlobals();
			// $this->post = $this->request->getParsedBody();
			// $this->get = $this->request->getQueryParams();
			// $this->files = $this->request->getUploadedFiles();
		} else {
			$this->request = $request;
		}
			$this->post = $this->request->getParsedBody();
			$this->get = $this->request->getQueryParams();
			$this->files = $this->request->getUploadedFiles();
			$this->body = $this->request->getBody()->getContents();
			if (empty($this->body) AND !empty($this->post)) $this->body = key($this->post);
    }

	public function getRequest() : mixed {
		return $this->request;
	}

	public function getHeaders(String $key = '') : mixed {
		if (empty($key)) return $this->request->getHeaders();
		else return $this->request->getHeader($key);
	}

	public function getCookies() : mixed {
		return $this->request->getCookieParams();
	}

	public function getSimpleCookies() : mixed {
		$cookies = $this->request->getCookieParams();
        $ret = [];
        foreach($cookies as $k => $v) $ret[] = $k . '=' . $v;
        return $ret;
	}

	public function getCookiesAsString() {
		$cookies = $this->request->getCookieParams();
        $ret = '';
        foreach($cookies as $k => $v) $ret .= $k . '=' . $v . '; ';
        if (!empty($ret)) $ret = substr($ret, 0, -2);
        return $ret;
	}

    /**
     * Function for get data POST
     * @param type $key
     * @return type
     */
    public function getPostVars(String $key = '') : mixed {
        if(!empty($key)) {
            if(isset($this->post[$key])) {
                return ($this->post[$key]);
            } else {
                return '';
            }
        } else {
            return $this->post;
        }
    }

    /**
     * Function for get data GET
     * @param type $key
     * @return type
     */
    public function getGetVars(String $key = '') : mixed {
        if(!empty($key)) {
            if(isset($this->get[$key])) {
                return ($this->get[$key]);
            } else {
                return '';
            }
        } else {
            return $this->get;
        }
    }

    /**
     * Function for get data FILES
     * @param type $key
     * @return type
     */
    public function getFilesVars(String $key = '') : mixed {
        if(!empty($key)) {
            if(isset($this->files[$key])) {
                return ($this->files[$key]);
            } else {
                return '';
            }
        } else {
            return $this->files;
        }
    }

    /**
     * Get data param from URL
     * @return string/array
     */
    public function getParams(String $key = '') : String|array {
        if($key === '') {
            return $this->params;
        } else {
            if(isset($this->params[$key])) {
                return $this->params[$key];
            } else {
                return '';
            }
        }
    }

    /**
     * Get action from URL
     * @return string
     */
    public function getAction() : String {
        return $this->action;
    }

    /**
     * Get request uri from URL
     * 
     * @return \Psr\Http\Message\UriInterface|string|null
     */
    public function getUri() : UriInterface|String|null {
        return $this->request?->getUri();
    }

    /**
     * Get request method
     * @return string
     */
    public function getMethod() : String {
        return $this->request->getMethod();
    }

    /**
     * Get data request body
     * @return type
     */
    public function getBody() : mixed {
		return $this->body;
    }

    /**
     * Get framework route
     * @return string
     */
    /*public function route() {
        $path_info      = $this->uri();
        $action         = strtolower(str_replace('do_', '', $this->action()));

        $param_path     = 0;
        $data_path      = explode('/', $path_info);
        $path           = array();

        foreach ($data_path as $key => $val) {
            if(strtolower($val) == strtolower($action)) break;
            $param_path     ++;
        }

        for($i = 0; $i < $param_path; $i ++) {
            $path[]     = $data_path[$i];
        }
        $route          = implode('/', $path);

        return $route;
    }
    */

    /**
     * Function for get data query string (raw GET)
     * @param type none
     * @return raw GET
     */
    public function getQueryString() : mixed {
        return $this->getUri()->getQuery();
    }

    public function addQueryArray(array $query) : self {
        $this->get = array_merge($this->get, $query);
        return $this;
    }

    public function addQueryArrayRecursive(array $query) : self {
        $this->get = array_merge_recursive($this->get, $query);
        return $this;
    }

    public function addQueryString(String $key, String $value) : self {
        $this->get[$key] = $value;
        return $this; 
    }

    public function setGetVars(array $get) : self {
        $this->get = $get;
        return $this;
    }

}

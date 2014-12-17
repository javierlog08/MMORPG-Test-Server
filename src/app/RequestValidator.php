<?php

namespace app;
use Yii;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class RequestValidator implements MessageComponentInterface
{

	protected $clients;

	public $enableCsrfValidation = true;

	public $csrfParam = '_csrf';

	public $csrfCookie = ['httpOnly' => true];

	public $enableCsrfCookie = true;

	public $enableCookieValidation = true;

	public $cookieValidationKey;

	public $request;

	private $_cookies;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	public function onOpen(ConnectionInterface $conn)
	{
		$this->request = $conn->WebSocket->request;
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		$this->request = $from->WebSocket->request;
	}

	public function onClose(ConnectionInterface $conn)
	{

	}

	public function onError(ConnectionInterface $conn, \Exception $e)
	{

	}

	protected function loadCsrfToken($request)
	{
		if ($this->enableCsrfCookie) {
			return $this->getCookies($request)->getValue($this->csrfParam);
		} else {
			return Yii::$app->getSession()->get($this->csrfParam);
		}
	}

	public function getCookies($request)
	{
		if ($this->_cookies === null) {
			$this->_cookies = new CookieCollection($this->loadCookies($request), [
				'readOnly' => true,
			]);
		}
		return $this->_cookies;
	}

	protected function loadCookies($request)
	{
		$cookies = [];
		if ($this->enableCookieValidation) {
			if ($this->cookieValidationKey == '') {
				throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
			}
			foreach ($request->getCookies() as $name => $value) {
				if (is_string($value) && ($value = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey)) !== false) {
					$cookies[$name] = new Cookie([
						'name' => $name,
						'value' => @unserialize($value),
						'expire'=> null
					]);
				}
			}
		} else {
			foreach ($request->getCookies() as $name => $value) {
				$cookies[$name] = new Cookie([
					'name' => $name,
					'value' => $value,
					'expire'=> null
				]);
			}
		}
		return $cookies;
	}


	public function validateCsrfToken($conn)
	{
		$request = $conn->WebSocket->request;

		$method = $request->getMethod();
		// only validate CSRF token on non-"safe" methods http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.1.1
		if (!$this->enableCsrfValidation || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
			return true;
		}
		$trueToken = $this->loadCsrfToken(request);
		return $this->validateCsrfTokenInternal($request->getParams()->get($this->csrfParam), $trueToken)
		|| $this->validateCsrfTokenInternal($this->getCsrfTokenFromHeader(), $trueToken);
	}
}
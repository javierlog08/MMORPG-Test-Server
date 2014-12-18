<?php

namespace app;

use Yii;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use yii\helpers\StringHelper;
use yii\web\CookieCollection;
use yii\base\InvalidConfigException;
use yii\web\Cookie;

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

	const CSRF_HEADER = 'X-CSRF-Token';

	const CSRF_MASK_LENGTH = 8;

	private $_csrfToken;


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

	/**
	 * Returns the token used to perform CSRF validation.
	 *
	 * This token is a masked version of [[rawCsrfToken]] to prevent [BREACH attacks](http://breachattack.com/).
	 * This token may be passed along via a hidden field of an HTML form or an HTTP header value
	 * to support CSRF validation.
	 * @param boolean $regenerate whether to regenerate CSRF token. When this parameter is true, each time
	 * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
	 * @return string the token used to perform CSRF validation.
	 */
	public function getCsrfToken($regenerate = false,$conn)
	{
		$request = $conn->WebSocket->request;

		if ($this->_csrfToken === null || $regenerate) {
			if ($regenerate || ($token = $this->loadCsrfToken($request)) === null) {
				$token = $this->generateCsrfToken();
			}
			// the mask doesn't need to be very random
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.';
			$mask = substr(str_shuffle(str_repeat($chars, 5)), 0, self::CSRF_MASK_LENGTH);
			// The + sign may be decoded as blank space later, which will fail the validation
			$this->_csrfToken = str_replace('+', '.', base64_encode($mask . $this->xorTokens($token, $mask)));
		}
		return $this->_csrfToken;
	}

	protected function generateCsrfToken()
	{
		$token = Yii::$app->getSecurity()->generateRandomString();
		if ($this->enableCsrfCookie) {
			$config = $this->csrfCookie;
			$config['name'] = $this->csrfParam;
			$config['value'] = $token;
			Yii::$app->getResponse()->getCookies()->add(new Cookie($config));
		} else {
			Yii::$app->getSession()->set($this->csrfParam, $token);
		}
		return $token;
	}

	private function xorTokens($token1, $token2)
	{
		$n1 = StringHelper::byteLength($token1);
		$n2 = StringHelper::byteLength($token2);
		if ($n1 > $n2) {
			$token2 = str_pad($token2, $n1, $token2);
		} elseif ($n1 < $n2) {
			$token1 = str_pad($token1, $n2, $n1 === 0 ? ' ' : $token1);
		}
		return $token1 ^ $token2;
	}

	private function validateCsrfTokenInternal($token, $trueToken)
	{
		$token = base64_decode(str_replace('.', '+', $token));
		$n = StringHelper::byteLength($token);
		if ($n <= self::CSRF_MASK_LENGTH) {
			return false;
		}
		$mask = StringHelper::byteSubstr($token, 0, self::CSRF_MASK_LENGTH);
		$token = StringHelper::byteSubstr($token, self::CSRF_MASK_LENGTH, $n - self::CSRF_MASK_LENGTH);
		$token = $this->xorTokens($mask, $token);
		return $token === $trueToken;
	}
}
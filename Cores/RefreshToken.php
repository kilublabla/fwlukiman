<?php
namespace Lukiman\Cores;

use \Lukiman\Cores\Request;
use \Lukiman\Cores\Session;
use \Lukiman\Cores\Cache;
use \Lukiman\Cores\Data\Authentication as AuthData;

/**
 * Handles refresh token creation, validation, and revocation.
 */
class RefreshToken {
	/**
	 * Creates a refresh token and stores its active state in cache.
	 *
	 * @param AuthData $cred The authenticated user credential
	 * @param string $sessionId The related active session identifier
	 * @param array|null $config Optional authentication configuration
	 * @return string The signed refresh token
	 */
	public static function create(AuthData $cred, String $sessionId, ?array $config = null) : String {
		$config = static::getConfig($config);
		$now = time();
		$ttl = static::getTTL($config);
		$nonce = Session::generate(24);
		$header = [
			'typ'	=> 'LukimanRefreshToken',
			'alg'	=> 'HS256',
		];

		$payload = [
			'id'				=> $cred->getId(),
			'relatedSession'	=> $sessionId,
			'authProvider'		=> $cred->getAuthProvider(),
			'iat'				=> $now,
			'exp'				=> $now + $ttl,
			'nonce'				=> $nonce,
		];

		$encodedHeader = static::base64UrlEncode(json_encode($header));
		$encodedPayload = static::base64UrlEncode(json_encode($payload));
		$signature = hash_hmac(
			'sha256',
			$encodedHeader . '.' . $encodedPayload,
			static::getSecret($config),
			true
		);

		$token = $encodedHeader . '.' . $encodedPayload . '.' . static::base64UrlEncode($signature);
		static::activate($payload, $ttl);
		return $token;
	}

	/**
	 * Parses and validates a refresh token.
	 *
	 * @param string $token The refresh token string
	 * @param array|null $config Optional authentication configuration
	 * @return array|null The token payload if valid, otherwise null
	 */
	public static function parse(String $token, ?array $config = null) : ?array {
		$config = static::getConfig($config);
		$parts = explode('.', $token);
		if (count($parts) != 3) return null;

		[$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
		$expectedSignature = static::base64UrlEncode(hash_hmac(
			'sha256',
			$encodedHeader . '.' . $encodedPayload,
			static::getSecret($config),
			true
		));

		if (!hash_equals($expectedSignature, $encodedSignature)) {
			return null;
		}

		$payload = json_decode(static::base64UrlDecode($encodedPayload), true);
		if (empty($payload) OR !is_array($payload)) {
			return null;
		}

		if (empty($payload['id']) OR empty($payload['iat']) OR empty($payload['exp']) OR empty($payload['nonce'])) {
			return null;
		}

		$now = time();
		if (($payload['iat'] > $now) OR ($payload['exp'] < $now)) {
			return null;
		}

		if (!static::isActive($payload)) {
			return null;
		}

		return $payload;
	}

	/**
	 * Gets the refresh token value from the configured request header.
	 *
	 * @param Request $request The current request object
	 * @param array|null $config Optional authentication configuration
	 * @return string The refresh token from request header
	 */
	public static function getFromRequest(Request $request, ?array $config = null) : String {
		$headerName = static::getHeader($config);
		$refreshToken = $request->getRequest()->getHeader($headerName);
		if (is_array($refreshToken)) $refreshToken = $refreshToken[0] ?? '';
		return trim((string) $refreshToken);
	}

	/**
	 * Revokes a refresh token using its parsed payload.
	 *
	 * @param array $payload The parsed refresh token payload
	 * @return void
	 */
	public static function revokeByPayload(array $payload) : void {
		if (empty($payload['nonce'])) return;
		$cache = Cache::getInstance();
		$cache->delete(static::getCacheKey($payload));
		if (!empty($payload['relatedSession'])) {
			$cache->delete(static::getSessionCacheKey($payload['relatedSession']));
		}
	}

	/**
	 * Revokes the active refresh token associated with a session id.
	 *
	 * @param string|null $sessionId The related session identifier
	 * @return void
	 */
	public static function revokeBySessionId(?String $sessionId) : void {
		if (empty($sessionId)) return;
		$cache = Cache::getInstance();
		$key = static::getSessionCacheKey($sessionId);
		$nonce = $cache->get($key);
		if (!empty($nonce)) {
			$cache->delete('refresh-token:' . $nonce);
		}
		$cache->delete($key);
	}

	/**
	 * Stores the refresh token nonce in cache as an active token marker.
	 *
	 * @param array $payload The parsed refresh token payload
	 * @param int $ttl Token time to live in seconds
	 * @return void
	 */
	protected static function activate(array $payload, int $ttl) : void {
		if (empty($payload['nonce']) OR empty($payload['relatedSession'])) return;
		$cache = Cache::getInstance();
		$cache->set(static::getCacheKey($payload), 1, max(1, $ttl));
		$cache->set(static::getSessionCacheKey($payload['relatedSession']), $payload['nonce'], max(1, $ttl));
	}

	/**
	 * Checks whether the refresh token nonce is still active in cache.
	 *
	 * @param array $payload The parsed refresh token payload
	 * @return bool True if the token is still active
	 */
	protected static function isActive(array $payload) : bool {
		if (empty($payload['nonce'])) return false;
		$cache = Cache::getInstance();
		return !empty($cache->get(static::getCacheKey($payload)));
	}

	/**
	 * Generates a cache key for storing refresh token activation data.
	 *
	 * @param array $payload The parsed refresh token payload
	 * @return string The formatted cache key for the refresh token
	 */
	protected static function getCacheKey(array $payload) : String {
		return 'refresh-token:' . $payload['nonce'];
	}

	/**
	 * Generates a cache key for storing refresh token session data.
	 *
	 * @param string $sessionId The unique identifier of the session
	 * @return string The formatted cache key for the refresh token session
	 */
	protected static function getSessionCacheKey(String $sessionId) : String {
		return 'refresh-token-session:' . $sessionId;
	}

	/**
	 * Gets the authentication configuration for refresh token processing.
	 *
	 * @param array|null $config Optional authentication configuration
	 * @return array The resolved authentication configuration
	 */
	protected static function getConfig(?array $config = null) : array {
		if (!empty($config)) return $config;
		return Loader::Config('Authentication');
	}

	/**
	 * Gets the refresh token lifetime from configuration.
	 *
	 * @param array|null $config Optional authentication configuration
	 * @return int The refresh token TTL in seconds
	 */
	protected static function getTTL(?array $config = null) : int {
		$config = static::getConfig($config);
		if (!empty($config['refreshTokenTTL'])) return intval($config['refreshTokenTTL']);
		return 2592000;
	}

	/**
	 * Gets the request header name used to send the refresh token.
	 *
	 * @param array|null $config Optional authentication configuration
	 * @return string The configured refresh token header name
	 */
	protected static function getHeader(?array $config = null) : String {
		$config = static::getConfig($config);
		if (!empty($config['refreshTokenHeader'])) return $config['refreshTokenHeader'];
		return 'Refresh-Token';
	}

	/**
	 * Gets the secret key used to sign refresh tokens.
	 *
	 * @param array|null $config Optional authentication configuration
	 * @return string The refresh token signing secret
	 */
	protected static function getSecret(?array $config = null) : String {
		$config = static::getConfig($config);
		if (!empty($config['refreshTokenSecret'])) return $config['refreshTokenSecret'];

		$rootPath = defined('LUKIMAN_ROOT_PATH') ? LUKIMAN_ROOT_PATH : __DIR__;
		$namespace = defined('LUKIMAN_NAMESPACE_PREFIX') ? LUKIMAN_NAMESPACE_PREFIX : __NAMESPACE__;
		return hash('sha256', $namespace . '|' . $rootPath . '|refresh-token');
	}

	/**
	 * Encodes a string using URL-safe base64 format.
	 *
	 * @param string $value The raw string value
	 * @return string The base64 URL-safe encoded string
	 */
	protected static function base64UrlEncode(String $value) : String {
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	/**
	 * Decodes a URL-safe base64 encoded string.
	 *
	 * @param string $value The base64 URL-safe encoded string
	 * @return string The decoded raw string
	 */
	protected static function base64UrlDecode(String $value) : String {
		$remainder = strlen($value) % 4;
		if ($remainder > 0) {
			$value .= str_repeat('=', 4 - $remainder);
		}
		return base64_decode(strtr($value, '-_', '+/'));
	}
}

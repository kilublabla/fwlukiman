<?php
namespace Lukiman\Cores;

use \Lukiman\Cores\Request;
use \Lukiman\Cores\Authentication;
use \Lukiman\Cores\Interfaces\Authentication as IAuthentication;
use \Lukiman\Cores\Cache;
use \Lukiman\Cores\Session;
use \Lukiman\Cores\RefreshToken;
use \Lukiman\Cores\Authorization\Role;
use \Lukiman\Cores\Data\Authentication as AuthData;

/**
 * Handles application login, session, refresh token, and logout flows.
 */
class Security {
	/**
	 * Logs in using an access token and creates a new session.
	 *
	 * @param string $token The access token from provider or client
	 * @param array|null $config Optional authentication configuration
	 * @return array The login result with session data
	 */
	public static function loginWithToken(String $token, ?array $config = null) : array {
		$auth = new Authentication($config);
		$auth->authWithToken($token);
		return static::proceedAuthentication($auth, $config);
	}

	public static function login(String $token, ?array $config = null) : array {
		return static::loginWithToken($token, $config);
	}

	/**
	 * Logs in using username and password, then creates a session.
	 *
	 * @param string $username The username used for authentication
	 * @param string $password The password used for authentication
	 * @param array|null $config Optional authentication configuration
	 * @return array The login result with session data
	 */
	public static function loginWithUserPassword(String $username, String $password, ?array $config = null) : array {
		$auth = new Authentication($config);
		$auth->authWithUserPassword($username, $password);
		return static::proceedAuthentication($auth, $config);
	}

	/**
	 * Creates a new session using a valid refresh token.
	 *
	 * @param string $refreshToken The refresh token string
	 * @param array|null $config Optional authentication configuration
	 * @return array The refresh result with a new session
	 */
	public static function refreshLogin(String $refreshToken, ?array $config = null) : array {
		$payload = RefreshToken::parse($refreshToken, $config);
		if (empty($payload)) {
			return [
				'status'	=> false,
				'message'	=> "Failed",
			];
		}

		$cred = static::createCredentialFromRefreshPayload($payload);
		if (empty($cred) OR empty($cred->getId()) OR !static::isUserExistAndActive($cred->getId())) {
			return [
				'status'	=> false,
				'message'	=> "Failed",
			];
		}

		$auth = new Authentication($config);
		$auth->grantAuthentication($cred);
		if (!$auth->isAuthenticated()) {
			return [
				'status'	=> false,
				'message'	=> "Failed",
			];
		}

		RefreshToken::revokeByPayload($payload);
		static::revokeSessionById($payload['relatedSession'] ?? '');

		return static::createSessionForCredential($auth->getCredentials(), $config);
	}

	/**
	 * Gets the refresh token from the current request.
	 *
	 * @param Request $request The current request object
	 * @return string The refresh token from request header
	 */
	public static function getRefreshTokenFromRequest(Request $request) : String {
		return RefreshToken::getFromRequest($request, static::getAuthenticationConfig());
	}

	/**
	 * Converts a successful authentication into an active session.
	 *
	 * @param IAuthentication $auth The authentication instance
	 * @param array|null $config Optional authentication configuration
	 * @return array The login result with session data
	 */
	public static function proceedAuthentication(IAuthentication $auth, ?array $config = null) : array {
		$cred = $auth->getCredentials();
		if (!$auth->isAuthenticated() OR empty($cred) OR empty($cred->getId()) OR !static::isUserExistAndActive($cred->getId())) {
			return [
				'status'	=> false,
				'message'	=> "Failed",
			];
		}

		return static::createSessionForCredential($cred, $config);
	}
	
	/**
	 * Gets the current session status from request data.
	 *
	 * @param Request $request The current request object
	 * @return array The active session data or invalid session response
	 */
	public static function getStatus(Request $request) : array {
		$session = static::getSession($request);

		if (!empty($session)) {
			return ['session' => $session];
		} else {
			return ['status' => false, 'message' => "Invalid Session!"];
		}
		
	}
	
	/**
	 * Logs out the current user and revokes related session data.
	 *
	 * @param Request $request The current request object
	 * @return string The logout result
	 */
	public static function logout(Request $request) : String {
		$sessionId = static::getSessionId($request);
		$refreshToken = static::getRefreshTokenFromRequest($request);
		if (!empty($refreshToken)) {
			$payload = RefreshToken::parse($refreshToken, static::getAuthenticationConfig());
			if (!empty($payload)) {
				RefreshToken::revokeByPayload($payload);
			}
		}
		RefreshToken::revokeBySessionId($sessionId);
		static::revokeSessionById($sessionId);
		return "OK";
	}
	
	/**
	 * Extracts the session id from cookie or authentication header.
	 *
	 * @param Request $request The current request object
	 * @param string $authenticationHeader The header name used for session token
	 * @return string The resolved session id
	 */
	protected static function getSessionId(Request $request, String $authenticationHeader = 'Authentication') : String {
		$sessionId = '';
		$headers = $request->getHeaders();
		if (empty($headers['Cookie'])) {
			$headers['Cookie'] = $request->getSimpleCookies();
		}
		if (!empty($headers['Cookie'])) {
			foreach($headers['Cookie'] as $cookies) {
				$cookie = explode(";", $cookies);
				foreach($cookie as $curCookie) {
					$curCookie = trim($curCookie);
					if (substr($curCookie, 0, strlen(COOKIE_NAME) + 1) == (COOKIE_NAME . '=')) {
						$sessionId = substr($curCookie, strlen(COOKIE_NAME) + 1);
						break;
					}
				}
			}
		}
		if (empty($sessionId) AND !empty($request->getRequest()->getHeader($authenticationHeader))) {
			$sessionId = $request->getRequest()->getHeader($authenticationHeader);
			if (is_array($sessionId)) $sessionId = $sessionId[0];
			if (strtolower(substr($sessionId, 0, 6)) == 'bearer') $sessionId = substr($sessionId, 6);
			$sessionId = trim($sessionId);
		}
		return $sessionId;
	}

	/**
	 * Gets session data from cache using the request session id.
	 *
	 * @param Request $request The current request object
	 * @return array|null The cached session data if available
	 */
	public static function getSession(Request $request) : ?array {
		$sessionId = static::getSessionId($request);
		$cache = Cache::getInstance();
		$content = [];
		if (!empty($sessionId)) $content = $cache->get($sessionId);
		if (empty($content)) return null;
		return $content;
	}

	/**
	 * Checks whether the user still exists and is allowed to login.
	 *
	 * @param string $userId The authenticated user identifier
	 * @return bool True if the user is valid and active
	 */
	protected static function isUserExistAndActive(String $userId) : bool {
		return true;
	}

	/**
	 * Gets the authorization roles assigned to a user.
	 *
	 * @param string $userId The authenticated user identifier
	 * @return Role The user authorization role object
	 */
	protected static function getAuthorizations(String $userId) : Role {
		return new Role('Base');
	}

	/**
	 * Gets additional session data to be stored for a user.
	 *
	 * @param string $userId The authenticated user identifier
	 * @return array Extra session information
	 */
	protected static function getAdditionalInfos(String $userId) : array {
		return [];
	}

	/**
	 * Creates a session entry and pairs it with a refresh token.
	 *
	 * @param AuthData $cred The authenticated user credential
	 * @param array|null $config Optional authentication configuration
	 * @return array The login result with session id and refresh token
	 */
	protected static function createSessionForCredential(AuthData $cred, ?array $config = null) : array {
		$sessionId = Session::generate();
		$roles = static::getAuthorizations($cred->getId());
		$cache = Cache::getInstance();
		$entry = ['credential' => $cred, 'authorization' => $roles];

		$additionalInfos = static::getAdditionalInfos($cred->getId());
		if (!empty($additionalInfos)) $entry += $additionalInfos;

		$ttl = SESSION_LENGTH;
		if (!empty($cred->getExpired())) $ttl = max(1, ($cred->getExpired() - time()));
		$cache->set($sessionId, $entry, $ttl);

		return [
			'status'		=> true,
			'message'		=> "OK",
			'sessionId'		=> $sessionId,
			'refreshToken'	=> RefreshToken::create($cred, $sessionId, $config),
		];
	}

	/**
	 * Creates a minimal credential object from refresh token payload.
	 *
	 * @param array $payload The parsed refresh token payload
	 * @return AuthData|null The reconstructed credential object
	 */
	protected static function createCredentialFromRefreshPayload(array $payload) : ?AuthData {
		if (empty($payload['id'])) return null;

		$cred = new AuthData();
		$cred->setId($payload['id']);
		$cred->setCreated(time());
		$cred->setExpired(time() + SESSION_LENGTH);
		return $cred;
	}

	/**
	 * Gets the authentication configuration for security operations.
	 *
	 * @param array|null $config Optional authentication configuration
	 * @return array The resolved authentication configuration
	 */
	protected static function getAuthenticationConfig(?array $config = null) : array {
		if (!empty($config)) return $config;
		return Loader::Config('Authentication');
	}

	/**
	 * Deletes an active session from cache by its id.
	 *
	 * @param string|null $sessionId The session identifier to revoke
	 * @return void
	 */
	protected static function revokeSessionById(?String $sessionId) : void {
		if (empty($sessionId)) return;
		$cache = Cache::getInstance();
		$cache->delete($sessionId);
	}
}

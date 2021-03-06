<?php
/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 * @link http://www.brainedia.com
 * @link http://robertsass.me
 */

namespace rsCore;


/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 * @internal
 */
interface AuthInterface {

	static function instance();

	static function isLoggedin();
	static function getUser();

	static function login( $email, $password, $throwExceptions );
	static function logout();

}


/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 */
class Auth extends CoreClass implements AuthInterface, \rsCore\CoreFrameworkInitializable {


	const ERROR_INVALID_EMAIL = "The email address does not exist.";
	const ERROR_INVALID_PASSWORD = "The given password is invalid.";

	const SESSION_KEY_EMAIL = 'session-email';
	const SESSION_KEY_TOKEN = 'session-token';

	const POST_KEY_EMAIL = 'user-email';
	const POST_KEY_PASSWORD = 'user-password';


	private static $_Instance;
	private static $_User;


	/* CoreFrameworkInitializable methods */

	/** Wird beim Autoloading dieser Klasse aufgerufen
	 * @internal
	 */
	public static function frameworkRegistration() {
	}


	/* Static methods */

	/** Gibt die Singleton-Instanz von Auth zurück
	 * @return Auth
	 * @api
	 */
	public static function instance() {
		if( self::$_Instance === null )
			self::$_Instance = new static();
		if( isset( $_GET['logout'] ) ) {
			self::destroySession();
			Core::functions()->redirect( './' );
		}
		return self::$_Instance;
	}


	/** Prüft, ob ein Nutzer angemeldet ist
	 * @return boolean
	 * @api
	 */
	public static function isLoggedin() {
		self::instance();
		return static::getUser() != null;
	}


	/** Gibt den angemeldeten Nutzer zurück
	 * @return \Brainstage\User
	 * @api
	 */
	public static function getUser() {
		self::instance();
		return self::$_User;
	}


	/** Versucht einen Nutzer einzuloggen
	 * @return boolean
	 * @api
	 */
	public static function login( $email, $password, $throwExceptions=true ) {
		self::instance();
		$User = \Brainstage\User::getUserByEmail( $email );
		if( !$User ) {
			if( $throwExceptions )
				throw new Exception( self::ERROR_INVALID_EMAIL );
			return false;
		}
		if( !$User->verifyPassword( $password ) ) {
			if( $throwExceptions )
				throw new Exception( self::ERROR_INVALID_PASSWORD );
			return false;
		}
		else {
			self::logout();
			self::$_User = $User;
			$_SESSION[ self::SESSION_KEY_EMAIL ] = $User->email;
			$_SESSION[ self::SESSION_KEY_TOKEN ] = self::getSessionToken( $User );
			$User->lastLogin = DatabaseConnector::encodeDatetime( new \DateTime() );
			return true;
		}
	}


	/** Meldet eine Nutzersession ab
	 * @return object Selbstreferenz
	 * @api
	 */
	public static function logout() {
		self::instance();
		if( self::isLoggedin() ) {
			self::$_User = null;
		}
		session_destroy();
		session_start();
	}


	/* Private methods */

	/** Konstruktor
	 * @access private
	 * @return Auth
	 * @internal
	 */
	private function __construct() {
		self::restoreSession();
		if( isset( $_POST[ self::POST_KEY_EMAIL ] ) && isset( $_POST[ self::POST_KEY_PASSWORD ] ) )
			self::login( $_POST[ self::POST_KEY_EMAIL ], $_POST[ self::POST_KEY_PASSWORD ], false );
	}


	/** Meldet eine Nutzersession ab
	 * @return object Selbstreferenz
	 * @api
	 */
	private static function destroySession() {
		self::$_User = null;
		session_destroy();
		session_start();
	}


	/** Stellt eine Nutzersession wieder her
	 * @access private
	 * @return boolean
	 */
	private static function restoreSession() {
		if( !isset( $_SESSION[ self::SESSION_KEY_EMAIL ] ) || !isset( $_SESSION[ self::SESSION_KEY_TOKEN ] ) )
			return false;
		$email = $_SESSION[ self::SESSION_KEY_EMAIL ];
		$token = $_SESSION[ self::SESSION_KEY_TOKEN ];
		$User = \Brainstage\User::getUserByEmail( $email );
		if( !$User )
			return false;
		if( $token == self::getSessionToken( $User ) ) {
			self::$_User = $User;
			return true;
		}
		return false;
	}


	/** Bildet für die aktuelle Session einen Hash des gehashten Passworts
	 * @access private
	 * @param \Brainstage\User $User
	 * @return string
	 */
	private static function getSessionToken( \Brainstage\User $User ) {
		if( $User ) {
			return \Brainstage\User::crypt( $User->password, session_id() );
		}
		return null;
	}


}
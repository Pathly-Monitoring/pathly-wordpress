<?php
/**
 * Links to pathlyhq.com, in the language of the administrator.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds every outgoing link. Pathly speaks French, English, Spanish and German.
 */
class Pathly_Links {

	const SITE = 'https://pathlyhq.com';

	/**
	 * Language prefix of pathlyhq.com for the current user.
	 *
	 * @return string
	 */
	public static function locale() {
		$lang = strtolower( substr( (string) get_user_locale(), 0, 2 ) );
		return in_array( $lang, array( 'fr', 'en', 'es', 'de' ), true ) ? $lang : 'en';
	}

	/**
	 * Free account sign-up, tagged with the plugin as source.
	 *
	 * @return string
	 */
	public static function signup() {
		return self::SITE . '/' . self::locale() . '/login?' . http_build_query(
			array(
				'mode'       => 'signup',
				'ref'        => 'wordpress',
				'utm_source' => 'wordpress',
				'utm_medium' => 'cms_module',
			)
		);
	}

	/**
	 * Page of Pathly settings where API keys are created.
	 *
	 * @return string
	 */
	public static function api_keys() {
		return self::SITE . '/' . self::locale() . '/settings#integrations';
	}

	/**
	 * Pathly dashboard.
	 *
	 * @return string
	 */
	public static function app() {
		return self::SITE . '/' . self::locale() . '/dashboard';
	}

	/**
	 * Terms of service.
	 *
	 * @return string
	 */
	public static function terms() {
		return self::SITE . '/' . self::locale() . '/legal/cgu';
	}

	/**
	 * Privacy policy.
	 *
	 * @return string
	 */
	public static function privacy() {
		return self::SITE . '/' . self::locale() . '/legal/privacy';
	}

	/**
	 * Only keeps https links, so a value sent by the API cannot become a script link.
	 *
	 * @param mixed $url Candidate URL.
	 * @return string The URL, or an empty string.
	 */
	public static function https_or_empty( $url ) {
		return is_string( $url ) && 0 === strpos( $url, 'https://' ) ? $url : '';
	}
}

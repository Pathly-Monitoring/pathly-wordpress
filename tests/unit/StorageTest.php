<?php
/**
 * Tests of Pathly_Key_Store, Pathly_State and Pathly_Links.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class StorageTest extends TestCase {

	public function test_key_format() {
		$this->assertTrue( \Pathly_Key_Store::is_valid_format( 'sp_0123456789abcdefXYZ_-' ) );
		$this->assertFalse( \Pathly_Key_Store::is_valid_format( 'sp_short' ) );
		$this->assertFalse( \Pathly_Key_Store::is_valid_format( 'pk_0123456789abcdef' ) );
		$this->assertFalse( \Pathly_Key_Store::is_valid_format( "sp_0123456789abcdef\n" ) );
	}

	public function test_key_is_stored_encrypted_and_read_back() {
		\Pathly_Key_Store::save( 'sp_secret_0123456789abcdef' );

		$stored = $this->options[ \Pathly_Key_Store::OPTION ];
		$this->assertStringNotContainsString( 'secret', $stored );
		$this->assertSame( 'sp_secret_0123456789abcdef', \Pathly_Key_Store::get() );
		$this->assertTrue( \Pathly_Key_Store::has() );
	}

	public function test_key_is_unreadable_after_a_salt_change() {
		\Pathly_Key_Store::save( 'sp_secret_0123456789abcdef' );
		Functions\when( 'wp_salt' )->justReturn( 'another-salt' );
		$this->assertSame( '', \Pathly_Key_Store::get() );
	}

	public function test_missing_or_damaged_key() {
		$this->assertSame( '', \Pathly_Key_Store::get() );
		$this->assertFalse( \Pathly_Key_Store::has() );

		$this->options[ \Pathly_Key_Store::OPTION ] = array( 'not', 'a', 'string' );
		$this->assertSame( '', \Pathly_Key_Store::get() );

		$this->options[ \Pathly_Key_Store::OPTION ] = 'zz-not-hex';
		$this->assertSame( '', \Pathly_Key_Store::get() );

		$this->options[ \Pathly_Key_Store::OPTION ] = 'abc';
		$this->assertSame( '', \Pathly_Key_Store::get() );

		$this->options[ \Pathly_Key_Store::OPTION ] = bin2hex( 'short' );
		$this->assertSame( '', \Pathly_Key_Store::get() );
	}

	public function test_clear_and_mask() {
		$this->with_key();
		\Pathly_Key_Store::clear();
		$this->assertArrayNotHasKey( \Pathly_Key_Store::OPTION, $this->options );
		$this->assertSame( 'sp_••••••••cdef', \Pathly_Key_Store::mask( 'sp_test_0123456789abcdef' ) );
	}

	public function test_state() {
		$this->assertSame( array(), \Pathly_State::all() );
		$this->assertSame( 'x', \Pathly_State::get( 'ping_id', 'x' ) );

		\Pathly_State::update(
			array(
				'ping_id' => 'p1',
				'flow_id' => 'f1',
			)
		);
		\Pathly_State::update( array( 'flow_id' => null ) );
		$this->assertSame( array( 'ping_id' => 'p1' ), \Pathly_State::all() );
		$this->assertSame( 'p1', \Pathly_State::get( 'ping_id' ) );

		$this->options[ \Pathly_State::OPTION ] = 'broken';
		$this->assertSame( array(), \Pathly_State::all() );

		\Pathly_State::clear();
		$this->assertArrayNotHasKey( \Pathly_State::OPTION, $this->options );
	}

	public function test_links_follow_the_admin_language() {
		$this->assertSame( 'fr', \Pathly_Links::locale() );
		$this->assertSame( 'https://pathlyhq.com/fr/login?mode=signup&ref=wordpress&utm_source=wordpress&utm_medium=cms_module', \Pathly_Links::signup() );
		$this->assertSame( 'https://pathlyhq.com/fr/settings#integrations', \Pathly_Links::api_keys() );
		$this->assertSame( 'https://pathlyhq.com/fr/dashboard', \Pathly_Links::app() );
		$this->assertSame( 'https://pathlyhq.com/fr/legal/cgu', \Pathly_Links::terms() );
		$this->assertSame( 'https://pathlyhq.com/fr/legal/privacy', \Pathly_Links::privacy() );

		Functions\when( 'get_user_locale' )->justReturn( 'it_IT' );
		$this->assertSame( 'en', \Pathly_Links::locale() );
		Functions\when( 'get_user_locale' )->justReturn( 'de_DE_formal' );
		$this->assertSame( 'de', \Pathly_Links::locale() );
	}

	public function test_https_or_empty() {
		$this->assertSame( 'https://a.example/x', \Pathly_Links::https_or_empty( 'https://a.example/x' ) );
		$this->assertSame( '', \Pathly_Links::https_or_empty( 'javascript:alert(1)' ) );
		$this->assertSame( '', \Pathly_Links::https_or_empty( 'http://a.example' ) );
		$this->assertSame( '', \Pathly_Links::https_or_empty( null ) );
	}
}

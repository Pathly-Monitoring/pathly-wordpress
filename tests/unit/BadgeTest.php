<?php
/**
 * Tests of Pathly_Badge: off by default, shortcode and block.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class BadgeTest extends TestCase {

	public function test_register_adds_shortcode_and_block() {
		Functions\expect( 'add_shortcode' )->once()->with( 'pathly_badge', array( 'Pathly_Badge', 'render' ) );
		Functions\expect( 'register_block_type' )->once()->with( PATHLY_DIR . '/blocks/badge', array( 'render_callback' => array( 'Pathly_Badge', 'render' ) ) );
		\Pathly_Badge::register();
		$this->addToAssertionCount( 1 );
	}

	public function test_off_by_default() {
		$this->assertSame( '', \Pathly_Badge::render() );
		\Pathly_State::update(
			array(
				'badge_url'  => 'https://pathlyhq.com/badge/x.svg',
				'status_url' => 'https://pathlyhq.com/status/x',
			)
		);
		$this->assertSame( '', \Pathly_Badge::render() );
	}

	public function test_on_without_urls_prints_nothing() {
		\Pathly_State::update(
			array(
				'badge_enabled' => true,
				'badge_url'     => 'https://pathlyhq.com/badge/x.svg',
			)
		);
		$this->assertSame( '', \Pathly_Badge::render() );
	}

	public function test_on() {
		\Pathly_State::update(
			array(
				'badge_enabled' => true,
				'badge_url'     => 'https://pathlyhq.com/badge/x.svg',
				'status_url'    => 'https://pathlyhq.com/status/x',
			)
		);
		$html = \Pathly_Badge::render();
		$this->assertStringContainsString( 'href="https://pathlyhq.com/status/x"', $html );
		$this->assertStringContainsString( 'src="https://pathlyhq.com/badge/x.svg"', $html );
	}

	public function test_enable_creates_and_publishes_the_status_page() {
		$api = $this->api();
		$api->shouldReceive( 'create_status_page' )->once()->with( array( 'public' => true ) )->andReturn(
			array(
				'id'       => 's1',
				'url'      => 'https://pathlyhq.com/status/s1',
				'badgeUrl' => 'https://pathlyhq.com/badge/s1.svg',
			)
		);

		\Pathly_Badge::enable( $api );
		$this->assertTrue( \Pathly_State::get( 'badge_enabled' ) );
		$this->assertSame( 's1', \Pathly_State::get( 'status_page_id' ) );
		$this->assertSame( 'https://pathlyhq.com/badge/s1.svg', \Pathly_State::get( 'badge_url' ) );
	}

	public function test_enable_republishes_an_existing_page() {
		\Pathly_State::update( array( 'status_page_id' => 's1' ) );
		$api = $this->api();
		$api->shouldReceive( 'update_status_page' )->once()->with( 's1', array( 'public' => true ) )->andReturn( array() );

		\Pathly_Badge::enable( $api );
		$this->assertSame( 's1', \Pathly_State::get( 'status_page_id' ) );
		$this->assertSame( '', \Pathly_State::get( 'badge_url' ) );
	}

	public function test_disable_unpublishes() {
		\Pathly_State::update(
			array(
				'badge_enabled'  => true,
				'status_page_id' => 's1',
			)
		);
		$api = $this->api();
		$api->shouldReceive( 'update_status_page' )->once()->with( 's1', array( 'public' => false ) );

		\Pathly_Badge::disable( $api );
		$this->assertFalse( \Pathly_State::get( 'badge_enabled' ) );
	}

	public function test_disable_without_page() {
		$api = $this->api();
		$api->shouldNotReceive( 'update_status_page' );
		\Pathly_Badge::disable( $api );
		$this->assertFalse( \Pathly_State::get( 'badge_enabled' ) );
	}

	public function test_block_asset_lists_the_editor_dependencies() {
		$asset = require PATHLY_DIR . '/blocks/badge/index.asset.php';
		$this->assertContains( 'wp-server-side-render', $asset['dependencies'] );
		$block = json_decode( (string) file_get_contents( PATHLY_DIR . '/blocks/badge/block.json' ), true );
		$this->assertSame( 'pathly/badge', $block['name'] );
		$this->assertSame( $asset['version'], $block['version'] );
	}
}

<?php
/**
 * Pathly_Admin without the exit that ends a real request.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

/**
 * Counts the calls to terminate() instead of ending the test run.
 */
class TestableAdmin extends \Pathly_Admin {

	/**
	 * Number of times the request would have ended.
	 *
	 * @var int
	 */
	public $terminated = 0;

	/**
	 * Records the end of the request.
	 *
	 * @return void
	 */
	protected function terminate() {
		++$this->terminated;
	}
}

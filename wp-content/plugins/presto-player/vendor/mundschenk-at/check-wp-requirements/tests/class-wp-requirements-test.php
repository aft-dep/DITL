<?php
/**
 *  This file is part of mundschenk-at/check-wp-requirements.
 *
 *  Copyright 2017-2024 Peter Putzer.
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; either version 2
 *  of the License, or ( at your option ) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *  @package mundschenk-at/check-wp-requirements/tests
 *  @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Mundschenk\WP_Requirements\Tests;

use Mundschenk\WP_Requirements;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

use org\bovigo\vfs\vfsStream;

use Mockery as m;

/**
 * Mundschenk\WP_Requirements unit test.
 *
 * @coversDefaultClass \Mundschenk\WP_Requirements
 * @usesDefaultClass \Mundschenk\WP_Requirements
 *
 * @uses Mundschenk\WP_Requirements::__construct
 */
class WP_Requirements_Test extends TestCase {

	/**
	 * Test fixture.
	 *
	 * @var WP_Requirements&m\MockInterface
	 */
	protected $req;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @return void
	 */
	protected function set_up() { // @codingStandardsIgnoreLine

		// Set up virtual filesystem.
		vfsStream::setup(
			'root',
			null,
			[
				'vendor' => [
					'partials' => [
						'requirements-error-notice.php' => 'REQUIREMENTS_ERROR',
					],
				],
			]
		);
		set_include_path( 'vfs://root/' ); // @codingStandardsIgnoreLine

		Functions\expect( 'wp_parse_args' )->once()->andReturnUsing(
			function ( $args, $defaults ) {
				return \array_merge( $defaults, $args );
			}
		);

		$this->req = m::mock(
			WP_Requirements::class,
			[
				'Foobar',
				'plugin/plugin.php',
				'textdomain',
				[
					'php'       => '5.6.0',
					'multibyte' => true,
					'utf-8'     => true,
				],
			]
		)->shouldAllowMockingProtectedMethods()->makePartial();

		parent::set_up();
	}

	/**
	 * Test constructor.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor() {
		Functions\expect( 'wp_parse_args' )->once()->andReturnUsing(
			function ( $args, $defaults ) {
				return \array_merge( $defaults, $args );
			}
		);

		$req = m::mock( \Mundschenk\WP_Requirements::class, [ 'Foobar', 'plugin/plugin.php', 'textdomain', [ 'php' => '5.3.5' ] ] );

		$requirements = $this->getValue( $req, 'install_requirements', \Mundschenk\WP_Requirements::class );
		$this->assertArrayHasKey( 'php', $requirements );
		$this->assertArrayHasKey( 'multibyte', $requirements );
		$this->assertArrayHasKey( 'utf-8', $requirements );

		$this->assertEquals( '5.3.5', $requirements['php'] );
		$this->assertFalse( $requirements['multibyte'] );
		$this->assertFalse( $requirements['utf-8'] );
	}

	/**
	 * Test display_error_notice.
	 *
	 * @covers ::display_error_notice
	 *
	 * @return void
	 */
	public function test_display_error_notice() {
		// Mock dirname( __FILE__ ).
		$this->setValue( $this->req, 'base_dir', 'vendor', \Mundschenk\WP_Requirements::class );

		$this->expectOutputString( 'REQUIREMENTS_ERROR' );
		$this->invokeMethod( $this->req, 'display_error_notice', [ 'foo' ] );
	}

	/**
	 * Test display_error_notice.
	 *
	 * @covers ::display_error_notice
	 *
	 * @expectedExceptionMessage Too few arguments to function
	 *
	 * @return void
	 */
	public function test_display_error_notice_no_arguments() {
		$this->expectOutputString( '' );

		$this->expectException( \ArgumentCountError::class );

		$this->invokeMethod( $this->req, 'display_error_notice', [] );
	}

	/**
	 * Test display_error_notice.
	 *
	 * @covers ::display_error_notice
	 *
	 * @return void
	 */
	public function test_display_error_notice_empty_format() {
		$this->expectOutputString( '' );

		$this->invokeMethod( $this->req, 'display_error_notice', [ '' ] );
	}

	/**
	 * Test admin_notices_php_version_incompatible.
	 *
	 * @covers ::admin_notices_php_version_incompatible
	 *
	 * @return void
	 */
	public function test_admin_notices_php_version_incompatible() {
		Functions\expect( '__' )->with( m::type( 'string' ), 'textdomain' )->atLeast()->once()->andReturn( 'translated' );
		$this->req->shouldReceive( 'display_error_notice' )->once();

		$this->assertNull( $this->req->admin_notices_php_version_incompatible() ); // @phpstan-ignore method.void
	}

	/**
	 * Test admin_notices_mbstring_incompatible.
	 *
	 * @covers ::admin_notices_mbstring_incompatible
	 *
	 * @return void
	 */
	public function test_admin_notices_mbstring_incompatible() {
		Functions\expect( '__' )->with( m::type( 'string' ), 'textdomain' )->atLeast()->once()->andReturn( 'translated' );
		$this->req->shouldReceive( 'display_error_notice' )->once();

		$this->assertNull( $this->req->admin_notices_mbstring_incompatible() ); // @phpstan-ignore method.void
	}

	/**
	 * Test admin_notices_charset_incompatible.
	 *
	 * @covers ::admin_notices_charset_incompatible
	 *
	 * @return void
	 */
	public function test_admin_notices_charset_incompatible() {
		Functions\expect( '__' )->with( m::type( 'string' ), 'textdomain' )->atLeast()->once()->andReturn( 'translated' );
		Functions\expect( 'get_bloginfo' )->with( 'charset' )->once()->andReturn( '8859-1' );
		$this->req->shouldReceive( 'display_error_notice' )->once();

		$this->assertNull( $this->req->admin_notices_charset_incompatible() ); // @phpstan-ignore method.void
	}

	/**
	 * Test check_php_support.
	 *
	 * @covers ::check_php_support
	 *
	 * @return void
	 */
	public function test_check_php_support() {
		// Fake PHP version check.
		$this->setValue( $this->req, 'install_requirements', [ 'php' => '999.0.0' ], \Mundschenk\WP_Requirements::class );
		$this->assertFalse( $this->invokeMethod( $this->req, 'check_php_support' ) );

		$this->setValue( $this->req, 'install_requirements', [ 'php' => PHP_VERSION ], \Mundschenk\WP_Requirements::class );
		$this->assertTrue( $this->invokeMethod( $this->req, 'check_php_support' ) );
	}

	/**
	 * Provides data for testing check_utf8_support.
	 *
	 * @return array<array{0:string,1:bool}>
	 */
	public function provide_check_utf8_support_data() {
		return [
			[ 'utf-8', true ],
			[ 'UTF-8', true ],
			[ '8859-1', false ],
			[ 'foobar', false ],
		];
	}

	/**
	 * Test check_utf8_support.
	 *
	 * @covers ::check_utf8_support
	 *
	 * @dataProvider provide_check_utf8_support_data
	 *
	 * @param string $charset  The blog charset.
	 * @param bool   $expected The expected result.
	 *
	 * @return void
	 */
	public function test_check_utf8_support( $charset, $expected ) {
		Functions\expect( 'get_bloginfo' )->with( 'charset' )->once()->andReturn( $charset );

		$this->assertSame( $expected, $this->invokeMethod( $this->req, 'check_utf8_support' ) );
	}

	/**
	 * Test check_utf8_support.
	 *
	 * @covers ::check_multibyte_support
	 *
	 * @return void
	 */
	public function test_check_multibyte_support() {
		// This will be true because mbstring is a requirement for running the test suite.
		$this->assertTrue( $this->invokeMethod( $this->req, 'check_multibyte_support' ) );
	}

	/**
	 * Provides data for testing check.
	 *
	 * @return array<int, bool[]>
	 */
	public function provide_check_data() {
		return [
			[ true, true, true, true, true ],
			[ false, false, false, true, false ],
			[ true, false, false, true, false ],
			[ false, true, false, true, false ],
			[ false, false, true, true, false ],
			[ true, true, true, false, true ],
			[ false, false, false, false, false ],
			[ true, false, false, false, false ],
			[ false, true, false, false, false ],
			[ false, false, true, false, false ],
			[ true, true, false, true, false ],
		];
	}

	/**
	 * Test check.
	 *
	 * @covers ::check
	 * @covers ::get_requirements
	 *
	 * @dataProvider provide_check_data
	 *
	 * @param  bool $php_version PHP version check flag.
	 * @param  bool $multibyte   Multibyte support check flag.
	 * @param  bool $charset     Charset check flag.
	 * @param  bool $admin       Result of is_admin().
	 * @param  bool $expected    Expected result.
	 *
	 * @return void
	 */
	public function test_check( $php_version, $multibyte, $charset, $admin, $expected ) {
		Functions\expect( 'is_admin' )->zeroOrMoreTimes()->andReturn( $admin );

		$this->req->shouldReceive( 'check_php_support' )->once()->andReturn( $php_version );
		$this->req->shouldReceive( 'check_multibyte_support' )->times( (int) $php_version )->andReturn( $multibyte );
		$this->req->shouldReceive( 'check_utf8_support' )->times( (int) ( $php_version && $multibyte ) )->andReturn( $charset );

		if ( $admin ) {
			$php_times       = $php_version ? 0 : 1;
			$multibyte_times = ! $php_version || $multibyte ? 0 : 1;
			$charset_times   = ! $php_version || ! $multibyte || $charset ? 0 : 1;

			if ( ! $expected ) {
				Functions\expect( 'load_plugin_textdomain' )->once()->with( 'textdomain' );
			}

			Actions\expectAdded( 'admin_notices' )->with( [ $this->req, 'admin_notices_php_version_incompatible' ] )->times( $php_times );
			Actions\expectAdded( 'admin_notices' )->with( [ $this->req, 'admin_notices_mbstring_incompatible' ] )->times( $multibyte_times );
			Actions\expectAdded( 'admin_notices' )->with( [ $this->req, 'admin_notices_charset_incompatible' ] )->times( $charset_times );
		}

		$this->assertSame( $expected, $this->invokeMethod( $this->req, 'check' ) );
	}

	/**
	 * Test deactivate_plugin.
	 *
	 * @covers ::deactivate_plugin
	 *
	 * @return void
	 */
	public function test_deactivate_plugin() {
		Functions\expect( 'plugin_basename' )->with( 'plugin/plugin.php' )->once()->andReturn( 'plugin' );
		Functions\expect( 'deactivate_plugins' )->with( 'plugin' )->once();

		$this->assertNull( $this->req->deactivate_plugin() ); // @phpstan-ignore method.void
	}
}

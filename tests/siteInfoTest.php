<?php

/**
 */
class WPRemoteSiteInfoTestCase extends WP_UnitTestCase {
	
	function testGetSiteURL() {
		
		$info = _wprp_get_site_info();
		$this->assertEquals( $info['site_url'], get_site_url() );
	}

	function testGetHomeURL() {
		
		$info = _wprp_get_site_info();
		$this->assertEquals( $info['home_url'], get_home_url() );
	}

	function testGetPluginVersion() {

		$this->assertEquals( _wprp_get_plugin_version(), '1.1' );
	}

	function testGetFileSystemMethod() {

		$this->assertEquals( _wprp_get_filesystem_method(), get_filesystem_method() );
	}

	function testGetSupportedFileSystemMethods() {

		$methods = _wprp_get_supported_filesystem_methods();

		$this->assertTrue( is_array( $methods ) );
	}

	function testGetWPVersion() {

		global $wp_version;

		$this->assertEquals( _wprp_get_wp_version(), $wp_version );
	}
}

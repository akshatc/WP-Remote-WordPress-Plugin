<?php

/**
 */
class WPRemotePluginsTestCase extends WP_UnitTestCase {

	function testGetPlugins() {
		
		$this->assertTrue( function_exists( 'wprp_catch_api_call' ) );
	}
}

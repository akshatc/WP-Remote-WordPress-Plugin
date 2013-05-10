<?php

class WPRP_Plugin_Upgrader_Skin extends Plugin_Installer_Skin {

	var $feedback;
	var $error;

	function error( $error ) {
		$this->error = $error;
	}

	function feedback( $feedback ) {
		$this->feedback = $feedback;
	}

	function before() { }

	function after() { }

	function header() { }

	function footer() { }
}
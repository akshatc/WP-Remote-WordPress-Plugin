<?php

class WPRP_Automatic_Upgrader_Skin extends Automatic_Upgrader_Skin {

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
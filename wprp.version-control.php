<?php

function _wprp_get_version_control_information() {

	$data = array(
		'system' => ''
	);

	if ( $git_info = _wprp_git_status_get_status() ) {
		$data['system'] = 'git';
		$data = array_merge( $data, $git_info );
	}

	return $data;
}

/**
 * Taken from the great Git Status plugin
 * https://raw.github.com/johnbillion/wp-git-status
 * 
 * @author John Blackbourn
 */
function _wprp_git_status_get_status() {

	if ( !function_exists( 'exec' ) )
		return false;

	exec( sprintf( 'cd %s/../; git status', escapeshellarg( ABSPATH ) ), $status );

	if ( empty( $status ) or ( false !== strpos( $status[0], 'fatal' ) ) )
		return false;

	$end = end( $status );
	$return = array(
		'dirty'  => true,
		'branch' => 'detached',
		'ref' => '',
	);

	if ( preg_match( '/On branch (.+)$/', $status[0], $matches ) )
		$return['branch'] = trim( $matches[1] );

	if ( empty( $end ) or ( false !== strpos( $end, 'nothing to commit' ) ) )
		$return['dirty'] = false;

	exec( sprintf( 'cd %s/../; git rev-parse HEAD', escapeshellarg( ABSPATH ) ), $rev );

	if ( $rev )
		$return['ref'] = $rev[0];

	return $return;

}
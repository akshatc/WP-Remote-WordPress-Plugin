<?php
/**
 * Convert multiple method calls to one result
 * 
 * @param array      $results     An array of one or more results.
 * @return array     $result      One final result.
 */
function wprp_get_singular_result( $results ) {

	$final_result = array( 'status' => 'success' );
	if ( count( $results ) > 1 ) {
		$all_errors = array();
		foreach( $results as $result ) {
			if ( 'error' == $result['status'] ) {
				$final_result['status'] = 'error';
				$all_errors[] = $result['error'];
			}				
		}
		if ( ! empty( $all_errors ) )
			$result['error'] = json_encode( $all_errors );
	} else if ( count( $results ) == 1 ) {
		return $results;
	}
	return $final_result;
}
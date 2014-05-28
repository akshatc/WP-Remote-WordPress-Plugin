<?php

/**
 * Return an array of content summary information
 *
 * @return array
 */
function _wprp_get_content_summary() {

	$num_posts           = wp_count_posts( 'post' );
	$num_pages           = wp_count_posts( 'page' );
	$num_categories      = count( get_categories( array( 'hide_empty' => 0 ) ) );
	$num_comments        = wp_count_comments();
	$num_themes          = count( wp_get_themes() );
	$num_plugins         = count( get_plugins() );
	$num_users           = count_users();
	$database_size       = _wprp_get_database_size();
	$wordpress_size      = _wprp_foldersize( _wprp_get_core_dir() );
	$uploads_size        = _wprp_foldersize( _wprp_get_uploads_dir() );

	$content_summary     = array(
		'post_count'          => ( ! empty( $num_posts->publish ) ) ? $num_posts->publish : 0,
		'page_count'          => ( ! empty( $num_pages->publish ) ) ? $num_pages->publish : 0,
		'category_count'      => $num_categories,
		'comment_count'       => ( ! empty( $num_comments->total_comments ) ) ? $num_comments->total_comments: 0,
		'theme_count'         => $num_themes,
		'plugin_count'        => $num_plugins,
		'user_count'          => ( ! empty( $num_users['total_users'] ) ) ? $num_users['total_users'] : 0,
		'database_size'       => _wprp_format_size( $database_size ),
		'wordpress_size'      => _wprp_format_size( $wordpress_size ),
		'uploads_size'        => _wprp_format_size( $uploads_size )
	);

	return $content_summary;
}

function _wprp_foldersize( $path ) {
	
	$total_size = 0;
	$files      = scandir( $path );

	foreach( $files as $t ) {
		if ( is_dir( rtrim( $path, '/' ) . '/' . $t ) ) {
			if ( $t<>"." && $t<>".." ) {
				$size = _wprp_foldersize( rtrim( $path, '/' ) . '/' . $t );
				$total_size += $size;
			}
		} else {
		$size        = filesize( rtrim( $path, '/' ) . '/' . $t );
		$total_size += $size;
		}
	}

	return $total_size;
}

function _wprp_get_database_size() {

	global $wpdb;

	$size = 0;
	$res  = $wpdb->get_results( 'SHOW TABLE STATUS FROM `' . DB_NAME . '`', ARRAY_A );

	foreach ( $res as $r ) {
		$size += (float) $r['Data_length'];
		$size += (float) $r['Index_length'];
	}

	return $size;
}

function _wprp_format_size( $size ) {

	$mod   = 1024;
	$units = explode( ' ','B KB MB GB TB PB' );

	for ( $i = 0; $size > $mod; $i++ ) {
		$size /= $mod;
	}

	return round( $size, 2 ) . ' ' . $units[$i];
}

function _wprp_get_core_dir() {
	return( ABSPATH );
}

function _wprp_get_uploads_dir() {

	$uploads_dir = wp_upload_dir();

	return( $uploads_dir['basedir'] );
}
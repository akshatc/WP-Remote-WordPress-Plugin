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
	$site_size           = 1;
	$database_size       = 2;
	$wordpress_size      = 3;
	$uploads_size        = 4;

	$content_summary     = array(
		'post_count'          => ( ! empty( $num_posts->publish ) ) ? $num_posts->publish : 0,
		'page_count'          => ( ! empty( $num_pages->publish ) ) ? $num_pages->publish : 0,
		'category_count'      => $num_categories,
		'comment_count'       => ( ! empty( $num_comments->total_comments ) ) ? $num_comments->total_comments: 0,
		'theme_count'         => $num_themes,
		'plugin_count'        => $num_plugins,
		'user_count'          => ( ! empty( $num_users['total_users'] ) ) ? $num_users['total_users'] : 0,
		'site_size'           => $site_size,
		'database_size'       => $database_size,
		'wordpress_size'      => $wordpress_size,
		'uploads_size'        => $uploads_size
	);

	return $content_summary;
}



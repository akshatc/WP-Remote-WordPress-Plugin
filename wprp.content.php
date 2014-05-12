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

	$content_summary     = array(
		'post_count'          => $num_posts->publish,
		'page_count'          => $num_pages->publish,
		'category_count'      => $num_categories,
		'comment_count'       => $num_comments->total_comments,
		'theme_count'         => $num_themes,
		'plugin_count'        => $num_plugins,
		'user_count'          => $num_users['total_users']
	);

	return $content_summary;
}



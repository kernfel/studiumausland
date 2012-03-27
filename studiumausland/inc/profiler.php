<?php

function fbk_profiler_log() {
	global $wpdb;
	if ( defined( 'FBK_PROFILING' ) && FBK_PROFILING && ( ! defined('DOING_CRON') || ! DOING_CRON ) ) {
		$totalTime = microtime(true) - $_SERVER['REQUEST_TIME'];
		$queryTime = $totalQueries = 0;
		foreach( $wpdb->queries as $query ) {
			$queryTime += $query[1];
			$totalQueries++;
		}
		if ( ! is_admin() )
			$type = 0;
		elseif ( defined( 'FBK_AJAX' ) )
			$type = 1;
		else
			$type = 2;
		file_put_contents( FBK_PROFILING_LOG, "\n" . date('[Y-m-d H:i.s]')
		 . "\t$type"
		 . "\t$totalQueries"
		 . "\t$queryTime"
		 . "\t$totalTime"
		 . "\t$_SERVER[REQUEST_URI]"
		 . @"\t$_SERVER[HTTP_REFERER]"
		 . @"\t$_SERVER[HTTP_USER_AGENT]", FILE_APPEND );
	}
}

add_action( 'wp_footer', 'fbk_profiler_log', 100 );
<?php
/**
 * @package Studium_Ausland
 */

if ( ! isset( $_REQUEST['action'] ) )
	die('-1');

define( 'DOING_AJAX', true );
define( 'FBK_AJAX', true );
define( 'WP_ADMIN', true );
define( 'WP_DEBUG', false );

$_can_gzip = isset($_SERVER['HTTP_ACCEPT_ENCODING']) && in_array( 'gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING']) );

if ( 'fbk_ajaxnav' == $_REQUEST['action']
 && isset($_REQUEST['obj']) && isset($_REQUEST['id']) && isset($_REQUEST['rel'])
 && empty($_REQUEST['sb'])
 && in_array( $_REQUEST['obj'], array( 'cat', 'loc', 'school' ) ) ) {
	do {
		// Request for a cacheable object and no sidebar. Let's see if it's in the cache!
		require( dirname(__FILE__) . '/inc/cache.php' );
		$fbk_cache = new FBK_Cache( true );
		$modified = array();
		if ( 'loc' == $_REQUEST['obj'] )
			$prefix = 'loc-' . $_REQUEST['rel'];
		else
			$prefix = $_REQUEST['obj'];

		if ( ! $modified[] = $fbk_cache->has( $prefix, $_REQUEST['id'] ) )
			break;

		if ( isset($_REQUEST['menu']) && ! $modified[] = $fbk_cache->has( 'menu', $_REQUEST['rel'] ) )
			break;

		sort($modified);
		if ( array_key_exists( 'HTTP_IF_MODIFIED_SINCE', $_SERVER ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) >= end($modified) ) {
			header( 'HTTP/1.1 304 Not Modified' );
			die();
		}

		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', end($modified) ) . ' GMT' );

		$response = array( 'html' => $fbk_cache->get( $prefix, $_REQUEST['id'] ) );
		if ( isset($_REQUEST['menu']) )
			$response['menu'] = $fbk_cache->get( 'menu', $_REQUEST['rel'] );
		$response = json_encode($response);
		if ( $_can_gzip ) {
			$response = gzencode($response);
			header( 'Content-Encoding: gzip' );
		}

		header( 'Content-Type: application/json' );
		header( 'Content-Length: ' . strlen($response) );
		die( $response );
	} while ( false );

	header( 'Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT' );

}

require( preg_replace( '/wp-content.*$/', 'wp-load.php', __FILE__ ) );

do_action( 'wp_ajax_nopriv_' . $_REQUEST['action'] );

die( '-1' );

?>
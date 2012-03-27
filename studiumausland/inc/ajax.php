<?php
/**
 * @package Studium_Ausland
 */

add_action( 'wp_ajax_nopriv_fbk_cform', 'fbk_ajaxcform' );
add_action( 'wp_ajax_fbk_cform', 'fbk_ajaxcform' );
function fbk_ajaxcform() {
	header( 'Content-Type: text/html', true );
	global $wp_query, $post;
	$wp_query->query( 'pagename=' . $_REQUEST['page'] );
	the_post();
	$template = get_post_meta($post->ID, '_wp_page_template', true);
	get_template_part( 'content', str_replace( '.php', '', $template ) );
	if ( FBK_PROFILING )
		fbk_profiler_log();
	die();
}

add_action( 'wp_ajax_nopriv_fbk_ajaxnav', 'fbk_ajaxnav' );
add_action( 'wp_ajax_fbk_ajaxnav', 'fbk_ajaxnav' );
function fbk_ajaxnav() {
	global $post, $wp_query, $wpdb, $fbk_query, $fbk, $_can_gzip;

	$response = array();
	$is_404 = false;
	ob_start();

	if ( empty($_REQUEST['obj']) || ! isset($_REQUEST['id']) || ! isset($_REQUEST['rel']) ) {
		$is_404 = true;
	} else switch ( $_REQUEST['obj'] ) {
	    case 'school':
		$wp_query->query( array( 'p' => $_REQUEST['id'], 'post_type' => 'school', 'post_status' => 'publish' ) );
		if ( have_posts() ) {
			$fbk_query->category = get_term( $_REQUEST['rel'], 'category' );
			the_post();
			get_template_part( 'content', 'school' );
		} else {
			$is_404 = true;
		}
		break;

	    case 'index':
		if ( get_option('show_on_front') != 'page' ) {
			$is_404 = true;
			break;
		}

	    case 'page':
		if ( empty($_REQUEST['id']) )
			$wp_query->query( array( 'page_id' => get_option( 'page_on_front' ) ) );
		else if ( $wpdb->query( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = 'page' AND post_status = 'publish'", $_REQUEST['id'] ) ) )
			$wp_query->query( array( 'pagename' => $_REQUEST['id'] ) );
		elseif ( class_exists( 'Content4Partners' ) ) {
			$c4p = new Content4Partners();
			$opts = $c4p->getOptions();
			$page = get_post( $opts['page_id'] );
			if ( 0 === strpos( $_REQUEST['id'], $page->post_name . '/' ) ) {
				preg_match( '!' . CONTENT_4_PARTNERS_PERMALINK_STRUCTURE . '!', $_REQUEST['id'], $matches );
				$_GET['acfpid'] = $matches[2];
				$wp_query->query( array( 'page_id' => $page->ID ) );
			}
		}

		if ( ! have_posts() ) {
			wp_redirect( site_url( '/' . $_REQUEST['id'] ) );
		} else {
			the_post();
			$template = get_post_meta($post->ID, '_wp_page_template', true);
			if ( ! $template || 'default' == $template )
				get_template_part( 'content', 'page' );
			else
				get_template_part( 'content', str_replace( '.php', '', $template ) );
		}
		break;

	    case 'search':
		$fbk_query = new FBK_Query( $wp_query );
		$wp_query->query( $_REQUEST['id'] . '&post_status=publish' );

		if ( ! empty( $_REQUEST['form'] ) ) {
			get_search_form();
			$response['form'] = ob_get_contents();
			ob_clean();
		}
		
		if ( ! empty( $fbk_query->search_query ) )
			$response['id'] = $fbk_query->search_query;

		get_template_part( 'content', 'search' );
		break;

	    case 'news':
		if ( ! $fbk->do_news_query( $_REQUEST['id'] ) ) {
			$is_404 = true;
			break;
		}
		get_template_part( 'content', 'news' );
		break;

	    case 'post':
		$wp_query->query( array( 'post_type' => 'post', 'p' => $_REQUEST['id'] ) );
		if ( have_posts() ) {
			the_post();
			get_template_part( 'content', 'post' );
		} else {
			$is_404 = true;
		}
		break;

	    case 'offer':
		$wp_query->query( array( 'post_type' => 'offer', 'p' => $_REQUEST['id'] ) );
		if ( have_posts() ) {
			the_post();
			get_template_part( 'content', 'offer' );
		} else {
			$is_404 = true;
		}
		break;

	    case 'cat':
	    case 'loc':
		$fbk_query = new FBK_Query( $wp_query, $_REQUEST );
		$wp_query->query( array( $_REQUEST['obj'] => $_REQUEST['id'] ) );
		if ( is_404() ) {
			$is_404 = true;
		} else {
			get_template_part( 'content', $fbk_query->detailed_taxonomy );
		}
		break;

	    default:
		$is_404 = true;
	}
	
	if ( $is_404 ) {
		ob_clean();
		get_template_part( 'content', '404' );
	}
	
	$response['html'] = ob_get_contents();
	$response['title'] = html_entity_decode( fbk_the_title(false), ENT_NOQUOTES, get_bloginfo('charset') );

	if ( ! empty( $_REQUEST['sb'] ) ) {
		if ( empty($_REQUEST['obj']) || in_array( $_REQUEST['obj'], $fbk->sb_objects[FBK_SIDEBAR_INDEX] ) )
			$fbk->set_sidebar_type( FBK_SIDEBAR_INDEX );
		else
			$fbk->set_sidebar_type( FBK_SIDEBAR_DEFAULT );
		foreach ( array('left','right') as $side ) {
			ob_clean();
			get_template_part( 'sidebar', $side );
			$response['sb'][$side] = ob_get_contents();
		}
	} elseif ( ! empty( $_REQUEST['menu'] ) ) {
		ob_clean();
		fbk_tt_the_navmenu( @$_REQUEST['rel'] );
		$response['menu'] = ob_get_contents();
	}

	ob_end_clean();

	if ( FBK_PROFILING )
		fbk_profiler_log();

	$response = json_encode( $response );
	if ( $_can_gzip ) {
		$response = gzencode( $response );
		header( 'Content-Encoding: gzip' );
	}
	header( 'Content-Type: application/json' );
	header( 'Content-Length: ' . strlen( $response ) );
	die( $response );
}

add_action( 'wp_ajax_fbk_request', 'fbk_ajax_request' );
add_action( 'wp_ajax_nopriv_fbk_request', 'fbk_ajax_request' );
function fbk_ajax_request() {
	global $fbk_quotes;
	header( 'Content-type: text/html', true );
	$user = $fbk_quotes->create_new( $_REQUEST );
	if ( 'yes' == get_option( 'fbk_quote_noauto' ) )
		die ( "<p class='ybox'>Vielen Dank für Ihr Interesse, $user->salutation $user->last_name. "
		. "Ihr Kostenvoranschlag wird so bald wie möglich bearbeitet.</p>" );
	else
		die ( "<p class='ybox'>Vielen Dank für Ihr Interesse, $user->salutation $user->last_name. "
		. "Ihr Kostenvoranschlag ist bereits unterwegs zu Ihrer Mailbox.</p>" );
}

add_action( 'wp_ajax_fbk_add_quote', 'fbk_ajax_add_quote' );
add_action( 'wp_ajax_nopriv_fbk_add_quote', 'fbk_ajax_add_quote' );
function fbk_ajax_add_quote() {
	global $fbk_quotes;
	header( 'Content-type: text/html', true );
	$user = $fbk_quotes->get_user( $_REQUEST['u'], $_REQUEST['q'], true );
	if ( $user ) {
		$quote = $fbk_quotes->add_quote( $_REQUEST, $user->user_id );
		$fbk_quotes->get_quote_html( $user, $quote, false );
		echo "<p class='ybox' id='at-quote'>Vielen Dank, $user->salutation $user->last_name. Ihre Anfrage wurde zu den bestehenden Kostenvoranschlägen hinzugefügt.</p>";
		echo "<p class='ybox' id='not-at-quote'>Vielen Dank, $user->salutation $user->last_name. Ihre Anfrage wurde zu den bestehenden Kostenvoranschlägen hinzugefügt.",
		" <a href='" . $fbk_quotes->get_quote_url( $user ) . "#quote-$quote->quote_id" . "'>Klicken Sie hier</a>, um den Kostenvoranschlag anzusehen.</p>";
		die;
	} else {
		die ( "<p class='ybox'>Ein Fehler ist aufgetreten. Bitte laden Sie die Seite neu; falls es erneut nicht klappt, melden Sie sich bitte bei uns. Vielen Dank!</p>" );
	}
}
	
add_action( 'wp_ajax_fbk_push_quote', 'fbk_ajax_push_quote' );
add_action( 'wp_ajax_nopriv_fbk_push_quote', 'fbk_ajax_push_quote' );
function fbk_ajax_push_quote() {
	global $fbk_quotes;
	header( 'Content-type: text/html', true );
	$user = $fbk_quotes->get_user( $_REQUEST['u'], $_REQUEST['q'] );
	if ( $user || $fbk_quotes->err == FBK_Quotes::U_ACCESS_KEY_EXPIRED && $user = $fbk_quotes->last_user_queried ) {
		$fbk_quotes->update_user( $user, $_REQUEST );
		$fbk_quotes->push_async( $user->user_id, $_REQUEST['quote'] );
		die ( "<p class='ybox'>Vielen Dank für Ihre Anmeldung, $user->salutation $user->last_name. Wir werden uns so bald wie möglich bei Ihnen melden!</p>" );
	} else {
		die ( "<p class='ybox'>Ein Fehler ist aufgetreten. Bitte laden Sie die Seite neu; falls es erneut nicht klappt, melden Sie sich bitte bei uns. Vielen Dank!</p>" );
	}
}
?>
<?php

if ( ! FBK_AJAX ) {
	if ( is_admin() ) {
		add_filter( 'mce_buttons', 'fbk_f_mce_buttons', 9 );
		add_filter( 'mce_buttons_2', 'fbk_f_mce_buttons_2', 9 );
		add_filter( 'tiny_mce_before_init', 'fbk_f_mce_addfeatures' );

		remove_action( 'post_updated', 'wp_check_for_changed_slugs', 12, 3 );
		add_action( 'post_updated', 'fbk_check_for_changed_slugs', 12, 3 );

		add_action( 'load-post.php', 'fbk_contextual_help' );

		add_action( 'fbk_cache_deleted', 'fbk_check_external_resync', 10, 2 );

		add_action( 'admin_head', 'fbk_admin_print_color_styles', 20 );
	}
	add_action( 'get_header', 'fbk_trim_header' );
	add_action( 'wp_title', 'fbk_title_l10n', 20, 1 );

	add_action( 'init', 'fbk_addscripts' );
	add_action( 'wp_enqueue_scripts', 'fbk_enqueue_scripts' );
	add_action( 'wp_print_styles', 'fbk_print_styles' );
	add_action( 'admin_enqueue_scripts', 'fbk_admin_enqueue_scripts' );
	add_action( 'admin_print_styles', 'fbk_admin_print_styles' );

	add_action( 'init', 'fbk_schedule_newsletter_cron' );
	add_filter( 'template_include', 'fbk_check_optout' );

	if ( defined('DOING_CRON') && DOING_CRON ) {
		add_action( 'fbk_newsletter_cron', 'fbk_send_newsletter' );
		add_action( 'fbk_check_fb_info', 'fbk_check_fb_info' );
		add_action( 'fbk_check_new_quotes', 'fbk_check_new_quotes' );
	}
}

/**
 * TinyMCE customization
 */
function fbk_f_mce_addfeatures( $options ) {
	$options['theme_advanced_styles'] = 'Box - Rahmen gepunktet=block,Inline (autop-Schutz)=noautop';
	$options['theme_advanced_blockformats'] = 'p,blockquote,h2,h3,h4';
	// This serves to force cleanup after inserting templates - there's no better hook, unfortunately.
	$options['init_instance_callback'] = 'function(ed){ed.onChange.add(function(ed){
		try{
			ed.execCommand("mceCleanup",false);
		} catch(e) {}
	});}';
	$options['setup'] = 'function(ed){ed.addButton("link_shortcode",'
	. '{title:"Interner Link",image:"' . get_stylesheet_directory_uri() . '/img/favicon.ico",'
	. 'onclick:function(){tb_show("Objekt wählen","media-upload.php?type=link_shortcode&amp;TB_iframe=true&amp;tab=type");}});}';
	return $options;
}
function fbk_f_mce_buttons( $in ) {
	return array( 'bold', 'italic', 'underline', '|', 'link', 'unlink', '|', 'bullist', 'numlist', '|', 'undo', 'redo', '|', 'charmap', '|', 'wp_adv' );
}
function fbk_f_mce_buttons_2( $in ) {
	return array( 'styleselect', 'formatselect', '|', 'pasteword', 'spellchecker', '|', 'code', '|', 'link_shortcode' );
}



/**
 * Theme setup
 */

add_action( 'after_setup_theme', 'fbk_f_theme_setup' );
function fbk_f_theme_setup() {
	global $fbk_query, $wp_query;

	add_editor_style( 'style.css' );

	add_theme_support( 'menus' );
	register_nav_menu( 'unternavi', 'Unternavigation' );
	register_nav_menu( FBK_LANG_ORDER_MENU, 'Sprachreihenfolge' );

	add_theme_support( 'post-thumbnails' );
	
	if ( ! is_admin() ) {
		$fbk_query = new FBK_Query( $wp_query );
	}
	
	if ( ! is_singular() )
		remove_action('wp_footer', 'dsq_output_footer_comment_js');
}

add_filter( 'excerpt_length', 'fbk_f_excerpt_length' );
function fbk_f_excerpt_length( $length ) {
	return 40;
}

add_filter( 'excerpt_more', 'fbk_f_auto_excerpt_more' );
function fbk_f_auto_excerpt_more( $more ) {
	return '&hellip;';
}

function fbk_trim_header() {
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'feed_links', 2, 1 );
	remove_action( 'wp_head', 'feed_links_extra', 3, 1 );
	remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
	remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );

	add_action( 'wp_head', 'setup_dev_env' );
}

function fbk_title_l10n( $title ) {
	if ( is_404() && false !== strpos( $title, 'Page not found' ) )
		$title = str_replace( 'Page not found', 'Seite nicht gefunden', $title );
	return $title;
}

function setup_dev_env() {
	if ( false !== strpos( get_option('siteurl'), 'ws4' ) )
		echo "<script>disqus_developer = 1;</script>";
}

add_filter( 'the_content', 'fbk_the_content', 20 );
function fbk_the_content( $html ) {
	// Remove p.noautop (is this really necessary? CSS would take care of it anyway...)
	$html = preg_replace( '!<p[^>]*\\bclass="noautop"[^>]*>(.*?)</p>!', '$1', $html );

	return $html;
}

add_filter( 'use_default_gallery_style', '__return_false' );

function fbk_check_for_changed_slugs( $post_id, $post, $post_before ) {
	if ( 'school' != $post->post_type )
		wp_check_for_changed_slugs( $post_id, $post, $post_before );
}

add_filter( 'query_vars', 'fbk_add_queryvars' );
function fbk_add_queryvars( $qv ) {
	// Search extension
	$qv[] = 'search_type';
	$qv[] = 'city';
	$qv[] = 'bu';

	// Print view
	$qv[] = 'print';

	// Quotes
	$qv[] = 'q';
	$qv[] = 'u';
	$qv[] = 'pdf';
	$qv[] = 'refresh_key';

	// Newsletter
	$qv[] = 'optout';

	if ( is_admin() ) {
		$qv[] = 'persistent_filter';
	}

	return $qv;
}

add_filter( 'request', 'sanitize_search_rq' );
add_action( 'parse_query', 'sanitize_search_q' );
function sanitize_search_rq( $request ) {
	if ( isset($request['course_tag']) && is_array($request['course_tag']) )
		$request['course_tag'] = implode( ',', $request['course_tag'] );
	return $request;
}
function sanitize_search_q( $wp_query ) {
	if ( $wp_query->get('city') )
		$wp_query->set( 'loc', $wp_query->get('city') );
}

add_filter( 'wp_nav_menu_unternavi_items', 'fbk_add_offer_menu_item' );
function fbk_add_offer_menu_item( $nav_menu ) {
	if ( $offers = get_current_offers() )
		$offer_menu_item = '<li class="menu-item"><a href="' . home_url() . '/news" class="news-1">Sonderangebote (' . count($offers) . ')</a></li>';
	else
		$offer_menu_item = '';
	return $offer_menu_item . $nav_menu;
}

add_shortcode( 'link', 'link_shortcode' );
function link_shortcode( $attributes, $content = '' ) {
	extract( shortcode_atts( array(
		'id' => 0,
		'class' => '',
	), $attributes ) );
	$post =& get_post( $id );
	if ( ! $post )
		return $content;
	if ( $post->post_type != 'page' )
		$class .= " $post->post_type-$id";
	return "<a href='" . get_permalink($id) . "' class='$class'>" . apply_filters( 'the_title', $content ? $content : $post->post_title ) . "</a>";
}


/**
 * Scripts & Styles
 */

function fbk_addscripts() {
	$dir = get_stylesheet_directory_uri();
	
	fbk_register_min( 'script', 'fbk-util', 'js/util.js', array('json2','jquery') );
	
	wp_register_script( 'jquery.datepicker', $dir.'/lib/jquery/jquery.ui.datepicker.min.js', array('jquery-ui-core'), false, true );
	wp_register_script( 'jquery.datepicker-de', $dir.'/lib/jquery/jquery.ui.datepicker-de.js', array('jquery.datepicker'), false, true );

	wp_register_style( 'jquery.ui.overcast.style', $dir.'/lib/jquery/theme/overcast/jquery.ui.all.css' );

	if ( is_admin() ) {
		wp_register_script( 'fbk-cf', $dir.'/js/fbk-cf.js', array('jquery', 'fbk-util'), false, true );
		wp_register_script( 'fbk.school-connect', $dir.'/js/school-connect.js', array( 'jquery' ), false, true );
		wp_register_style( 'fbk-admin-stylesheet', $dir.'/fbk-admin-style.css' );
	} else {
		wp_register_script( 'history.js', $dir.'/lib/jquery.history.js', false, false, true );

		wp_register_script( 'jquery.anim', $dir.'/lib/jquery/jquery.animate-enhanced.js', array('jquery'), false, true );

		fbk_register_min( 'script', 'fbk-ajax', 'js/ajax.js', array('fbk-util', 'json2', 'jquery', 'history.js') );
		fbk_register_min( 'script', 'fbk-membox', 'js/membox.js', array( 'fbk-util', 'jquery', 'fbk-ajax' ) );

		fbk_register_min( 'style', 'fbk.base', 'style.css' );
		fbk_register_min( 'style', 'fbk.layout', 'layout.css', array('fbk.base') );

		wp_register_style( 'fbk.colors', $dir . '/' . FBK_COLORS_CSS_FILE, array('fbk.layout') );

		fbk_register_min( 'style', 'fbk.print', 'print.css', array('fbk.layout') );
	}
}

function fbk_register_min( $type, $handle, $path, $deps = array(), $ver = 'mtime', $in_footer_or_medium = null ) {
	static $dir, $uri;
	if ( empty($dir) ) {
		$dir = get_stylesheet_directory() . '/';
		$uri = get_stylesheet_directory_uri() . '/';
	}
	if ( 'script' == $type ) {
		$func = 'wp_register_script';
		$in_footer_or_medium = ( null == $in_footer_or_medium ? 1: $in_footer_or_medium );
	} else {
		$func = 'wp_register_style';
		$in_footer_or_medium = ( null == $in_footer_or_medium ? 'all' : $in_footer_or_medium );
	}
	if ( ! file_exists( $dir . ( $file = preg_replace( '/\.[a-z0-9]+$/', '-min$0', $path ) ) )
	 || ( 'style' == $type && 'yes' != get_option( 'fbk_use_min_css' ) )
	)
		$file = $path;
	if ( 'mtime' == $ver )
		$ver = filemtime( $dir . $file );

	$func( $handle, $uri . $file, $deps, $ver, $in_footer_or_medium );
}

function fbk_enqueue_scripts() {
	wp_enqueue_script( 'history.js' );
	wp_enqueue_script( 'jquery.anim' );
	wp_enqueue_script( 'jquery.datepicker-de' );

	wp_enqueue_script( 'fbk-membox' );
	wp_enqueue_script( 'fbk-ajax' );
}

function fbk_print_styles() {
	if ( ! is_readable( get_stylesheet_directory() . '/colors-min.css' ) )
		fbk_rebuild_color_css();

	wp_enqueue_style( 'fbk.base' );
	wp_enqueue_style( 'fbk.layout' );
	wp_enqueue_style( 'fbk.colors' );
	wp_enqueue_style( 'jquery.ui.overcast.style' );

	if ( $GLOBALS['wp_query']->get( 'print' ) )
		wp_enqueue_style( 'fbk.print' );
}

function fbk_admin_enqueue_scripts() {
	$screen = get_current_screen();
	if ( 'school' == $screen->id || 'offer' == $screen->id ) {
		wp_enqueue_script( 'fbk-cf' );
		wp_enqueue_script( 'jquery.datepicker-de' );
	}
}

function fbk_admin_print_styles() {
	wp_enqueue_style( 'fbk-admin-stylesheet' );
	wp_enqueue_style( 'jquery.ui.overcast.style' );
}

function fbk_admin_print_color_styles() {
	global $fbk;
	$out = '';
	foreach ( $fbk->cats as $slug => $cat ) {
		$out .= '.c-' . $slug . '{background-color:#' . get_term_meta( $cat->term_id, 'fbk_color_e', true ) . ';}';
	}
	echo "<style type='text/css'>$out</style>";
}



/**
 * Documentation
 */
function fbk_contextual_help() {
	$screen = get_current_screen();
	if ( 'school' == $screen->id )
	$screen->add_help_tab( array(
		'id' => 'fbk-help-links',
		'title' => 'Interne Links',
		'content' => "<p>Aus technischen Gründen ist es besser, interne Links, d.h. Links zu anderen Schulen, Artikeln etc., "
		. "über den Kurztag [link id=...] einzufügen. Ein handliches Interface dafür findest du, wenn du im Editor auf den Button "
		. "mit dem Studium-Ausland-Logo klickst.</p>"
	));
}

/**
 * Newsletter send-out
 */
function fbk_schedule_newsletter_cron() {
	if ( ! wp_next_scheduled( 'fbk_newsletter_cron' ) )
		wp_schedule_event( time(), 'daily', 'fbk_newsletter_cron' );
}

function fbk_send_newsletter() {
	$news = get_posts( array(
		'post_status' => 'publish',
		'post_type' => array( 'post', 'offer' ),
		'year' => date('Y'),
		'w' => date('W')
	));
	if ( empty($news) )
		return;
	foreach ( $news as $_item ) {
		if ( ! get_post_meta( $_item->ID, '_fbk_newsletter_sent', true ) ) {
			$item = $_item;
			break;
		}
	}
	if ( empty($item) )
		return;

	global $wpdb;

	$mime_boundary = 'multipart-' . md5(time());
	$permalink = get_permalink($item);

	$html = '(Probleme mit der Darstellung? <a href="' . $permalink . '">Lesen Sie diese News online!</a>)'
	. '<h2>' . apply_filters( 'the_title', $item->post_title ) . '</h2>'
	. apply_filters( 'the_content', $item->post_content )
	. '<p>&nbsp;</p><p>' . str_replace( "\n", '<br />', stripslashes(get_option( 'fbk_mail_signature' )) ) . '</p>'
	. '<p style="font-size: 0.8em;">Sie können <a href="%optout%">diesen Newsletter abbestellen</a>.</p>';

	$plain = substr_replace( strip_tags( $html ), ' unter der Adresse <%optout%>', -1 );
	$plain = str_replace( 'Lesen Sie diese News online!', 'Lesen Sie diese News online unter ' . $permalink . '!', $plain );

	$header = 'MIME-Version: 1.0'
	. PHP_EOL . 'Content-Type: multipart/alternative; boundary="' . $mime_boundary . '"'
	. PHP_EOL . 'From: ' . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_newsletter' )));

	$users = $wpdb->get_results( "SELECT * FROM " . FBK_Quotes::USER_TABLE . " WHERE `newsletter` = 1 AND `parent_user` = '' GROUP BY `email`" );
	$home = home_url( '?' );

	update_post_meta( $item->ID, '_fbk_newsletter_sent', 1 ); // Let's set that flag before the next cron is spawned... we don't want to send out twice, do we.

	set_time_limit( 0 );
	$i = 0;
	foreach ( $users as $user ) {
		$optout = $home . 'u=' . $user->user_id . '&optout=' . $user->access_key;
		list( $html, $plain ) = str_replace( '%optout%', $optout, array( $html, $plain ) );
		$message = '--' . $mime_boundary
		. PHP_EOL . 'Content-Type: text/plain; charset="utf-8"'
		. PHP_EOL . 'Content-Transfer-Encoding: base64'
		. PHP_EOL . PHP_EOL . chunk_split(base64_encode($plain))
		. PHP_EOL . '--' . $mime_boundary
		. PHP_EOL . 'Content-Type: text/html; charset="utf-8"'
		. PHP_EOL . 'Content-Transfer-Encoding: base64'
		. PHP_EOL . PHP_EOL . chunk_split(base64_encode($html));

		mail(
			'"=?UTF-8?B?' . base64_encode( $user->first_name . ' ' . $user->last_name ) . '?=" <' . $user->email . '>',
			'=?UTF-8?B?' . base64_encode($item->post_title) . '?=',
			$message,
			$header
		);

		if ( (++$i) % 5 == 0 ) // 5 emails out, 5 seconds break, rinse & repeat.
			sleep( 5 );
	}
}

function fbk_check_optout( $template ) {
	global $wp_query, $fbk_quotes, $wpdb;
	if ( $optout = $wp_query->get('optout') && $u = $wp_query->get('u') ) {
		$user = $fbk_quotes->get_user( $u, $optout, true );
		if ( $user ) {
			$fbk_quotes->update_user( $user, array( 'newsletter' => 0 ) );
			echo "<p>Besten Dank. Sie werden künftig keine E-Mails mehr von uns erhalten.</p><p><a href='" . home_url() . "'>Zur Startseite</a></p>";
			return false;
		}
	}
	return $template;
}

/**
 * Cron to check for new quotes
 */
if ( 'yes' == get_option( 'fbk_quote_noauto' ) )
	wp_clear_scheduled_hook( 'fbk_check_new_quotes' );
elseif ( ! wp_next_scheduled( 'fbk_check_new_quotes' ) )
	wp_schedule_event( strtotime('today 8 am'), 'daily', 'fbk_check_new_quotes' );

function fbk_check_new_quotes() {
	$last_check = get_option( 'fbk_last_new_quotes_check' );
	if ( $last_check ) {
		global $wpdb;
		$new_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . FBK_Quotes::QUOTES_TABLE . ' WHERE date_added > ' . (int) $last_check );
		update_option( 'fbk_last_new_quotes_check', time() );
		if ( $new_count ) {
			if ( 1 == $new_count ) {
				$subject = '1 neuer Kostenvoranschlag auf studium-ausland.eu';
				$mailtext = 'Auf der Studium-Ausland-Webseite wurde ein neuer Kostenvoranschlag beantragt. Schau doch mal vorbei unter '
				. '<' . admin_url( '/tools.php?page=quote_list' ) . '>!';
			} else {
				$subject = $new_count . ' neue Kostenvoranschl=C3=A4ge auf studium-ausland.eu';
				$mailtext = 'Auf der Studium-Ausland-Webseite wurden ' . $new_count . ' neue Kostenvoranschl=C3=D4ge beantragt. Schau doch mal vorbei unter '
				. '<' . admin_url( '/tools.php?page=quote_list' ) . '>!';
			}
			mail(
				q_encode_angle_address(stripslashes(get_option( 'fbk_mail_to' ))),
				$subject,
				$mailtext,
				'Content-Type: text/plain; charset=utf-8'
				. PHP_EOL . 'Content-Transfer-Encoding: quoted-printable'
				. PHP_EOL . 'From: ' . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_internal' )))
			);
		}
	}
}

/**
 * Cron to update facebook page info
 */
if ( ! wp_next_scheduled( 'fbk_check_fb_info' ) )
	wp_schedule_event( time(), 'daily', 'fbk_check_fb_info' );

function fbk_check_fb_info() {
	$fb_props = json_decode( file_get_contents( "http://graph.facebook.com/" . get_option( 'fbk_fb_pageid' ) . "?fields=name,link,likes" ) );
	foreach ( $fb_props as $key => $prop )
		update_option( 'fbk_fb_' . $key, $prop );
}


/**
 * Update synced external menus: Check on cache flush
 */
function fbk_check_external_resync( $type, $id ) {
	if ( 'menu' == $type && is_numeric( $id ) ) {
		$cat = get_term( (int) $id, 'category' );
		if ( ! $cat || is_wp_error( $cat ) )
			return;
		$categories = array( $cat );
	} elseif ( 'menu' == $type && '_all' == $id ) {
		$categories = get_terms( 'category', array( 'get' => 'all' ) );
	}

	if ( ! empty($categories) )
		foreach ( $categories as $category )
			if ( 'yes' == get_term_meta( $category->term_id, 'fbk_menu_sync', true ) )
				fbk_resync_external_menu( $category->term_id );
}

/**
 * Update synced external menus
 */
function fbk_resync_external_menu( $cat_id ) {
	$cat = get_term( $cat_id, 'category' );
	if ( ! $cat || is_wp_error( $cat ) )
		return;

	$markers = array(
		'start' => get_term_meta( $cat_id, 'fbk_menu_sync_start', true ),
		'end' => get_term_meta( $cat_id, 'fbk_menu_sync_end', true )
	);
	if ( empty($markers['start']) || empty($markers['end']) )
		return;

	$sync_file = get_term_meta( $cat_id, 'fbk_menu_sync_file', true );
	if ( ! $sync_file || ! is_file( ABSPATH . $sync_file ) || ! is_writable( ABSPATH . $sync_file ) )
		return;

	$file_contents = file_get_contents( ABSPATH . $sync_file );
	if (
		empty($file_contents)
		|| false === ( $start_index = strpos( $file_contents, $markers['start'] ) )
		|| false === ( $end_index = strpos( $file_contents, $markers['end'], $start_index += strlen($markers['start']) ) )
	)
		return;

	$old_menu = substr( $file_contents, $start_index, $end_index - $start_index );

	if ( 'yes' == get_term_meta( $cat_id, 'fbk_menu_sync', true ) ) {
		$depth = (int) get_term_meta( $cat_id, 'fbk_menu_sync_depth', true );
		$need_school_info = $depth == 4 || $depth < 1;
		$pattern = "/^$cat_id-(?P<lang>\\d+)-(?P<ctry>\\d+)-(?P<city>\\d+)-(?P<school>\\d+)$/";
		$tree = array();
		$cache = get_option( 'fbk_menu_cache' );
		if ( empty($cache) || ! is_array($cache) ) {
			fbk_rebuid_navmenus();
			$cache = get_option( 'fbk_menu_cache' );
		}
		foreach ( $cache as $tuple )
			if ( preg_match( $pattern, $tuple, $matches ) )
				if ( $need_school_info )
					$tree[$matches['lang']][$matches['ctry']][$matches['city']][$matches['school']] =& get_post( $matches['school'] );
				else // Depth is below school level anyway, don't bother screwing the database over this.
					$tree[$matches['lang']][$matches['ctry']][$matches['city']][$matches['school']] = true;
		$args = array(
			'container' => false,
			'include_title' => false,
			'menu_class' => get_term_meta( $cat_id, 'fbk_menu_sync_class', true ),
			'depth' => $depth
		);
		$new_menu = fbk_generate_menu( $tree, $cat_id, $args );
	} else {
		$new_menu = '';
	}

	if ( $old_menu == $new_menu )
		return;

	file_put_contents(
		ABSPATH . $sync_file,
		substr_replace( $file_contents, $new_menu, $start_index, $end_index - $start_index )
	);
}

/**
 * Miscellaneous helper functions
 */

function fbk_ua_supports( $feature ) {
	static $cache = array();
	if ( array_key_exists( $feature, $cache ) )
		return $cache[$feature];
	$ua = $_SERVER['HTTP_USER_AGENT'];
	switch ( $feature ) {
		case 'placeholder':
			if ( preg_match( '~^Mozilla.*Chrome~', $ua ) )
				return $cache[$feature] = true;
			if ( preg_match( '~^Mozilla.*Firefox/(\d+)~', $ua, $matches ) || preg_match( '~^Mozilla.*Version/(\d+).*Safari~', $ua, $matches ) )
				return $cache[$feature] = ($matches[1] > 3);
			if ( preg_match( '~^Opera.*Version/(\d+)~', $ua, $matches ) )
				return $cache[$feature] = ($matches[1] > 10);
			break;
		case 'html5shiv':
			if ( preg_match( '~MSIE (\d+)~', $ua, $matches ) )
				return $cache[$feature] = ($matches[1] < 9);
			break;
		case 'ie7css':
			if ( preg_match( '~MSIE (\d+)~', $ua, $matches ) )
				return $cache[$feature] = ($matches[1] < 7);
			break;
		case 'datepicker':
			if ( preg_match( '~^Opera.*Version/(\d+)~', $ua, $matches) )
				return $cache[$feature] = ($matches[1] > 8);
			break;
		case 'generated':
			if ( preg_match( '~MSIE (\d+)~', $ua, $matches ) )
				return $cache[$feature] = ($matches[1] > 7 );
			else
				return $cache[$feature] = true;
			break;
	}
	return $cache[$feature] = false;
}

function _fbk_usort_by_title( $a, $b ) {
	return strcmp( $a->title, $b->title );
}
function _fbk_usort_by_name( $a, $b ) {
	return strcmp( $a->name, $b->name );
}
function _fbk_usort_by_post_title( $a, $b ) {
	return strcmp( $a->post_title, $b->post_title );
}
function _fbk_linesort_by_id( $a, $b ) {
	if ( ! isset($a['_id']) || ! isset($b['_id']) )
		return 0;
	return ( $a['_id'] < $b['_id'] ? -1 : ($a['_id'] > $b['_id'] ? 1 : 0) );
}
function _fbk_usort_by_menu_order_x( $a, $b ) {
	global $_fbk_usort_pid;
	$a_order = (int) get_post_meta( $a->ID, "_fbk_menu_order_$_fbk_usort_pid", true );
	$b_order = (int) get_post_meta( $b->ID, "_fbk_menu_order_$_fbk_usort_pid", true );
	if ( ! $a_order && ! $b_order )
		return $a->menu_order < $b->menu_order ? -1 : ($a->menu_order > $b->menu_order ? 1 : 0);
	if ( ! $b_order )
		return -1;
	if ( ! $a_order )
		return 1;
	if ( $a_order == $b_order )
		return 0;
	return $a_order < $b_order ? -1 : 1;
}

function fbk_f_matrix_normalize( &$matrix, $pad_value = array(), $alter_matrix = true ) {
	$result = (array) $matrix;
	$y_axis = array();
	foreach ( $result as $key => $column ) {
		if ( empty($column) )
			unset( $result[$key] );
		foreach ( $column as $ckey => $cell )
			$y_axis[$ckey] = 1;
	}
	$y_axis = array_keys( $y_axis );
	foreach ( $result as $key => $column )
		foreach ( $y_axis as $ckey )
			if ( ! array_key_exists( $ckey, $column ) )
				$result[$key][$ckey] = $pad_value;
	if ( $alter_matrix )
		$matrix = $result;
	return $result;
}

function array_chunk_vertical($data, $columns, $preserve_keys = false) {
	$n = count($data);
	$per_column = floor($n / $columns);
	$rest = $n % $columns;
	reset( $data );

	$per_columns = array();
	for ( $i = 0; $i < $columns; $i++ ) {
		$per_columns[$i] = $per_column + ($i < $rest ? 1 : 0);
	}

	$tabular = array();
	if ( $preserve_keys ) {
		foreach ( $per_columns as $rows ) {
			for ( $i = 0; $i < $rows; $i++ ) {
				$tabular[$i][key($data)] = current($data);
				next($data);
			}
		}
	} else {
		foreach ( $per_columns as $rows ) {
			for ( $i = 0; $i < $rows; $i++ ) {
				$tabular[$i][] = current($data);
				next($data);
			}
		}
	}

	return $tabular;
}

function fbk_thickbox_filter( $args, $pagination = false ) {
	global $fbk;
	$defaults = array(
		'query_args' => array( 'tab' => 'type', 'type' => '' ),
		'filter' => array( 's' => '', 'post_type' => 'any' ),
		'post_types' => array( 'any', 'school', 'post', 'page', 'offer' ),
	);
	extract( $args = wp_parse_args( $args, $defaults ) );

	if ( false !== $pagination && ! is_string($pagination) ) {
		$pagination_defaults = array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $GLOBALS['wp_query']->max_num_pages,
			'current' => $GLOBALS['wp_query']->get('paged')
		);
		$pagination = wp_parse_args( $pagination, $pagination_defaults );
		if ( ! $pagination['current'] )
			$pagination['current'] = 1;
		if ( $pagination['total'] > 1 )
			$pagination = paginate_links( $pagination );
		else
			$pagination = false;
	}

?>
<form id="filter" action="<?= admin_url("media-upload.php") ?>" method="get">
<?php foreach ( $query_args as $query_arg_key => $query_arg_value )
	echo "<input type='hidden' name='$query_arg_key' value='$query_arg_value'>";
?>
<p class="search-box">
<?php
	if ( ! empty( $taxonomies ) ) {
		foreach ( $taxonomies as $tax => $slug ) {
			$tax_spec = get_taxonomy( 'cat'==$tax ? 'category' : $tax );
			$sel = empty($slug) ? "" : " selected='selected'";
			echo " <select name='$tax'>";
			echo "<option value=''$sel>" . $tax_spec->labels->all_items . "</option>";
			echo fbk_rw_get_taxonomy_dropdown_opts(
				'cat'==$tax ? 'category' : $tax,
				(array) $slug,
				'slug',
				'loc'==$tax ? true : false
			);
			echo "</select> ";
		}
	}
?>
<?php if ( array_key_exists('post_type', $filter) ) : ?>
<select name="post_type">
<?php	foreach ( $post_types as $post_type ) {
		if ( $post_type == $filter['post_type'] || 'any' == $post_type && ! $filter['post_type'] )
			$sel = "selected='selected'";
		else
			$sel = '';
		if ( 'any' == $post_type ) {
			echo "<option value='any' $sel>Alle Objekttypen</option>";
			continue;
		}
		$post_type = get_post_type_object( $post_type );
		echo "<option value='$post_type->name' $sel>$post_type->label</option>";
	}
?>
</select>
<?php endif; ?>
<?php if ( array_key_exists('s', $filter) ) : ?>
<input type="text" name="s" placeholder="Schulname" value="<?= esc_attr( $filter['s'] ) ?>">
<?php endif; ?>
<input type="submit" class="button" value="Suchen">
</p>
<?php if ( $pagination ) : ?>
<div class="tablenav"><div class="tablenav-pages"><?= $pagination ?></div></div>
<?php endif; ?>
</form>
<?php
}

function fbk_compress_css( $in ) {
	$out = preg_replace(
		array(
			'!/\\*[^*]*\\*+([^/][^*]*\\*+)*/!', // Comments
			'!\\s{2,}!', // Multiple spaces => single space
			'!\\s*([;,{}])\\s*!', // Whitespace before & after ;,{}
			'!(?<=:)\\s*!', // Whitespace after : (before would include :hover and similar pseudo-classes)
			'!;}!',
			'!\\b0(\\.\\d)!' // 0.xy -> .xy
		),
		array(
			'',
			' ',
			'$1',
			'',
			'}',
			'$1'
		),
		$in
	);
	$out = str_replace( array( "\r", "\n", "\t" ), '', $out );
	return trim($out);
}

function q_encode_angle_address( $in ) {
	$in = trim($in);
	if ( false === $str_start = strpos( $in, '"' ) )
		return $in;
	$str_end = strpos( $in, '"', ++$str_start );
	$str = substr( $in, $str_start, $str_end-$str_start );
	$str = '=?UTF-8?B?' . base64_encode( $str ) . '?=';
	return substr_replace( $in, $str, $str_start, $str_end-$str_start );
}
?>
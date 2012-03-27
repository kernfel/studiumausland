<?php
/**
 * @package Studium_Ausland
 */

global $fbk_query, $fbk;

$home = home_url();
$stylesheet_directory_uri = get_stylesheet_directory_uri();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<title><?php fbk_the_title(); ?></title>
<meta language="de-DE">
<meta name="description" content="<?php
	if ( ! empty($fbk_query->category) ) {
		if ( is_archive() )
			$description = fbk_get_category_meta( $fbk_query->detailed_taxonomy . '_desc' );
		elseif ( is_singular( 'school' ) )
			$description = fbk_get_category_meta( 'school_desc' );
	}
	if ( empty($description) )
		$description = 'Studium-Ausland: Sprachkurse, Praktika, Sommer-Sprachprogramme sowie Kurse '
		. 'der Sekundar- und Hochschulbildung in aller Welt - für Lernwillige aus allen Altersgruppen!';
	echo $description;
?>">
<?php if ( $wp_query->get('print') ) echo '<meta name="robots" value="noindex">'; ?>
<link rel="icon" href="<?= get_stylesheet_directory_uri() ?>/img/favicon.ico">
<?= fbk_get_adjacent_link( '<link rel="%<>%" href="%_%" title="%t%" />', true ); ?>
<?= fbk_get_adjacent_link( '<link rel="%<>%" href="%_%" title="%t%" />', false ); ?>
<?php if ( is_attachment() ) : ?><meta name="robots" content="noindex"><?php endif; ?>
<?php if ( is_home() || is_front_page() ) : ?>
	<meta property="og:image" content="<?= $stylesheet_directory_uri ?>/img/logo-fb.jpg">
	<meta property="og:url" content="<?= home_url() ?>">
	<meta property="og:type" content="website">
	<meta property="og:title" content="Studium Ausland">
<?php endif; ?>
<script>
/* <![CDATA[ */
fbk={
ajaxurl: '<?= get_stylesheet_directory_uri() ?>/ajax-ap.php',
siteTitle: '<?php bloginfo('name') ?>',
memboxContainer: 'visits',
rc_pk: '<?= stripslashes(get_option( 'fbk_recaptcha_pubkey' )) ?>',
rc_url: 'http://www.google.com/recaptcha/api/js/recaptcha_ajax.js',
cboxOpts: {maxWidth:'95%', maxHeight:'95%'},
noAutoQ: <?= 'yes' == get_option('fbk_quote_noauto') ? 'true' : 'false' ?>,
sb: {<?php 
	$sb = array();
	foreach ( $fbk->sb_objects as $type => $objs )
		foreach ( $objs as $obj )
			$sb[] = $obj . ':' . $type;
	echo implode( ',', $sb );
?>},
cats: {<?php
	$cats = array();
	foreach ( $fbk->cats as $cat )
		$cats[] = "'$cat->term_id':'$cat->slug'";
	echo implode( ',', $cats );
?>}
};
RecaptchaOptions={theme:'white',lang:'de'};
/* ]]> */
</script>
<?php
	wp_head();
?>
<script>
var _gaq = _gaq || [];
_gaq.push(['_setAccount', '<?= stripslashes(get_option( 'fbk_analytics_id' )) ?>']);
_gaq.push(['_trackPageview']);
(function() {
var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
</script>
<!--[if lt IE 9]><script type="text/javascript" src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script><![endif]-->
<!--[if lt IE 8]><link rel="stylesheet" type="text/css" src="<?= $stylesheet_directory_uri ?>/ie7.css"><![endif]-->
<!--[if lt IE 7]><script type="text/javascript" src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE7.js"></script><![endif]-->
</head>
<?php

flush();

$body_class = 'nojs';
if ( $fbk_query && $fbk_query->category )
	$body_class .= ' c-'.$fbk_query->category->slug;
else
	$body_class .= ' c-0';
if ( is_page() )
	$body_class .= ' page-name-' . $post->post_name;
if ( is_category() )
	$body_class .= ' alllangs';
if ( $fbk->did_news_query )
	$body_class .= ' news';
?>
<body <?php body_class( @$body_class ) ?>>
<script>document.body.className=document.body.className.replace(/(\bnojs\b<?php
	if ( is_category() )	echo "|\balllangs\b";
?>)/g,'');</script>
 <header id="top"><div class="w1k catbar">
<?php foreach ( $fbk->cats as $cat ) : ?>
  <a href="<?= $home . '/' . $cat->slug ?>" class="c-<?= $cat->slug ?>-full cat-<?= $cat->term_id ?>" style="width:<?= $cat->fbk_width ?>px;"><?= $cat->name ?></a>
<?php endforeach; ?>
 </div></header>
 <div id="page"><div class="w1k"><div class="pagewrap">
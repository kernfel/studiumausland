<?php
/**
 * @package Studium_Ausland
 *
 * Note: Changes in which context gets which sidebar need to be reflected also in inc/class-fbk.php ( FBK::$sb_objects and FBK::get_sidebar_type() ).
 */

global $fbk, $fbk_cache;

$sidebar_type = $fbk->get_sidebar_type();

?>
<aside id="left">
<h1 id="logo"><a href="<?= home_url(); ?>" rel="home index">Sprachkurs im Ausland - <?= get_bloginfo( 'name' ) ?></a></h1>
<div id="dyn-left">
<?php

$news_box = '
<aside id="news" class="ad noheading ad-fullwidth">
	<a href="' . home_url( '/news' ) . '" style="height:100px;" class="news-1">News und Sonderangebote zu unseren Sprachschulen</a>
	<a href="' . esc_url(get_feed_link()) . '" id="rss-icon" target="_blank">RSS</a>
</aside>';

$unternavi = $fbk_cache->get( 'menu', 'pages' );
if ( ! $unternavi ) {
	$fbk_cache->rec();
	wp_nav_menu( array('theme_location' => 'unternavi' ) );
	$unternavi = $fbk_cache->done( 'menu', 'pages', false );
}

/************* Front page, Pages, News, Offers ***************/
if ( FBK_SIDEBAR_INDEX == $sidebar_type ) : ?>
	<?= $news_box ?>
	<nav id="unternavi">
		<?= $unternavi ?>
	</nav>
	<aside class="ad" id="fblb" data-title="Facebook"><?php
		if ( ! fbk_ua_supports('generated') )
			echo "<h1 class='boxheading'>Facebook</h1>";
		echo "<a href='", get_option( 'fbk_fb_link' ), "' id='fb_pagelink'>",
		"<img src='http://graph.facebook.com/", get_option( 'fbk_fb_pageid' ), "/picture?type=small' alt='", get_option( 'fbk_fb_name' ), "' id='fb_thumb'>",
		"Besuche uns auf Facebook und werde unser ", get_option( 'fbk_fb_likes' ) + 1, ". Freund!</a>";
	?></aside>
	<aside class="ad ad-fullwidth" style="background:url(<?= get_stylesheet_directory_uri() ?>/img/aupair_teaser.gif) no-repeat left 30px;"><?php
		if ( ! fbk_ua_supports('generated') )
			echo "<h1 class='boxheading'>Anzeige</h1>"; ?>
		<a href="http://www.aupairagentur-cefelin.de" style="display:block;height:131px;line-height:0;text-indent:-99999px;">Au-Pair-Agentur Cefelin, Demi-Pair, Work&amp;Travel</a>
	</aside>
	<aside class="ad noheading"><p><a href="http://www.intact.cz">www.intact.cz</a></p></aside>
<?php

/************* Schools; Country, City and Category archives ********/
else : ?>
	<?php fbk_tt_the_navmenu(); ?>
	<?= $news_box ?>
	<nav id="unternavi">
		<?= $unternavi ?>
	</nav>
<?php endif; ?>
</div>
</aside>
<?php
/**
 * @package Studium_Ausland
 */

global $fbk;

?>
<aside id="right" style="">
	<a id="print" href="?print=true" target="_blank" rel="alternate nofollow" media="print">Druckansicht</a>
	<form role="search" method="get" action="<?= home_url() ?>" id="searchform">
	 <input type="hidden" name="search_type" value="simple">
	 <input type="search" placeholder="Suchen…" id="search" name="s" tabindex="100" <?php
	 	if ( isset($_GET['s']) )
	 		echo 'value="' . @implode( ' ', $wp_query->get('search_terms') ) . '"';
	 	elseif ( ! fbk_ua_supports( 'placeholder' ) )
	 		echo 'value="Suchen…"';
	 ?>>
	 <input type="submit" id="search-submit" value="Suchen">
	</form>
	<div class="link"><a href="/anfrage" id="request-link">Unverbindliche Anfrage</a></div>
	<section id="visits" style="display: none;">
		<ul id="mb"></ul>
	</section>
<?php

if ( $random_offer = get_current_offers( array('orderby' => 'rand', 'posts_per_page' => 1) ) ) :
	$offer = $random_offer[0];
	$cat = get_connect_category( $offer, 'slug' );
?>
	<section id="sotd" class="teaser c-<?= $cat ?>">
		<h1>Sonderangebot</h1>
<?php
	$link_open_tag = "<a href='" . get_permalink( $offer->ID ) . "' class='offer-$offer->ID'>";
	if ( $thumbnail = get_offer_thumbnail( $offer, explode('x',FBK_IMAGESIZE_SIDEBAR) ) )
		echo $link_open_tag . $thumbnail. '</a>';
	echo apply_filters( 'the_excerpt', fbk_tt_get_the_excerpt( $offer->ID ) );
?>
		<div class="link"><?= $link_open_tag . apply_filters( 'the_title', $offer->post_title ) ?></a></div>
	</section>
<?php endif; ?>
<div id="dyn-right">
<?php if ( $fbk->get_sidebar_type() == FBK_SIDEBAR_INDEX ) : ?>
	<aside class="ad" data-title="Praktikanten gesucht"><?php
		if ( ! fbk_ua_supports('generated') )
			echo "<h1 class='boxheading'>Praktikanten gesucht</h1>"; ?>
		<p>Deine Aufgaben bei uns: Telefonische Beratung von Interessenten für ein Studium im Ausland, schriftliche und telefonische Kommunikation mit unseren Partnerschulen und Bildungsinstituten.</p>
		<p>Voraussetzungen: Englisch in Wort und Schrift, sicherer Umgang mit PC-Software wie Word und Outlook. Spätere Übernahme ist ausdrücklich erwünscht.</p>
		<p>Bewerbungsunterlagen per <a href="mailto:cefelin@studium-ausland.eu">E-Mail</a> oder per <a href="<?= home_url('/impressum') ?>">Post</a>.</p>
	</aside>
<?php endif; ?>
</div>
</aside>
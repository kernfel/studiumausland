<?php
/**
 * @package Studium_Ausland
 */

global $post, $fbk_cf_slices, $fbk_cf_prefix, $fbk_cache, $footer_open;

if ( is_preview() || ! $fbk_cache->get_e( 'school', $post->ID ) ) :
	$fbk_cache->rec();
	$permalink = get_permalink();
	$title = get_the_title();

?><!--{<?= html_entity_decode( fbk_the_title( false ), ENT_COMPAT, 'UTF-8' ) ?>}-->
<header>
<div id="social">
	<a id="fb" title="Auf Facebook weiterempfehlen" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode( $permalink ) ?>&t=<?=
		rawurlencode( $title . " | Sprachkurs im Ausland, Studium Ausland" )
	?>" target="_blank"></a>
	<a id="twitter" title="Auf Twitter weiterempfehlen" href="https://twitter.com/intent/tweet?source=webclient&text=<?= rawurlencode(
		$title . " " . str_replace( array('http://www.','https://www.'), '', $permalink )
	) ?>" target="_blank"></a>
	<a id="gplus" title="Auf Google+ weiterempfehlen" href="https://plusone.google.com/_/+1/confirm?hl=de&url=<?= urlencode( $permalink ) ?>&title=<?=
		rawurlencode( $title . " | Sprachkurs im Ausland, Studium Ausland" )
	?>" target="_blank"></a>
	<a id="mail" title="Per E-Mail weiterempfehlen" href="mailto:?subject=<?= rawurlencode( $title ) ?>&body=<?= rawurlencode( utf8_decode(
		"Hallo!\n\nIch bin auf der Webseite von Studium Ausland (" . home_url() . ") auf diese Schule gestoßen. Wäre das nicht etwas für dich?\n\n"
		. $permalink . "\n\nLiebe Grüße, "
	)) ?>"></a>
</div>
<h1><?= $title ?></h1>
<?php if ( $offers = get_current_offers(
	array(
		'meta_query' => array(
			array(
				'key' => '_school_connect',
				'value' => $post->ID,
			)
		)
	)
) ) : ?>
<aside id="school-offers">
<h2>Jetzt aktuell:</h2>
<?php
	foreach ( $offers as $offer )
		echo "<a class='ln offer-$offer->ID' href='" . get_permalink($offer->ID) . "'>" . apply_filters( 'the_title', $offer->post_title ) . "</a>";
?>
</aside>
<?php endif; ?>
<nav class="slicenav">
	<?php fbk_tt_the_slice_navigation(); ?>
</nav>
</header>
<?php

foreach ( $fbk_cf_slices as $slug ) {
	if ( ! fbk_tt_has_slice( $slug, $post->ID ) )
		continue;
	$footer_open = false;

	echo "<section id='$slug' class='slice slice-$slug" . ( FBK_DEFAULT_SLICE == $slug ? " active" : "" ) . "'>";
	echo "<h2 class='hide-if-js'>" . wptexturize(stripslashes(get_option( 'fbk_slice_label_' . $slug ))) . "</h2>";
	get_template_part( 'slice', $slug );
	if ( ! $footer_open ) echo "<footer>";
	echo "<div class='toplink'><a href='#top'>Nach oben</a></div>";
	echo "</footer>";
	echo "</section>";
}
?>
<?php if ( 'open' == $post->comment_status ) : ?>
<section id='comments' class='slice slice-comments'>
	<h2 class='hide-if-js'>Kommentare</h2>
	<?php get_template_part( 'slice', 'comments' ); ?>
	<div class='toplink'><a href='#top'>Nach oben</a></div>
</section>
<?php endif; ?>
<script id="fbk_current">fbk.current=<?= json_encode( fbk_tt_get_school_data_array( $post ) ) ?>;</script>
<?php
	if ( ! is_preview() )
		$fbk_cache->done( 'school', $post->ID );
endif;
?>

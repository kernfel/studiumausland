<?php
/**
 * @package Studium_Ausland
 */

global $post, $wp_query;

$offer_ = array(
	'start' => get_post_meta( $post->ID, '_fbk_offer_start', true ),
	'end' => get_post_meta( $post->ID, '_fbk_offer_end', true )
);

$title = get_the_title();
$permalink = get_permalink();

?>
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
		"Hallo!\n\nIch bin auf der Webseite von Studium Ausland (" . home_url() . ") auf dieses Sonderangebot gestoßen. Wäre das nicht etwas für dich?\n\n"
		. $permalink . "\n\nLiebe Grüße, "
	)) ?>"></a>
	<a id="rss" title="Mit RSS abonnieren" href="<?= esc_url(get_feed_link()) ?>" target="_blank"></a>
</div>
<h1><?= $title ?></h1>
<div class="runtime <?php if ( strtotime($offer_['end']) < time() ) echo 'expired'; ?>">
	Angebotsdauer:
	<span class="runtime-date"><?= fbk_date( $offer_['start'] ) ?></span>
	bis <span class="runtime-date"><?= fbk_date( $offer_['end'] ) ?></span>
</div>
</header>
<?php the_content(); ?>
<?php
$school_ids = get_post_meta( $post->ID, '_school_connect' );
if ( $school_ids ) :
	$schools = new WP_Query( array('post__in' => $school_ids, 'post_type' => 'school', 'posts_per_page' => -1, 'orderby' => 'none') );
?>
<h2>Beteiligte Schulen</h2>
<?php for ( $left = true; $schools->have_posts(); $left = ! $left ) {
	$schools->the_post();
	$cat = get_the_category();
	$cat = $cat ? 'c-'.$cat[0]->slug : '';
	if ( $left )
		echo "<div class='double'>";
	echo "<article class='" . ( $left ? 'left' : 'right' ) . " teaser $cat'>";
	the_title( '<h2>', '</h2>' );
	$link_open_tag = '<a href="' . get_permalink() . '" title="' . get_the_title() . '" class="school-' . $post->ID . '">';

	if ( $thumbnail = fbk_get_first_gallery_image() )
		echo $link_open_tag . $thumbnail . '</a>';
	
	the_excerpt();
	
	echo '<div class="link">' . $link_open_tag . 'Erfahren Sie mehr</a></div>';
	echo '</article>' . ( $left ? '' : '</div>' );
}
if ( ! $left ) echo '</div>';

wp_reset_postdata();
endif;
?>
<footer><?php comments_template(); ?></footer>
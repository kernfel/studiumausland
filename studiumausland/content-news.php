<?php
/**
 * @package Studium_Ausland
 * @template News archive
 */

global $post, $fbk;

$tpl = '<a class="nav-%<>% news-%#%" href="' . home_url( '/news/page/%#%' ) . '">';
$older = fbk_get_adjacent_link( $tpl . '« Ältere Einträge</a>', true );
$newer = fbk_get_adjacent_link( $tpl . 'Neuere Einträge »</a>', false );

$archive_nav = ($newer || $older) ? "<nav class='archive-nav'>$older $newer</nav>" : "";

?>
<header>
<div id="social"><a href="<?= esc_url(get_feed_link()) ?>" target="_blank" id="rss" title="Mit RSS abonnieren"></a></div>
<h1>News</h1>
<?= $archive_nav ?>
</header>
<?php if ( $fbk->offers ) : ?>
<section id="offers">
<?php foreach ( $fbk->offers as $offer ) :
	$offer_ = array(
		'start' => fbk_date( get_post_meta( $offer->ID, '_fbk_offer_start', true ), 'j.n.Y' ),
		'end' => fbk_date( get_post_meta( $offer->ID, '_fbk_offer_end', true ), 'j.n.Y' )
	);
?>
	<article id="offer-<?= $offer->ID ?>" class="offer foldout c-<?= get_connect_category( $offer, 'slug' ) ?>">
		<header><h2><?= apply_filters( 'the_title', $offer->post_title ) ?></h2><span class="tag"><?= $offer_['start'] . ' – ' . $offer_['end'] ?></span></header>
		<div class="foldout-outer"><div class="foldout-inner">
			<?= apply_filters( 'the_excerpt', fbk_tt_get_the_excerpt( $offer->ID ) ) ?>
			<a href="<?= get_permalink( $offer->ID ); ?>" class="ln offer-<?= $offer->ID ?>">Mehr über dieses Angebot…</a>
		</div></div>
	</article>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php

for ( $left = true; have_posts(); $left = ! $left ) :
	the_post();
	$link_open_tag = "<a href='" . get_permalink() . "' class='post-$post->ID' title='" . esc_attr(get_the_title()) . "'>";
	if ( $left )
		echo "<div class='double'>";
?>
<article id="post-<?= get_the_ID() ?>" class="post teaser <?= $left ? 'left' : 'right' ?>">
<header>
<?php the_title( '<h2>', '</h2>' ); ?>
<div class="entry-date">Veröffentlicht am <?= get_the_date() ?></div>
</header>
<?php	if ( $thumbnail = fbk_get_first_gallery_image() )
		echo $link_open_tag . $thumbnail . '</a>';
?>
<?php the_excerpt(); ?>
<div class="link"><?= $link_open_tag ?>Ganzen Artikel ansehen</a></div>
</article>
<?php
	if ( ! $left )
		echo "</div>";
endfor;

if ( ! $left )
	echo "</div>";

?>
<footer><?= $archive_nav ?></footer>
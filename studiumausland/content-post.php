<?php
/**
 * @package Studium_Ausland
 */

$tpl = '<a href="%_%" class="post-%#% nav-%<>%">';
$prev = fbk_get_adjacent_link( $tpl . '« %t%</a>', true );
$next = fbk_get_adjacent_link( $tpl . '%t% »</a>', false );

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
		"Hallo!\n\nIch bin auf der Webseite von Studium Ausland (" . home_url() . ") auf diese interessante News gestoßen. Wäre das nicht etwas für dich?\n\n"
		. $permalink . "\n\nLiebe Grüße, "
	)) ?>"></a>
	<a id="rss" title="Mit RSS abonnieren" href="<?= esc_url(get_feed_link()) ?>" target="_blank"></a>
</div>
<h1><?= $title ?></h1>
<div class="entry-date">Veröffentlicht am <?php the_date() ?></div>
</header>
<?php the_content(); ?>
<footer>
<?php if ( $prev || $next ) echo "<nav class='archive-nav'>$prev $next</nav>"; ?>
<?php comments_template(); ?>
</footer>
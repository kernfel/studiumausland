<?php
/**
 * @package Studium_Ausland
 * @role Search Results display
 */
?>
<div id="searchresults">
<?php

global $wp_query;
$searchterms = @implode( ' ', $wp_query->get('search_terms') );

if ( have_posts() ) : ?>
<h2><?= $wp_query->found_posts ?> Resultat<?= 1 == $wp_query->found_posts ? '' : 'e' ?></h2>
<nav class="pagination"><?php echo $pagination = paginate_links(
	array(
		'total' => $wp_query->max_num_pages,
		'current' => $wp_query->get( 'paged' ) ? $wp_query->get('paged') : 1,
		'base' => home_url( '%_%' ),
		'format' => '?paged=%#%',
		'add_args' => array_diff_key( $wp_query->query, array('paged' => '') ),
	)
); ?></nav>
<?php	for ( $left = true; have_posts(); $left = ! $left ) :
		the_post();
		$cat = $p_class = '';
		if ( 'school' == $post->post_type ) {
			$cat = get_the_category();
			if ( $cat )
				$cat = $cat[0];
			$p_class = "class='school-$post->ID'";
		} elseif ( 'post' == $post->post_type ) {
			$p_class = "class='post-$post->ID'";
		} elseif ( 'offer' == $post->post_type ) {
			$cat = get_connect_category( $post );
			$p_class= "class='offer-$post->ID'";
		}

		if ( $cat )
			$cat = 'c-' . $cat->slug;

		if ( $left ) : ?>
<div class="double">
	<article class="left teaser <?= $cat ?>"><header>
<?php		else : ?>
	<article class="right teaser <?= $cat ?>"><header>
<?php		endif;
		
		the_title( '<h2>', '</h2>' );

		$link_open_tag = "<a href='" . get_permalink() . "' title='" . esc_attr(get_the_title()) . "' $p_class>";

		if ( 'post' == $post->post_type )
			echo "<div class='entry-date'>Veröffentlicht am " . get_the_date() . "</div>";
		elseif ( 'offer' == $post->post_type ) {
			foreach ( array( 'start', 'end' ) as $se )
				$offer_[$se] = get_post_meta( $post->ID, '_fbk_offer_'.$se, true );
			echo "<div class='runtime" . ( strtotime( $offer_['end'] ) < time() ? " expired" : "" ) . "'><span class='runtime-date'>"
			. fbk_date( $offer_['start'] ) . "</span> bis <span class='runtime-date'>"
			. fbk_date( $offer_['end'] ) . "</span></div>";
		} elseif ( 'school' == $post->post_type ) {
			$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
			foreach ( wp_get_object_terms( $post->ID, array_keys( $terms ) ) as $term )
				$terms[$term->taxonomy] = $term;
			$keywords = array();
			foreach ( $terms as $term )
				if ( $term )
					$keywords[] = $term->name;
			echo "<div class='entry-meta'>", implode( ' | ', $keywords ), "</div>";
		}

		echo "</header>";
		
		if ( $thumbnail = fbk_get_first_gallery_image() )
			echo $link_open_tag . $thumbnail . '</a>';
		
		the_excerpt();
?>
		<div class="link"><?= $link_open_tag ?>Anzeigen</a></div>
	</article>
<?php		if ( ! $left ) : ?>
</div>
<?php		endif;
	endfor;
	if ( ! $left ) : ?>
</div>
<?php	endif; ?>
<nav class="pagination"><?= $pagination ?></nav>
<?php else : ?>
<h2>Ihre Suche nach "<?= $searchterms ?>" hat keine Ergebnisse gebracht.</h2>
<p>Das tut uns Leid. Vielleicht klappt's mit einem verwandten Begriff!</p>
<?php endif; ?>
<?php unset( $searchterms ); ?>
</div>
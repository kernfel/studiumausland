<?php
/**
 * @package Studium_Ausland
 */

global $fbk_query, $fbk_cache, $post;

if ( ! $fbk_cache->get_e( 'loc-' . $fbk_query->category->term_id, $fbk_query->term->term_id ) ) :
$fbk_cache->rec();

$country = get_term( $fbk_query->term->parent, 'loc' );

?><!--{<?= html_entity_decode( fbk_the_title( false ), ENT_COMPAT, 'UTF-8' ) ?>}-->
<h1><?= fbk_get_category_meta( 'city_h1' ) ?>
 <a href="<?= get_term_link( $country ) ?>" class="cat-ext loc-<?= $country->term_id ?>"><?= $country->name ?></a>
 <a href="<?= get_term_link( $fbk_query->category ) ?>" class="cat-ext cat-<?= $fbk_query->category->term_id ?>"><?= apply_filters( 'the_title', $fbk_query->category->name ) ?>,</a>
</h1>
<?php

if ( $fbk_query->has_page() ) {
	echo apply_filters( 'the_content', $fbk_query->page->post_content );

	$city_img_atts = get_posts( array(	'post_type' => 'attachment',
					'post_parent' => $fbk_query->page->ID,
					'post_status' => 'inherit',
					'post_mime_type' => 'image',
					'orderby' => 'menu_order ID',
					'numberposts' => 1
	));
	if ( $city_img_atts )
		foreach ( $city_img_atts as $img )
			echo '<div class="head">', wp_get_attachment_image( $img->ID, 'medium', false, array( 'class' => '' ) ), '</div>';
}
?>
<h2><?= fbk_get_category_meta( 'city_h2' ) ?></h2>
<?php

$languages = $fbk_query->get_languages();
usort( $languages, '_fbk_usort_by_name' );

fbk_get_images( array_keys( $fbk_query->schools ), false, true ); // Prime the cache

foreach ( $languages as $lang ) :
	$catmeta_args = array( 'lang' => $lang );

	if ( count( $languages ) > 1 || 'yes' == fbk_get_category_meta( 'city_always_use_h3' ) )
		echo '<h3>' . fbk_get_category_meta( 'city_h3', $catmeta_args ) . '</h3>';

	if ( count( $languages ) > 1 )
		$restrict_schools = array( 'language' => $lang->term_id );
	else
		$restrict_schools = array();

	$fbk_query->get_loop( $restrict_schools );
	for ( $left = true; have_posts(); $left = ! $left ) :
		the_post();
		$catmeta_args['school'] = $post;

		if ( $left ) : ?>
<div class="double">
	<article class="left teaser">
<?php		else : ?>
	<article class="right teaser">
<?php		endif;
		
		the_title( '<h4>', '</h4>' );
		$link_open_tag = '<a href="' . get_permalink() . '" class="school-' . get_the_ID() . '" title="'
		 . fbk_get_category_meta( 'city_schoolbox_more_attr-title', $catmeta_args ) . '">';

		if ( $thumbnail = fbk_get_first_gallery_image() )
			echo $link_open_tag . $thumbnail . '</a>';
		
		the_excerpt();
?>
		<div class="link">
			<?= $link_open_tag . fbk_get_category_meta( 'city_schoolbox_more_text', $catmeta_args ) ?></a>
		</div>
	</article>
<?php		if ( ! $left ) : ?>
</div>
<?php		endif;
	endfor;
	if ( ! $left ) : ?>
</div>
<?php	endif;
endforeach;

$fbk_cache->done( 'loc-' . $fbk_query->category->term_id, $fbk_query->term->term_id );
endif;
?>
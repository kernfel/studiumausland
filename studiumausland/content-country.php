<?php
/**
 * @package Studium_Ausland
 */

global $fbk_query, $fbk, $fbk_cache, $wpdb;

if ( ! $fbk_cache->get_e( 'loc-' . $fbk_query->category->term_id, $fbk_query->term->term_id ) ) :
$fbk_cache->rec();

?><!--{<?= html_entity_decode( fbk_the_title( false ), ENT_COMPAT, 'UTF-8' ) ?>}-->
<h1><?= fbk_get_category_meta( 'country_h1' ) ?>
 <a href="<?= get_term_link( $fbk_query->category ) ?>" class="cat-ext cat-<?= $fbk_query->category->term_id ?>"><?= apply_filters( 'the_title', $fbk_query->category->name ) ?></a>
</h1>
<?php

if ( $fbk_query->has_page() )
	echo apply_filters( 'the_content', $fbk_query->page->post_content ); ?>
<h2><?= fbk_get_category_meta( 'country_h2' ) ?></h2>
<?php

// Get all cities and schools and prime the cache
$_all_cities = $fbk_query->get_cities();
foreach ( $_all_cities as $city )
	$all_cities[$city->term_id] = $city;
$query = "
select tt.term_id, p.*
from $wpdb->term_taxonomy tt
join $wpdb->term_relationships tr on tr.term_taxonomy_id = tt.term_taxonomy_id
join $wpdb->posts p on p.ID = tr.object_id
where p.post_type = 'desc' and tt.taxonomy = 'loc' and tt.term_id in (" . implode( ',', array_keys($all_cities) ) . ")
group by tt.term_id
";
$city_desc_termids = $wpdb->get_col( $query, 0 );
if ( $city_desc_termids ) {
	$city_descs = array_combine( $city_desc_termids, $wpdb->last_result );
	foreach ( $city_descs as $term_id => $desc ) {
		unset( $desc->term_id );
		$city_descs[$term_id] = $desc;
		$desc_ids[] = $desc->ID;
	}
	update_post_caches( $city_descs, 'desc', false, true );
	fbk_get_images( $desc_ids, false, true ); // Prime the image cache
} else {
	$city_descs = array();
}


$languages = $fbk_query->get_languages();
usort( $languages, '_fbk_usort_by_name' );
foreach ( $languages as $lang ) :
	$catmeta_args = array( 'lang' => $lang );

	if ( count( $languages ) > 1 || 'yes' == fbk_get_category_meta( 'country_always_use_h3' ) )
		echo "<h3>" . fbk_get_category_meta( 'country_h3', $catmeta_args ) . "</h3>";

	$cities = $fbk_query->get_cities( $lang );
	usort( $cities, '_fbk_usort_by_name' );

	$left = true;
	foreach ( $cities as $city ) :
		$catmeta_args['city'] = $city;

		if ( $left )
			echo "<div class='double'>";
		echo "<article class='teaser " . ( $left ? 'left' : 'right' ) . "' id='$city->slug'>";
		echo "<h4>" . fbk_get_category_meta( 'country_citybox_heading', $catmeta_args ) . "</h4>";

		$link_open_tag = "<a href='" . get_term_link( $city, 'loc' ) . "' class='loc-$city->term_id' title='"
		 . fbk_get_category_meta( 'country_citybox_more_attr-title', $catmeta_args ) . "'>";

		if ( isset($city_descs[$city->term_id]) ) {
			if ( $thumbnail = fbk_get_first_gallery_image( $city_descs[$city->term_id]->ID ) )
				echo $link_open_tag . $thumbnail . '</a>';
			echo apply_filters( 'the_excerpt', fbk_tt_get_the_excerpt( $city_descs[$city->term_id] ) );
		}

		$schools = $fbk_query->get_schools( array('city' => $city->term_id, 'language' => $lang->term_id) );

		echo "<ul class='minimenu'>";
		foreach ( $schools as $school ) {
			$catmeta_args['school'] = $school;
			echo "<li><a href='" . get_post_permalink( $school ) . "' class='school-$school->ID' title='"
			 . fbk_get_category_meta( 'country_citybox_entry_attr-title', $catmeta_args ) . "'>"
			  . fbk_get_category_meta( 'country_citybox_entry_text', $catmeta_args )
			. "</a></li>";
		}
		echo "</ul>";
		unset( $catmeta_args['school'] );

		echo "<div class='link'>" . $link_open_tag . fbk_get_category_meta( 'country_citybox_more_text', $catmeta_args ) . "</a></div></article>";
		
		if ( ! $left )
			echo "</div>";

		$left = ! $left;
	endforeach;
	if ( ! $left )
		echo "</div>";
endforeach;

$fbk_cache->done( 'loc-' . $fbk_query->category->term_id, $fbk_query->term->term_id );
endif;
?>
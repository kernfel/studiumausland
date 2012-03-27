<?php
/**
 * @package Studium_Ausland
 */

global $fbk_query, $fbk, $fbk_cache;

if ( ! $fbk_cache->get_e( 'cat', $fbk_query->term->term_id ) ) :
	$fbk_cache->rec();

?><!--{<?= html_entity_decode( fbk_the_title( false ), ENT_COMPAT, 'UTF-8' ) ?>}-->
<h1><?= fbk_get_category_meta( 'category_h1' ) ?></h1>
<?php if ( $fbk_query->has_page() )
	echo apply_filters( 'the_content', $fbk_query->page->post_content );
?>
<h2><?= fbk_get_category_meta( 'category_h2' ) ?></h2>
<?php
	$terms = $lang_to_ctry = array();
	foreach ( get_terms( array( 'lang', 'loc' ), array( 'get' => 'all' ) ) as $term )
		$terms[$term->term_id] = $term;
	$pattern = "/^" . $fbk_query->term->term_id . "-(\\d+)-(\\d+)-/";
	foreach ( get_option( 'fbk_menu_cache' ) as $tuple ) {
		if ( preg_match( $pattern, $tuple, $matches ) ) {
			$lang_to_ctry[ $matches[1] ][ $matches[2] ] = $terms[$matches[2]];
		}
	}
	// Sort by number of entries, descending
	uasort( $lang_to_ctry, create_function( '$a,$b', 'return -strcmp( count($a), count($b) );' ) );

	if ( ! empty($lang_to_ctry) ) {

		$left = false;
		foreach ( $lang_to_ctry as $lang_id => $countries ) {
			$left = ! $left;
			if ( $left )
				echo "<div class='double'>";
			echo "<article class='compact teaser " . ( $left ? "left" : "right" ) . "'>",
			"<h3>" . fbk_get_category_meta( 'category_langbox_heading', $catmeta_args = array( 'lang' => $terms[$lang_id] ) ) . "</h3>";

			echo "<ul class='minimenu'>";
			foreach ( $countries as $country ) {
				$catmeta_args['country'] = $country;
				echo "<li><a href='" . get_term_link( $country, 'loc' ) . "' class='loc-$country->term_id' title='"
				 . fbk_get_category_meta( 'category_langbox_entry_attr-title', $catmeta_args ) . "'>"
				  . fbk_get_category_meta( 'category_langbox_entry_text', $catmeta_args )
				. "</a></li>";
			}
			echo "</ul></article>";
			if ( ! $left )
				echo "</div>";
		}
		if ( $left )
			echo "</div>";
	} else { ?>
<p>In dieser Kategorie haben wir zurzeit noch keine Programme online. Das ändert sich aber bestimmt bald &ndash;
 <a href="/kontakt">fragen Sie doch gleich direkt bei uns nach</a> oder kommen Sie in ein paar Tagen erneut auf diese Seite.</p>
<?php
	}

	$fbk_cache->done( 'cat', $fbk_query->term->term_id );
endif;
?>
<?php
/**
 * @package Studium_Ausland
 */

add_action( 'wp_update_nav_menu', 'fbk_check_lang_order' );
function fbk_check_lang_order( $menu_id ) {
	$locations = get_nav_menu_locations();
	if ( isset( $locations[ FBK_LANG_ORDER_MENU ] ) && $locations[ FBK_LANG_ORDER_MENU ] == $menu_id ) {
		global $fbk, $fbk_cache;
		foreach ( $fbk->cats as $cat )
			$fbk_cache->delete( 'menu', $cat->term_id );
	}
}

add_action( 'update_option_theme_mods_' . get_option('stylesheet'), 'fbk_check_lang_order_assoc', 10, 2 );
function fbk_check_lang_order_assoc( $old_theme_mods, $new_theme_mods ) {
	if ( ( $old_theme_mods['nav_menu_locations'][FBK_LANG_ORDER_MENU] != $new_theme_mods['nav_menu_locations'][FBK_LANG_ORDER_MENU] ) ) {
		global $fbk, $fbk_cache;
		foreach ( $fbk->cats as $cat )
			$fbk_cache->delete( 'menu', $cat->term_id );
	}
}

add_action( 'save_post', 'fbk_rebuild_navmenus' );
function fbk_rebuild_navmenus( $post_id = false ) {
	if ( $post_id ) {
		$post =& get_post( $post_id );
		if ( ! $post || 'school' != $post->post_type )
			return;
	} else {
		delete_option( 'fbk_menu_cache' );
	}

	global $fbk, $fbk_cache, $wpdb;

	$_cat_ids = array();
	foreach ( $fbk->cats as $cat )
		$_cat_ids[] = $cat->term_id;
	$cat_ids = implode( ',', $_cat_ids );
	unset( $_cat_ids );

	$q = "
		SELECT
			p.ID AS school_id, p.post_name AS school_slug, p.post_title AS school_title,
			cat.term_id AS cat_id,
			lang.term_id AS lang_id,
			ctry.term_id AS ctry_id,
			loc.term_id AS loc_id
		
		FROM $wpdb->posts AS p
		
		JOIN $wpdb->term_relationships AS rel_cat ON p.ID = rel_cat.object_id
		JOIN $wpdb->term_relationships AS rel_lang ON p.ID = rel_lang.object_id
		JOIN $wpdb->term_relationships AS rel_loc ON p.ID = rel_loc.object_id
		
		JOIN $wpdb->term_taxonomy AS cat ON rel_cat.term_taxonomy_id = cat.term_taxonomy_id
		JOIN $wpdb->term_taxonomy AS lang ON rel_lang.term_taxonomy_id = lang.term_taxonomy_id
		JOIN $wpdb->term_taxonomy AS loc ON rel_loc.term_taxonomy_id = loc.term_taxonomy_id
		JOIN $wpdb->term_taxonomy AS ctry ON loc.parent = ctry.term_id
		
		JOIN $wpdb->terms AS t_ctry ON ctry.term_id = t_ctry.term_id
		JOIN $wpdb->terms AS t_loc ON loc.term_id = t_loc.term_id
		
		WHERE
			p.post_type = 'school' AND p.post_status = 'publish'
			AND cat.taxonomy = 'category' AND cat.term_id IN ( $cat_ids )
			AND lang.taxonomy = 'lang'
			AND loc.taxonomy = 'loc'
			AND ctry.taxonomy = 'loc'
		
		ORDER BY cat_id, lang_id, t_ctry.slug, t_loc.slug, school_title
	";

	$flat_alldata = $wpdb->get_results( $q );
	$tree = $stale_cache = $flat_reduced = array();
	$old = get_option( 'fbk_menu_cache' );
	if ( empty($old) )
		$old = array();

	foreach ( $flat_alldata as $item ) {
		$reduced_post_item = (object) array(
			'ID' => $item->school_id,
			'post_title' => $item->school_title,
			'post_name' => $item->school_slug
		);
		$tree[ $item->cat_id ][ $item->lang_id ][ $item->ctry_id ][ $item->loc_id ][] = $reduced_post_item;
		$flat_reduced[] = "$item->cat_id-$item->lang_id-$item->ctry_id-$item->loc_id-$item->school_id";
	}

	$alterations = array_merge( array_diff( $old, $flat_reduced ), array_diff( $flat_reduced, $old ) );
	if ( empty($alterations) )
		return;

	$pattern = "/^(?P<cat_id>\\d+)-(?P<lang_id>\\d+)-(?P<ctry_id>\\d+)-(?P<loc_id>\\d+)-(?P<school_id>\\d+)$/";
	foreach ( $alterations as $tuple ) {
		preg_match( $pattern, $tuple, $matches );
		$stale_cache['cat'][] = $matches['cat_id'];
		$stale_cache['loc'][] = array( $matches['cat_id'], $matches['ctry_id'] );
		$stale_cache['loc'][] = array( $matches['cat_id'], $matches['loc_id'] );
	}

	foreach ( $stale_cache['loc'] as $cat_loc ) {
		$fbk_cache->delete( 'loc-' . $cat_loc[0], $cat_loc[1] );
	}

	foreach ( array_unique( $stale_cache['cat'] ) as $cat_id ) {
		$fbk_cache->delete( 'cat', $cat_id );
		$menu = fbk_generate_menu( $tree[$cat_id], $cat_id );
		$fbk_cache->add( 'menu', $cat_id, $menu );
	}

	update_option( 'fbk_menu_cache', $flat_reduced );
	$fbk_cache->delete( 'footer' );
}

function fbk_get_menu( $cat_id, $force_regenerate = false ) {
	global $wpdb, $fbk_cache;
	if ( ! $force_regenerate && $menu = $fbk_cache->get( 'menu', $cat_id ) )
		return fbk_add_context_to_menu( $menu );
	$cat_id = (int) $cat_id;

	$q = "
		SELECT
			p.ID AS school_id, p.post_name AS school_slug, p.post_title AS school_title,
			lang.term_id AS lang_id,
			ctry.term_id AS ctry_id,
			loc.term_id AS loc_id

		FROM $wpdb->posts AS p

		JOIN $wpdb->term_relationships AS rel_cat ON p.ID = rel_cat.object_id
		JOIN $wpdb->term_taxonomy AS cat ON rel_cat.term_taxonomy_id = cat.term_taxonomy_id

		JOIN $wpdb->term_relationships AS rel_lang ON p.ID = rel_lang.object_id
		JOIN $wpdb->term_taxonomy AS lang ON rel_lang.term_taxonomy_id = lang.term_taxonomy_id

		JOIN $wpdb->term_relationships AS rel_loc ON p.ID = rel_loc.object_id
		JOIN $wpdb->term_taxonomy AS loc ON rel_loc.term_taxonomy_id = loc.term_taxonomy_id
		JOIN $wpdb->term_taxonomy AS ctry ON loc.parent = ctry.term_id

		JOIN $wpdb->terms AS t_loc ON loc.term_id = t_loc.term_id
		JOIN $wpdb->terms AS t_ctry ON ctry.term_id = t_ctry.term_id

		WHERE
			p.post_type = 'school' AND p.post_status = 'publish'
			AND cat.taxonomy = 'category' AND cat.term_id = $cat_id
			AND lang.taxonomy = 'lang'
			AND loc.taxonomy = 'loc'
			AND ctry.taxonomy = 'loc'

		ORDER BY lang_id, t_ctry.slug, t_loc.slug, school_title
	";
	foreach ( $wpdb->get_results( $q ) as $item ) {
		$reduced_post_item = (object) array(
			'ID' => $item->school_id,
			'post_title' => $item->school_title,
			'post_name' => $item->school_slug
		);
		$tree[ $item->lang_id ][ $item->ctry_id ][ $item->loc_id ][] = $reduced_post_item;
	}
	$menu = fbk_generate_menu( $tree, $cat_id );
	$fbk_cache->add( 'menu', $cat_id, $menu );
	return fbk_add_context_to_menu( $menu );
}

function fbk_add_context_to_menu( $menu ) {
	if ( is_admin() )
		return $menu;

	global $fbk_query, $post;

	if ( is_singular( 'school' ) ) {
		$menu = str_replace( $i = "menu-item-school-$post->ID ", "current-menu-item " . $i, $menu );
		$_ancestors = wp_get_object_terms( $post->ID, array( 'lang', 'loc' ) );
		foreach ( $_ancestors as $_ancestor ) {
			if ( 'lang' == $_ancestor->taxonomy )
				$menu = str_replace( $i = "menu-item-lang-$_ancestor->term_id ", "current-menu-ancestor " . $i, $menu );
			elseif ( 'loc' == $_ancestor->taxonomy ) {
				$menu = str_replace( $i = "menu-item-loc-$_ancestor->term_id ", "current-menu-ancestor " . $i, $menu );
				$menu = str_replace( $i = "menu-item-loc-$_ancestor->parent ", "current-menu-ancestor " . $i, $menu );
			}
		}
	} elseif ( 'loc' == $fbk_query->taxonomy ) {
		$menu = str_replace( $i = "menu-item-loc-{$fbk_query->term->term_id} ", "current-menu-item " . $i, $menu );
		if ( 'city' == $fbk_query->detailed_taxonomy ) {
			$menu = str_replace( $i = "menu-item-loc-{$fbk_query->term->parent} ", "current-menu-ancestor " . $i, $menu );
			$pattern = "/^" . $fbk_query->category->term_id . "-(\\d+)-\\d+-" . $fbk_query->term->term_id . "-/";
		} else {
			$pattern = "/^" . $fbk_query->category->term_id . "-(\\d+)-" . $fbk_query->term->term_id . "-/";
		}
		$langs = array();
		foreach ( get_option( 'fbk_menu_cache' ) as $tuple )
			if ( preg_match( $pattern, $tuple, $matches ) )
				$langs[$matches[1]] = true;
		foreach ( array_keys( $langs ) as $lang )
			$menu = str_replace( $i = "menu-item-lang-$lang ", "current-menu-ancestor " . $i, $menu );
	}

	return $menu;
}

function fbk_generate_menu( $tree, $cat_id, $args = array() ) {
	$defaults = array(
		'container' => 'nav',
		'container_id' => 'navi',
		'include_title' => true,
		'title_tag' => 'h1',
		'menu_class' => "menu menu-cat-$cat_id",
		'depth' => 4,
		'lang_sort' => '_fbk_lang_sort_cb'
	);
	extract(wp_parse_args( $args, $defaults ));
	$depth = (int) $depth;

	$_terms = get_terms( array( 'category', 'lang', 'loc' ), array( 'get' => 'all' ) );
	$terms = array();
	foreach ( $_terms as $term )
		$terms[ $term->term_id ] = $term;

	$menu = "";
	if ( $container )
		$menu = "<$container id='$container_id'>\n";
	if ( $include_title )
		$menu .= "<$title_tag>"
		 . fbk_get_category_meta( 'menu_heading', array( 'category' => $terms[$cat_id], 'no_defaults' => true ) )
		 . "</$title_tag>\n";
	$menu .= "<ul class='$menu_class'>\n";
	$cat_base = home_url( '/' ) . $terms[$cat_id]->slug;

	if ( $lang_sort && is_callable( $lang_sort ) ) {
		uksort( $tree, $lang_sort );
	}

	foreach ( $tree as $lang_id => $lang ) {
		$menu .= "\t<li class='menu-item menu-item-level-0 menu-item-lang-$lang_id menu-item-object-lang'>\n"
		 . "\t\t<a href='$cat_base#" . $terms[$lang_id]->slug . "' title='"
		  . fbk_get_category_meta(
			'menu_level_0',
			array(
				'category' => $terms[$cat_id],
				'lang' => $terms[$lang_id],
				'no_defaults' => true
			)
		    )
		  . "'>" . $terms[$lang_id]->name . "</a>\n";

		if ( ! $depth || $depth > 1 ) {
			$menu .= "\t\t<ul class='sub-menu'>\n";
			foreach ( $lang as $ctry_id => $ctry ) {
				$ctry_base = $cat_base . '/' . $terms[$ctry_id]->slug;
				$menu .= "\t\t\t<li class='menu-item menu-item-level-1 menu-item-loc-$ctry_id menu-item-object-loc'>\n"
				 . "\t\t\t\t<a href='$ctry_base' title='"
				  . fbk_get_category_meta(
					'menu_level_1',
					array(
						'category' => $terms[$cat_id],
						'lang' => $terms[$lang_id],
						'country' => $terms[$ctry_id],
						'no_defaults' => true
					)
				    )
				  . "'>" . $terms[$ctry_id]->name . "</a>\n";

				if ( ! $depth || $depth > 2 ) {
					$menu .= "\t\t\t\t<ul class='sub-menu'>\n";
					foreach ( $ctry as $loc_id => $loc ) {
						$loc_base = $ctry_base . '/' . $terms[$loc_id]->slug;
						$menu .= "\t\t\t\t\t<li class='menu-item menu-item-level-2 menu-item-loc-$loc_id menu-item-object-loc'>\n"
						 . "\t\t\t\t\t\t<a href='$loc_base' title='"
						  . fbk_get_category_meta(
							'menu_level_2',
							array(
								'category' => $terms[$cat_id],
								'lang' => $terms[$lang_id],
								'country' => $terms[$ctry_id],
								'city' => $terms[$loc_id],
								'no_defaults' => true
							)
						    )
						  . "'>" . $terms[$loc_id]->name . "</a>\n";

						if ( ! $depth || $depth > 3 ) {
							$menu .= "\t\t\t\t\t\t<ul class='sub-menu'>\n";
							foreach ( $loc as $school ) {
								$menu .= "\t\t\t\t\t\t\t<li class='menu-item menu-item-level-3 menu-item-school-$school->ID menu-item-object-school'>\n"
								. "\t\t\t\t\t\t\t\t<a href='$loc_base/$school->post_name' title='"
								. fbk_get_category_meta(
									'menu_level_3',
									array(
										'category' => $terms[$cat_id],
										'lang' => $terms[$lang_id],
										'country' => $terms[$ctry_id],
										'city' => $terms[$loc_id],
										'school' => $school,
										'no_defaults' => true
									)
								  )
								. "'>$school->post_title</a>\n"
								. "\t\t\t\t\t\t\t</li>\n";
							}
							$menu .= "\t\t\t\t\t\t</ul>\n";
						}
						$menu .= "\t\t\t\t\t</li>\n";
					}
					$menu .= "\t\t\t\t</ul>\n";
				}
				$menu .= "\t\t\t</li>\n";
			}
			$menu .= "\t\t</ul>\n";
		}
		$menu .= "\t</li>\n";
	}
	$menu .= "</ul>\n";
	if ( $container )
		$menu .= "</nav>";

	return $menu;
}

function _fbk_lang_sort_cb( $a, $b ) {
	static $order = false;
	if ( false === $order ) {
		$order = array();
		$locations = get_nav_menu_locations();
		if ( isset( $locations[ FBK_LANG_ORDER_MENU ] ) ) {
			$menu = wp_get_nav_menu_object( $locations[ FBK_LANG_ORDER_MENU ] );
			if ( $menu ) {
				$items = wp_get_nav_menu_items( $menu->term_id );
				foreach ( $items as $item ) {
					if ( 'lang' == $item->object )
						$order[ $item->object_id ] = $item->menu_order;
				}
			}
		}
	}
	if ( empty( $order ) )
		return 0;
	if ( ! array_key_exists( $a, $order ) )
		return 1;
	if ( ! array_key_exists( $b, $order ) )
		return -1;
	return strcmp( $order[$a], $order[$b] );
}
?>
<?php
/**
 * @package Studium_Ausland
 */

function fbk_rw_register_school_metaboxes() {
	remove_meta_box( 'categorydiv', 'school', 'side' );
	remove_meta_box( 'tagsdiv-course_tag', 'school', 'side' );
	add_meta_box( 'categorydiv_school', 'Kategorie', 'fbk_rw_add_taxonomy_metabox', 'school', 'side', 'high', array('category') );
	add_meta_box( 'tagsdiv-lang', 'Sprache', 'fbk_rw_add_taxonomy_metabox', 'school', 'side', 'high', array('lang') );
	add_meta_box( 'locdiv', 'Ort', 'fbk_rw_add_taxonomy_metabox', 'school', 'side', 'high', array('loc') );
	add_meta_box( 'tagsdiv-price_age', 'Preise von...', 'fbk_rw_add_taxonomy_metabox', 'school', 'side', 'high', array('price_age') );
	add_meta_box( 'tagsdiv-currency', 'Währung', 'fbk_rw_add_taxonomy_metabox', 'school', 'side', 'default', array('currency', true) );
	fbk_cf_register_metaboxes();
}

function fbk_rw_register_desc_metaboxes() {
	remove_meta_box( 'tagsdiv-lang', 'desc', 'side' );
	remove_meta_box( 'locdiv', 'desc', 'side' );
	add_meta_box( 'categorydiv', 'Kategorie/Sprache/Ort', 'fbk_rw_add_taxonomy_metabox', 'desc', 'side', 'high', array( 'multitax', array('category', 'lang', 'loc') ) );
}

function fbk_rw_add_taxonomy_metabox( $post, $args ) {
	$type = $args['args'][0];
	$use_empty = empty($args['args'][1]);
	wp_nonce_field( 'fbk-rw-add-'.$type, 'fbk-rw-'.$type.'_nonce' );
	echo "\n<select name='fbk-rw-$type' id='fbk-rw-$type' size='1' style='width: 100%;'>\n";
	if ( $use_empty )
		echo "<option value=''>-- Bitte auswählen --</option>";
	if ( $type == 'multitax' ) {
		foreach ( $args['args'][1] as $tax ) {
			$post_terms = wp_get_object_terms( $post->ID, $tax, array('fields' => 'ids') );
			$tax_obj = get_taxonomy( $tax );
			$tax_label = $tax_obj->label;
			echo "<optgroup label='$tax_label'>" . fbk_rw_get_taxonomy_dropdown_opts( $tax, $post_terms, 'term_id', true, $tax.':'.implode(',', $args['args'][1]).':' ) . "</optgroup>";
		}
	} else {
		$post_terms = wp_get_object_terms( $post->ID, $type, array('fields' => 'ids') );
		if ( 'currency' == $type && empty($post_terms) ) {
			$post_terms = array( get_term_by( 'slug', 'eur', 'currency' )->term_id );
		}
		echo fbk_rw_get_taxonomy_dropdown_opts( $type, $post_terms, 'term_id', ('loc'==$type&&isset($args['args'][1])) ? $args['args'][1] : false );
	}
	echo "</select>";
}

function fbk_rw_get_taxonomy_dropdown_opts( $type, $selection, $field = 'term_id', $flat = false, $value_prefix = '' ) {
	$output = '';
	if ( 'category' == $type ) {
		$terms = get_terms( $type, array('hide_empty' => false, 'hierarchical' => false, 'orderby' => 'name', 'parent' => 0, 'fields' => 'all') );
		foreach ( $terms as $tax ) {
			$selected = in_array( $tax->{$field}, $selection ) ? " selected='selected'" : "";
			$output .= " <option value='$value_prefix$tax->slug'$selected>$tax->name</option>\n";
		}
	} elseif ( 'lang' == $type || 'price_age' == $type || 'currency' == $type ) {
		$terms = get_terms( $type, array('hide_empty' => false, 'orderby' => 'name') );
		foreach ( $terms as $tax ) {
			$selected = in_array( $tax->{$field}, $selection ) ? " selected='selected'" : "";
			$output .= " <option value='$value_prefix$tax->slug'$selected>$tax->name</option>\n";
		}
	} elseif ( 'loc' == $type ) {
		$flat = $flat ? '&nbsp;&ndash;&nbsp;' : '';
		$parents = get_terms( $type, array('hide_empty' => false, 'orderby' => 'name', 'parent' => 0 ) );
		foreach ( $parents as $country ) {
			$children = get_terms( $type, array( 'hide_empty' => false, 'orderby' => 'name', 'parent' => $country->term_id) );
			if ( $flat ) {
				$selected = in_array( $country->{$field}, $selection ) ? " selected='selected'" : "";
				$output .= "<option value='$value_prefix$country->slug'$selected>$country->name</option>\n";
			} else {
				$output .= "<optgroup label='$country->name'>\n";
			}
			foreach ( $children as $city ) {
				$selected = in_array( $city->{$field}, $selection ) ? " selected='selected'" : "";
				$output .= "  <option value='$value_prefix$city->slug'$selected>$flat$city->name</option>\n";
			}
			$output .= $flat ? "" : " </optgroup>";
		}
	}
	return $output;
}

//Add columns to the edit.php screen, right behind the categories column
add_filter( 'manage_posts_columns', 'fbk_rw_add_extracols' );
add_filter( 'manage_pages_columns', 'fbk_rw_add_extracols' );
add_action( 'manage_posts_custom_column', 'fbk_rw_populate_extracol', 10, 2 );
add_action( 'manage_pages_custom_column', 'fbk_rw_populate_extracol', 10, 2 );
function fbk_rw_add_extracols( $columns ) {
	$screen = get_current_screen();
	if ( in_array( $screen->post_type, array( 'school', 'desc' ) ) ) {
		$result = $behind = array();
		$i = 0;
		$has_cat = array_key_exists( 'categories', $columns );
		foreach ( $columns as $key => $value ) {
			$result[$key] = $value;
			$i++;
			if ( $has_cat && $key == 'categories' || ! $has_cat && $key == 'author' ) {
				$behind = array_slice( $columns, $i, null, true );
				break;
			}
		}
		if ( ! $has_cat )
			$result['categories'] = __('Categories');
		foreach ( array( 'lang', 'loc' ) as $tax ) {
			$tax_spec = get_taxonomy( $tax );
			$result[$tax] = $tax_spec->labels->name;
		}

		if ( 'school' == $screen->post_type )
			$result['price_age'] = 'Preise von...';

		$columns = $result + $behind;
	}
	return $columns;
}
function fbk_rw_populate_extracol( $column_name, $post_id ) {
	if ( ! in_array( $column_name, array('lang','loc','category','price_age') ) )
		return;
	if ( 'category' == $column_name )
		$query_tax = 'category_name';
	else
		$query_tax = $column_name;
	$screen = get_current_screen();
	$terms = get_the_terms( $post_id, $column_name );
	$links = array();
	if ( $terms )
		foreach ( $terms as $term ) {
			if ( isset( $screen->parent_file ) && 'price_age' != $column_name )
				$links[] = "<a href='$screen->parent_file&$query_tax=$term->slug'>$term->name</a>";
			else
				$links[] = $term->name;
		}
	echo implode( ', ', $links );
}

add_action( 'restrict_manage_posts', 'fbk_add_editposts_filter' );
function fbk_add_editposts_filter() {
	$screen = get_current_screen();

	if ( 'edit' != $screen->base )
		return;

	if ( 'page' == $screen->post_type )
		$taxes = array( 'cat', 'lang', 'loc' );
	elseif ( 'school' == $screen->post_type )
		$taxes = array( 'lang', 'loc', 'price_age' );
	elseif ( 'desc' == $screen->post_type )
		$taxes = array( 'lang', 'loc' );
	else
		$taxes = array();

	foreach ( $taxes as $tax ) {
		$tax_spec = get_taxonomy( 'cat'==$tax ? 'category' : $tax );
		$sel = empty($GLOBALS[$tax]) ? "" : " selected='selected'";
		echo " <select name='$tax'>";
		echo "<option value=''$sel>" . $tax_spec->labels->all_items . "</option>";
		echo fbk_rw_get_taxonomy_dropdown_opts(
			'cat'==$tax ? 'category' : $tax,
			(array) @$GLOBALS[$tax],
			'slug',
			'loc'==$tax ? true : false
		);
		echo "</select> ";
	}

	$user = wp_get_current_user();
	if ( $user->ID && 'school' == $screen->post_type )
		echo "<span id='persistent-filter'>",
		"<label for='persistent-filter-on'>Filter speichern: ein<input type='radio' id='persistent-filter-on' name='persistent_filter' value='on'",
		( $persistent = 'on' == get_user_meta( $user->ID, '_fbk_persistent_filter', true ) ? " checked" : "" ), "></label> ",
		"<label for='persistent-filter-off'>aus<input type='radio' id='persistent-filter-off' name='persistent_filter' value='off'",
		( $persistent ? "" : " checked" ), "></label> ",
		"<label for='persistent-filter-clear'>zurücksetzen<input type='radio' id='persistent-filter-clear' name='persistent_filter' value='clear'></label>",
		"</span>";
}

if ( is_admin() && ( ! defined('FBK_AJAX') || ! FBK_AJAX ) )
	add_action( 'parse_query', 'fbk_persistent_filter' );
function fbk_persistent_filter( $wp_query ) {
	$screen = get_current_screen();
	$user = wp_get_current_user();
	if ( ! $user || ! $user->ID || ! $screen || 'school' != $screen->post_type || 'edit' != $screen->base )
		return;

	$persistent_filter_qv = $wp_query->get( 'persistent_filter' );
	$use_persistent = 'on' == get_user_meta( $user->ID, '_fbk_persistent_filter', true );
	if ( 'off' == $persistent_filter_qv ) {
		update_user_meta( $user->ID, '_fbk_persistent_filter', 'off' );
		delete_user_meta( $user->ID, '_fbk_saved_filter' );
		return;
	} elseif ( 'clear' == $persistent_filter_qv ) {
		delete_user_meta( $user->ID, '_fbk_saved_filter' );
		$wp_query->parse_query( array( 'post_type' => $wp_query->get( 'post_type' ) ) );
		$update_filter = false;
	} elseif ( 'on' == $persistent_filter_qv ) {
		$update_filter = true;
		update_user_meta( $user->ID, '_fbk_persistent_filter', 'on' );
	} else {
		$update_filter = false;
	}

	if ( $use_persistent ) {
		$saved_filter = get_user_meta( $user->ID, '_fbk_saved_filter', true );
		$filter_changed = false;
		if ( $saved_filter ) {
			foreach ( $saved_filter as $key => $value ) {
				if ( $wp_query->get( $key ) != $value ) {
					if ( $update_filter ) {
						$saved_filter[$key] = $wp_query->get( $key );
						$filter_changed = true;
					} else {
						$wp_query->query_vars[$key] = $value;
					}
				}
			}
		} elseif ( $update_filter ) {
			$saved_filter = $wp_query->query_vars;
			$filter_changed = true;
		}

		if ( $saved_filter ) {
			if ( array_key_exists( 's', $saved_filter ) && ! empty( $saved_filter['s'] ) )
				$_REQUEST['s'] = $saved_filter['s'];

			foreach ( array( 'post_type', 'paged', 'persistent_filter' ) as $untouchable )
				if ( array_key_exists( $untouchable, $saved_filter ) )
					unset( $saved_filter[$untouchable] );

			if ( $filter_changed )
				update_user_meta( $user->ID, '_fbk_saved_filter', $saved_filter );
		}
	}
}

add_action( 'save_page', 'fbk_rw_save_taxonomy_data', 1 );
add_action( 'save_post', 'fbk_rw_save_taxonomy_data', 1 );
function fbk_rw_save_taxonomy_data( $post_id ) {
	if ( wp_is_post_revision( $post_id ) )
		return;
	if ( ! current_user_can( 'edit_posts', $post_id ) ) //_post as per the (default) capability type of the school post type
		return;
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return;
	// =12940=update: Remove check for $_POST['_inline_edit'] after adding custom quickedit boxes
	if ( isset( $_POST['_inline_edit'] ) )
		return;
	if ( empty( $_POST ) )
		return;

	foreach ( array('category', 'lang', 'loc', 'price_age', 'currency') as $tax )
		if ( array_key_exists( 'fbk-rw-'.$tax.'_nonce', $_POST ) && wp_verify_nonce( $_POST['fbk-rw-'.$tax.'_nonce'], 'fbk-rw-add-'.$tax ) )
			wp_set_object_terms( $post_id, $_POST['fbk-rw-'.$tax], $tax, false );

	if ( array_key_exists( 'fbk-rw-multitax_nonce', $_POST ) && wp_verify_nonce( $_POST['fbk-rw-multitax_nonce'], 'fbk-rw-add-multitax' ) ) {
		$set_tax = strtok( $_POST['fbk-rw-multitax'], ':' );
		$multi_taxes = explode( ',', strtok( ':' ) );
		$set_term = strtok( ':' );
		foreach ( $multi_taxes as $tax ) {
			if ( $tax == $set_tax )
				wp_set_object_terms( $post_id, $set_term, $set_tax, false );
			else
				wp_set_object_terms( $post_id, array(), $tax, false );
		}
	}
}

add_action( 'init', 'fbk_rw_build_taxonomies', 0 );
function fbk_rw_build_taxonomies() {
	register_taxonomy(
		'lang',
		array('school', 'desc'),
		array(
			'hierarchical' => false,
			'rewrite' => false,
			'labels' => array(
				'name' => 'Sprachen',
				'singular_name' => 'Sprache',
				'search_items' => 'Nach Sprache suchen',
				'popular_items' => 'Beliebte Sprachen',
				'all_items' => 'Alle Sprachen',
				'edit_item' => 'Sprache bearbeiten',
				'update_item' => 'Sprache aktualisieren',
				'add_new_item' => 'Neue Sprache hinzufügen',
				'new_item_name' => 'Neue Sprache',
				'separate_items_with_commas' => 'Trenne Sprachen mit Kommata',
				'add_or_remove_items' => 'Sprachen hinzufügen oder entfernen',
				'choose_from_most_used' => 'Aus häufig genutzen Sprachen wählen',
				'view_item' => 'Sprache ansehen'
			),
			'update_count_callback' => '_update_post_term_count'
		)
	);
	register_taxonomy(
		'loc',
		array('school', 'desc'),
		array(
			'hierarchical' => true,
			'rewrite' => false,
			'labels' => array(
				'name' => 'Orte',
				'singular_name' => 'Ort',
				'search_items' => 'Nach Ort suchen',
				'all_items' => 'Alle Orte',
				'parent_item' => 'Übergeordnet',
				'parent_item_colon' => 'Übergeordnet:',
				'edit_item' => 'Ort bearbeiten',
				'update_item' => 'Ort aktualisieren',
				'add_new_item' => 'Neuen Ort hinzufügen',
				'new_item_name' => 'Neuer Ort',
				'view_item' => 'Ort ansehen'
			),
			'update_count_callback' => '_update_post_term_count'
		)
	);
	register_taxonomy(
		'course_tag',
		'school',
		array(
			'hierarchical' => false,
			'show_ui' => true,
			'rewrite' => false,
			'labels' => array(
				'name' => 'Kursschlagwörter',
				'singular_name' => 'Kursschlagwort',
				'search_items' => 'Nach Kursschlagwörtern suchen',
				'popular_items' => 'Beliebte Kursschlagwörter',
				'all_items' => 'Alle Kursschlagwörter',
				'edit_item' => 'Kursschlagwort bearbeiten',
				'update_item' => 'Kursschlagwort aktualisieren',
				'add_new_item' => 'Neues Kursschlagwort hinzufügen',
				'new_item_name' => 'Neues Kursschlagwort',
				'separate_items_with_commas' => 'Trenne Kursschlagwörter mit Kommata',
				'add_or_remove_items' => 'Kursschlagwörter hinzufügen oder entfernen',
				'choose_from_most_used' => 'Aus häufig genutzen Kursschlagwörtern wählen',
				'view_item' => 'Kursschlagwort ansehen'
			),
			'update_count_callback' => '' //=todo
		)
	);
	register_taxonomy(
		'price_age',
		array( 'school' ),
		array(
			'hierarchical' => false,
			'rewrite' => false,
			'public' => false,
			'label' => 'Preise von ...'
		)
	);
	register_taxonomy(
		'currency',
		array( 'school' ),
		array(
			'hierarchical' => false,
			'rewrite' => false,
			'public' => false,
			'show_ui' => true,
			'labels' => array(
				'name' => 'Währungen',
				'singular_name' => 'Währung'
			),
			
		)
	);
	// Make sure the necessary terms exist
	$price_ages = get_terms( 'price_age', array( 'fields' => 'names' ) );
	for ( $i = 2011; $i < date('Y') + 2; $i++ )
		if ( ! in_array( $i, $price_ages ) )
			wp_insert_term( $i, 'price_age' );

	global $wp_rewrite;

	if ( ! $regexes = get_option( 'fbk_rewrite_regexes' ) ) {
		$regexes = array(
			'loc' => fbk_rw_get_taxonomy_regex('loc', array('restrict', 'open'), true, 0),
			'cat' => fbk_rw_get_taxonomy_regex('category', 'literal', false, 0)
		);
		update_option( 'fbk_rewrite_regexes', $regexes );
	}

	$wp_rewrite->add_rewrite_tag( '%loc%', $regexes['loc'], 'loc=' );
	$wp_rewrite->add_rewrite_tag( '%fbk_category%', $regexes['cat'], 'category_name=' );
	//Note: Hierarchical taxonomies need to be directory-walked in fbk_rw_sweeprules!
}

add_action( 'currency_pre_add_form', 'fbk_currency_explained' );
function fbk_currency_explained() {
?>
<div class="updated"><p><b>Wichtig:</b> Der "Slug" (=Titelform) einer Währung wird dafür verwendet, den Tageskurs zu berechnen.
Schau dir bitte die Liste auf der <a href="http://www.ecb.int/stats/eurofxref/eurofxref-daily.xml">Seite der Europäischen Zentralbank</a>
an &ndash; stimmt der Slug mit keiner der dort eingetragenen Währungen überein, wird ein Umrechnungsfaktor 1:1 zum Euro angenommen!
(Zwischen Groß-/Kleinschreibung wird nicht unterschieden.)</p></div>
<?php
}

add_action( 'init', 'fbk_rw_add_schooltypes', 1 );
function fbk_rw_add_schooltypes() {
	register_post_type(
		'school',
		array(
			'label' => 'Schulen',
			'labels' => array(
				'name' => 'Schulen',
				'singular_name' => 'Schule',
				'add_new_item' => 'Neue Schule erstellen',
				'edit_item' => 'Schule bearbeiten',
				'new_item' => 'Neue Schule',
				'view_item' => 'Schule ansehen',
				'search_items' => 'Nach Schulen suchen',
				'not_found' => 'Keine Schulen gefunden',
				'not_found_in_trash' => 'Keine Schulen im Papierkorb gefunden'
			),
			'public' => true,
			'menu_position' => 5,
			'supports' => array( 'title', 'editor', 'thumbnail', 'revisions', 'author', FBK_CF_FEATURE, 'comments', 'f_school_connect' ),
			'register_meta_box_cb' => 'fbk_rw_register_school_metaboxes',
			'taxonomies' => array( 'category', 'lang', 'loc', 'course_tag', 'price_age', 'currency' ),
			'has_archive' => false,
			'rewrite' => array(

				'slug' => '%fbk_category%/%loc%',
				'with_front' => false,
				'feeds' => false,
				'pages' => false
			),
			'query_var' => 'school'
		)
	);
	register_post_type(
		'desc',
		array(
			'label' => 'Ortsseiten etc.',
			'labels' => array(
				'singular_name' => 'Beschreibung (Ort, Sprache o. Kategorie)',
				'add_new_item' => 'Neue Ortsseite erstellen',
				'edit_item' => 'Ortsseite bearbeiten',
				'new_item' => 'Neue Ortsseite',
				'view_item' => 'Ortsseite ansehen',
				'search_items' => 'Nach Ortsseiten suchen',
				'not_found' => 'Keine Ortsseiten gefunden',
				'not_found_in_trash' => 'Keine Ortsseiten im Papierkorb gefunden'
			),
			'public' => false,
			'show_ui' => true,
			'menu_position' => 7,
			'supports' => array( 'title', 'editor', 'revisions', 'author' ),
			'register_meta_box_cb' => 'fbk_rw_register_desc_metaboxes',
			'taxonomies' => array( 'category', 'lang', 'loc' ),
			'has_archive' => false,
			'query_var' => false,
			'rewrite' => false
		)
	);
	register_post_type(
		'offer',
		array(
			'label' => 'Angebote',
			'labels' => array(
				'singular_name' => 'Sonderangebot',
				'add_new_item' => 'Neues Sonderangebot erstellen',
				'edit_item' => 'Sonderangebot bearbeiten',
				'new_item' => 'Neues Sonderangebot',
				'view_item' => 'Sonderangebot ansehen',
				'search_items' => 'Nach Sonderangeboten suchen',
				'not_found' => 'Keine Sonderangebote gefunden',
				'not_found_in_trash' => 'Keine Ortsseiten im Papierkorb gefunden'
			),
			'public' => true,
			'menu_position' => 6,
			'supports' => array( 'title', 'editor', 'author', 'f_runtime', 'f_school_connect', 'comments' ),
			'register_meta_box_cb' => 'fbk_register_offer_metaboxes',
			'taxonomies' => array(),
			'permalink_epmask' => EP_NONE,
			'has_archive' => false,
			'rewrite' => array(
				'slug' => 'offer',
				'with_front' => false,
				'feeds' => false,
				'pages' => false
			),
		)
	);
	add_post_type_support( 'revision', FBK_CF_FEATURE );
	add_post_type_support( 'post', 'f_school_connect' );
}

// Currently assumes any taxonomy links to be in the category of global $post. This may lead to problems in case cross-category taxonomy links come up!
// Nav menu links are dealt with differently (see fbk-navmenu.php/fbk_nm_hierarchize_urls), but other links may break.
// Overall, most of this function is a DIRTY hack, but I can't really think of any better way to do things outside of a nav menu.
add_filter( 'term_link', 'fbk_rw_fix_termlinks', 10, 3 );
function fbk_rw_fix_termlinks( $link, $term, $tax ) {
	if ( 'category' == $tax && false !== strpos( $link, '%fbk_category%' ) ) {
		$link = str_replace("%fbk_category%", $term->slug, $link);
	} elseif ( 'category' == $tax && ( ! is_admin() || defined('FBK_AJAX') && FBK_AJAX || ! empty($GLOBALS['fbk_termlink_frontend']) ) ) {
		$link = home_url( '/' . $term->slug );
	} elseif ( 'loc' == $tax ) {
		global $post, $fbk_query;
		if ( ! empty($GLOBALS['fbk_termlink_cat_override']) ) {
			$cat = array( $GLOBALS['fbk_termlink_cat_override'] );
		} elseif ( empty( $fbk_query ) ) {
			if ( empty( $post ) || ! is_admin() )
				return $link;
			elseif ( is_array( $post ) )
				$post_id = $post['ID'];
			else
				$post_id = $post->ID;
			$cat = wp_get_object_terms( $post_id, 'category' );
			if ( 1 != count( $cat ) )
				return $link;
		} else {
			$cat = array( $fbk_query->category );
		}

		if ( $cat[0] ) {
			$path = $cat[0]->slug . '/' . implode( '/', fbk_rw_get_hierarchical_slugs( $term, $tax ) );
			$link = substr_replace( $link, $path, strpos( $link, '?' ) );
		}
	}

	return $link;
}

add_filter( 'post_type_link', 'fbk_rw_filter_schoollink', 0, 2 );
function fbk_rw_filter_schoollink( $link, $post ) {
	if ( 'school' != $post->post_type )
		return $link;

	// These will occasionally be set through FBK_Query::_do_taxes().
	if ( isset( $post->category ) && isset( $post->loc ) && isset( $post->country ) ) {
		$terms = array( 'category' => $post->category, 'loc' => $post->loc, 'country' => $post->country );
	} else {
		$_terms = wp_get_object_terms( $post->ID, array( 'category', 'loc' ) );
		foreach ( $_terms as $term )
			if ( empty( $terms[$term->taxonomy] ) )
				$terms[$term->taxonomy] = $term;
		if ( ! empty($terms['loc']) && $terms['loc']->parent )
			$terms['country'] = get_term( $terms['loc']->parent, 'loc' );
	}

	if ( false !== strpos( $link, '%fbk_category%' ) ) {
		if ( empty($terms['category']) )
			return '<span style="color: #e11">Kein Link verfügbar - bitte kategorisieren!</span>';
		$link = str_replace( '%fbk_category%', $terms['category']->slug, $link );
	}

	if ( false !== strpos( $link, '%loc%' ) ) {
		if ( empty($terms['loc']) || empty($terms['country']) )
			return '<span style="color: #e11">Kein Link verfügbar - bitte Ort zuteilen!</span>';
		$link = str_replace( '%loc%', $terms['country']->slug . '/' . $terms['loc']->slug, $link );
	}
	return $link;
}

// Adapted from wp-includes/taxonomy.php::get_term_link. Helper function for filter_schoollink and fix_catlinks.
function fbk_rw_get_hierarchical_slugs( $term, $taxonomy = 'loc' ) {
	$hierarchical_slugs = array();
	$ancestors = get_ancestors($term->term_id, $taxonomy);
	foreach ( (array)$ancestors as $ancestor ) {
		$ancestor_term = get_term($ancestor, $taxonomy);
		$hierarchical_slugs[] = $ancestor_term->slug;
	}
	$hierarchical_slugs = array_reverse($hierarchical_slugs);
	$hierarchical_slugs[] = $term->slug;
	return $hierarchical_slugs;
}

/*Removing the ()s is a no-go for alternation regexes...
* This filter removes all attachment rewrite rules, however.
* If attachments should ever be needed, add brackets in and change the replace $matches[1] to [2] instead of just dropping them.
* Should any additional taxonomy hierarchies be necessary, these will need to be added as with the loc (country) hierarchy.
*/
add_filter( 'school_rewrite_rules', 'fbk_rw_sweeprules' );
function fbk_rw_sweeprules( $in ) {
	global $wp_rewrite, $wp;

	//Make sure rewrite definitions are up to date - flush doesn't see to that by itself
	$regexes = get_option( 'fbk_rewrite_regexes' );

	$cat_regex_root_noslash = $regexes['cat'];
	$wp_rewrite->add_rewrite_tag('%fbk_category%', $cat_regex_root_noslash, 'category_name=');
	$cat_regex_noparentheses = trim( $cat_regex_root_noslash, '()' );
	$cat_regex_root = $cat_regex_root_noslash . '/';

	$loc_regex_original = $regexes['loc'];
	$wp_rewrite->add_rewrite_tag('%loc%', $loc_regex_original, 'loc=');

	$loc_regex_nohierarchy = fbk_rw_get_taxonomy_regex('loc', 'restrict', false, 0) . '/';
	$additional_rules_template = $wp_rewrite->generate_rewrite_rules( '%fbk_category%', EP_PERMALINK, true, true, false, false );

	$additional_rules = array();
	$additional_rules_added = false;
	foreach ( $additional_rules_template as $regex => $replace ) {
		$replace = str_replace( array('matches[2]','matches[1]'),  array('matches[3]','matches[1]&loc=$matches[2]'), $replace );
		$additional_rules[ str_replace( $cat_regex_root, $cat_regex_root . $loc_regex_nohierarchy, $regex ) ] = $replace;
	}

	$out = array();
	foreach ( $in as $regex => $replace ) {

		if ( 0 === strpos( $regex, '(' ) ) {
			//Add the additional hierarchy-walked rules generated above right below the /category/country/city rules
			if ( 0 !== strpos( $regex, $cat_regex_root . $loc_regex_original ) && ! $additional_rules_added ) {
				$out = $out + $additional_rules;
				$additional_rules_added = true;
			}

			$out[$regex] = $replace;
		} elseif ( 0 === strpos( $regex, $cat_regex_noparentheses ) ) {
			// Fix attachment rules to include parentheses around the cat regex
			$regex = str_replace( $cat_regex_noparentheses, '(?:' . $cat_regex_noparentheses . ')', $regex );
			$out[$regex] = $replace;
		}
	}

	return $out;
}

// Refresh rules on activation/deactivation/taxonomy changes
add_action( 'created_term', 'fbk_rw_flush', 10, 3 );
add_action( 'delete_term', 'fbk_rw_flush', 1, 3 );

add_action( 'edit_term', 'fbk_rw_flush_check_prepare', 10, 3 );
function fbk_rw_flush_check_prepare( $term_id, $tt_id, $taxonomy ) {
	global $fbk_flush_check_terms;
	$fbk_flush_check_terms[ $term_id ] = get_term( $term_id, $taxonomy );
}

add_action( 'edited_term', 'fbk_rw_flush_check' );
function fbk_rw_flush_check( $term_id ) {
	global $fbk_flush_check_terms, $fbk_cache;
	if ( ! empty($fbk_flush_check_terms[$term_id]) ) {
		$old_term = $fbk_flush_check_terms[$term_id];
		$new_term = get_term( $term_id, $old_term->taxonomy );
		if ( $old_term->slug != $new_term->slug || $old_term->parent != $new_term->parent )
			fbk_rw_flush();
		elseif ( $old_term->name != $new_term->name || $old_term->description != $new_term->description )
			$fbk_cache->flush();
		unset( $fbk_flush_check_terms[$term_id] );
	}
}

function fbk_rw_flush( $term_id = 0, $tt_id = 0, $taxonomy = 0 ) {
	if ( ! $taxonomy || 'loc' == $taxonomy || 'category' == $taxonomy ) {
		global $wp_rewrite, $fbk;
		wp_cache_flush();

		$regexes = array(
			'loc' => fbk_rw_get_taxonomy_regex('loc', array('restrict', 'open'), true, 0),
			'cat' => fbk_rw_get_taxonomy_regex('category', 'literal', false, 0)
		);
		update_option( 'fbk_rewrite_regexes', $regexes );

		$wp_rewrite->flush_rules();

		fbk_rebuild_navmenus();
	}
}


//For lack of a set of theme activation/deactivation hooks;
//see http://www.krishnakantsharma.com/2011/01/activationdeactivation-hook-for-wordpress-theme/
fbk_rw_activate();
add_action( 'switch_theme', 'fbk_rw_deactivate' );
function fbk_rw_activate() {
	if ( get_option( 'fbk_theme_active' ) )
		return;
	update_option( 'fbk_theme_active', 1 );
	global $wp_rewrite;
	if ( !is_object($wp_rewrite) ) {
		add_action( 'init', 'fbk_rw_flush', 20 );
		return;
	}
	fbk_rw_build_taxonomies();
	fbk_rw_add_schooltypes();
	fbk_rw_flush();
}
function fbk_rw_deactivate() {
	delete_option( 'fbk_theme_active' );
	remove_filter('school_rewrite_rules', 'fbk_rw_sweeprules');
	global $wp_rewrite;
	unset( $wp_rewrite->extra_permastructs['loc'] );
	unset( $wp_rewrite->extra_permastructs['lang'] );
	$wp_rewrite->extra_permastructs['category'][0] = 'category/%category%/';
	fbk_rw_flush();
}

/** function fbk_rw_get_taxonomy_regex  --  Helper function for fbk_rw_build_taxonomies()
* $regex_type:
*	literal: categoryname|categoryname|categoryname
*	restrict: [a-z0-9-]{min,max} or [a-z0-9-]{len} as the case may be
*	open: [^/]+
*	Note: Pass $regex_type as an array to define the type over hierarchy levels, top to bottom. If more levels are found, the last type is inherited.
* Things to consider:
* - This function is mainly designed for dynamic literal and, to an extent, restrict regex types.
* - Hierarchical will consider only the first subcategory it can find on each level. Careful what you ask for
*	(literal will break completely, restrict MAY work, depending on the actual subcategory slugs.)
**/
function fbk_rw_get_taxonomy_regex( $tax, $regex_type = 'literal', $hierarchical = false, $parent = 0 ) {
	@$rtype = array_shift($regex_type);
	if ( ! isset($rtype) )
		$rtype = $regex_type;
	if ( empty($regex_type) )
		$regex_type = $rtype;
	$categories = get_terms( $tax, array('hide_empty' => false, 'parent' => $parent) );
	if ( ! $categories )	
		return false;
	$slugs = array();
	$lengths = array();
	foreach ( $categories as $cat ) {
		$slugs[] = $cat->slug;
		$lengths[strlen($cat->slug)] = true;
		$cat_ids[] = $cat->term_id;
	}
	if ( 'literal' == $rtype ) {
		$out = implode( '|', $slugs );
	} elseif ( 'restrict' == $rtype ) {
		$i = count($lengths);
		if ( count($lengths) == 0 ) {
			$out = '[^/]+';
		} elseif ( count($lengths) == 1 ) {
			$len = array_search( true, $lengths );
			$out = '[a-z0-9-]{' . $len . '}';
		} else {
			ksort( $lengths );
			$lenmin = array_search( true, $lengths );
			$lengths = array_reverse( $lengths, true );
			$lenmax = array_search( true, $lengths );
			$out = '[a-z0-9-]{' . $lenmin . ',' . $lenmax . '}';
		}
	} else {
		$out = '[^/]+';
	}
	if ( $hierarchical ) {
		$subcat_found = false;
		foreach( $cat_ids as $cat_id ) {
			$subcat_out = fbk_rw_get_taxonomy_regex( $tax, $regex_type, true, $cat_id );
			if ( false !== $subcat_out ) {
				$out = $out . "/" . $subcat_out;
				$subcat_found = true;
				break;
			}
		}
		if ( $subcat_found )
			return $out;
	}
	return "($out)";
}
?>
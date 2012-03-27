<?php
/**
 * @package Studium_Ausland
 */

// Term metas are not built into the core. Thank you to Jacob M. Goldman for a wonderful little plugin that enables this feature!
require( get_stylesheet_directory() . '/lib/simple-term-meta.php' );

add_filter( 'manage_edit-category_columns', 'fbk_catmeta_add_order_column', 20, 1 );
function fbk_catmeta_add_order_column( $columns ) {
	$tax = get_taxonomy( 'category' );
	if ( current_user_can( $tax->cap->edit_terms ) ) {
		$columns['fbk_use_cat'] = 'Verwenden';
		$columns['fbk_category_order'] = 'Reihenfolge'
		. ' <input type="submit" class="button button-primary" value="Speichern" style="margin:0;padding:3px 10px;">'
		. '<input type="hidden" name="fbk_action" value="save_order">'
		. wp_nonce_field( 'save_category_order', 'fbk_nonce', true, false );
	}
	return $columns;
}

add_filter( 'manage_category_custom_column', 'fbk_catmeta_order_column', 10, 3 );
function fbk_catmeta_order_column( $output, $column_name, $term_id ) {
	$tax = get_taxonomy( 'category' );
	if ( ! current_user_can( $tax->cap->edit_terms ) )
		return $output;

	if ( 'fbk_category_order' == $column_name ) {
		$output = '<input type="text" class="small-text" name="fbk_category_order[' . $term_id . ']" value="'
		. get_term_meta( $term_id, 'fbk_category_order', true ) . '">';
	} elseif ( 'fbk_use_cat' == $column_name ) {
		$output = '<input type="checkbox" name="fbk_use_cat[' . $term_id . ']" value="yes"'
		. ( 'yes' == get_term_meta( $term_id, 'fbk_use_cat', true ) ? ' checked="checked"' : '' ) . '>'
		. '<input type="hidden" name="fbk_use_cat_cb[]" value="' . $term_id . '">';
	}
	return $output;
}

add_action( 'admin_init', 'fbk_catmeta_save_category_order' );
function fbk_catmeta_save_category_order() {
	global $taxnow, $fbk, $fbk_cache;
	if (
	    ! $taxnow
	 || 'category' != $taxnow
	 || ! isset($_REQUEST['fbk_action'])
	 || ! 'save_order' == $_REQUEST['fbk_action']
	 || ! check_admin_referer( 'save_category_order', 'fbk_nonce' )
	)
		return;

	$tax = get_taxonomy( $taxnow );
	if ( ! current_user_can( $tax->cap->edit_terms ) )
		return;

	foreach ( $_REQUEST['fbk_category_order'] as $term_id => $order ) {
		$order = (int) $order;
		if ( ! $order )
			continue;

		$term =& get_term( $term_id, 'category' );
		if ( ! $term || is_wp_error( $term ) )
			continue;

		if ( get_term_meta( $term_id, 'fbk_category_order', true ) != $order ) {
			update_term_meta( $term_id, 'fbk_category_order', $order );
			$need_cats_rebuild = true;
		}
	}

	foreach ( $_REQUEST['fbk_use_cat_cb'] as $term_id ) {
		if ( isset($_REQUEST['fbk_use_cat'][$term_id]) && 'yes' == $_REQUEST['fbk_use_cat'][$term_id] ) {
			if ( 'yes' != get_term_meta( $term_id, 'fbk_use_cat', true ) ) {
				update_term_meta( $term_id, 'fbk_use_cat', 'yes' );
				$need_cats_rebuild = true;
			}
		} else {
			if ( 'no' != get_term_meta( $term_id, 'fbk_use_cat', true ) ) {
				update_term_meta( $term_id, 'fbk_use_cat', 'no' );
				$need_cats_rebuild = true;
			}
		}
	}

	if ( ! empty($need_cats_rebuild) ) {
		$fbk->populate_cats();
		$fbk_cache->delete( 'footer' );
		fbk_rebuild_color_css();
	}
}

add_action( 'category_edit_form_fields', 'fbk_catmeta_edit_form_fields' );
function fbk_catmeta_edit_form_fields( $term ) {
	global $fbk_catmeta_defaults;
	$meta = get_term_custom( $term->term_id );
	foreach ( $meta as $key => $value ) {
		if ( 0 === strpos( $key, 'fbk_' ) && is_array( $value ) )
			$meta[$key] = $value[0];
	}
	foreach ( $fbk_catmeta_defaults as $key => $default ) {
		if ( ! isset($meta['fbk_' . $key]) )
			$meta['fbk_' . $key] = $default;
	}
	$imgdir = get_stylesheet_directory_uri() . '/img';
?>
<tr class="form-field">
	<th scope="row" valign="top"><label for="fbk_menu_sync">Kategorie-Menü in einer externen Datei synchronisieren</label></th>
	<td>
	 <ul>
	  <li>
	   <label for="fbk_menu_sync">
	    <input type="checkbox" id="fbk_menu_sync" name="fbk[menu_sync]" value="yes" <?php if ( ! empty($meta['fbk_menu_sync']) && 'yes' == $meta['fbk_menu_sync'] ) echo 'checked="checked"'; ?> style="width:auto;">
	    <input type="hidden" name="fbk_checkboxes[]" value="menu_sync">
	    Synchronisieren
	   </label>
	  </li>
	  <li>
	   <label for="fbk_menu_sync_file">
	    Pfad zur Datei, ausgehend von <code><?= ABSPATH ?></code>, z.B. <code>../50plus-sprachreisen.de/index.htm</code>:<br>
	    <input type="text" id="fbk_menu_sync_file" name="fbk[menu_sync_file]" value="<?= esc_attr(@$meta['fbk_menu_sync_file']) ?>">
	   </label>
	  </li>
	  <li>
	   <label for="fbk_menu_sync_depth">
	    <input type="text" id="fbk_menu_sync_depth" name="fbk[menu_sync_depth]" value="<?= esc_attr(@$meta['fbk_menu_sync_depth']) ?>" style="width:4em;">
	    Tiefe des Menüs (1 = nur Sprachen, 2 = Sprachen und Länder, etc.; 0 oder leer = gesamtes Menü)
	   </label>
	  </li>
	  <li>
	   <label for="fbk_menu_sync_class">
	    CSS-Klasse(n) des eingefügten Menüs:<br>
	    <input type="text" id="fbk_menu_sync_class" name="fbk[menu_sync_class]" value="<?= esc_attr(@$meta['fbk_menu_sync_class']) ?>">
	   </label>
	  </li>
	  <li>
	   <label for="fbk_menu_sync_start">
	    Textmarke <i>vor</i> dem eingefügten Menü (z.B. ein HTML-Kommentar-Tag):<br>
	    <input type="text" id="fbk_menu_sync_start" name="fbk[menu_sync_start]" value="<?= esc_attr(@$meta['fbk_menu_sync_start']) ?>">
	   </label>
	  </li>
	  <li>
	   <label for="fbk_menu_sync_end">
	    Textmarke <i>nach</i> dem eingefügten Menü:<br>
	    <input type="text" id="fbk_menu_sync_end" name="fbk[menu_sync_end]" value="<?= esc_attr(@$meta['fbk_menu_sync_end']) ?>">
	   </label>
	  </li>
	 </ul>
	</td>
</tr>
<tr class="form-field">
 <th scope="row" valign="top"><label for="fbk_search_terms">Suchbegriffe</label></th>
 <td>
  <textarea id="fbk_search_terms" name="fbk[search_terms]" rows="2"><?= esc_textarea( @implode(' ', maybe_unserialize( $meta['fbk_search_terms'] ) ) ) ?></textarea>
  <br><span class="description">Diese Begriffe werden verwendet, um Anfragen aus der Schnellsuche &bdquo;schlau&ldquo; zu interpretieren.
  Beachte, dass jeder Suchbegriff nur auf eine Kategorie verweisen kann.
  <br>Trenne Begriffe mit einem Leerzeichen.</span>
 </td>
</tr>
<tr class="form-field">
 <th scope="row" valign="top"><label for="fbk_color_full">Farben</label></th>
 <td>
  <ul id="fbk_colors">
   <li><label for="fbk_color_full">
    #<input type="text" style="width: 6em;" id="fbk_color_full" name="fbk[color_full]" value="<?= esc_attr( @$meta['fbk_color_full'] ) ?>">
    Gesättigte Farbe (Kopf- und Fußzeile)
   </label></li>
   <li><label for="fbk_color_main">
    #<input type="text" style="width: 6em;" id="fbk_color_main" name="fbk[color_main]" value="<?= esc_attr( @$meta['fbk_color_main'] ) ?>">
    Hauptfarbe (z.B. Menü-Überschrift)
   </label></li>
   <li><label for="fbk_color_b">
    #<input type="text" style="width: 6em;" id="fbk_color_b" name="fbk[color_b]" value="<?= esc_attr( @$meta['fbk_color_b'] ) ?>">
    B-Farbe (z.B. Teaser-Titel)
   </label></li>
   <li><label for="fbk_color_c">
    #<input type="text" style="width: 6em;" id="fbk_color_c" name="fbk[color_c]" value="<?= esc_attr( @$meta['fbk_color_c'] ) ?>">
    C-Farbe (z.B. Menüeinträge, Sprachen)
   </label></li>
   <li><label for="fbk_color_d">
    #<input type="text" style="width: 6em;" id="fbk_color_d" name="fbk[color_d]" value="<?= esc_attr( @$meta['fbk_color_d'] ) ?>">
    D-Farbe (z.B. Menüeinträge, Länder)
   </label></li>
   <li><label for="fbk_color_e">
    #<input type="text" style="width: 6em;" id="fbk_color_e" name="fbk[color_e]" value="<?= esc_attr( @$meta['fbk_color_e'] ) ?>">
    E-Farbe (z.B. Menüeinträge, Städte)
   </label></li>
   <li><label for="fbk_color_mainsec">
    #<input type="text" style="width: 6em;" id="fbk_color_mainsec" name="fbk[color_mainsec]" value="<?= esc_attr( @$meta['fbk_color_mainsec'] ) ?>">
    Hauptfarbe 2
   </label></li>
   <li><label for="fbk_color_bsec">
    #<input type="text" style="width: 6em;" id="fbk_color_bsec" name="fbk[color_bsec]" value="<?= esc_attr( @$meta['fbk_color_bsec'] ) ?>">
    B-Farbe 2
   </label></li>
   <li><label for="fbk_color_csec">
    #<input type="text" style="width: 6em;" id="fbk_color_csec" name="fbk[color_csec]" value="<?= esc_attr( @$meta['fbk_color_csec'] ) ?>">
    C-Farbe 2
   </label></li>
   <li><label for="fbk_color_dsec">
    #<input type="text" style="width: 6em;" id="fbk_color_dsec" name="fbk[color_dsec]" value="<?= esc_attr( @$meta['fbk_color_dsec'] ) ?>">
    D-Farbe 2
   </label></li>
   <li><label for="fbk_color_esec">
    #<input type="text" style="width: 6em;" id="fbk_color_esec" name="fbk[color_esec]" value="<?= esc_attr( @$meta['fbk_color_esec'] ) ?>">
    E-Farbe 2
   </label></li>
  </ul>
  <script>
jQuery(function($){
	$('#fbk_colors label').each(function(){
		$('<div />').css({width:25,height:25,display:'inline-block',verticalAlign:'top',backgroundColor:'#'+$('input',this).val()}).prependTo($(this));
	}).find('input').on('change', function(){
		$(this).prev(':first-child').css('backgroundColor', '#'+this.value);
	}).on('keypress', function(event){
		if ( 13 == event.which ) {
			$(this).change();
			return false;
		}
	});
});
  </script>
 </td>
</tr>
<tr class="form-field" style="background-color: #ccc;">
	<td colspan="2">In den folgenden Feldern können diverse Platzhalter verwendet werden, die dynamisch ersetzt werden. Folgende Platzhalter werden unterstützt:
		<ul>
			<li>{sitename}: Name der Website (gem. Einstellungen -&gt; Allgemein)</li>
			<li>{category}: Kategoriename (s.o.)</li>
			<li>{category_shortdesc}: Kurzbeschreibung (s.o.); fällt auf den Kategorienamen zurück, falls leer</li>
			<li>{lang}: Sprache</li>
			<li>{langs_or}: Bei mehreren Sprachen: Komma-getrennte Aufzählung mit "oder" vor dem letzten Element</li>
			<li>{langs_and}: Bei mehreren Sprachen: Komma-getrennte Aufzählung mit "und" vor dem letzten Element</li>
			<li>{country}, {countries_or}, {countries_and}: Land</li>
			<li>{city}, {cities_or}, {cities_and}: Stadt</li>
			<li>{course}: Kursname</li>
			<li>{school}: Schulname</li>
		</ul>
		Ist der Wert für einen Platzhalter nicht vorhanden, wird dieser durch eine leere Zeichenkette ersetzt.
	</td>
</tr>
<tr class="form-field">
 <td valign="top"><img src="<?= $imgdir ?>/catmeta_menu.gif" alt="" style="float:right;" /></td>
 <td valign="top">
  <div class="catmeta-heading">Navigationsmenü</div>
  <ul>
   <li><label for="fbk_menu_heading">
    &#10122; Überschrift:<br>
    <input type="text" class="code" id="fbk_menu_heading" name="fbk[menu_heading]" value="<?= esc_attr($meta['fbk_menu_heading']) ?>">
   </li>
   <li><label for="fbk_menu_level_0">
    &#10123; title-Attribut, Sprachen:<br>
    <input type="text" class="code" id="fbk_menu_level_0" name="fbk[menu_level_0]" value="<?= esc_attr($meta['fbk_menu_level_0']) ?>">
   </li>
   <li><label for="fbk_menu_level_1">
    &#10124; title-Attribut, Länder:<br>
    <input type="text" class="code" id="fbk_menu_level_1" name="fbk[menu_level_1]" value="<?= esc_attr($meta['fbk_menu_level_1']) ?>">
   </li>
   <li><label for="fbk_menu_level_2">
    &#10125; title-Attribut, Städte:<br>
    <input type="text" class="code" id="fbk_menu_level_2" name="fbk[menu_level_2]" value="<?= esc_attr($meta['fbk_menu_level_2']) ?>">
   </li>
   <li><label for="fbk_menu_level_3">
    &#10126; title-Attribut, Schulen:<br>
    <input type="text" class="code" id="fbk_menu_level_3" name="fbk[menu_level_3]" value="<?= esc_attr($meta['fbk_menu_level_3']) ?>">
   </li>
  </ul>
 </td>
</tr>
<tr class="form-field">
 <td valign="top"><img src="<?= $imgdir ?>/catmeta_category.gif" alt="" style="float:right;" /></td>
 <td valign="top">
  <div class="catmeta-heading">Kategorie-Seite</div>
  <ul>
   <li><label for="fbk_category_title">
    Seitentitel:<br>
    <input type="text" class="code" id="fbk_category_title" name="fbk[category_title]" value="<?= esc_attr($meta['fbk_category_title']) ?>">
   </li>
   <li><label for="fbk_category_desc">
    Meta-Beschreibung:<br>
    <input type="text" class="code" id="fbk_category_desc" name="fbk[category_desc]" value="<?= esc_attr($meta['fbk_category_desc']) ?>">
   </li>
   <li><label for="fbk_category_h1">
    &#10122; Überschrift 1:<br>
    <input type="text" class="code" id="fbk_category_h1" name="fbk[category_h1]" value="<?= esc_attr($meta['fbk_category_h1']) ?>">
   </li>
   <li><label for="fbk_category_h2">
    &#10123; Überschrift 2, zwischen der Beschreibung und der Auflistung von Sprachen und Ländern:<br>
    <input type="text" class="code" id="fbk_category_h2" name="fbk[category_h2]" value="<?= esc_attr($meta['fbk_category_h2']) ?>">
   </li>
   <li><label for="fbk_category_langbox_heading">
    &#10124; Überschriften der Sprachkästchen:<br>
    <input type="text" class="code" id="fbk_category_langbox_heading" name="fbk[category_langbox_heading]" value="<?= esc_attr($meta['fbk_category_langbox_heading']) ?>">
   </li>
   <li><label for="fbk_category_langbox_entry_text">
    &#10125; Einträge in den Sprachkästchen, Text:<br>
    <input type="text" class="code" id="fbk_category_langbox_entry_text" name="fbk[category_langbox_entry_text]" value="<?= esc_attr($meta['fbk_category_langbox_entry_text']) ?>">
   </li>
   <li><label for="fbk_category_langbox_entry_attr-title">
    &#10126; Einträge in den Sprachkästchen, title-Attribut:<br>
    <input type="text" class="code" id="fbk_category_langbox_entry_attr-title" name="fbk[category_langbox_entry_attr-title]" value="<?= esc_attr($meta['fbk_category_langbox_entry_attr-title']) ?>">
   </li>
  </ul>
 </td>
</tr>
<tr class="form-field">
 <td valign="top"><img src="<?= $imgdir ?>/catmeta_country.gif" alt="" style="float:right;" /></td>
 <td valign="top">
  <div class="catmeta-heading">Länder-Seiten</div>
  <ul>
   <li><label for="fbk_country_title">
    Seitentitel:<br>
    <input type="text" class="code" id="fbk_country_title" name="fbk[country_title]" value="<?= esc_attr($meta['fbk_country_title']) ?>">
   </li>
   <li><label for="fbk_country_desc">
    Meta-Beschreibung:<br>
    <input type="text" class="code" id="fbk_country_desc" name="fbk[country_desc]" value="<?= esc_attr($meta['fbk_country_desc']) ?>">
   </li>
   <li><label for="fbk_country_h1">
    &#10122; Überschrift 1:<br>
    <input type="text" class="code" id="fbk_country_h1" name="fbk[country_h1]" value="<?= esc_attr($meta['fbk_country_h1']) ?>">
   </li>
   <li><label for="fbk_country_h2">
    &#10123; Überschrift 2, zwischen der Beschreibung und der Auflistung von Sprachen, Städten und Schulen:<br>
    <input type="text" class="code" id="fbk_country_h2" name="fbk[country_h2]" value="<?= esc_attr($meta['fbk_country_h2']) ?>">
   </li>
   <li><label for="fbk_country_h3">
    &#10124; Überschrift 3, zur Bezeichnung der Sprache:<br>
    <input type="text" class="code" id="fbk_country_h3" name="fbk[country_h3]" value="<?= esc_attr($meta['fbk_country_h3']) ?>">
   </li>
   <li><label for="fbk_country_always_use_h3">
    <input type="checkbox" style="width:auto;" id="fbk_country_always_use_h3" name="fbk[country_always_use_h3]" value="yes" <?= 'yes' == $meta['fbk_country_always_use_h3'] ? ' checked="checked"' : '' ?>>
    <input type="hidden" name="fbk_checkboxes[]" value="country_always_use_h3">
    Überschrift 3 auch anzeigen, wenn nur eine Sprache vorhanden ist
   </li>
   <li><label for="fbk_country_citybox_heading">
    &#10125; Überschriften der Städtekästchen:<br>
    <input type="text" class="code" id="fbk_country_citybox_heading" name="fbk[country_citybox_heading]" value="<?= esc_attr($meta['fbk_country_citybox_heading']) ?>">
   </li>
   <li><label for="fbk_country_citybox_entry_text">
    &#10126; Schuleinträge in den Städtekästchen, Text:<br>
    <input type="text" class="code" id="fbk_country_citybox_entry_text" name="fbk[country_citybox_entry_text]" value="<?= esc_attr($meta['fbk_country_citybox_entry_text']) ?>">
   </li>
   <li><label for="fbk_country_citybox_entry_attr-title">
    &#10127; Schuleinträge in den Städtekästchen, title-Attribut:<br>
    <input type="text" class="code" id="fbk_country_citybox_entry_attr-title" name="fbk[country_citybox_entry_attr-title]" value="<?= esc_attr($meta['fbk_country_citybox_entry_attr-title']) ?>">
   </li>
   <li><label for="fbk_country_citybox_more_text">
    &#10128; &bdquo;Mehr lesen&ldquo;-Link der Städtekästchen, Text:<br>
    <input type="text" class="code" id="fbk_country_citybox_more_text" name="fbk[country_citybox_more_text]" value="<?= esc_attr($meta['fbk_country_citybox_more_text']) ?>">
   </li>
   <li><label for="fbk_country_citybox_more_attr-title">
    &#10129; &bdquo;Mehr lesen&ldquo;-Link der Städtekästchen, title-Attribut:<br>
    <input type="text" class="code" id="fbk_country_citybox_more_attr-title" name="fbk[country_citybox_more_attr-title]" value="<?= esc_attr($meta['fbk_country_citybox_more_attr-title']) ?>">
   </li>
  </ul>
 </td>
</tr>
<tr class="form-field">
 <td valign="top"><img src="<?= $imgdir ?>/catmeta_city.gif" alt="" style="float:right;" /></td>
 <td valign="top">
  <div class="catmeta-heading">Städte-Seiten</div>
  <ul>
   <li><label for="fbk_city_title">
    Seitentitel:<br>
    <input type="text" class="code" id="fbk_city_title" name="fbk[city_title]" value="<?= esc_attr($meta['fbk_city_title']) ?>">
   </li>
   <li><label for="fbk_city_desc">
    Meta-Beschreibung:<br>
    <input type="text" class="code" id="fbk_city_desc" name="fbk[city_desc]" value="<?= esc_attr($meta['fbk_city_desc']) ?>">
   </li>
   <li><label for="fbk_city_h1">
    &#10122; Überschrift 1:<br>
    <input type="text" class="code" id="fbk_city_h1" name="fbk[city_h1]" value="<?= esc_attr($meta['fbk_city_h1']) ?>">
   </li>
   <li><label for="fbk_city_h2">
    &#10123; Überschrift 2, zwischen der Beschreibung und der Auflistung der Schulen:<br>
    <input type="text" class="code" id="fbk_city_h2" name="fbk[city_h2]" value="<?= esc_attr($meta['fbk_city_h2']) ?>">
   </li>
   <li><label for="fbk_city_h3">
    &#10124; Überschrift 3, zur Bezeichnung der Sprache:<br>
    <input type="text" class="code" id="fbk_city_h3" name="fbk[city_h3]" value="<?= esc_attr($meta['fbk_city_h3']) ?>">
   </li>
   <li><label for="fbk_city_always_use_h3">
    <input type="checkbox" style="width:auto;" id="fbk_city_always_use_h3" name="fbk[city_always_use_h3]" value="yes" <?= 'yes' == $meta['fbk_city_always_use_h3'] ? ' checked="checked"' : '' ?>>
    <input type="hidden" name="fbk_checkboxes[]" value="city_always_use_h3">
    Überschrift 3 auch anzeigen, wenn nur eine Sprache vorhanden ist
   </li>
   <li><label for="fbk_city_schoolbox_more_text">
    &#10125; &bdquo;Mehr lesen&ldquo;-Link der Schulkästchen, Text:<br>
    <input type="text" class="code" id="fbk_city_schoolbox_more_text" name="fbk[city_schoolbox_more_text]" value="<?= esc_attr($meta['fbk_city_schoolbox_more_text']) ?>">
   </li>
   <li><label for="fbk_city_schoolbox_more_attr-title">
    &#10126; &bdquo;Mehr lesen&ldquo;-Link der Schulkästchen, title-Attribut:<br>
    <input type="text" class="code" id="fbk_city_schoolbox_more_attr-title" name="fbk[city_schoolbox_more_attr-title]" value="<?= esc_attr($meta['fbk_city_schoolbox_more_attr-title']) ?>">
   </li>
  </ul>
 </td>
</tr>
<tr class="form-field">
 <td valign="top">&nbsp;</td>
 <td valign="top">
  <div class="catmeta-heading">Schulen</div>
  <ul>
   <li><label for="fbk_school_title">
    Seitentitel:<br>
    <input type="text" class="code" id="fbk_school_title" name="fbk[school_title]" value="<?= esc_attr($meta['fbk_school_title']) ?>">
   </li>
   <li><label for="fbk_school_desc">
    Meta-Beschreibung:<br>
    <input type="text" class="code" id="fbk_school_desc" name="fbk[school_desc]" value="<?= esc_attr($meta['fbk_school_desc']) ?>">
   </li>
   <li><label for="fbk_school_course_heading">
    Kursüberschriften:<br>
    <input type="text" class="code" id="fbk_school_course_heading" name="fbk[school_course_heading]" value="<?= esc_attr($meta['fbk_school_course_heading']) ?>">
   </li>
  </ul>
 </td>
</tr>
<?php
}

add_action( 'edited_category', 'fbk_catmeta_edit_term' );
function fbk_catmeta_edit_term( $term_id ) {
	global $fbk_catmeta_defaults, $fbk_cache;
	$existing_meta = get_term_custom( $term_id );
	if ( isset( $_POST['fbk'] ) && is_array( $_POST['fbk'] ) ) {
		$clear = array();

		if ( isset($_POST['fbk']['search_terms']) )
			$_POST['fbk']['search_terms'] = preg_split( '/[\s,]/', $_POST['fbk']['search_terms'], null, PREG_SPLIT_NO_EMPTY );

		foreach ( $_POST['fbk'] as $key => $meta ) {
			if ( empty($existing_meta['fbk_' . $key]) || $existing_meta['fbk_'.$key][0] != $meta ) {
				update_term_meta( $term_id, 'fbk_' . $key, $meta );
				$parts = explode( '_', $key );
				if ( 'menu' == $parts[0] && 'sync' == $parts[1] )
					$clear['sync'] = true;
				else
					$clear[ $parts[0] ] = true;
			}
		}

		if ( isset( $_POST['fbk_checkboxes'] ) && is_array( $_POST['fbk_checkboxes'] ) ) {
			foreach ( $_POST['fbk_checkboxes'] as $key ) {
				if ( empty($_POST['fbk'][$key]) && ( empty($existing_meta['fbk_' . $key]) || $existing_meta['fbk_'.$key][0] != 'no' ) ) {
					update_term_meta( $term_id, 'fbk_' . $key, 'no' );
					$parts = explode( '_', $key );
					if ( 'menu' == $parts[0] && 'sync' == $parts[1] )
						$clear['sync'] = true;
					else
						$clear[ $parts[0] ] = true;
				}
			}
		}

		if ( ! empty($clear['menu']) ) {
			$fbk_cache->delete( 'menu', $term_id );
		}
		if ( ! empty($clear['category']) ) {
			$fbk_cache->delete( 'cat', $term_id );
		}
		if ( ! empty($clear['country']) ) {
			$fbk_cache->delete( "loc-$term_id", _fbk_catmeta_get_matching_ids( "/^$term_id-\\d+-(\\d+)-/" ) );
		}
		if ( ! empty($clear['city']) ) {
			$fbk_cache->delete( "loc-$term_id", _fbk_catmeta_get_matching_ids( "/^$term_id-\\d+-\\d+-(\\d+)-/" ) );
		}
		if ( ! empty($clear['school']) ) {
			$fbk_cache->delete( "school", _fbk_catmeta_get_matching_ids( "/^$term_id-\\d+-\\d+-\\d+-(\\d+)$/" ) );
		}
		if ( ! empty($clear['sync']) ) {
			fbk_resync_external_menu( $term_id );
		}
		if ( ! empty($clear['color']) ) {
			fbk_rebuild_color_css();
		}
	}
}

function fbk_get_category_meta( $where, $args = array(), $actualised = true ) {
	global $fbk_query, $fbk_catmeta_defaults;
	if ( isset($args['category']) && is_object($args['category']) )
		$cat = $args['category'];
	elseif ( ! empty($fbk_query->category) )
		$cat = $fbk_query->category;
	else
		return '';

	$meta = get_term_meta( $cat->term_id, 'fbk_' . $where, true );
	if ( '' === $meta ) {
		$meta_cache = wp_cache_get( $cat->term_id, 'term_meta' );
		if ( ! isset($meta_cache['fbk_'.$where]) && isset($fbk_catmeta_defaults[$where]) ) {
			$meta = $fbk_catmeta_defaults[$where];
		}
	}

	if ( $actualised )
		return fbk_replace_tokens( $meta, $args );
	else
		return $meta;
}

function fbk_replace_tokens( $subject, $args = array() ) {
	global $fbk_query;

	$defaults = array();
	if ( empty($args['no_defaults']) ) {
		if ( ! empty($fbk_query->category) )
			$defaults['category'] = $fbk_query->category;
		if ( is_archive() && 'loc' == $fbk_query->taxonomy ) {
			$defaults['loc'] = $fbk_query->term;
		} elseif ( is_singular( 'school' ) ) {
			$defaults['school'] = get_queried_object();
		}
	}

	$args = wp_parse_args( $args, $defaults );

	if ( isset($args['school']) && is_object( $args['school'] ) && ( empty($args['lang']) || empty($args['loc']) ) ) {
		$terms = wp_get_object_terms( $args['school']->ID, array( 'lang', 'loc' ) );
		if ( $terms )
			foreach ( $terms as $term )
				if ( empty($args[$term->taxonomy]) )
					$args[$term->taxonomy] = $term;
	}

	if ( isset($args['loc']) && is_object( $args['loc'] ) ) {
		if ( empty($args['country']) ) {
			if ( $args['loc']->parent )
				$args['country'] = get_term( $args['loc']->parent, 'loc' );
			else
				$args['country'] = $args['loc'];
		}
		if ( empty($args['city']) && $args['loc']->parent )
			$args['city'] = $args['loc'];
	}

	$replace = $temp = array();
	$split = explode( '{', $subject );
	foreach ( $split as $segment ) {
		$token = strtok( $segment, '}' );
		if ( false === $token || array_key_exists( $token, $replace ) )
			continue;
		switch ( strtolower($token) ) {
			case 'sitename':
				$replace[ '{' . $token . '}' ] = get_option( 'blogname' );
				break;
			case 'category':
			case 'lang':
			case 'country':
			case 'city':
				if ( empty( $args[$token] ) ) {
					$replace[ '{' . $token . '}' ] = '';
				} elseif ( is_array( $args[$token] ) ) {
					if ( 1 == count($args[$token]) ) {
						$tmp = reset($args[$token]);
						$replace[ '{' . $token . '}' ] = $tmp->name;
					} else {
						$replace[ '{' . $token . '}' ] = '';
					}
				} else {
					$replace[ '{' . $token . '}' ] = $args[$token]->name;
				}
				break;
			case 'category_shortdesc':
				if ( empty( $args['category'] ) ) {
					$replace[ '{' . $token . '}' ] = '';
				} elseif ( is_array( $args['category'] ) ) {
					if ( 1 == count($args['category']) ) {
						$tmp = reset($args['category']);
						$replace[ '{' . $token . '}' ] = $tmp->description ? $tmp->description : $tmp->name;
					} else {
						$replace[ '{' . $token . '}' ] = '';
					}
				} else {
					$replace[ '{' . $token . '}' ] = $args['category']->description ? $args['category']->description : $args['category']->name;
				}
				break;
			case 'langs_or':
			case 'langs_and':
			case 'countries_or':
			case 'countries_and':
			case 'cities_or':
			case 'cities_and':
				$token_basename = substr( $token, 0, strpos( $token, '_' ) );
				if ( ! isset($temp[$token_basename]) ) {
					if ( 'langs' == $token_basename )         $taxonomy = 'lang';
					elseif ( 'countries' == $token_basename ) $taxonomy = 'country';
					elseif ( 'cities' == $token_basename )    $taxonomy = 'city';

					if ( ! empty($args[$taxonomy]) ) {
						if ( is_array( $args[$taxonomy] ) )
							$temp[$token_basename] = $args[$taxonomy];
						else
							$temp[$token_basename] = array( $args[$taxonomy] );
					} else {
						if ( ! isset($pattern_arr) )
							$pattern_arr = _fbk_catmeta_get_pattern( $args );
						$pattern_arr_x = $pattern_arr;
						$pattern_arr_x[$taxonomy] = '(\\d+)';
						$pattern = "/^$pattern_arr_x[category]-$pattern_arr_x[lang]-$pattern_arr_x[country]-$pattern_arr_x[city]-/";
						$temp[$token_basename] = _fbk_catmeta_get_matching_terms( $pattern, 'lang' == $taxonomy ? 'lang' : 'loc' );
					}
				}
				if ( empty($temp[$token_basename]) ) {
					$replace[ '{' . $token . '}' ] = '';
				} elseif ( 1 == count($temp[$token_basename]) ) {
					$tmp = reset($temp[$token_basename]);
					$replace[ '{' . $token . '}' ] = $tmp->name;
				} else {
					$names = array();
					foreach ( $temp[$token_basename] as $term )
						$names[] = $term->name;
					sort( $names );
					$last_name = array_pop( $names );
					$replace[ '{' . $token . '}' ] = implode( ', ', $names ) . ( strpos( $token, '_and' ) ? ' und ' : ' oder ' ) . $last_name;
				}
				break;
			case 'school':
				if ( empty($args['school']) )
					$replace[ '{' . $token . '}' ] = '';
				else
					$replace[ '{' . $token . '}' ] = $args['school']->post_title;
				break;
			case 'course':
				if ( empty( $args['course'] ) )
					$replace[ '{' . $token . '}' ] = '';
				else
					$replace[ '{' . $token . '}' ] = $args['course']['name'];
				break;
		}
	}
	$result = str_ireplace( array_keys( $replace ), array_map( 'trim', $replace ), $subject );
	return wptexturize( $result );
}

function _fbk_catmeta_get_pattern( $args ) {
	$pattern = array();
	foreach ( array( 'category', 'lang', 'country', 'city' ) as $type ) {
		if ( empty( $args[$type] ) )
			$pattern[$type] = '\\d+';
		else
			$pattern[$type] = _fbk_catmeta_get_pattern_part( $args[$type] );
	}
	return $pattern;
}

function _fbk_catmeta_get_pattern_part( $terms ) {
	if ( is_array( $terms ) ) {
		$ids = array();
		foreach ( $terms as $term )
			$ids[] = $term->term_id;
		$part = '(?:' . implode( '|', array_unique($ids) ) . ')';
	} elseif ( is_object( $terms ) ) {
		$part = $terms->term_id;
	} else {
		$part = '\\d+';
	}
	return $part;
}

function _fbk_catmeta_get_matching_terms( $pattern, $taxonomy, $indices = 1 ) {
	$ids = _fbk_catmeta_get_matching_ids( $pattern, $indices );

	$all_terms = get_terms( $taxonomy, array( 'get' => 'all' ) );
	$my_terms = array();
	foreach ( $all_terms as $term )
		if ( in_array( $term->term_id, $ids ) )
			$my_terms[$term->term_id] = $term;
	return $my_terms;
}

function _fbk_catmeta_get_matching_ids( $pattern, $indices = 1 ) {
	$indices = (array) $indices;
	$struct = get_option( 'fbk_menu_cache' );
	$ids = array();
	foreach ( $struct as $string ) {
		if ( preg_match( $pattern, $string, $matches ) )
			foreach ( $indices as $index )
				$ids[] = $matches[$index];
	}
	return array_unique( $ids );
}

function fbk_rebuild_color_css() {
	global $fbk;
	$col_phs = array(
		'full',
		'main',
		'mainsec',
		'b',
		'bsec',
		'c',
		'csec',
		'd',
		'dsec',
		'e',
		'esec'
	);
	$root = get_stylesheet_directory();

	$tpl = fbk_compress_css( file_get_contents( $root . '/color-template.css' ) );

	$out = '';
	foreach ( $fbk->cats as $slug => $cat ) {
		$replace = array( '/@cat/i' => 'c-' . $slug );
		foreach ( $col_phs as $ph )
			$replace[ "/@$ph\\b/i" ] = '#' . get_term_meta( $cat->term_id, 'fbk_color_' . $ph, true );
		$out .= preg_replace( array_keys($replace), $replace, $tpl );
	}

	if ( is_readable( $root . '/color-overrides.css' ) )
		$out .= fbk_compress_css( file_get_contents( $root . '/color-overrides.css' ) );

	if ( is_readable( $root . '/' . FBK_COLORS_CSS_FILE ) )
		$previous_css = file_get_contents( $root . '/' . FBK_COLORS_CSS_FILE );
	else
		$previous_css = '';

	if ( $out != $previous_css )
		file_put_contents( $root . '/' . FBK_COLORS_CSS_FILE, $out );
}
?>
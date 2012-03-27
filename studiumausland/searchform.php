<?php
/**
 * @package Studium_Ausland
 */

global $fbk, $wp_query;

?>
<form role="search" id="extended_search" action="<?= home_url() ?>">
<h1>Suche</h1>
<div class="double teaser">
<div class="left"><div class="input-wrapper"><input type="search" name="s" value="<?= $wp_query->get('s') ?>" id="s_terms"></div></div>
<div class="right"><div class="input-wrapper"><input type="submit" value="Suchen" id="s_submit"></div></div>
<div class="left"><div class="input-wrapper">
<?php fbk_the_search_tax_select( 'cat', array( 'field' => 'term_id', 'terms' => $fbk->cats, 'empty' => '-- Kategorie --' ) ); ?>
<?php fbk_the_search_tax_select( 'course_tag', array('empty' => '-- Zertifikat --') ); ?>
<select name="post_type">
<?php
	$selected_pt = $wp_query->get('post_type');
	foreach ( array ( 'school' => 'Schulen', 'offer' => 'Sonderangebote', 'post' => 'Neuigkeiten' ) as $post_type => $label ) {
		if ( $selected_pt == $post_type || ! $selected_pt && 'school' == $post_type )
			$sel = 'selected="selected"';
		else
			$sel = '';
		echo "<option $sel value='$post_type'>$label</option>";
	}
?>
</select>
<?php
	if ( empty($_REQUEST['bu']) )
		$checked = '';
	else
		$checked = 'checked="checked"';
?>
<div id="bu-wrap"><input type="checkbox" value="1" name="bu" id="bu" <?= $checked ?>><label for="bu">Nur Kurse mit Bildungsurlaub</label></div>
<a><input type="reset" value="Alle zurücksetzen" id="s_reset"></a>
</div></div>
<div class="right"><div class="input-wrapper">
<?php fbk_the_search_tax_select( 'lang', array( 'empty' => '-- Sprache --' ) ); ?>
<?php
	if ( $city_sel = $wp_query->get('city') ) {
		 $city_sel = get_term_by( 'slug', $city_sel, 'loc' );
		 if ( ! is_object( $city_sel ) || is_wp_error( $city_sel ) ) {
		 	$ctry_sel = $wp_query->get( 'loc' );
		 	$city_sel = false;
		 } else {
			 $ctry_sel = get_term( $city_sel->parent, 'loc' );
			 $ctry_sel = $ctry_sel->slug;
		}
	} else {
		$ctry_sel = $wp_query->get( 'loc' );
	}
	$countries = get_terms( 'loc', array( 'parent' => 0, 'hide_empty' => false ) );
	fbk_the_search_tax_select( 'loc', array(
		'empty' => '-- Land --',
		'terms' => $countries,
		'id' => 's_country',
		'selected' => $ctry_sel 
	));
?>
<?php
	foreach ( $countries as $ctry ) {
		$c_cities = get_terms( 'loc', array( 'parent' => $ctry->term_id ) );
		$show = ( ! empty($city_sel) ? $city_sel->parent == $ctry->term_id : $ctry_sel == $ctry->slug );
		fbk_the_search_tax_select(
			$show ? 'city' : '',
			array(
				'terms' => $c_cities,
				'id' => 's_city_'.$ctry->slug,
				'empty' => '-- Ort --',
				'attrib' => $show ? '' : 'style="display: none;"',
				'selected' => ( $show && ! empty($city_sel) ? $city_sel->slug : '' )
			)
		);
	}
?>
</div></div>
</div>
<script>(function($){
var h=function(){$('select[name="city"]').hide().attr('name','')};
$('#s_country').change(function(){
	h();
	$('#s_city_'+$(this).val()).show().attr('name','city');
}).closest('form').bind('reset',h);
})(jQuery);</script>
</form>
<?php
/**
 * @package Studium_Ausland
 */

add_action( 'media_upload_link_shortcode', 'fbk_link_shortcode_tb' );
function fbk_link_shortcode_tb() {
	wp_iframe( 'fbk_link_shortcode_iframe' );
}

function fbk_link_shortcode_iframe() {
	global $wp_query, $post;

	$tax_query = array();
	foreach ( array( 'lang', 'loc' ) as $tax ) {
		if ( ! empty( $_REQUEST[$tax] ) )
			$tax_query[] = array(
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $_REQUEST[$tax]
			);
	}
	$filter = @array(
		's' => $_GET['s'],
		'post_type' => $_GET['post_type'] ? $_GET['post_type'] : 'any',
		'category_name' => $_GET['cat'],
		'paged' => $_GET['paged'],
		'post_status' => 'publish',
		'posts_per_page' => 10,
		'orderby' => 'title',
		'order' => 'ASC'
	);
	if ( $tax_query )
		$filter['tax_query'] = $tax_query;
	$wp_query->query( $filter );
?>
<div id="fbk-tabless">
<?php
	fbk_thickbox_filter( array(
		'query_args' => array( 'tab' => 'type', 'type' => 'link_shortcode' ),
		'filter' => $filter,
		'taxonomies' => array( 'cat' => $filter['category_name'], 'lang' => @$_REQUEST['lang'], 'loc' => @$_REQUEST['loc'] )
	), array() );

	if ( have_posts() ) :
		$post_types = get_post_types( '', 'objects' );
		wp_get_object_terms( array_map( create_function('$a','return $a->ID;'), $wp_query->posts ), array('loc','category') );
?>
<table class="form-table fbk-searchresults"><tbody>
<tr><th>&nbsp;</th><th>Typ</th><th>Name</th><th><?php _e('Category'); ?></th><th>Sprache</th><th>Ort</th></tr>
<?php while ( have_posts() ) :
	the_post();
	$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
	foreach ( wp_get_object_terms( $post->ID, array_keys( $terms ) ) as $term )
		$terms[$term->taxonomy] = $term;
	if ( 'school' != $post->post_type && post_type_supports( $post->post_type, 'f_school_connect' ) && ! $terms['category'] )
		$terms['category'] = get_connect_category( $post );
	$label_open = '<label for="obj-' . $post->ID . '">';
?>
<tr class="<?php if ( $terms['category'] ) echo "c-" . $terms['category']->slug; ?>">
	<td><input type="radio" name="select" value="<?= $post->ID ?>"</td>
	<td><?= $post_types[$post->post_type]->labels->singular_name ?></td>
	<td id="title-<?= $post->ID ?>"><?php the_title(); ?></td>
	<?php foreach ( $terms as $term )
		if ( $term )
			echo "<td>$term->name</td>";
		else
			echo "<td>&nbsp;</td>";
	?>
</tr>
<?php endwhile; ?>
</tbody>
<tfoot><tr><td colspan="6">
<form id="link_shorttag_form">
<input type="text" placeholder="Linktext" id="link_shorttag_content">
<input type="submit" value="Einfügen" class="button-primary">
</form>
<script>jQuery(function($){
$('input[name="select"]').change(function(){
	var id = $('input[name="select"]:checked').val();
	$('#link_shorttag_form').data('id',id).children('#link_shorttag_content').val($('#title-'+id).text()).focus().select();
});
$('tbody tr').click(function(){
	$(this).find('input[name="select"]').not(':checked').prop('checked',true).change();
});
$('#link_shorttag_form').submit(function(){
	window.parent.send_to_editor(' [link id=' + jQuery(this).data('id') + ']' + $('#link_shorttag_content').val() + '[/link] ');
	return false;
});
});</script>
</td></tr></tfoot>
</table>
<?php
	endif;
?>
</div>
<?php
}

?>
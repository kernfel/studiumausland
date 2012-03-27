<?php
/**
 * @package Studium_Ausland
 */

add_action( 'media_upload_school_object_share', 'fbk_school_object_share' );
function fbk_school_object_share() {
	if ( ! empty( $_POST ) )
		$message = fbk_school_object_share_handler();
	else
		$message = "";

	wp_iframe( 'fbk_school_object_share_form', $message );
}

function fbk_school_object_share_handler() {
	global $fbk_cf_boxes, $fbk_cf_prefix, $wpdb;
	$post_id = (int) $_POST['post_id'];
	
	if ( ! check_admin_referer('media-form') || ! current_user_can( 'edit_posts' ) || empty($_POST['dolink']) )
		return;

	$added_links = array();
	foreach ( $fbk_cf_boxes as $object_type => $box ) {
		if ( ! empty($box['shared']) && ! empty($_POST[$object_type]) && is_array($_POST[$object_type]) ) {
			$object_ids = array_map( 'absint', $_POST[$object_type] );
			foreach ( get_post_meta( $post_id, "_foreign_$object_type" ) as $shareset ) {
				if ( in_array( $shareset['object_id'], $object_ids ) ) {
					unset( $object_ids[ array_search( $shareset['object_id'], $object_ids ) ] );
				}
			}
			if ( empty($object_ids) )
				continue;

			if ( in_array( $object_type, array( 'courses', 'accommodation' ) ) ) {
				$objects = $wpdb->get_results( "SELECT post_id, meta_id, _id FROM wp_fbk_$object_type WHERE meta_id IN (" . implode( ',', $object_ids ) . ")", ARRAY_A );
			} else {
				$_objects = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE meta_id IN (" . implode( ',', $object_ids ) . ")", ARRAY_A );
				$objects = array();
				if ( $_objects ) foreach ( $_objects as $object ) {
					$object['meta_value'] = unserialize($object['meta_value']);
					$objects[] = array( 'post_id' => $object['post_id'], 'meta_id' => $object['meta_id'], '_id' => $object['meta_value']['_id'] );
				}
			}
			if ( $objects ) foreach ( $objects as $object ) {
				if ( $object['post_id'] == $post_id )
					continue;
				add_post_meta( $post_id, "_foreign_$object_type", array(
					'school_id' => $object['post_id'],
					'object_id' => $object['meta_id'],
					'order' => $object['_id']
				));
				add_post_meta( $object['post_id'], "_shared_$object_type", array(
					'school_id' => $post_id,
					'object_id' => $object['meta_id']
				));
				$added_links[$object_type][] = $object['meta_id'];
			}
		}
	}

	$message = "";
	if ( ! empty($added_links) ) {
		$message = "Die ausgewählten Objekte wurden verknüpft. Um sie sehen und bearbeiten zu können, schließe dieses Dialogfenster und aktualisiere die Schule.";
		$message .= "<script>jQuery(function($){var f = $('form#post', top.document);";
		foreach ( $added_links as $type => $object_ids )
			foreach ( $object_ids as $object_id )
				$message .= "f.append(\"<input type='hidden' name='" . $fbk_cf_prefix . $type . "[" . $object_id . "][_just_linked]' value='1'>\");";
		$message .= "})</script>";
		
	}
	return $message;
}

function fbk_school_object_share_form( $message = '' ) {
	global $fbk_cf_boxes, $post, $wp_query;
	$post_id = $_REQUEST['post_id'];
	$request = @array(
		's' => $_REQUEST['s'],
		'paged' => $_REQUEST['paged'],
		'cat' => $_REQUEST['cat'],
		'post_id' => $post_id,
		'lang' => $_REQUEST['lang'],
		'loc' => $_REQUEST['loc']
	);
	$nonce = wp_nonce_field( 'media-form', '_wpnonce', true, false );
	$linked_schools = get_post_meta( $post_id, '_school_connect' );
?>
<div id="fbk-tabless">
<style>
tr[id] {
	cursor: pointer;
}
.share-objects-list {
	float: left;
	margin: .5em;
	padding: .5em;
	border: solid #bbb;
	border-width: 0 0 0 1px;
}
</style>
<?php
	if ( $message )
		echo "<div class='updated'><p>$message</p></div>";

/************************ Linked schools ****************************************/
if ( $linked_schools ) :
?>
<h2>Von verknüpften Schulen:</h2>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php?type=school_object_share&tab=type") ?>" class="school-object-share">
<?php foreach ( $request as $key => $field ) 
	echo "<input type='hidden' name='$key' value='$field'>"; ?>
<?= $nonce ?>
<table class="form-table fbk-searchresults">
<thead><tr><th>Schulname</th><th><?php _e('Category'); ?><th>Sprache</th><th>Ort</th></tr></thead>
<tbody>
<?php foreach ( $linked_schools as $school_id ) :
	$school = get_post( $school_id );
	$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
	foreach ( wp_get_object_terms( $school_id, array_keys( $terms ) ) as $term )
		$terms[$term->taxonomy] = $term;
	$selection[] = array( 't' => $school->post_title, 'c' => $terms['category'] ? $terms['category']->slug : '' );
?>
<tr <?php if ( $terms['category'] ) echo "class='c-{$terms['category']->slug}'"; ?> id="h-<?= $school_id ?>">
	<td><?= $school->post_title ?></td>
	<?php	foreach ( $terms as $term )
			if ( $term )
				echo "<td>$term->name</td>";
			else
				echo "<td>&nbsp;</td>";
	?>
</tr>
<tr <?php if ( $terms['category'] ) echo "class='c-{$terms['category']->slug}'"; ?> style="display:none;">
	<td colspan="4">
<?php
	foreach ( $fbk_cf_boxes as $object_type => $box ) {
		if ( ! empty($box['shared']) ) {
			if ( $all_objects = fbk_get_school_meta( $school_id, $object_type ) ) {
				echo "<div class='share-objects-list'><h4><input type='checkbox'> $box[title]:</h4><ul>";
				foreach ( $all_objects as $object_id => $object ) {
					echo "<li><label for='share-$school_id-$object_type-$object_id'>",
					"<input type='checkbox' id='share-$school_id-$object_type-$object_id' name='{$object_type}[]' value='$object_id'> ",
					fbk_cf_get_meta_object_name( $object_type, $object ) . "</label></li>";
				}
				echo "</ul></div>";
			}
		}
	}
?>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="4"><input type="submit" class="button-primary" value="Gewählte Objekte der aktuellen Schule beifügen" name="dolink"></td></tr></tfoot>
</table>
</form>
<?php
endif;
/************************ Free search *******************************************/
?>
<h2>Suche</h2>
<?php
	$tax_query = array();
	foreach ( array( 'lang', 'loc' ) as $tax )
		if ( $request[$tax] )
			$tax_query[] = array(
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $request[$tax]
			);
	$query_args = array(
		's' => $request['s'],
		'post_type' => 'school',
		'post_status' => 'publish',
		'posts_per_page' => 10,
		'paged' => $request['paged'],
		'category_name' => $request['cat'],
		'orderby' => 'title',
		'order' => 'ASC',
		'post__not_in' => array_merge( $linked_schools, array($post_id) )
	);
	if ( $tax_query )
		$query_args['tax_query'] = $tax_query;
	$wp_query->query( $query_args );

	fbk_thickbox_filter( array(
		'query_args' => array( 'post_id' => $post_id, 'tab' => 'type', 'type' => 'school_object_share' ),
		'filter' => array( 's' => $request['s'] ),
		'taxonomies' => array( 'cat' => $request['cat'], 'lang' => $request['lang'], 'loc' => $request['loc'] )
	), array('current'=>$request['paged']) );

if ( have_posts() ) : ?>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php?type=school_object_share&tab=type") ?>" class="school-object-share">
<?php foreach ( $request as $key => $field ) 
	echo "<input type='hidden' name='$key' value='$field'>"; ?>
<?= $nonce ?>
<table class="form-table fbk-searchresults">
<thead><tr><th>Schulname</th><th><?php _e('Category'); ?><th>Sprache</th><th>Ort</th></tr></thead>
<tbody>
<?php while ( have_posts() ) :
	the_post();
	$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
	foreach ( wp_get_object_terms( $post->ID, array_keys( $terms ) ) as $term )
		$terms[$term->taxonomy] = $term;
	$selection[] = array( 't' => $post->post_title, 'c' => $terms['category'] ? $terms['category']->slug : '' );
?>
<tr <?php if ( $terms['category'] ) echo "class='c-{$terms['category']->slug}'"; ?> id="hs-<?= $post->ID ?>">
	<td><?= $post->post_title ?></td>
	<?php	foreach ( $terms as $term )
			if ( $term )
				echo "<td>$term->name</td>";
			else
				echo "<td>&nbsp;</td>";
	?>
</tr>
<tr <?php if ( $terms['category'] ) echo "class='c-{$terms['category']->slug}'"; ?> style="display:none;">
	<td colspan="4">
<?php
	foreach ( $fbk_cf_boxes as $object_type => $box ) {
		if ( ! empty($box['shared']) ) {
			if ( $all_objects = fbk_get_school_meta( $post->ID, $object_type ) ) {
				echo "<div class='share-objects-list'><h4><input type='checkbox'> $box[title]:</h4><ul>";
				foreach ( $all_objects as $object_id => $object ) {
					echo "<li><label for='share-$post->ID-$object_type-$object_id'>",
					"<input type='checkbox' id='share-$post->ID-$object_type-$object_id' name='{$object_type}[]' value='$object_id'> ",
					fbk_cf_get_meta_object_name( $object_type, $object ) . "</label></li>";
				}
				echo "</ul></div>";
			}
		}
	}
?>
	</td>
</tr>
<?php endwhile; ?>
</tbody>
<tfoot><tr><td colspan="4"><input type="submit" class="button-primary" value="Gewählte Objekte der aktuellen Schule beifügen" name="dolink"></td></tr></tfoot>
</table>
</form>
<?php else : ?>
<div class="updated"><p>Keine Resultate.</p></div>
<?php endif; ?>
</form>
<script>
jQuery(function($){
	$('tr[id]').click(function(){
		var n = $(this).next();
		if ( n.is(':visible') )
			n.hide();
		else
			n.show();
	});
	$('h4 :checkbox').change(function(){
		$(this).closest('h4').next().find(':checkbox').prop('checked', $(this).prop('checked'));
	});
});
</script>
</div>
<?php
}
?>
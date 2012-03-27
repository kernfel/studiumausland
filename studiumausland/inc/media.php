<?php
/**
 * @package Studium_Ausland
 */

add_action( 'attachment_fields_to_edit', 'fbk_attachment_fields_edit', 10, 2 );
add_action( 'attachment_fields_to_save', 'fbk_attachment_fields_save', 10, 2 );
add_filter( 'media_upload_tabs', 'fbk_add_media_upload_tabs' );
add_action( 'media_upload_hijack', 'fbk_media_upload_hijack' );

function fbk_attachment_fields_edit( $form_fields, $post ) {
	global $fbk_cf_slices;
	$field_name = "attachments[$post->ID][fbk_use_attachment_in]";
	$form_fields['fbk_use_attachment_in'] = array(
		'label' => 'Anzeige in Sektion',
		'input' => 'html',
		'html' => "<select name='$field_name' id='$field_name'>"
	);
	$selection = get_post_meta( $post->ID, apply_filters( 'fbk_use_att_in', '_fbk_use_attachment_in' ), true );
	if ( ! $selection )
		$selection = FBK_GALLERY_SLUG;
	$locations = array(
		FBK_GALLERY_SLUG => stripslashes(get_option( 'fbk_slice_label_' . FBK_GALLERY_SLUG )),
		FBK_DESC_SLUG => stripslashes(get_option( 'fbk_slice_label_' . FBK_DESC_SLUG )),
		'_none' => 'Keine automatische Anzeige'
	);
	foreach ( $locations as $slug => $label ) {
		if ( $selection == $slug )
			$sel = ' selected="selected"';
		else
			$sel = '';
		$form_fields['fbk_use_attachment_in']['html'] .= "<option$sel value='$slug'>$label</option>";
	}
	$form_fields['fbk_use_attachment_in']['html'] .= '</select>';
	return $form_fields;
}

function fbk_attachment_fields_save( $post, $attachment ) {
	if ( isset( $attachment['fbk_use_attachment_in'] ) )
		update_post_meta( $post['ID'], apply_filters( 'fbk_use_att_in', '_fbk_use_attachment_in' ), $attachment['fbk_use_attachment_in'] );
	return $post;
}

function fbk_add_media_upload_tabs( $tabs ) {
	$tabs['hijack'] = 'Von einer anderen Schule';
	if ( ! empty($_REQUEST['post_id']) && get_post_meta( $_REQUEST['post_id'], '_fbk_gallery_hijack', true ) )
		$tabs['hijack'] .= ' (aktiv)';
	return $tabs;
}

function fbk_media_upload_hijack() {
	$errors = array();
	
	add_filter( 'attachment_fields_to_edit', 'fbk_media_upload_hijack_fields_edit', 10, 2 );
	add_filter( 'attachment_fields_to_save', 'fbk_media_upload_hijack_fields_save', 10, 2 );
	add_filter( 'fbk_use_att_in', create_function( '$a', 'return $a . "_" . $_REQUEST["post_id"];' ) );

	if ( ! empty( $_POST ) )
		$errors = fbk_media_upload_hijack_handler();
	
	wp_enqueue_script( 'admin-gallery' );
	wp_iframe( 'media_upload_fbk_hijack_form', $errors );
}

function fbk_media_upload_hijack_handler() {
	$errors = array();

	$post_id = (int) $_REQUEST['post_id'];
	
	if ( ! check_admin_referer('media-form') || ! current_user_can( 'edit_posts' ) )
		return $errors;

	if ( ! empty($_POST['school']) ) {
		$previous_hijack = get_post_meta( $post_id, '_fbk_gallery_hijack' );
		if ( $previous_hijack ) {
			$attachments = get_children( array( 'post_type' => 'attachment', 'post_parent' => $previous_hijack ) );
			foreach ( $attachments as $att_id => $attachment ) {
				delete_post_meta( $att_id, "_fbk_menu_order_$post_id" );
				delete_post_meta( $att_id, "_fbk_use_attachment_in_$post_id" );
			}
		}
		update_post_meta( $post_id, '_fbk_gallery_hijack', $_POST['school'] );
	}

	if ( ! empty($_POST['attachments']) ) {
		if ( empty($_POST['end_hijack']) ) {
			$errors = media_upload_form_handler();
		} else {
			$hijack_id = get_post_meta( $post_id, '_fbk_gallery_hijack', true );
			$attachments = get_children( array( 'post_type' => 'attachment', 'post_parent' => $hijack_id ) );
			foreach ( $attachments as $att_id => $attachment ) {
				delete_post_meta( $att_id, "_fbk_menu_order_$post_id" );
				delete_post_meta( $att_id, "_fbk_use_attachment_in_$post_id" );
			}
			delete_post_meta( $post_id, '_fbk_gallery_hijack' );
		}
	}

	return $errors;
}

function fbk_media_upload_hijack_fields_edit( $form_fields, $att ) {
	$post_id = $_REQUEST['post_id'];
	if ( isset($form_fields['menu_order']) ) {
		$_hijack_order = get_post_meta( $att->ID, '_fbk_menu_order_' . $post_id, true );
		if ( ! empty($_hijack_order) )
			$form_fields['menu_order']['value'] = $_hijack_order;
	}
	return $form_fields;
}

function fbk_media_upload_hijack_fields_save( $att, $rq_attachment ) {
	$post_id = (int) $_REQUEST['post_id'];
	$_att =& get_post( $att['ID'], ARRAY_A );

	if ( isset( $att['menu_order'] ) ) {

		// Use the order for the hijacker...
		$_hijacked_order = get_post_meta( $att['ID'], '_fbk_menu_order_' . $post_id, true );
		if ( $att['menu_order'] != $_hijacked_order )
			update_post_meta( $att['ID'], '_fbk_menu_order_' . $post_id, $att['menu_order'] );

		// ... but don't change anything for the original.
		$att['menu_order'] = $_att['menu_order'];
	}

	return $att;
}

function media_upload_fbk_hijack_form( $errors ) { // This function needs "media" at strpos(0) for arcane reasons
	global $wp_query, $wp_the_query, $post, $redir_tab;

	media_upload_header();
?>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php") ?>" class="media-upload-form validate" id="hijack-gallery">
<h3 class="media-title">Galerie einer anderen Schule übernehmen</h3>
<p>Du kannst zusätzlich zur schuleigenen Galerie die Galerie einer anderen Schule anzeigen lassen.</p>
<p>Beachte bitte: Änderungen, die du an den Bildern hier vornimmst, werden auch in der ursprünglichen Galerie übernommen.
Davon ausgenommen sind einzig die <i>Reihenfolge</i> und der <i>Anzeigeort</i>; diese werden für jede Schule separat gespeichert.</p>
<?php
	$nonce = wp_nonce_field( 'media-form', '_wpnonce', true, false );

	$post_id = (int) $_REQUEST['post_id'];

	$request = @array(
		's' => $_REQUEST['s'],
		'paged' => $_REQUEST['paged'],
		'cat' => $_REQUEST['cat'],
	);

	$hijack_id = get_post_meta( $post_id, '_fbk_gallery_hijack', true );
	if ( ! empty($hijack_id) ) :
		$atts =& $wp_the_query->query( array(
			'post_type' => 'attachment',
			'post_parent' => $hijack_id,
			'post_status' => 'inherit',
			'nopaging' => true
		));

		/* Need to sort manually, because I can't sort by meta_value without querying for meta_key "_fbk_menu_order_$post_id",
		 * but that meta may not be set for any of the attachments (newly linked gallery) or - even worse - for some of them
		 * (updated, but previously linked gallery).
		 */
		$GLOBALS['_fbk_usort_pid'] = $post_id;
		usort( $atts, '_fbk_usort_by_menu_order_x' );
		unset( $GLOBALS['_fbk_usort_pid'] );

		$redir_tab = 'gallery'; // To make menu_order inputs show up
?>
<script type="text/javascript">
<!--
jQuery(function($){
	var preloaded = $(".media-item.preloaded");
	if ( preloaded.length > 0 ) {
		preloaded.each(function(){prepareMediaItem({id:this.id.replace(/[^0-9]/g, '')},'');});
		updateMediaForm();
	}
});
-->
</script>
<table class="widefat" cellspacing="0">
<thead><tr>
<th><?php _e('Media'); ?></th>
<th class="order-head"><?php _e('Order'); ?></th>
<th class="actions-head"><?php _e('Actions'); ?></th>
</tr></thead>
</table>
<?php add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2); ?>
<input type="hidden" name="post_id" id="post_id" value="<?= $post_id; ?>" />
<input type="hidden" name="tab" value="hijack">
<?php foreach ( $request as $key => $field ) 
	echo "<input type='hidden' name='$key' value='$field'>"; ?>
<?= $nonce ?>
<div id="media-items">
<?= get_media_items( false, $errors ); ?>
</div>

<p class="ml-submit">
 <?php submit_button( 'Zuordnung zu dieser Galerie löschen', 'button delete', 'end_hijack', false, array( 'onclick' => 'return confirm("Möchtest du diese Zuordnung wirklich löschen?")' ) ); ?>
 <?php submit_button( __( 'Save all changes' ), 'button savebutton', 'save', false, array( 'id' => 'save-all', 'style' => 'display: none;' ) ); ?>
</p>

<?php	endif; ?>
</form>
<?php
	$wp_query->query( array(
		's' => $request['s'],
		'post_type' => 'school',
		'post_status' => 'any',
		'posts_per_page' => 10,
		'paged' => $request['paged'],
		'cat' => $request['cat'],
		'post__not_in' => array( $post_id ),
		'orderby' => 'title',
		'order' => 'ASC'
	));
?>
<form id="filter" action="<?= admin_url("media-upload.php") ?>" method="get">
<input type="hidden" name="post_id" value="<?= $post_id ?>">
<input type="hidden" name="tab" value="hijack">
<p class="search-box">
<select name="cat"><option>
<?php
	foreach ( get_categories() as $cat ) {
		$sel = $cat->term_id == $request['cat'] ? ' selected="selected"' : '';
		echo "<option$sel value='$cat->term_id'>$cat->name</option>";
	}
?>
</select>
<input type="text" name="s" placeholder="Schulname" value="<?= esc_attr( $request['s'] ) ?>">
<input type="submit" class="button" value="Suchen">
</p>
<?php if ( $wp_query->max_num_pages > 1 ) : ?>
<div class="tablenav"><div class="tablenav-pages">
<?php
	$paged = $wp_query->get('paged');
	echo paginate_links( array(
		'base' => add_query_arg( 'paged', '%#%' ),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => $wp_query->max_num_pages,
		'current' => $paged ? $paged : 1,
	));
?>
</div></div>
<?php endif; ?>
</form>
<div id="media-hijack">
<?php
if ( have_posts() ) : ?>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php") ?>" class="media-upload-form validate" id="hijack-schools">
<input type="hidden" name="post_id" id="post_id" value="<?= $post_id; ?>" />
<input type="hidden" name="tab" value="hijack">
<?php foreach ( $request as $key => $field ) 
	echo "<input type='hidden' name='$key' value='$field'>"; ?>
<?= $nonce ?>
<table class="form-table fbk-searchresults"><tbody>
<tr><th>Verwenden</th><th>Schulname</th><th><?php _e('Category'); ?></th><th>Vorschau</th></tr>
<?php
 while ( have_posts() ) :
	the_post();
	$mini_thumb = fbk_get_first_gallery_image( $post->ID, array(119,57), false, true );
	$cats = get_the_category();
?>
<tr <?php if ( count($cats) ) echo "class='c-{$cats[0]->slug}'"; ?>>
 <td>
  <input type="radio" name="school" id="school-<?= $post->ID ?>" value="<?= $post->ID ?>">
 </td>
 <td><?php the_title("<label for='school-$post->ID'>", "</label>") ?></td>
 <td><?php if ( count($cats) )  echo $cats[0]->name; ?></td>
 <td><?= $mini_thumb ? $mini_thumb : "Keine eigene Galerie" ?></td>
</tr>
<?php
 endwhile; ?>
</tbody>
<tfoot><tr><td colspan="4"><input type="submit" value="Zuordnen" class="button"></td></tr></tfoot>
</table>
</form>
<?php else : ?>
<div class="updated"><p>Keine Resultate.</p></div>
<?php endif; ?>
</div>
<?php
}
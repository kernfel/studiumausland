<?php
/*
 * @package Studium_Ausland
 */

function fbk_register_offer_metaboxes() {
	add_meta_box( 'fbk_offer_runtime', 'Laufzeit', 'fbk_offer_runtime_metabox', 'offer', 'side', 'high' );
}

function fbk_offer_runtime_metabox( $post ) {
	if ( ! post_type_supports( $post->post_type, 'f_runtime' ) )
		return;
	
	$offer = array(
		'start' => get_post_meta( $post->ID, '_fbk_offer_start', true ),
		'end' => get_post_meta( $post->ID, '_fbk_offer_end', true ),
	);

	wp_nonce_field( 'fbk_offer_runtime', 'fbk_add_offer_runtime' );
?>
<label for="fbk_offer_start">Von:</label> <input type="date" id="fbk_offer_start" name="fbk_offer_start" value="<?= fbk_date( $offer['start'], 'd.m.Y' ) ?>"><br>
<label for="fbk_offer_end">Bis:</label> <input type="date" id="fbk_offer_end" name="fbk_offer_end" value="<?= fbk_date( $offer['end'], 'd.m.Y' ) ?>"><br>
<?php
}

add_action( 'save_post', 'fbk_offer_save', 10, 2 );
function fbk_offer_save( $post_id, $post ) {
	if ( ! post_type_supports( $post->post_type, 'f_runtime' ) )
		return;
	if ( ! current_user_can( 'edit_posts', $post_id ) )
		return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return;
	if ( ! isset($_POST['fbk_add_offer_runtime']) || ! wp_verify_nonce( $_POST['fbk_add_offer_runtime'], 'fbk_offer_runtime' ) )
		return;

	foreach ( array( 'start', 'end' ) as $field ) {
		$previous = get_post_meta( $post_id, '_fbk_offer_' . $field, true );
		$new = isset($_POST['fbk_offer_'.$field]) ? fbk_date( $_POST['fbk_offer_'.$field], 'Y-m-d' ) : '';
		if ( $previous != $new ) {
			update_post_meta( $post_id, '_fbk_offer_'.$field, $new );
			if ( $previous ) {
				$t_previous = strtotime($previous);
				wp_unschedule_event( $t_previous, "fbk_offer_$field", array($post_id) );
			}
			if ( $new ) {
				$t_new = strtotime($new);
				wp_schedule_single_event( $t_new, "fbk_offer_$field", array($post_id) );
			}
			if ( $previous && $new && 'publish' == $post->post_status ) {
				$t = time();
				if ( $t_previous > $t && $t_new < $t ) {
					if ( 'start' == $field )
						do_action( 'fbk_offer_start', $post_id );
					elseif( 'end' == $field )
						do_action( 'fbk_offer_end', $post_id );
				} elseif ( $t_previous < $t && $t_new > $t && 'end' == $field ) {
					do_action( 'fbk_offer_start', $post_id );
				}
			}
		}
	}

	$is_public = fbk_is_public_offer($post_id);
	$was_public = fbk_was_public_offer($post_id);
	if ( $is_public && ! $was_public )
		do_action( 'fbk_offer_start', $post_id );
	elseif ( $was_public && ! $is_public )
		do_action( 'fbk_offer_end', $post_id );
}

function fbk_is_public_offer( $post_id ) {
	$p = get_post( $post_id );
	return ( 'offer' == $p->post_type && 'publish' == $p->post_status && strtotime(get_post_meta($post_id,'_fbk_offer_end',true)) > time() );
}
function fbk_was_public_offer( $post_id, $prime = null ) {
	static $was_public = array();
	if ( null !== $prime )
		$was_public[$post_id] = $prime;
	return @$was_public[$post_id];
}
add_action( 'post_updated', 'fbk_was_public_offer_primer', 10, 3 );
function fbk_was_public_offer_primer( $post_id, $post, $post_before ) {
	fbk_was_public_offer( $post_id,
		'offer' == $post->post_type && 'publish' == $post_before->post_status && strtotime(get_post_meta($post_id,'_fbk_offer_end',true)) > time()
	);
}

?>
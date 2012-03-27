<?php
/**
 * @package Studium_Ausland
 */

global $post, $post_id, $slice, $fbk_cf_prefix;
if ( ! $post_id )
	$post_id = $post->ID;

$leisure = get_post_meta( $post_id, $fbk_cf_prefix . 'leisure', true );

echo apply_filters( 'the_content', $leisure );

$slice = FBK_LEISURE_SLUG;
get_template_part( 'services' );
?>
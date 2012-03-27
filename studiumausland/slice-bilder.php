<?php
/**
 * @package Studium_Ausland
 */

global $post, $post_id, $fbk;
if ( ! $post_id )
	$post_id = $post->ID;

if ( get_theme_support( 'post-thumbnails' ) && $post_thumbnail_id = get_post_thumbnail_id() )
	$exclude = $post_thumbnail_id;
else
	$exclude = -1;

if ( $gallery_images = fbk_get_images( $post_id, FBK_GALLERY_SLUG ) ) {
	echo "<div id='gallery-$post_id' class='gallery'>";
	foreach ( $gallery_images as $key => $img )
		if ( $img->ID != $exclude ) {
			echo '<div class="gallery-item">', wp_get_attachment_link( $img->ID ), '</div>';
		}
	echo "</div>";
}
?>
<?php
/**
 * @package Studium_Ausland
 */

get_header(); ?>
<?= wp_get_attachment_image( $post->ID, 'full', false, array('style' => 'margin:1em auto;display:block;') ) ?>
<?php if ( $post->post_parent ) :
	$parent =& get_post( $post->post_parent );
?>
<nav class="archive-nav">
<a href="<?= get_permalink( $parent->ID ) ?>" class="nav-prev">« Zurück zu <?= apply_filters( 'the_title', $parent->post_title ) ?></a>
</nav>
<?php endif; ?>
<?php get_footer(); ?>
<?php
/**
 * @package Studium_Ausland
 * Template Name: Seite ohne Titelanzeige
 */

global $post;

the_post();

get_header(); ?>
<div id="contentwrap"><section id="content">
<?php if ( is_front_page() ) : ?>
<script>fbk.state={object:'index',id:0,cat:0};</script>
<?php else : ?>
<script>fbk.state={object:'page',id:'<?= $post->post_name ?>',cat:0};</script>
<?php endif; ?>
<?php get_template_part( 'content', 'page-noheading' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
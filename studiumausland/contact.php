<?php
/**
 * @package Studium_Ausland
 * Template Name: Kontaktformular
 */

global $post;

the_post();

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'page',id:'<?= $post->post_name ?>',cat:0};</script>
<?php get_template_part( 'content', 'contact' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>

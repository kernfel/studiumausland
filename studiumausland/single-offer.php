<?php
/**
 * @package Studium_Ausland
 */

global $post;

the_post();

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'offer',id:<?= $post->ID ?>,cat:0};</script>
<?php get_template_part( 'content', 'offer' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
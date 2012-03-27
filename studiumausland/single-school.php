<?php
/**
 * @package Studium_Ausland
 */

global $post;
$cats = get_the_category();

the_post();

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'school',id:<?= $post->ID ?>,cat:'<?= $cats[0]->term_id ?>'};</script>
<?php get_template_part( 'content', 'school' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
<?php
/**
 * @package Studium_Ausland
 */

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'page',id:404,cat:0};</script>
<?php get_template_part( 'content', '404' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
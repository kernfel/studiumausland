<?php
/**
 * @package Studium_Ausland
 */

global $fbk_query;

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'cat',id:<?= $fbk_query->term->term_id ?>,cat:'<?= $fbk_query->term->term_id ?>'};</script>
<?php get_template_part( 'content', 'category' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
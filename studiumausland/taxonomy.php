<?php
/**
 * @package Studium_Ausland
 */

global $fbk_query;

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'loc',id:<?= $fbk_query->term->term_id ?>,cat:'<?= $fbk_query->category->term_id ?>'};</script>
<?php get_template_part( 'content', $fbk_query->detailed_taxonomy ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
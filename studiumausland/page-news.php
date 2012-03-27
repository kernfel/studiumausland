<?php
/**
 * @package Studium_Ausland
 */

global $fbk, $paged;
$fbk->do_news_query( $paged );

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'news',id:<?= $paged ? $paged : 1 ?>,cat:0};</script>
<?php get_template_part( 'content', 'news' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
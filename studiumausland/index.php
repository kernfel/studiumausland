<?php
/**
 * The main template file. At current, this silently redirects to the extended search form.
 *
 * @package Studium_Ausland
 */

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'search',id:'<?= $_SERVER['QUERY_STRING'] ?>',cat:0};</script>
<?php get_search_form(); ?>
<div id="searchresults"></div>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>
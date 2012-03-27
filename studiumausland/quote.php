<?php
/*
Template Name: Kostenvoranschlag
*/
/**
 * @package Studium_Ausland
 */

the_post();

global $pdf, $u, $q, $fbk_quotes;

if ( ! empty($pdf) ) {
	if ( $user = $fbk_quotes->get_user( $u, $q ) ) {
		$quote = $fbk_quotes->get_quote( $pdf, $user->user_id );
		if ( $quote ) {
			$fbk_quotes->get_quote_pdf( $quote, $user );
			return;
		} else {
			header( 'HTTP/1.1 404 Not Found', true, 404 );
			die( 'Fehler: Kostenvoranschlag nicht gefunden.' );
		}
	}
}

get_header(); ?>
<div id="contentwrap"><section id="content">
<script>fbk.state={object:'page',id:'<?= $post->post_name ?>',cat:0};</script>
<?php get_template_part( 'content', 'quote' ); ?>
</section></div>
<?php
get_sidebar( 'left' );
get_sidebar( 'right' );
get_footer();
?>

<?php
/**
 * @package Studium_Ausland
 */

global $post, $post_id, $fbk_cf_prefix, $fbk_cf_boxes;
if ( ! $post_id )
	$post_id = $post->ID;

$leisure = get_post_meta( $post_id, $fbk_cf_prefix . 'leisure', true );
$services = fbk_get_school_meta( $post_id, 'fees' );
$linked_schools = get_post_meta( $post_id, '_school_connect' );

if ( $leisure )
	echo '<h3>Freizeitangebot</h3>',
	str_replace( array( '<h3', '</h3>', '<h2', '</h2>' ), array( '<h4', '</h4>', '<h3', '</h3>' ), apply_filters( 'the_content', $leisure ) );


if ( count($services) ) :
?>
<section class='services'>
 <h3>Zusätzliche Leistungen</h3>
 <table>
  <thead>
   <tr>
    <th>Art der Leistung</th>
    <th>Preis</th>
    <th>Zahlungsart</th>
   </tr>
  </thead>
  <tbody>
<?php			foreach ( $services as $svc ) : ?>
   <tr>
    <th><?php
	echo $svc['key'];
	if ( $svc['desc'] )
		echo "<br><i>" . $svc['desc'] . "</i>";
    ?></th>
<?php if ( empty($svc['cost']) ) : ?>
    <td colspan="2" class="last">Gratis!</td>
<?php else : ?>
    <td><?= $svc['cost'] . ' ' . fbk_tt_get_currency($post_id) ?></td>
    <td class="last"><?= $fbk_cf_boxes['fees']['fields']['type']['opts'][$svc['type']] ?></td>
<?php endif; ?>
   </tr>
<?php			endforeach; ?>
  </tbody>
 </table>
</section>
<?php endif;


if ( count($linked_schools) ) :
?>
<section class='linked_schools'>
 <h3>Verwandte Schulen</h3>
<?php
$schools = new WP_Query( array('post__in' => $linked_schools, 'post_type' => 'school', 'posts_per_page' => -1, 'orderby' => 'none') );
for ( $left = true; $schools->have_posts(); $left = ! $left ) {
	$schools->the_post();
	$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
	foreach ( wp_get_object_terms( get_the_ID(), array_keys( $terms ) ) as $term )
		$terms[$term->taxonomy] = $term;

	if ( $left )
		echo "<div class='double'>";
	echo "<article class='" . ( $left ? 'left' : 'right' ) . " teaser " . ( $terms['category'] ? "c-" . $terms['category']->slug : "" ) . "'><header>";
	the_title( '<h2>', '</h2>' );
	$keywords = array();
	foreach ( $terms as $tax => $term )
		if ( $term )
			$keywords[] = $term->name;
	echo "<div class='entry-meta'>", implode( ' | ', $keywords ), "</div>";
	
	$link_open_tag = '<a href="' . get_permalink() . '" title="' . get_the_title() . '" class="school-' . $post->ID . '">';

	if ( $thumbnail = fbk_get_first_gallery_image() )
		echo $link_open_tag . $thumbnail . '</a>';
	
	the_excerpt();
	
	echo '<div class="link">' . $link_open_tag . 'Erfahren Sie mehr</a></div>';
	echo '</article>' . ( $left ? '' : '</div>' );
}
if ( ! $left ) echo '</div>';

wp_reset_postdata();
?>
</section>
<?php endif; ?>
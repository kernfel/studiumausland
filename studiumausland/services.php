<?php
/**
 * @package Studium_Ausland
 */

global $fbk_cf_boxes, $post, $post_id, $slice;

if ( ! $post_id )
	$post_id = $post->ID;

$services = fbk_get_school_meta( $post_id, 'fees' );

foreach ( $services as $key => $svc )
	if ( $svc['slice'] != $slice || isset($svc['disp']) && ! (1 & $svc['disp']) )
		unset ( $services[$key] );

if ( count( $services ) ) :
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
   <tr id="f-<?= $svc['meta_id'] ?>">
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

<?php endif; ?>

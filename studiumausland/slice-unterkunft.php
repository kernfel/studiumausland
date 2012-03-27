<?php
/**
 * @package Studium_Ausland
 *
 * Table markup is in reduced HTML syntax (HTML5 non-XHTML) for ease of maintenance
 */

global $post, $post_id, $fbk_cf_boxes;
if ( ! $post_id )
	$post_id = $post->ID;

$accommodations = fbk_get_school_meta( $post_id, 'accommodation' );
$types = $fbk_cf_boxes['accommodation']['fields']['type']['opts']; //Shortcut
$rooms = array(
	's' => 'Einzel',
	'd' => 'Doppel',
	't' => 'Zweibett',
	'm' => 'Mehrbett'
);
$boards = array(
	'sc' => 'Ohne Verpflegung',
	'br' => 'Frühstück',
	'hb' => 'Halbpension',
	'fb' => 'Vollpension'
);
$fromto = array( 'from', 'to' );
$currency = fbk_tt_get_currency( $post_id );

foreach ( $accommodations as $acc ) :

?>
<section id="a-<?= $acc['meta_id'] ?>" class="foldout">
 <header><h2><?= $acc['name'] ? wptexturize($acc['name']) : $types[$acc['type']] ?></h2><span class="tag"><?= $types[$acc['type']] ?></span></header>
 <div class="foldout-outer"><div class="foldout-inner">
 <?= apply_filters( 'the_content', $acc['desc'] ) ?>
<?php

if ( empty( $acc['cost'] ) ) : ?>
 <table><tr><td>Preise für diese Unterkunft erhalten Sie auf Anfrage. Füllen Sie dazu einfach das Anmeldeformular aus, schreiben Sie eine E-Mail oder rufen Sie uns an!</table>
<?php

else :
	$min_prices = array();
	$periods = array();
	foreach ( $acc['cost'] as $period => $complex )
		if ( 'add' != $complex['calc'] ) {
			foreach ( $complex['values'] as $room => $row )
				foreach ( $row as $board => $col ) {
					if ( 'tot' == $complex['calc'] )
						foreach ( $col as $k => $v )
							$col[$k] = $v / ($k+1);
					$min = min( array_filter( $col ) );
					if ( ! isset( $min_prices[$room][$board] ) || $min < $min_prices[$room][$board] )
						 $min_prices[$room][$board] = $min;
					$periods[$room][$board][] = $period;
				}
			foreach ( $fromto as $ft )
				if ( $complex[$ft] )
					$acc['cost'][$period][$ft] = strtotime( $complex[$ft] );
		}
	fbk_f_matrix_normalize( $min_prices, false ); ?>
 <table class='acc-cost'>
  <thead>
   <tr><th colspan='<?= ( count($min_prices) + 1 ) ?>'><?= empty($acc['name']) ? '' : $acc['name'].' – ' ?>Mindestpreise pro Person &amp; Woche</th></tr>
  </thead>
  <tbody>
   <tr><th scope="row">Zimmer:</th><?php
	foreach ( $min_prices as $r => $col )
		echo "<th>$rooms[$r]</th>"; ?>
   </tr>
<?php
	$TRs = array();
	$first_col = true;
	foreach ( $min_prices as $r => $col ) {
		foreach ( $col as $b => $cell ) {
			if ( $first_col )
				$TRs[$b] = "<tr><th>$boards[$b]</th>";
			if ( $cell ) {
				if ( is_float($cell) )
					$cell = number_format( $cell, 2, ',', '' );
				$TRs[$b] .= "<td>$cell $currency";
				$min = PHP_INT_MAX;
				$max = 1;
				foreach ( $periods[$r][$b] as $period ) {
					if ( ! $acc['cost'][$period]['from'] )
						$min = 0;
					elseif ( $min && $acc['cost'][$period]['from'] < $min )
						$min = $acc['cost'][$period]['from'];
					if ( ! $acc['cost'][$period]['to'] )
						$max = 0;
					elseif ( $max && $acc['cost'][$period]['to'] > $max )
						$max = $acc['cost'][$period]['to'];
						
				}
				if ( $min && $max && ! ( date('j.n',$min) == '1.1' && date('j.n',$max) == '31.12' ) )
					$TRs[$b] .= ' <i>(' . fbk_date( $min, 'j. M' ) . ' bis ' . fbk_date( $max, 'j. M' ) . ')</i>';
				$TRs[$b] .= "</td>";
			} else {
				$TRs[$b] .= "<td>&ndash;</td>";
			}
		}
		$first_col = false;
	}
	echo implode( '</tr>', $TRs ) . "</tr>";
?>
  </tbody>
  <tfoot>
   <tr><th>Vermittlungsgebühr</th><td colspan='<?= count($min_prices) ?>'>
    <?= empty($acc['fee']) ? 'Keine!' : $acc['fee'] . ' ' . $currency ?></td>
   </tr>
  </tfoot>
 </table>
<?php endif; ?>
 </div></div>
</section>
<?php
endforeach; // $accommodations
?>
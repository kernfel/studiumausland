<?php
/**
 * @package Studium_Ausland
 */

global $post, $post_id, $fbk, $footer_open, $months_opts;
if ( ! $post_id )
	$post_id = $post->ID;

if ( in_the_loop() )
	the_content();
else
	echo apply_filters( 'the_content', $post->post_content );

if ( $desc_images = fbk_get_images( $post->ID, FBK_DESC_SLUG ) )
	foreach ( $desc_images as $img )
		echo "<div class='head'>", wp_get_attachment_image( $img->ID, 'medium', false, array( 'class' => '' ) ), '</div>';



$currency = fbk_tt_get_currency( $post_id );

$courses = fbk_get_school_meta( $post_id, 'courses' );
if ( $courses ) :
	echo "<div class='courses'>";
	foreach ( $courses as $course ) :
		$course_tags = $matfees = $tuitions = $intervals = $_tuitions = $_intervals = $intervals_verbal = array();
		$is_semester = $matfee_included = false;
		$cols = 0;

		if ( $course['bu'] )
			$course_tags[] = 'Bildungsurlaub';
		if ( $course['tag'] ) foreach ( explode(',', $course['tag']) as $tag ) {
			$tag = get_term( $tag, 'course_tag' );
			$course_tags[] = $tag->name;
		}

		if ( $course_tags )
			$course_tags = "<span class='tag'>" . implode( ', ', $course_tags ) . "</span>";
		else
			$course_tags = '';
?>
<section id="c-<?= $course['meta_id'] ?>" class="foldout">
 <header><h2><?= $coursename = fbk_get_category_meta( 'school_course_heading', array( 'course' => $course ) ); ?></h2><?= $course_tags ?></header>
 <div class="foldout-outer"><div class="foldout-inner">
 <?= apply_filters( 'the_content', $course['desc'] ) ?>
 <table class="course-details">
  <thead><tr><th colspan="2"><?= $course['name'] ?> &ndash; Details
  <tbody>
<?php		if ( ! empty( $course['hpw'] ) ) : ?>
   <tr>
    <th>Umfang
    <td><?= $course['hpw'] ?> Lektionen<?php
   			if ( ! empty($course['mpl']) ) 
   				echo " à $course[mpl] Minuten";
   	?> pro Woche
<?php		endif;

		if ( ! ( empty($course['size'][0]) && empty($course['size'][1]) && empty($course['size'][2]) ) ) : ?>
   <tr>
    <th>Klassengröße
    <td><?php
		   	if ( ! empty($course['size'][0]) && ! empty($course['size'][2]) )
		   		echo $course['size'][0] . '&ndash;' . $course['size'][2] . ' Teilnehmer' . ( empty($course['size'][1])?'':' (Ø '.$course['size'][1].')' );
		   	elseif ( empty($course['size'][0]) && empty($course['size'][2]) )
		   		echo 'Ø '.$course['size'][1].' Teilnehmer';
		   	elseif ( empty($course['size'][0]) )
		   		echo 'Max. '.$course['size'][2].' Teilnehmer' . ( empty($course['size'][1])?'':' (Ø '.$course['size'][1].')' );
		   	else
		   		echo 'Min. '.$course['size'][0].' Teilnehmer' . ( empty($course['size'][1])?'':' (Ø '.$course['size'][1].')' );
		endif;

		if ( count($course['cost']) > 1 && 's' == $course['cost']['period'] ) :
			$is_semester = true;
			if ( ! empty($course['dur'][0]) || ! empty($course['dur'][1]) && ($course['dur'][0] = $course['dur'][1]) ) : ?>
   <tr>
    <th>Kursdauer
    <td><?= empty($course['dur'][1]) ? 1 : (int)($course['dur'][1] / $course['dur'][0]), ' Semester à ' . $course['dur'][0] . ' Wochen';
				if ( empty($course['dur'][1]) )
					$course['dur'] = array( 1, 1 );
				else
					$course['dur'] = array( 1, (int)($course['dur'][1] / $course['dur'][0]) );
			else :
				$course['dur'][1] = 0;
			endif;
		else :
			$is_semester = false; ?>
   <tr>
    <th>Kursdauer
    <td><?php
			if ( empty($course['dur'][0]) && empty($course['dur'][1]) )
				echo 'Ab 1 Woche';
			elseif ( empty($course['dur'][1]) )
				echo 'Ab '.$course['dur'][0].' Woche'.($course['dur'][0]==1?'':'n');
			elseif ( empty($course['dur'][0]) )
				echo '1–'.$course['dur'][1].' Wochen';
			elseif ( $course['dur'][0] == $course['dur'][1] ) {
				if ( 1 == $course['dur'][0] )
					echo '1 Woche';
				else
					echo $course['dur'][0].' Wochen';
			} else {
				echo $course['dur'][0].'&ndash;'.$course['dur'][1].' Wochen';
			}
		endif;
?>
   <tr>
    <th>Anmeldegebühr
    <td><?php
	   	if ( empty($course['fee']) )
	   		echo 'Keine!';
	   	else
	   		echo $course['fee'] . ' ' . $currency; ?>
 </table>
<?php
		if ( count($course['cost']) == 1 ) :
?>
 <table><tr><td>Preise für diesen Kurs erhalten Sie auf Anfrage. Füllen Sie dazu einfach das Anmeldeformular aus, schreiben Sie eine E-Mail oder rufen Sie uns an!</table>
<?php
		else :
			foreach ( $course['cost'] as $i => $stack ) {
				if ( 'period' === $i )
					continue;
				if ( 'mat' == $stack['type'] ) {
					$matfees = fbk_calc_getFullCol( $stack['values'], $course['dur'], $stack['calc'] );
				} else {
					$col = fbk_calc_getFullCol( $stack['values'], $course['dur'], $stack['calc'] );
					foreach ( $col as $week => $val )
						if ( ! $val )
							unset( $col[$week] );
					$_calc = 'add' == $stack['calc'] ? 'add' : 'base';
					$_tuitions[$_calc][] = array(
						'calc' => $stack['calc'],
						'col' => $col
					);
					$_intervals[$_calc][] = @array( $stack['from'], $stack['to'] );
					$cols = max( $cols, min( FBK_COURSE_PRICE_COLS, ceil( count($col)/3 ) ) );
				}
			}

			$matfee_included = ! empty($matfees);
			$num_bases = count($_tuitions['base']);

			foreach ( $_tuitions['base'] as $i => $tuition )
				foreach ( $tuition['col'] as $week => $val )
					$_tuitions['base'][$i]['col'][$week] = $val + ( $matfee_included && isset($matfees[$week]) ? $matfees[$week] : 0 );

			if ( empty( $_tuitions['add'] ) ) {
				$tuitions = $_tuitions['base'];
				foreach ( fbk_tt_get_intervals_by_timeline( $_intervals['base'] ) as $ibase )
					$intervals[ $ibase[2] ][] = array_slice( $ibase, 0, 2 ); // For consistency with multi-interval data as seen below
			} else {
				$additional_years = array();
				foreach ( $_intervals['add'] as $tuple )
					foreach ( $tuple as $datestr )
						if ( ! empty($datestr) ) {
							$d = new DateTime( $datestr );
							$additional_years[] = $d->format( 'Y' );
						}
				$intervals_base = fbk_tt_get_intervals_by_timeline( $_intervals['base'], false, $additional_years ); // Opaque
				$intervals_all = fbk_tt_get_intervals_by_timeline( array_merge( $_intervals['base'], $_intervals['add'] ), true ); // Transparent
				foreach ( $intervals_all as $tuple ) {
					$found_base = false;
					foreach ( $intervals_base as $base_tuple ) // Find the base that applies during the $tuple interval
						if ( $tuple[0] >= $base_tuple[0] && $tuple[0] < $base_tuple[1] ) {
							$found_base = true;
							break;
						}
					if ( ! $found_base ) // Discard baseless intervals
						continue;
					foreach ( $tuple[2] as $k => $i ) // Discard irrelevant bases
						if ( $i < $num_bases && $i != $base_tuple[2] )
							unset( $tuple[2][$k] );
					sort( $tuple[2] );
					$tkey = implode( ',', $tuple[2] );
					if ( ! array_key_exists( $tkey, $tuitions ) ) { // Math's not done yet
						$compound = $_tuitions['base'][ $base_tuple[2] ]; // Use the relevant base
						foreach ( $tuple[2] as $i )
							if ( $i != $base_tuple[2] ) // Add all calc=add cols
								foreach ( array_keys( $compound['col'] ) as $week )
									$compound['col'][$week] += @$_tuitions['add'][$i - $num_bases]['col'][$week];
						$tuitions[$tkey] = $compound;
					}
					$intervals[$tkey][] = array_slice( $tuple, 0, 2 );
				}
			}

			foreach ( $intervals as $k => $interval_array ) {
				$verbal = array();
				foreach ( $interval_array as $tuple )
					$verbal[] = fbk_date( (int)$tuple[0]->format('U'), 'j. M Y' ) . ' bis ' . fbk_date( (int)$tuple[1]->format('U'), 'j. M Y' );
				$intervals_verbal[$k] = implode( ', ', $verbal );
			}

			$is_multi_table = count($tuitions) > 1;

?>
 <table class="course-cost">
  <thead><tr><th colspan="<?= 2 * $cols + ( $is_multi_table ? 1 : 0 ) ?>"><?= $course['name'] ?> &ndash; Preise<?php
			if ( $matfee_included )
				echo " inkl. Materialgebühren";
  ?></th></tr></thead>
<?php
			foreach ( array_keys( $intervals ) as $i ) :
				$prices = $tuitions[$i]['col'];
				$firstweek = array_shift( array_keys( $prices ) );
				$prices_chunked = array_chunk_vertical( $prices, $cols, true );
				$rows = count( $prices_chunked );
				$j = 0;
?>
  <tbody>
<?php
				if ( $is_multi_table ) : ?>
   <tr><th colspan="<?= 2 * $cols + ( $is_multi_table ? 1 : 0 ) ?>" scope="rowgroup"><?= $intervals_verbal[$i] ?></th></tr>
<?php				endif;

				foreach ( $prices_chunked as $k => $row ) : ?>
   <tr>
<?php
					if ( $is_multi_table && ! $k ) echo "<th rowspan='" . count($prices_chunked) . "' scope='rowgroup' class='tbody-sidebar'>&nbsp;</th>";
					foreach ( $row as $week => $price ) :
						$week++;
						$j++;
						if ( is_float( $price ) )
							$price = number_format( $price, 2, ',', '' );
?>
    <th><?php					if ( $is_semester )
				    			echo "$week Semester";
				    		else
				    			echo "$week Woche" . ( 1 != $week ? "n" : "" ); ?></th>
    <td><?= $price . ' ' . $currency ?></td>
<?php
					endforeach; // $row

					if ( $j = $j % $cols ) : ?>
    <td class="empty" colspan="<?= 2*($cols-$j) ?>">&nbsp;</td>
<?php					endif; ?>
   </tr><?php
				endforeach; // $prices_chunked ?>
  </tbody>
<?php
				if ( ! $is_multi_table && array_key_exists( $i, $_intervals['base'] ) && 2 == count( array_filter( $_intervals['base'][$i] ) ) ) {
					echo "<tfoot><tr><td colspan='" . 2 * $cols . "'>Hinweis: Der Kurs ist nur verfügbar vom $intervals_verbal[$i].</td></tr></tfoot>";
				}
			endforeach; // array_keys($intervals) ?>
 </table>
<?php
		endif; // empty( $course['cost'] )

		if ( ! empty($course['shared_with']) ) {
			$list = 1 < count($course['shared_with']);
			echo $list ? "<p>Dieser Kurs ist auch in folgenden Schulen verfügbar: <ul>" : "<p>Dieser Kurs ist auch bei ";
			foreach ( $course['shared_with'] as $other_school_id ) {
				$other_school =& get_post( $other_school_id );
				foreach ( wp_get_object_terms( $other_school_id, array( 'category', 'loc' ) ) as $term )
					$terms[$term->taxonomy] = $term;
				echo ( $list ? "<li>" : "" ),
				"<a href='" . get_permalink( $other_school_id ) . "' class='ln c-" . $terms['category']->slug . " school-$other_school_id' title='"
				 . esc_attr($coursename) . " in " . $terms['loc']->name . "'>"
				 . $other_school->post_title . " in " . $terms['loc']->name
				 . "</a>",
				( $list ? "</li>" : "" );
			}
			echo $list ? "</ul></p>" : " verfügbar.</p>";
		}
?>
 </div></div>
</section>
<?php
	endforeach; // $courses
?></div><?php
endif;




$locs = wp_get_object_terms( $post->ID, 'loc' );
foreach ( $locs as $loc )
	if ( $loc->parent ) {
		echo "<footer><a href='" . get_term_link( $loc ) . "' class='ln-nocat loc-$loc->term_id'>&laquo; Alle Schulen in $loc->name</a>";
		$footer_open = true;
	}
?>
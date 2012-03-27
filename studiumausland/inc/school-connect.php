<?php
/**
 * @package Studium_Ausland
 */

add_action( 'add_meta_boxes', 'fbk_add_school_connect_meta_box' );
function fbk_add_school_connect_meta_box( $post_type ) {
	if ( post_type_supports( $post_type, 'f_school_connect' ) )
		add_meta_box( 'school_connect', 'Verknüpfte Schulen', 'fbk_school_connect_meta_box', $post_type, 'normal', 'default' );
}

function fbk_school_connect_meta_box( $post ) {
?>
<input type="button" class="button" id="fbk_school_connect_tb_btn" value="Schulen hinzufügen / entfernen">
<span id="school_connect_items">
<?php
	$schools = get_post_meta( $post->ID, '_school_connect' );
	foreach ( $schools as $school_id ) {
		$s = get_post( $school_id );
		$terms = array();
		foreach ( wp_get_object_terms( $school_id, array( 'category', 'loc' ) ) as $term )
			$terms[$term->taxonomy] = $term;
		if ( ! empty($terms['category']) )
			$c = 'c-' . $terms['category']->slug;
		else
			$c = '';
		echo "<span class='school_connect_item $c'>$s->post_title ({$terms['loc']->name})</span>";
	}
?>
</span>
<script>jQuery(document).ready(function($){
	$('#fbk_school_connect_tb_btn').click(function(){
		tb_show('Verknüpfte Schulen', 'media-upload.php?post_id=<?= $post->ID ?>&amp;type=school_connect&amp;TB_iframe=true&amp;tab=type' );
	});
});</script>
<?php
}

add_action( 'media_upload_school_connect', 'fbk_school_connect' );
function fbk_school_connect() {
	if ( ! empty( $_POST ) )
		fbk_school_connect_handler();

	wp_iframe( 'fbk_school_connect_form' );
}

function fbk_school_connect_handler() {
	$post_id = (int) $_REQUEST['post_id'];
	$parent =& get_post( $post_id );
	$new_schools = array();
	
	if ( ! check_admin_referer('media-form') || ! current_user_can( 'edit_posts' ) )
		return;
	
	$linked_schools = get_post_meta( $post_id, '_school_connect' );

	if ( ! empty( $_REQUEST['add_schools'] ) ) {
		foreach ( (array)$_REQUEST['add_schools'] as $school_id ) {
			if ( ! in_array( $school_id, $linked_schools ) ) {
				add_post_meta( $post_id, '_school_connect', $school_id );
				do_action( 'fbk_school_connect_add_link', $post_id, $school_id );
			}

			// Reciprocal
			if ( 'school' == $parent->post_type ) {
				$child_links = get_post_meta( $school_id, '_school_connect' );
				if ( ! $child_links || ! in_array( $post_id, $child_links ) ) {
					add_post_meta( $school_id, '_school_connect', $post_id );
					do_action( 'fbk_school_connect_add_link', $school_id, $post_id );
				}
			}
		}
	}
	
	if ( ! empty( $_REQUEST['remove_schools'] ) ) {
		foreach ( $_REQUEST['remove_schools'] as $school_id ) {
			delete_post_meta( $post_id, '_school_connect', $school_id );
			do_action( 'fbk_school_connect_remove_link', $post_id, $school_id );

			// Reciprocal
			if ( 'school' == $parent->post_type ) {
				$child_links = get_post_meta( $school_id, '_school_connect' );
				if ( $child_links && in_array( $post_id, $child_links ) ) {
					delete_post_meta( $school_id, '_school_connect', $post_id );
					do_action( 'fbk_school_connect_remove_link', $school_id, $post_id );
				}
			}
		}
	}
}

function fbk_school_connect_form() {
	global $wp_query, $post, $wpdb;
	$post_id = $_REQUEST['post_id'];
	$parent =& get_post( $post_id );
	$request = @array(
		's' => $_REQUEST['s'],
		'paged' => $_REQUEST['paged'],
		'cat' => $_REQUEST['cat'],
		'post_id' => $post_id,
		'lang' => $_REQUEST['lang'],
		'loc' => $_REQUEST['loc']
	);
	$nonce = wp_nonce_field( 'media-form', '_wpnonce', true, false );
	$linked_schools = get_post_meta( $post_id, '_school_connect' );
?>
<div id="fbk-tabless">

<?php // ********************** already linked schools: ***********************************************************
if ( $linked_schools ) :
?>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php?type=school_connect&tab=type") ?>" id="connect-schools-existing">
<h2>Bestehende Verknüpfungen</h2>
<?php foreach ( $request as $key => $field ) 
	echo "<input type='hidden' name='$key' value='$field'>"; ?>
<?= $nonce ?>
<table class="form-table fbk-searchresults">
<thead><tr><th><input type="checkbox"></th><th>Schulname</th><th><?php _e('Category'); ?><th>Sprache</th><th>Ort</th><th>Kurse</th></tr></thead>
<tbody>
<?php foreach ( $linked_schools as $school_id ) :
	$school = get_post( $school_id );
	$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
	foreach ( wp_get_object_terms( $school_id, array_keys( $terms ) ) as $term )
		$terms[$term->taxonomy] = $term;
	$selection[] = array( 't' => $school->post_title, 'c' => $terms['category'] ? $terms['category']->slug : '' );
?>
<tr <?php if ( $terms['category'] ) echo "class='c-{$terms['category']->slug}'"; ?>>
	<th><input type="checkbox" name="remove_schools[]" id="rm-schools-<?= $school_id ?>" value="<?= $school_id ?>"></th>
	<td><label for="rm-schools-<?= $school_id ?>"><?= $school->post_title ?></label></td>
	<?php	foreach ( $terms as $term )
			if ( $term )
				echo "<td>$term->name</td>";
			else
				echo "<td>&nbsp;</td>";
	?>
	<td><ul class="fbk-course-list collapsed"><?php
		foreach ( fbk_get_school_meta( $school_id, 'courses' ) as $course )
			echo "<li>$course[name]</li>";
	?></ul></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="6"><?php submit_button( 'Verknüpfung zu ausgewählten Schulen löschen', 'button delete' ); ?></td></tr></tfoot>
</table>
<script>
(function($){
var c = $('#school_connect_items',top.document).empty();
<?php foreach ( $selection as $s )
	echo "c.append('<span class=\"school_connect_item c-$s[c]\">$s[t]</span>');\n";
?>
})(jQuery);
</script>
</form>
<?php endif;

// ******************************************* Automatic recommendations for school-school links **********************************************
if ( 'school' == $parent->post_type ) :
	$search_filter = create_function( "", "return ' AND ($wpdb->posts.post_title LIKE \\'%$parent->post_title%\\') ';" );
	add_filter( 'posts_search', $search_filter );
	$wp_query->query( array(
		'post_type' => 'school',
		'post_status' => 'publish',
		'post__not_in' => array_merge( $linked_schools, array($post_id) )
	));
	remove_filter( 'posts_search', $search_filter );
	if ( have_posts() ) :
?>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php?type=school_connect&tab=type") ?>" id="connect-schools-auto">
<h2>Automatische Vorschläge</h2>
<?= $nonce ?>
<?php fbk_school_connect_form_table(); ?>
</form>
<?php
	endif;
endif;


// ********************************************* Free search *****************************************************************************
	echo "<h2>Suche</h2>";
	$tax_query = array();
	foreach ( array( 'lang', 'loc' ) as $tax )
		if ( $request[$tax] )
			$tax_query[] = array(
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $request[$tax]
			);
	$query_args = array(
		's' => $request['s'],
		'post_type' => 'school',
		'post_status' => 'publish',
		'posts_per_page' => 10,
		'paged' => $request['paged'],
		'category_name' => $request['cat'],
		'orderby' => 'title',
		'order' => 'ASC',
		'post__not_in' => array_merge( $linked_schools, array($post_id) )
	);
	if ( $tax_query )
		$query_args['tax_query'] = $tax_query;
	$wp_query->query( $query_args );

	fbk_thickbox_filter( array(
		'query_args' => array( 'post_id' => $post_id, 'tab' => 'type', 'type' => 'school_connect' ),
		'filter' => array( 's' => $request['s'] ),
		'taxonomies' => array( 'cat' => $request['cat'], 'lang' => $request['lang'], 'loc' => $request['loc'] )
	), array('current'=>$request['paged']) );

if ( have_posts() ) : ?>
<form enctype="multipart/form-data" method="post" action="<? admin_url("media-upload.php?type=school_connect&tab=type") ?>" id="connect-schools-search">
<?php foreach ( $request as $key => $field ) 
	echo "<input type='hidden' name='$key' value='$field'>"; ?>
<?= $nonce ?>
<?php fbk_school_connect_form_table(); ?>
<script>(function($){$('.fbk-course-list').click(function(){$(this).toggleClass('collapsed');});})(jQuery);</script>
</form>
<?php else : ?>
<div class="updated"><p>Keine Resultate.</p></div>
<?php endif; ?>
<script>(function($){
	$('thead :checkbox').change(function(){
		$(this).closest('table').find('tbody :checkbox').prop('checked',$(this).prop('checked'));
	});
})(jQuery);
</script>
</div>
<?php
}

function fbk_school_connect_form_table() {
	global $post;
?>
<table class="form-table fbk-searchresults">
<thead><tr><th><input type="checkbox"></th><th>Schulname</th><th><?php _e('Category'); ?></th><th>Sprache</th><th>Ort</th><th>Kurse</th></tr></thead>
<tbody>
<?php
 while ( have_posts() ) :
	the_post();
	$terms = array( 'category' => false, 'lang' => false, 'loc' => false );
	foreach ( wp_get_object_terms( get_the_ID(), array_keys( $terms ) ) as $term )
		$terms[$term->taxonomy] = $term;
?>
<tr <?php if ( $terms['category'] ) echo "class='c-{$terms['category']->slug}'"; ?>>
 <th>
  <input type="checkbox" name="add_schools[]" id="school-<?= $post->ID ?>" value="<?= $post->ID ?>">
 </th>
 <td><?php the_title("<label for='school-$post->ID'>", "</label>") ?></td>
<?php foreach ( $terms as $term )
	if ( $term )
		echo "<td>$term->name</td>";
	else
		echo "<td>&nbsp;</td>";
?>
 <td><ul class="fbk-course-list collapsed"><?php 
	foreach ( fbk_get_school_meta( $post->ID, 'courses' ) as $course )
		echo "<li>$course[name]</li>";
 ?></ul></td>
</tr>
<?php
 endwhile; ?>
</tbody>
<tfoot><tr><td colspan="6"><input type="submit" value="Hinzufügen" class="button"></td></tr></tfoot>
</table>
<?php
}

add_action( 'before_delete_post', 'fbk_school_connect_delete_post_filter' );
function fbk_school_connect_delete_post_filter( $post_id ) {
	$post =& get_post( $post_id );
	if ( 'school' != $post->post_type )
		return;
	$linked_schools = get_post_meta( $post_id, '_school_connect' );
	if ( $linked_schools ) {
		foreach ( $linked_schools as $linked_school_id ) {
			delete_post_meta( $linked_school_id, '_school_connect', $post_id );
			do_action( 'fbk_school_connect_remove_link', $post_id, $linked_school_id );
			do_action( 'fbk_school_connect_remove_link', $linked_school_id, $post_id );
		}
	}
}

/*********************************************
 * Batch connect tool ************************
 *********************************************
*/
add_action( 'admin_menu', 'fbk_school_connect_batch_register' );
function fbk_school_connect_batch_register() {
	add_submenu_page( 'tools.php', 'Schulen verknüpfen', 'Schulen verknüpfen', 'edit_posts', 'school_connect_batch', 'fbk_school_connect_batch' );
	add_submenu_page( 'tools.php', 'Bestehende Schulverknüpfungen analysieren', 'Bestehende Verknüpfungen', 'edit_posts', 'school_connect_analyse', 'fbk_school_connect_analyse' );
}

function fbk_school_connect_batch() {
	global $wpdb, $fbk;
?>
<div class="wrap"><h2>Schulen verknüpfen</h2>
<?php
	$offset = isset( $_REQUEST['offset'] ) ? (int) $_REQUEST['offset'] : 0;
	if ( $offset < 0 )
		$offset = 0;

	$ignore = isset($_REQUEST['i']) ? (array) $_REQUEST['i'] : array();
	if ( $ignore ) {
		$ignore_str = implode( ',', $ignore );
		$ignore_str = " AND ( source.ID NOT IN ($ignore_str) ) AND ( target.ID NOT IN ($ignore_str) ) ";
	} else {
		$ignore_str = "";
	}

	$ignore_tuples = isset( $_REQUEST['it'] ) ? (array) $_REQUEST['it'] : array();

	if ( isset($_POST['submit']) ) {
		check_admin_referer( 'fbk-connect-schools' );
		if ( isset($_POST['connect']) && is_array($_POST['connect']) ) {
			foreach ( $_POST['connect'] as $connect ) {
				$tuple = explode( ',', $connect );
				add_post_meta( $tuple[0], '_school_connect', $tuple[1] );
				add_post_meta( $tuple[1], '_school_connect', $tuple[0] );
				do_action( 'fbk_school_connect_add_link', $tuple[0], $tuple[1] );
				do_action( 'fbk_school_connect_add_link', $tuple[1], $tuple[0] );
			}
		}
		foreach ( $_POST['check'] as $check ) {
			if ( ! isset($_POST['connect']) || ! is_array($_POST['connect']) || ! in_array( $check, $_POST['connect'] ) ) {
				$check_split = explode( ',', $check );
				foreach ( $check_split as $split )
					if ( in_array( $split, $ignore ) )
						continue 2;
				$ignore_tuples[] = $check;
			}
		}
	}

	if ( $ignore_tuples ) {
		$ignore_tuples_str = implode( "','", $ignore_tuples );
		$ignore_str .= " AND ( CONCAT( source.ID, ',', target.ID ) NOT IN ('$ignore_tuples_str') )
				 AND ( CONCAT( target.ID, ',', source.ID ) NOT IN ('$ignore_tuples_str') ) ";
	}

	$query = "
		SELECT source.ID AS source_id, source.post_title AS source_title, target.ID AS target_id, target.post_title AS target_title
		FROM $wpdb->posts AS source, $wpdb->posts AS target
		WHERE
			source.post_status = 'publish'
			AND target.post_status = 'publish'
			AND source.ID != target.ID
			AND source.post_type = 'school'
			AND target.post_type = 'school'
			$ignore_str
			AND (
				source.post_title LIKE CONCAT( '%', target.post_title, '%' )
				OR target.post_title LIKE CONCAT( '%', source.post_title, '%' )
			)
		ORDER BY source.ID ASC
	";
	$couplings = array();
	$query_count = 0;
	$start = microtime(true);
	do {
		$ten = $wpdb->get_results( $query . " LIMIT $offset, 10" );
		$offset += 10;
		$query_count++;
		if ( ! $ten )
			break;
		foreach ( $ten as $set ) {
			$links = get_post_meta( $set->source_id, '_school_connect' );
			if ( $links && in_array( $set->target_id, $links ) )
				continue;
			foreach ( wp_get_object_terms( array( $set->source_id, $set->target_id ), 'category' ) as $term )
				if ( ! array_key_exists( $term->slug, $fbk->cats ) )
					continue 2;
			$couplings[] = $set;
		}
	} while ( count($couplings) < 20 );
	$end = microtime(true);

	$stats = "Derzeit werden " . count($ignore) . " Schulen und " . count($ignore_tuples) . " Paare ignoriert. Die Datenbankabfrage ist ungefähr "
	. strlen( $query ) . " Zeichen lang und wurde $query_count Mal aufgerufen, um dieses Ergebnis zu generieren. Die gesamte Abfrage dauerte ca. "
	. ($end-$start) . " Sekunden.";

	if ( ! $couplings ) :
		echo "<p>Keine weiteren Vorschläge. $stats</p>";
	else :
?>
<style>
#linkup td {
	padding: 8px 7px 6px;
}
#linkup td:nth-child(2),
#linkup td:nth-child(5),
#linkup p.submit {
	text-align: right;
}
#linkup td:nth-child(3),
#linkup th {
	text-align: center;
}
#linkup td.greyed-out {
	opacity: 0.2;
}
#linkup td.greyed-in,
#linkup td:nth-child(odd):hover {
	background-color: #aaa;
}
</style>
<form id="linkup" method="post">
<?php wp_nonce_field( 'fbk-connect-schools' ); ?>
<?php	foreach ( $ignore as $i )
		echo '<input type="hidden" name="i[]" value="' . $i . '">';
	foreach ( $ignore_tuples as $i )
		echo '<input type="hidden" name="it[]" value="' . $i . '">';
?>
<table class="widefat">
<thead><tr><th>Ignorieren</th><th>Schule</th><th><input type="checkbox" checked> Verknüpfen</th><th>Schule</th><th>Ignorieren</th></tr></thead>
<tbody>
<?php
		$taxonomies = array( 'category', 'lang', 'loc' );
		$tr_class = true;
		foreach ( $couplings as $set ) :
			$tr_class = $tr_class ? '' : ' class="alternate"';
			$s_terms = $t_terms = array();
			foreach ( wp_get_object_terms( $set->source_id, $taxonomies ) as $term )
				$s_terms[$term->taxonomy] = $term;
			foreach ( wp_get_object_terms( $set->target_id, $taxonomies ) as $term )
				$t_terms[$term->taxonomy] = $term;
?>
<tr<?= $tr_class ?>>
	<td><input type="checkbox" name="i[]" value="<?= $set->source_id ?>"></td>
	<td class="c-<?= $s_terms['category']->slug ?>"><?php
			echo $set->source_title, " (", $s_terms['lang']->name, ", ", $s_terms['loc']->name, ")";
	?></td>
	<td>
	  <input type="checkbox" name="connect[]" value="<?= $set->source_id, ",", $set->target_id ?>" id="connect-<?= $set->source_id ?>-<?= $set->target_id ?>" checked>
	  <input type="hidden" name="check[]" value="<?= $set->source_id, ",", $set->target_id ?>">
	</td>
	<td class="c-<?= $t_terms['category']->slug ?> s-<?= $set->target_id ?>"><?php
			echo $set->target_title, " (", $t_terms['lang']->name, ", ", $t_terms['loc']->name, ")";
	?></td>
	<td><input type="checkbox" name="i[]" value="<?= $set->target_id ?>"></td>
</tr>
<?php
		endforeach;
?>
</tbody></table>
<p class="submit">
	<span style="float:left;"><?= $stats ?></span>
	<input type="submit" name="submit" id="submit" class="button-primary" value="Übernehmen und weiter...">
</p>
</form>
<script>(function($){
	var toggle = function(i,val){return !val;};
	$('td').click(function(){
		if ( $(this).is('[class]') )
			$(this).parent().find(':checkbox[id]').prop('checked',toggle);
		else if ( ! $(event.target).is(':checkbox') )
			$(this).find(':checkbox').prop('checked',toggle).trigger('greyout').trigger('change-1');
		else
			$(this).find(':checkbox').trigger('greyout').trigger('change-1');
	});
	$(':checkbox:not([id])').bind('change-1', function(){
		cb = $(':checkbox[value="' + $(this).val() + '"]').not(this).prop('checked',$(this).prop('checked')).trigger('greyout');
		if ( $(this).prop('checked') )
			cb.add(this).closest('tr').find(':checkbox[id]').prop('checked',false);
	}).bind('greyout', function(){
		var td = $(this).parent().next();
		if ( ! td.length )
			td = $(this).parent().prev();
		td.toggleClass('greyed-out');
	});
	$('td[class]').hover(function(){$(this).siblings(':nth-child(3)').toggleClass('greyed-in');});
	$('#linkup table').mouseleave(function(){$(this).find('.greyed-in').removeClass('greyed-in');});
	$('th :checkbox').change(function(){
		$(this).closest('table').find('td:nth-child(' + ($(this).closest('th').prevAll().length+1) + ') :checkbox').prop('checked', $(this).prop('checked'));
	});
})(jQuery);</script>
<?php
	endif;
?>
</div>
<?php
}

function fbk_school_connect_analyse_handler() {
	global $wpdb, $fbk_cache;
	if ( isset($_POST['changes']) ) {
		check_admin_referer( 'school-connect-analyse' );
		if ( is_array( $_POST['changes'] ) ) foreach ( $_POST['changes'] as $source => $targets ) {
			$source = (int) $source;
			if ( is_array( $targets ) ) foreach ( $targets as $target => $change ) {
				$target = (int) $target;
				if ( (int) $change ) {
					add_post_meta( $source, '_school_connect', $target );
					do_action( 'fbk_school_connect_add_link', $source, $target );
				} else {
					delete_post_meta( $source, '_school_connect', $target );
					do_action( 'fbk_school_connect_remove_link', $source, $target );
				}
			}
		}
	}

	if ( isset($_POST['shareChanges']) ) {
		check_admin_referer( 'school-connect-analyse' );
		$stale_schools = array();
		if ( is_array( $_POST['shareChanges'] ) ) foreach ( $_POST['shareChanges'] as $object_type => $objects ) {
			if ( is_array( $objects ) ) foreach ( $objects as $object_id => $schools ) {
				$object_id = (int) $object_id;
				$additions = $deletions = array();
				if ( is_array( $schools ) ) foreach ( $schools as $school_id => $change ) {
					if ( (int) $change ) {
						$additions[] = (int) $school_id;
					} else {
						$deletions[] = (int) $school_id;
					}
				}
				// Do all additions first, since otherwise, data loss may ensue
				foreach ( $additions as $school_id ) {
					if ( in_array( $object_type, array( 'courses', 'accommodation' ) ) ) {
						$owner_id = $wpdb->get_var( "SELECT post_id, _id FROM wp_fbk_$object_type WHERE meta_id = $object_id" );
						$owner_order = $wpdb->get_var( null, 1 );
					} else {
						$meta_table_row = $wpdb->get_row( "SELECT * FROM $wpdb->postmeta WHERE meta_id = $object_id" );
						$meta_value = unserialize( $meta_table_row->meta_value );
						$owner_id = $meta_table_row->post_id;
						$owner_order = $meta_value['_id'];
					}
					add_post_meta( $school_id, "_foreign_$object_type", array(
						'school_id' => $owner_id,
						'object_id' => $object_id,
						'order' => $owner_order
					));
					add_post_meta( $owner_id, "_shared_$object_type", array(
						'school_id' => $school_id,
						'object_id' => $object_id
					));
					$stale_schools[] = $owner_id;
					$stale_schools[] = $school_id;
				}
				foreach ( $deletions as $school_id ) {
					fbk_delete_school_meta( $school_id, $object_type, $object_id );
				}
			}
		}
		if ( $stale_schools )
			$fbk_cache->delete( 'school', array_unique( $stale_schools ) );
	}

	if ( isset($change) )
		return "Änderungen übernommen.";
}

function fbk_school_connect_analyse() {
	if ( ! current_user_can( 'edit_posts' ) )
		return;
	$message = fbk_school_connect_analyse_handler();
?>
<div class="wrap"><h2>Bestehende Schulverknüpfungen analysieren</h2>
<?php if ( $message ) : ?>
<div class="updated"><p><?= $message ?></p></div>
<?php endif;

// Get all schools that are somehow linking to something out of the database
	global $wpdb, $fbk_cf_boxes;
	$query = "
		SELECT source.ID AS ID, source.post_title AS title, meta.meta_value AS target
		FROM $wpdb->posts AS source
		JOIN $wpdb->postmeta AS meta on source.ID = meta.post_id
		WHERE
			source.post_type = 'school'
			AND source.post_status = 'publish'
			AND meta.meta_key = '_school_connect'
		ORDER BY source.ID ASC
	";
	$links = $wpdb->get_results( $query );

// Structure the data
	$schools = $target_only = $groups = $check = array();
	$group_index = 0;
	foreach ( $links as $link ) {
		if ( ! array_key_exists( $link->ID, $schools ) ) {
			$schools[$link->ID] = array(
				'links' => array( $link->target => 1 ),
				'name' => $link->title,
				'group' => false
			);
		} else {
			$schools[$link->ID]['links'][$link->target] = 1;
		}
	}

// Identify common links and build the resulting groups
	foreach ( $schools as $ID => $school ) {
		$group = false;
		$merge_groups = $_target_only = array();
		foreach ( array_keys( $school['links'] ) as $target ) {
			if ( array_key_exists( $target, $schools ) ) {
				if ( false === $group ) {
					$group = $schools[$target]['group'];
				} elseif ( $group != $schools[$target]['group'] && false !== $schools[$target]['group'] ) {
					$merge_groups[] = $schools[$target]['group'];
				}
			} else {
				$_target_only[] = $target_only[] = $target;
			}
		}
		if ( false === $group )
			$group = ++$group_index;
		$schools[$ID]['group'] = $group;
		$groups[$group]['schools'][] = $ID;
		if ( $_target_only ) {
			foreach ( array_unique( $_target_only ) as $target ) {
				$schools[$target] = array(
					'links' => array(),
					'name' => '',
					'group' => $group
				);
				$groups[$group]['schools'][] = $target;
			}
		}
		foreach ( array_unique($merge_groups) as $merge ) {
			foreach ( $groups[$merge]['schools'] as $merge_group_entry )
				$schools[$merge_group_entry]['group'] = $group;
			$groups[$group]['schools'] = array_merge( $groups[$group]['schools'], $groups[$merge]['schools'] );
			unset( $groups[$merge] );
		}
	}

// Fetch shareable meta objects
	$meta_objects = array();
	foreach ( $fbk_cf_boxes as $object_type => $box ) {
		if ( ! empty($box['shared']) ) {
			$meta_objects[$object_type] = array( 'label' => $box['title'] );
			foreach ( $groups as $group_id => $group ) {
				foreach ( $group['schools'] as $school_id ) {
					if ( ! ( $meta = fbk_get_school_meta( $school_id, $object_type ) ) ) {
						$schools[$school_id][$object_type] = (object) array();
						continue;
					}
					$schools[$school_id][$object_type] = array_flip( array_keys( $meta ) );
					foreach ( $meta as $meta_id => $object ) {
						if ( ! array_key_exists( $meta_id, $meta_objects[$object_type] ) ) {
							$object['shared_with'][] = $school_id;
							$meta_objects[$object_type][$meta_id] = array(
								'name' => fbk_cf_get_meta_object_name( $object_type, $object ),
								'shared' => array_map( 'absint', $object['shared_with'] )
							);
						}
					}
				}
			}
		}
	}

// Get the names of posts that are referenced, but don't actively link anywhere (and hence, did not figure in the first query)
	if ( $target_only ) {
		$target_only = array_unique( $target_only );
		$check = array_flip( $target_only );
		$query = "SELECT ID, post_title, post_type, post_status FROM $wpdb->posts WHERE ID IN (" . implode(',', $target_only) . ")";
		foreach ( $wpdb->get_results( $query ) as $set ) {
			if ( 'school' == $set->post_type && 'publish' == $set->post_status )
				$schools[$set->ID]['name'] = $set->post_title;
			else
				$schools[$set->ID]['name'] = $set->post_title . ' (' . $set->post_type . ', ' . $set->post_status . ')';
			unset( $check[$set->ID] );
		}
		foreach ( array_keys($check) as $dead_link )
			$schools[$dead_link]['name'] = '(unbekanntes Objekt)';
	}

// Add taxonomy information to each school dataset
	$all_school_ids = array_keys( array_diff_key( $schools, $check ) );
	update_object_term_cache( $all_school_ids, 'school' );
	foreach ( $all_school_ids as $ID ) {
		$c = get_object_term_cache( $ID, 'category' );
		if ( $c )
			$schools[$ID]['cat'] = reset($c)->slug;
		$c = get_object_term_cache( $ID, 'lang' );
		if ( $c )
			$schools[$ID]['lang'] = reset($c)->name;
		$c = get_object_term_cache( $ID, 'loc' );
		if ( $c )
			$schools[$ID]['loc'] = reset($c)->name;
	}

// Try to find the longest common substring in the names of each group to use as a group name
	foreach ( $groups as $g_id => $group ) {
		$lengths = $names = array();
		foreach ( $group['schools'] as $ID ) {
			$lengths[$ID] = strlen( $schools[$ID]['name'] );
			$names[$ID] = $schools[$ID]['name'];
		}
		$maxlength = min($lengths);
		$shortest = $names[ array_search($maxlength, $lengths) ];
		for ( $i = $maxlength; $i > 0; $i-- ) {
			for ( $j = 0; $j < $maxlength - $i; $j++ ) {
				$substr = substr( $shortest, $j, $i + 1 );
				$match = true;
				foreach ( $names as $name ) {
					if ( false === strpos( $name, $substr ) ) {
						$match = false;
						break 1;
					}
				}
				if ( $match )
					break 2;
			}
		}
		if ( strlen($substr) > 3 || preg_match( '/^[A-Z]{2,3}$/', $substr ) )
			$groups[$g_id]['name'] = $substr;
		else
			$groups[$g_id]['name'] = $g_id;
	}

	wp_enqueue_script( 'fbk.school-connect' );
?>
<style>
#connect th,
#connect td {
	padding: 5px;
}
td.linked {
	background-color: #39AD39;
}
td.unlinking {
	background-color: #F0A3D0;
}
td.unlinked {
	background-color: #CF3A3A;
}
td.linking {
	background-color: #A2EBCB;
}
td.mirrored {
	background-color: grey;
}
.shared td {
	text-align: right;
}
#groups,
#connect-changes {
	float: left;
	margin-right: 2em;
}
#groups tbody th,
#groups td {
	text-align: right;
	vertical-align: inherit;
}
#groups tr.missing {
	background-color: pink;
}
#groups tbody tr:hover {
	background-color: #ccc;
	cursor: pointer;
}
#groups td.merge:hover {
	text-decoration: underline;
}
</style>
<form method="post">
<?php wp_nonce_field( 'school-connect-analyse' ); ?>
<div id="groups">
<h3>Gefundene Gruppierungen:</h3>
<table class="widefat">
<thead><tr><th>Gruppe</th><th>Schulen</th><th>Fehlend</th><th>Gruppen verbinden</th></tr></thead>
<tbody>
<?php
	$alternate = true;
	foreach ( $groups as $i => $group ) {
		$missing = 0;
		foreach ( $group['schools'] as $source ) {
			foreach ( $group['schools'] as $target ) {
				if ( $source == $target )
					continue;
				if ( ! array_key_exists( $target, $schools[$source]['links'] ) )
					$missing++;
			}
		}
		$classes = array();
		if ( $alternate = ! $alternate )
			$classes[] = 'alternate';
		if ( $missing )
			$classes[] = 'missing';
		echo "<tr data-group='$i' class='", implode(' ', $classes), "'><th>$group[name]</th><td>", count($group['schools']), "</td><td>$missing</td>",
		"<td class='merge'>Verbinden mit...</td></tr>";
	}
?>
</tbody></table>
</div>
<script>
groupData = <?= json_encode( array(
	'schools' => $schools,
	'groups' => $groups,
	'shared' => $meta_objects,
	'baseUrl' => admin_url( '/post.php?action=edit&post=' )
)); ?>;
</script>
<div id="connect-changes">
<input type="submit" class="button-primary" value="Änderungen übernehmen">
<h3>Anstehende Änderungen</h3>
</div>
<div id="connect"></div>
</form>
</h2>
<?php
}





















?>
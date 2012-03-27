<?php
/**
 * @package Studium_Ausland
 */

//Registered through the school post type definition
function fbk_cf_register_metaboxes() {
	global $fbk_cf_boxes;
	foreach ( $fbk_cf_boxes as $id => $box ) {
		add_meta_box( "fbk-cf-$id-box", $box['title'], 'fbk_cf_display_box', 'school', $box['context'], $box['priority'], array($id) );
	}
}

function fbk_cf_print_field( $field_name, $field_specs, $prefix, $line, $data ) {
	global $fbk_cf_prefix;

	$classes = array(
		$prefix.'-'.$field_name.'-wrapper',
		$fbk_cf_prefix.$field_specs['type']
	);
	if ( 'course_tag' == $field_specs['type'] ) {
		$classes[] = 'inside';
		$field_specs['field_id'] = $field_name;
	}
	$classes[] = $fbk_cf_prefix.'lineitem';
	if ( ! empty( $field_specs['collapse'] ) )
		$classes[] = 'collapse-hide';

	$field_specs['name'] = $prefix . '[' . $line . '][' . $field_name . ']';
	$field_specs['value'] = ( isset($data[$field_name]) ? $data[$field_name]
		: (isset($field_specs['default']) ? $field_specs['default'] : '') );

	$field_specs['line'] = $line;
	echo "<div class='" . implode( ' ', $classes ) . "'" . ( empty($field_specs['collapse']) ? "" : " style='display:none;'" ) . ">"
	. ( empty($field_specs['nolabel']) ? "<label for='$field_specs[name]'>$field_specs[label]</label>" : "" )
	. fbk_cf_get_input_html( $field_specs )
	. "</div>";
}

function fbk_cf_get_input_html( $in ) {
	$str = '';

	extract( $in );
	
	if ( empty($noid) )
		$id = " id='$name'";
	else
		$id = '';
	
	$attr = '';

	switch ( $type ) {
	    case 'text':
	    case 'shorttext':
	    case 'longtext':
		$str = "<input$id$attr type='text' name='$name' value='$value'>";
		break;
	    case 'date':
		$str = "<input$id$attr type='text' name='$name' value='$value' class='fbk_date'>";
		break;
	    case 'multitext':
		$strings = array();
		for ( $i = 0; $i < $nfields; $i++ ) {
			@$strings[] = "<input$attr type='text' name='".$name."[$i]' value='$value[$i]'>";
		}
		$str = implode( $separator, $strings );
		break;
	    case 'checkbox':
		$str = "<input$id$attr type='checkbox' name='$name'";
		if ( $value )
			$str .= " checked='checked'";
		$str .= ">";
		break;
	    case 'select':
		$str = "<select$id$attr name='$name'>";
		foreach ( $opts as $k => $v ) {
			$sel = '';
			if ( $k == $value )
				$sel = " selected='selected'";
			$str .= "<option$sel value='$k'>$v</option>";
		}
		$str .= "</select>";
		break;
	    case 'bitmask':
		foreach ( $opts as $i => $opt ) {
			if ( $value & $opt['mask'] )
				$sel = " checked='checked'";
			else
				$sel = '';
			$attr = '';
			if ( isset($opt_attribs[$i]) )
				foreach ( $opt_attribs[$i] as $attrib_name => $attrib_value )
					$attr .= " $attrib_name='$attrib_value'";
			$cb = "<input$attr type='checkbox' name='".$name."[]' value='$opt[mask]'$sel$attr>";
			if ( isset($label_pos) && 'left' == $label_pos )
				$str .= "<div class='fbk_cf_bitmask_entry'>" . $opt['label'] . $cb . "</div>";
			else
				$str .= "<div class='fbk_cf_bitmask_entry'>" . $cb . $opt['label'] . "</div>";
		}
		break;
	    case 'wysiwyg':
		if ( isset($instantly_active) && $instantly_active ) {
			$class = " class='mceEditor'";
			$after = "<script type='text/javascript'>jQuery(function(){tinyMCE.execCommand('mceAddControl',false,'$name');jQuery('#$name').next().remove()})</script>";
		} else {
			$class = " class='fbk_cf_mce_mark'";
			$after = '';
		}
	    case 'textarea':
		@$str .= "<textarea$id$class$attr name='$name'>$value</textarea>$after";
		break;
	    case 'course_tag':
		ob_start();
		post_tags_meta_box( $GLOBALS['post'], array( 'args' => array('taxonomy' => 'course_tag'), 'title' => $label ) );
		$str = ob_get_contents();
		ob_end_clean();
		// Make sure ids remain unique, but let's not break anything...
		$str = preg_replace( '/(id|for)="([^"]+)"/', '$1="fbk_cf_line_' . $line . '__$2"', $str );
		// Make sure names are unique & identifiable
		$str = str_replace( '[course_tag]', "[course_tag][$line][$field_id]", $str );

		$tag_ids = empty($value) ? array() : explode(',', $value);
		$tags = array();
		foreach ( $tag_ids as $tag ) {
			$tag_object = get_term( $tag, 'course_tag' );
			$tags[] = $tag_object->name;
		}
		$str = preg_replace( '/(class="the-tags".*?>)[^<]*/', '$1' . implode(',', $tags), $str );
		break;
	    case 'collection':
		$i = 0;
		foreach ( $include as $inc_name => $inc ) {
			$inc['value'] = @$value[$inc_name];
			$inc['name'] = $name . '[' . $inc_name . ']';
			$inc['noid'] = true;
			$str .= fbk_cf_get_input_html( $inc );
			if ( ++$i < count( $include ) )
				$str .= @$separator;
		}
		break;
	    case 'coursestack':
		if ( empty($value) )
			$value = array( 'period' => 'w' );
		$value['proto'] = array(
			'type' => 'tuition',
			'calc' => 'pw',
			'from' => '',
			'to' => '',
			'values' => array()
		);

		$_id = $name . "[period]";
		$str = "<div class='group-horizontal'><label for='$_id'>Berechnungseinheit: </label><select name='$_id' id='$_id'>";
		foreach ( array( 'w' => 'Wochen', 's' => 'Semester' ) as $k => $v )
			$str .= "<option value='$k'" . ( $value['period']==$k ? " selected" : "" ) . ">$v</option>";
		$str .= "</select></div>";

		$calcs = array(
			'pw' => 'Pro Woche/Semester',
			'tot' => 'Total',
			'nth' => 'Preis der n-ten Woche',
			'add' => 'Zuschlag pro Woche',
			'un' => 'Einmaliger Preis'
		);
		$types = array(
			'tuition' => 'Kurskosten',
			'mat' => 'Materialgebühr'
		);
		$iteration_helper = 0;
		foreach ( $value as $i => $column ) {
			if ( 'period' === $i )
				continue;
			else {
				$is_proto = ( 'proto' === $i );
				// Make sure i is a well-formed dense array index
				if ( ! $is_proto )
					$i = $iteration_helper++;
			}

			$str .= "<div class='stack-item stack-closed" . ( $is_proto ? " proto-stack" : "" ) . "'>";

			$str .= "<div class='group-horizontal'><select class='stack-item-title stack-type' name='" . $name . "[$i][type]'>";
			foreach ( $types as $k => $v )
				$str .= "<option value='$k'" . ( $column['type'] == $k ? " selected" : "" ) . ">$v</option>";
			$str .= "</select>"
			. "<span class='sort-stack'>▲▼</span>"
			. "<a class='open-stack' href='#'>Anzeigen</a>"
			. "<a class='close-stack' href='#'>Verstecken</a>"
			. "<a class='dupe-stack' href='#'>Hinzufügen</a>"
			. "<a class='kill-stack' href='#'>Entfernen</a>"
			. "<input type='hidden' class='stack-menu_order' name='{$name}[$i][menu_order]'>"
			. "</div>";

			$str .= "<div class='stack-details'>";

			$_id = $name . "[$i][calc]";
			$str .= "<div class='group-horizontal'><label for='$_id'>Berechnungstyp</label><select name='$_id' id='$_id' class='stack-calc'>";
			foreach ( $calcs as $k => $v ) {
				$disabled = 'mat' == $column['type'] && 'add' == $k ? 'disabled' : '';
				$str .= "<option value='$k'" . ( $column['calc'] == $k ? " selected" : "" ) . " $disabled>$v</option>";
			}
			$str .= "</select></div>";

			$_id = $name . "[$i][from]";
			$str .= "<div class='group-horizontal'><label for='$_id'>von</label>"
			. "<input name='$_id' id='$_id' class='fbk_date stack-from' value='" . @$column['from'] . "'></div>";

			$_id = $name . "[$i][to]";
			$str .= "<div class='group-horizontal'><label for='$_id'>bis</label>"
			. "<input name='$_id' id='$_id' class='fbk_date stack-to' value='" . @$column['to'] . "'></div>";

			if ( empty($column['values']) )
				$jmax = 9;
			else
				$jmax = max( array_merge( array_keys($column['values']), array(9) ) );
			for ( $j = 0; $j <= $jmax; $j++ ) {
				$_id = $name . "[$i][values][$j]";
				$str .= "<div class='group-horizontal stackval'><label for='$_id'>" . ($j+1) . "</label>"
				. "<input name='$_id' id='$_id' class='stackval' value='" . @$column['values'][$j] . "'></div>";
			}

			$str .= "</div></div>";
		}
		break;
	    case 'accstack':
		if ( empty( $value ) )
			$value = array();
		$value['proto'] = array(
			'type' => 'tuition',
			'calc' => 'pw',
			'from' => '',
			'to' => '',
			'values' => array()
		);

		$rooms = array(
			's' => 'Einzel',
			'd' => 'Doppel',
			't' => 'Zweibett',
			'm' => 'Mehrbett'
		);
		$boards = array(
			'sc' => 'Ohne Vpf.',
			'br' => 'B&amp;B',
			'hb' => 'HP',
			'fb' => 'VP'
		);
		$calcs = array(
			'pw' => 'Pro Woche',
			'tot' => 'Total',
			'add' => 'Zuschlag pro Woche'
		);
		$iteration_helper = 0;
		foreach ( $value as $i => $column ) {
			if ( 'period' === $i )
				continue;
			else {
				$is_proto = ( 'proto' === $i );
				// Make sure i is a well-formed dense array index
				if ( ! $is_proto )
					$i = $iteration_helper++;
			}

			$str .= "<div class='stack-item stack-closed" . ( $is_proto ? " proto-stack" : "" ) . "'>";

			$str .= "<div class='group-horizontal'>";

			$_id = $name . "[$i][from]";
			$str .= "<div class='group-horizontal stack-item-title'><label for='$_id'>von</label>"
			. "<input name='$_id' id='$_id' class='fbk_date stack-from' value='$column[from]'></div>";

			$_id = $name . "[$i][to]";
			$str .= "<div class='group-horizontal stack-item-title'><label for='$_id'>bis</label>"
			. "<input name='$_id' id='$_id' class='fbk_date stack-to' value='$column[to]'></div>";

			$str .= "<select class='stack-item-title stack-calc' name='" . $name . "[$i][calc]'>";
			foreach ( $calcs as $k => $v )
				$str .= "<option value='$k'" . ( $column['calc'] == $k ? " selected" : "" ) . ">$v</option>";
			$str .= "</select>";

			$str .= "<span class='sort-stack'>▲▼</span>"
			. "<a class='dupe-stack' href='#'>Hinzufügen</a>"
			. "<a class='kill-stack' href='#'>Entfernen</a>"
			. "<a class='open-stack' href='#'>Anzeigen</a>"
			. "<a class='close-stack' href='#'>Verstecken</a>"
			. "<input type='hidden' class='stack-menu_order' name='{$name}[$i][menu_order]'>"
			. "</div>";

			$str .= "<div class='stack-details'>";

			$str .= "<table><tbody><tr><th>&nbsp;</th>";
			foreach ( $rooms as $r => $r_label )
				$str .= "<th>$r_label</th>";
			$str .= "</tr>";
			foreach ( $boards as $b => $b_label ) {
				$str .= "<tr><th>$b_label</th>";
				foreach ( array_keys( $rooms ) as $r ) {
					$_id = $name . "[$i][values][$r][$b]";
					$str .= "<td data-r='$r' data-b='$b'>"
					. "<input type='text' class='noval' name='" . $_id . "[0]' value='" . @$column['values'][$r][$b][0] . "'>";
					if ( isset($column['values'][$r][$b]) && ( count($column['values'][$r][$b]) > 1 || key($column['values'][$r][$b]) ) )
						foreach ( $column['values'][$r][$b] as $j => $v )
							if ( $j )
								$str .= "<input type='text' name='" . $_id . "[$j]' value='$v' style='display:none;'>";
					$str .= "</td>";
				}
				$str .= "</tr>";
			}
			$str .= "</tbody></table>";
			$str .= "</div></div>";
		}
		break;
	    default:
		$str = "Error: Invalid field type.";
		break;
	}
	return $str;
}

function fbk_cf_display_box( $post, $args ) {
	if ( ! post_type_supports( $post->post_type, FBK_CF_FEATURE ) )
		return;
	global $fbk_cf_prefix, $fbk_cf_boxes;
	$css = $fbk_cf_prefix;
	$box = $args['args'][0];
	$prefix = $fbk_cf_prefix . $box;
	$fields = $fbk_cf_boxes[$box]['fields'];
	wp_nonce_field( $prefix . '-add', $prefix . '_nonce' );
	
	if ( 'lines' == $fbk_cf_boxes[$box]['type'] ) {

		echo "<div class='fbk_cf_sortable'>";

		$values = fbk_get_school_meta( $post->ID, $box );

		if ( empty($values) )
			$values = array( array('_id' => 0) );
		else
			uasort( $values, '_fbk_linesort_by_id' );

		if ( isset($fbk_cf_boxes[$box]['expandable']) && $fbk_cf_boxes[$box]['expandable'] )
			$expandable = " fbk_cf_expandable";
		else
			$expandable = "";

		foreach ( $values as $meta_id => $linedata ) {
			echo "<div class='$prefix {$css}line$expandable' id='$prefix-$meta_id'>"
			. "<div class='{$css}js-flap'>"
			 . "<div class='ui-state-default'><a class='fbk_cf_js fbk_cf_sortable_handle'>⇧<br>⇩</a></div>"
			 . "<div class='ui-state-default'><a class='ui-icon ui-icon-plusthick fbk_cf_js' title='Element duplizieren (Shiftklick: Neues leeres Element)'>+</a></div>"
			 . "<div class='ui-state-default'><a class='ui-icon ui-icon-closethick fbk_cf_js' title='Element löschen (Shiftklick: Element leeren)'>x</a></div>"
			 . ( $expandable ? "<div class='ui-state-default'><a class='ui-icon ui-icon-zoomin fbk_cf_js' title='Element auf- oder zuklappen'>*</a></div>" : '' )
			. "</div>";

			if ( ! empty($fbk_cf_boxes[$box]['shared']) && ! empty($linedata['shared_with']) ) {
				$unlink_id = "{$prefix}[$meta_id][_unlink_shared]";
				echo "<div class='fbk-share-info'><p>Dieses Objekt wird mit ",
				 ( count($linedata['shared_with']) > 1 ? "den folgenden Schulen" : "der folgenden Schule" ), " geteilt: ";
				$shares = array();
				foreach ( $linedata['shared_with'] as $shared_with ) {
					$terms = array();
					foreach ( wp_get_object_terms( $shared_with, array( 'category', 'loc' ) ) as $term )
						$terms[$term->taxonomy] = $term;
					echo "<a class='c-{$terms['category']->slug} school_connect_item' href='" . get_edit_post_link( $shared_with )
					 . "'> " . get_the_title( $shared_with ) . " (" . $terms['loc']->name . ")</a>";
				}
				echo "<br>Alle Änderungen (außer der Objektreihenfolge) werden für alle Schulen übernommen.<br>",
				 "<label for='$unlink_id'><input type='checkbox' name='$unlink_id' id='$unlink_id'> Diese Verbindung kappen</label></p></div>";
			}

			foreach ( $fields as $id => $content ) {
				fbk_cf_print_field( $id, $content, $prefix, $meta_id, $linedata );
			}
			echo "<input type='hidden' name='{$prefix}[$meta_id][_id]' class='menu_order' value='$linedata[_id]'>";
			echo "</div>";
		}
		echo "</div>\n";

		if ( ! empty($fbk_cf_boxes[$box]['shared']) ) {
?>
<div class='fbk_share_add'>
<input
	type='button'
	class='button'
	value='<?= $fbk_cf_boxes[$box]['title'] ?> von anderen Schulen einbinden'
	onclick='tb_show("Objekte von anderen Schulen übernehmen","media-upload.php?post_id=<?= $post->ID ?>&amp;type=school_object_share&amp;TB_iframe=true&amp;tab=type");'
>
</div>
<?php
		}

	} elseif ( 'single' == $fbk_cf_boxes[$box]['type'] ) {
		$value = get_post_meta( $post->ID, $prefix, true );
		$fields['value'] = $value;
		$fields['name'] = $prefix . 'content';
		echo "\n<div class='$prefix-wrapper $css$fields[type]'>" . (isset($fields['label'])?"<label for='$fields[name]'>$fields[label]</label>":"")
		. fbk_cf_get_input_html( $fields )
		. "</div>";

	} else {
		$values = (array) get_post_meta( $post->ID, $prefix, true );
		foreach ( $fields as $id => $content ) {
			$content['value'] = ( isset($values[$id]) ? $values[$id] : (isset($content['default'])?$content['default']:'') );
			$content['name'] = $prefix . $id;
			if ( isset( $content['callback'] ) )
				foreach ( $content['callback'] as $f => $cb )
					if ( is_callable( $cb ) )
						$content[$f] = call_user_func( $cb, $content[$f] );
			echo "\n<div class='$prefix-wrapper $prefix-$id-wrapper $css$content[type]'>" . (isset($content['label'])?"<label for='$content[name]'>$content[label]</label>":"")
			. fbk_cf_get_input_html( $content )
			. "</div>";
		}
	}
}

// Course tag save prep
add_action( 'admin_init', 'fbk_cf_filter_postarray' );
function fbk_cf_filter_postarray() {
	global $fbk_cf_prefix;
	if ( ! isset( $_POST['tax_input']['course_tag'] ) || ! is_array( $_POST['tax_input']['course_tag'] ) )
		return;
	$alltags = array();
	foreach ( $_POST['tax_input']['course_tag'] as $meta_id => $fields ) {
		foreach ( $fields as $field_id => $tags ) {
			$tags = explode(',', $tags);
			$_POST[$fbk_cf_prefix . 'courses'][$meta_id][$field_id] = $tags;
			$alltags = array_merge( $alltags, $tags );
		}
	}
	$alltags = implode( ',', array_unique( $alltags) );
	$_POST['tax_input']['course_tag'] = $alltags;
}

// Course tag auto-complete request sends a malformed ID we can correct
add_filter( 'sanitize_key', 'fbk_ajax_filter_tagsearch', 1, 2 );
function fbk_ajax_filter_tagsearch( $key, $raw_key ) {
	if ( preg_match( '/fbk_cf_line_[a-z0-9]+__course_tag/', $raw_key ) )
		return 'course_tag';
	return $key;
}

/***** Saving routines ******/

add_action( 'save_post', 'fbk_cf_save_post', 10, 2 );
function fbk_cf_save_post( $post_id, $post ) {
	if ( ! post_type_supports( $post->post_type, FBK_CF_FEATURE ) )
		return;
	if ( ! current_user_can( 'edit_posts', $post_id ) ) //_post as per the (default) capability type of the school post type
		return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return;

	global $fbk_cf_prefix, $fbk_cf_boxes, $wpdb;

	$parent_id = wp_is_post_revision( $post_id );

	foreach ( $fbk_cf_boxes as $box_id => $box ) {
		$prefix = $fbk_cf_prefix . $box_id;
		unset($value);

		/*** Save to revision ***/
		if ( ! empty($parent_id) ) {
			if ( ! fbk_mirror_school_meta( $parent_id, $post_id, $box_id ) ) {
				foreach ( (array) get_post_meta( $parent_id, $prefix, false ) as $v )
					add_metadata( 'post', $post_id, $prefix, $v );
			}
			continue;
		}
		/*** End revision ***/

		if ( ! isset( $_POST[$prefix . '_nonce'] ) || ! wp_verify_nonce( $_POST[$prefix . '_nonce'], $prefix . '-add' ) )
			continue;

		/*** Box type 'lines' ***/
		if ( 'lines' == $box['type'] ) {
			$old_data = fbk_get_school_meta( $post_id, $box_id );

			//Collect the data:
			foreach ( $_POST[$prefix] as $meta_id => $fields ) {
				if ( isset($fields['_just_linked']) ) {
					unset( $old_data[$meta_id] );
					continue;
				}
				if ( isset($box['req']) && empty($fields[$box['req']]) )
					continue;
				if ( isset($box['req-one_of']) ) {
					$one_of = false;
					foreach ( $box['req-one_of'] as $req ) {
						if ( ! empty($fields[$req]) ) {
							$one_of = true;
							break;
						}
					}
					if ( ! $one_of )
						continue;
				}

				$value = array( '_id' => $fields['_id'] );

				foreach ( $box['fields'] as $field_id => $field_content ) {
					$value[$field_id] = fbk_cf_sanitize_input( @$fields[$field_id], $field_content, $post_id );
				}

				if ( ! empty($box['shared']) && isset($fields['_unlink_shared']) ) {
					if ( $old_data[$meta_id]['is_foreign'] )
						fbk_cf_unlink_shared( $box_id, $meta_id, $old_data[$meta_id]['shared_with'][0], $post_id );
					else
						fbk_cf_relocate_shared( $box_id, $meta_id, $post_id );
					unset( $old_data[$meta_id] );
				}

				if ( $old_data && array_key_exists( $meta_id, $old_data ) ) {
					if ( ! empty($box['shared']) && $old_data[$meta_id]['is_foreign'] ) {
						if ( $old_data[$meta_id]['_id'] != $value['_id'] ) {
							foreach ( get_post_meta( $post_id, "_foreign_$box_id" ) as $shareset ) {
								if ( $old_data[$meta_id]['shared_with'][0] == $shareset['school_id'] && $meta_id == $shareset['object_id'] ) {
									$new_shareset = $shareset;
									$new_shareset['order'] = $value['_id'];
									update_post_meta( $post_id, "_foreign_$box_id", $new_shareset, $shareset );
									break;
								}
							}
						}
						unset( $value['_id'] );
						fbk_update_school_meta( $old_data[$meta_id]['shared_with'][0], $box_id, $value, $meta_id );
					} else {
						fbk_update_school_meta( $post_id, $box_id, $value, $meta_id );
					}
					unset( $old_data[$meta_id] );
				} else {
					fbk_add_school_meta( $post_id, $box_id, $value );
				}
			}
			if ( $old_data ) {
				foreach ( $old_data as $meta_id => $old_object ) {
					fbk_delete_school_meta( $post_id, $box_id, $meta_id );
				}
			}

		} elseif ( 'single' == $box['type'] ) {
		/*** Box type 'single' ***/
			$v = @$_POST[$prefix.'content'];
			if ( 'bitmask' == $field_content['type'] )
				if ( empty($v) )
					$v = 1;
				else
					$v = array_sum( $v );
			if ( is_string($v) )
				trim($v);
			update_post_meta( $post_id, $prefix, $v );
		} else {
		/*** Box type 'normal' ***/
			foreach ( $box['fields'] as $field_id => $field_content ) {
				$v = @$_POST[$prefix.$field_id];
				if ( 'bitmask' == $field_content['type'] )
					if ( empty($v) )
						$v = 1;
					else
						$v = array_sum( $v );
				if ( is_string($v) )
					trim($v);
				$value[$field_id] = $v;
			}
			update_post_meta( $post_id, $prefix, $value );
		}
	}
}

function fbk_cf_sanitize_input( $input, $field_specs, $post_id ) {
	switch ( $field_specs['type'] ) {
	    case 'course_tag':
		if ( empty($input) ) {
			$value = '';
		} else {
			$obj_course_tags = wp_get_object_terms( $post_id, 'course_tag' );
			$course_tag_ids = array();
			foreach ( $obj_course_tags as $obj_course_tag ) {
				if ( in_array( $obj_course_tag->name, $input ) ) {
					$course_tag_ids[] = $obj_course_tag->term_id;
				}
			}
			$value = implode( ',', $course_tag_ids );
		}
		break;
	    case 'checkbox':
		$value = empty($input) ? 0 : 1;
		break;
	    case 'accstack':
		$value = array();
		foreach ( $input as $i => $stack ) {
			if ( $i !== 'proto' && fbk_f_matrix_compress( $stack['values'] ) ) {
    				$menu_order = @$stack['menu_order'];
    				unset( $stack['menu_order'] );
	    			if ( ! empty($menu_order) || '0' === $menu_order )
	    				$value[ (int)$menu_order ] = $stack;
	    			else
		    			$value[] = $stack;
			}
		}
		ksort( $value );
		break;
	    case 'coursestack':
	    	$value = array();
	    	foreach ( $input as $i => $stack ) {
	    		if ( 'period' === $i ) {
	    			$value['period'] = $stack;
	    		} elseif ( 'proto' !== $i ) {
		    		foreach ( $stack['values'] as $j => $v ) {
		    			if ( ! $v && 0 !== $v && '0' !== $v )
		    				unset( $stack['values'][$j] );
		    			elseif ( false === strpos( $v, ',' ) && false === strpos( $v, '.' ) )
		    				$stack['values'][$j] = (int) $v;
		    			else
		    				$stack['values'][$j] = str_replace( ',', '.', $v );
		    		}
		    		if ( ! empty( $stack['values'] ) ) {
	    				$menu_order = @$stack['menu_order'];
	    				unset( $stack['menu_order'] );
		    			if ( ! empty($menu_order) || '0' === $menu_order )
		    				$value[ (int)$menu_order ] = $stack;
		    			else
			    			$value[] = $stack;
		    		}
		    	}
	    	}
	    	ksort( $value );
		break;
	    default:
		if ( ! empty( $field_specs['numeric'] ) ) {
			if ( preg_match( '~^\s*(\d+)\s*$~', $input, $matches ) )
				$value = (int) $matches[1];
			elseif ( preg_match( '~\s*(\d+)[.,](\d+)\s*$~', $input, $matches ) )
				$value = $matches[1] . '.' . $matches[2];
			else
				$value = '';
		} else {
			$value = $input;
		}
		break;
	}
	return $value;
}

function fbk_f_matrix_compress( &$matrix, $alter_matrix = true ) {
	$result = (array) $matrix;
	foreach ( $result as $key => $column ) {
		foreach ( $column as $ckey => $cell ) {
			foreach ( $cell as $week => $price )
				if ( empty( $price ) && 0 !== $price && '0' !== $price )
					unset( $result[$key][$ckey][$week] );
				elseif ( false === strpos( $price, ',' ) && false === strpos( $price, '.' ) )
					$result[$key][$ckey][$week] = (int) $price;
				else
					$result[$key][$ckey][$week] = str_replace( ',', '.', $price );
			if ( empty( $result[$key][$ckey] ) )
				unset( $result[$key][$ckey] );
		}
		if ( empty( $result[$key] ) )
			unset ( $result[$key] );
	}
	if ( $alter_matrix )
		$matrix = $result;
	return $result;
}

add_action( 'wp_restore_post_revision', 'fbk_cf_restore_revision', 10, 2 );
function fbk_cf_restore_revision( $post_id, $revision_id ) {
	$post = get_post( $post_id );
	$rev = get_post( $revision_id );
	if ( ! post_type_supports( $post->post_type, FBK_CF_FEATURE ) || ! post_type_supports( $rev->post_type, FBK_CF_FEATURE ) )
		return;
	global $fbk_cf_prefix, $fbk_cf_boxes;
	foreach ( $fbk_cf_boxes as $box_id => $box ) {
		if ( 'courses' == $box_id || 'accommodation' == $box_id ) {
			fbk_mirror_school_meta( $revision_id, $post_id, $box_id );
		} else {
			$prefix = $fbk_cf_prefix . $box_id;
			delete_metadata( 'post', $post_id, $prefix );
			$meta = (array) get_post_meta( $revision_id, $prefix, false );
			foreach ( $meta as $v )
				add_metadata( 'post', $post_id, $prefix, $v );
		}
	}
}

function fbk_cf_relocate_shared( $object_type, $object_id, $current_owner, $new_owner = 0, $keep_current_owner_linked = false ) {
	global $wpdb, $fbk_cache, $fbk_cf_prefix;

	$_objects = fbk_get_school_meta( $current_owner, $object_type );
	if ( ! $_objects || ! array_key_exists( $object_id, $_objects ) )
		return false;

	$object = $_objects[$object_id];

	if ( ! isset($object['is_foreign']) || $object['is_foreign'] )
		return false;

	$new_owner = (int) $new_owner;
	if ( ! $new_owner || ! in_array( $new_owner, $object['shared_with'][0] ) )
		$new_owner = $object['shared_with'][0];

	$new_shared_with = $object['shared_with'];
	$new_owner_order = 0;
	if ( $keep_current_owner_linked )
		$new_shared_with[] = $current_owner;
	unset( $new_shared_with[ array_search($new_owner, $new_shared_with) ] );
	foreach ( get_post_meta( $new_owner, "_foreign_$object_type" ) as $shareset ) {
		if ( $current_owner == $shareset['school_id'] && $object_id == $shareset['object_id'] ) {
			$new_owner_order = $shareset['order'];
			delete_post_meta( $new_owner, "_foreign_$object_type", $shareset );
			break;
		}
	}

	if ( in_array( $object_type, array( 'courses', 'accommodation' ) ) ) {
		$wpdb->query( "UPDATE wp_fbk_$object_type SET post_id = $new_owner, _id = $new_owner_order WHERE meta_id = $object_id" );
	} else {
		$object['_original_meta_value']['_id'] = $new_owner_order;
		$wpdb->query( "UPDATE $wpdb->postmeta SET post_id = $new_owner, meta_value = '" . serialize( $object['_original_meta_value'] ) . "' WHERE meta_id = $object_id" );
	}

	foreach ( $new_shared_with as $id ) {
		$current_shareset = false;
		if ( $_sharesets = get_post_meta( $id, "_foreign_$object_type" ) ) {
			foreach ( $_sharesets as $shareset ) {
				if ( $current_owner == $shareset['school_id'] && $object_id == $shareset['object_id'] ) {
					$current_shareset = $shareset;
					break;
				}
			}
		}
		if ( ! $current_shareset ) {
			$new_shareset = array(
				'school_id' => $new_owner,
				'object_id' => $object_id,
				'order' => $object['_id']
			);
			add_post_meta( $id, "_foreign_$object_type", $new_shareset );
		} else {
			$new_shareset = $current_shareset;
			$new_shareset['school_id'] = $new_owner;
			update_post_meta( $id, "_foreign_$object_type", $new_shareset, $current_shareset );
		}

		add_post_meta( $new_owner, "_shared_$object_type", array(
			'school_id' => $id,
			'object_id' => $object_id
		));
	}

	foreach ( get_post_meta( $current_owner, "_shared_$object_type" ) as $shareset )
		if ( $object_id == $shareset['object_id'] )
			delete_post_meta( $current_owner, "_shared_$object_type", $shareset );

	$stale_schools = $new_shared_with;
	$stale_schools[] = $new_owner;
	$stale_schools[] = $current_owner;
	$fbk_cache->delete( 'school', array_unique( $stale_schools ) );

	return true;
}

function fbk_cf_unlink_shared( $object_type, $object_id, $owner_id, $foreign_id ) {
	global $fbk_cache;
	if ( $_sharesets = get_post_meta( $foreign_id, "_foreign_$object_type" ) ) {
		foreach ( $_sharesets as $shareset ) {
			if ( $owner_id == $shareset['school_id'] && $object_id == $shareset['object_id'] ) {
				delete_post_meta( $foreign_id, "_foreign_$object_type", $shareset );
				$fbk_cache->delete( 'school', $foreign_id );
				break;
			}
		}
	}
	if ( $_sharesets = get_post_meta( $owner_id, "_shared_$object_type" ) ) {
		foreach ( $_sharesets as $shareset ) {
			if ( $foreign_id == $shareset['school_id'] && $object_id == $shareset['object_id'] ) {
				delete_post_meta( $owner_id, "_shared_$object_type", $shareset );
				$fbk_cache->delete( 'school', $owner_id );
				break;
			}
		}
	}
}

/**
 * Duplicate all school metas specified by $from_post and $type, deleting all existing school metas of the relevant type in $to_post.
 *
 * @arg from_post int post ID from which to take the metas
 * @arg to_post int post ID to which to mirror
 * @arg type string 'course' | 'accommodation'
 */
function fbk_mirror_school_meta( $from_post, $to_post, $type ) {
	global $wpdb, $fbk_cf_boxes;
	if ( ! in_array( $type, array( 'courses', 'accommodation' ) ) )
		return false;

	fbk_delete_school_meta( $to_post, $type );

	$_fields = array( '_id' );
	foreach ( array_keys( $fbk_cf_boxes[$type]['fields'] ) as $key )
		$_fields[] = $key;
	$source_fields = 'source.' . implode( ', source.', $_fields );
	$target_fields = '`' . implode( '`, `', $_fields ) . '`';

	$query = "INSERT INTO `wp_fbk_$type` ( `post_id`, $target_fields ) SELECT $to_post, $source_fields FROM `wp_fbk_$type` AS source WHERE source.post_id = $from_post";
	$affected_rows = $wpdb->query( $query );

	return $affected_rows ? $affected_rows : -1;
}

function fbk_update_school_meta( $school_id, $type, $value, $meta_id ) {
	global $wpdb, $fbk_cf_prefix;
	$existing_data = fbk_get_school_meta( $school_id, $type );
	if ( false === $existing_data )
		return $existing_data;

	if ( ! array_key_exists( $meta_id, $existing_data ) )
		return fbk_add_school_meta( $school_id, $type, $value );

	unset(
		$value['meta_id'],
		$value['meta_key'],
		$value['post_id']
	);

	if ( in_array( $type, array( 'courses', 'accommodation' ) ) ) {
		$formats = fbk_cf_get_formats( $value, $type );
		$set = array();
		foreach ( array_keys( $value ) as $key ) {
			$set[$key] = '`' . $key . '`=' . $formats[$key];
		}
		$query = $wpdb->prepare(
			"UPDATE `wp_fbk_$type` SET " . implode(', ', $set) . " WHERE `meta_id`=$meta_id AND `post_id`=$school_id",
			array_map( 'maybe_serialize', $value )
		);
		return $wpdb->query( $query );
	} else {
		return update_post_meta( $school_id, $fbk_cf_prefix . $type, $value, $existing_data[$meta_id]['_original_meta_value'] );
	}
}

function fbk_add_school_meta( $school_id, $type, $value ) {
	global $wpdb, $fbk_cf_prefix;

	unset(
		$value['meta_id'],
		$value['meta_key'],
		$value['post_id']
	);

	if ( in_array( $type, array( 'courses', 'accommodation' ) ) ) {
		$value['post_id'] = $school_id;
		$formats = fbk_cf_get_formats( $value, $type );
		$set = array();
		foreach ( array_keys( $value ) as $key ) {
			if ( array_key_exists( $key, $formats ) )
				$set[$key] = '`' . $key . '`=' . $formats[$key];
		}
		$query = $wpdb->prepare(
			"INSERT INTO `wp_fbk_$type` SET " . implode(', ', $set),
			array_map( 'maybe_serialize', $value )
		);
		return $wpdb->query( $query );

	} else {
		return add_post_meta( $school_id, $fbk_cf_prefix . $type, $value );
	}
}

function fbk_delete_school_meta( $school_id, $type, $meta_id = false ) {
	global $wpdb, $fbk_cf_prefix, $fbk_cf_boxes, $fbk_cache;
	if ( ! empty($fbk_cf_boxes[$type]['shared']) && ! wp_is_post_revision( $school_id ) ) {
		$existing_objects = fbk_get_school_meta( $school_id, $type );
		if ( $meta_id )
			$existing_objects = array( $meta_id => $existing_objects[$meta_id] );
		$meta_ids = array_flip( array_keys( $existing_objects ) );
		foreach ( $existing_objects as $_meta_id => $object ) {
			if ( $object['is_foreign'] ) {
				unset( $meta_ids[$_meta_id] );
				fbk_cf_unlink_shared( $type, $_meta_id, $object['shared_with'][0], $school_id );
			} elseif ( ! empty($object['shared_with']) ) {
				unset( $meta_ids[$_meta_id] );
				fbk_cf_relocate_shared( $type, $_meta_id, $school_id );
			}
		}
		$meta_ids = array_keys( $meta_ids );
		if ( empty($meta_ids) )
			return true;
	} else {
		if ( false === $meta_id )
			$meta_ids = false;
		else
			$meta_ids = (array) $meta_id;
	}

	if ( in_array( $type, array( 'courses', 'accommodation' ) ) ) {
		$query = "DELETE FROM `wp_fbk_$type` WHERE post_id = $school_id";
		if ( $meta_ids )
			$query .= " AND `meta_id` IN (" . implode( ',', $meta_ids ) . ")";
		$wpdb->query( $query );
		wp_cache_delete( $school_id, 'school_meta' );
	} else {
		if ( false === $meta_ids ) {
			delete_post_meta( $school_id, $fbk_cf_prefix . $type );
		} else {
			foreach ( $meta_ids as $_meta_id ) {
				$existing_value = fbk_get_school_meta( $school_id, $type );
				if ( array_key_exists( $_meta_id, $existing_value ) )
					delete_post_meta( $school_id, $fbk_cf_prefix . $type, $existing_value[$_meta_id]['_original_meta_value'] );
			}
		}
	}

	$fbk_cache->delete( 'school', $school_id );
}

add_action( 'before_delete_post', 'fbk_cf_delete_post_filter' );
function fbk_cf_delete_post_filter( $post_id ) {
	global $fbk_cf_boxes;
	$post =& get_post( $post_id );
	if ( ! post_type_supports( $post->post_type, FBK_CF_FEATURE ) )
		return;
	$parent_id = wp_is_post_revision( $post_id );
	if ( $parent_id ) {
		$parent =& get_post( $parent_id );
		if ( ! post_type_supports( $parent->post_type, FBK_CF_FEATURE ) )
			return;
	}

	foreach ( $fbk_cf_boxes as $box_id => $box ) {
		if ( ! empty($box['shared']) ) {
			fbk_delete_school_meta( $post_id, $box_id );
			$is_deleted[] = $box_id;
		}
	}
	foreach ( array_diff( array( 'courses', 'accommodation' ), $is_deleted ) as $type ) {
		fbk_delete_school_meta( $post_id, $type );
	}
}

function fbk_cf_get_formats( $data, $type ) {
	global $fbk_cf_boxes;
	$formats = array();
	if ( in_array( $type, array( 'courses', 'accommodation' ) ) ) {
		$box = $fbk_cf_boxes[$type];
		foreach ( $data as $field_id => $field )
			if ( '_id' == $field_id || 'post_id' == $field_id || 'meta_id' == $field_id
			 || 'checkbox' == @$box['fields'][$field_id]['type'] )
				$formats[$field_id] = '%d';
			elseif ( array_key_exists( $field_id, $box['fields'] ) )
				$formats[$field_id] = '%s';
	}
	return $formats;
}
?>
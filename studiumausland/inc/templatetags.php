<?php
/**
 * @package Studium_Ausland
 */

/**
 * fbk_tt_the_navmenu
 *	Echoes the navigation menu of the given category.
 *
 * @args $cat_id	int	Category term_id
 */
function fbk_tt_the_navmenu( $cat_id = 0 ) {
	global $wp_query, $fbk_query, $fbk;
	if ( $cat_id || is_archive() || is_single() ) {
		if ( ! $cat_id ) {
			if ( ! empty($fbk_query->category) )
				$cat_id = $fbk_query->category->term_id;
			elseif ( FBK_AJAX )
				$cat_id = get_term( $_REQUEST['rel'], 'category' )->term_id;
			else
				$cat_id = $GLOBALS['fbk']->default_cat->term_id;
		}

		echo fbk_get_menu( $cat_id );
	} else {
		echo '<nav id="navi" style="display:none;"></nav>';
	}
}

/**
 * fbk_the_title - Returns or displays a title for use in <title> tags.
 *	Outside of category-specific contexts (archive pages, schools), the sitename will be added automatically.
 *
 * @arg $echo               boolean  true to display, false to return the title.
 * @arg $sep                string   Character(s) to use as separator between page title and site name, where applicable.
 * @arg $sitename_location  string   Where to place the site name. Any string except 'right' causes the site name to be placed to the left of the content title.
 * @arg $paged_override     int      Set to override global $paged
 */
function fbk_the_title( $echo = true, $sep = '|', $sitename_location = 'right', $paged_override = false ) {
	global $fbk, $fbk_query, $paged;
	$append_sitename = true;
	if ( is_front_page() ) {
		$title = get_bloginfo( 'description' );
	} elseif ( is_404() ) {
		$title = 'Nicht gefunden';
	} elseif ( is_search() ) {
		$title = 'Suche';
	} elseif ( is_home() ) {
		$title = 'News';
		if ( $paged_override ) {
			if ( $paged_override > 1 )
				$title .= ', Seite ' . $paged_override;
		} elseif ( is_paged() ) {
			$title .= ', Seite ' . $paged;
		}
	} elseif ( $fbk->did_news_query ) {
		$title = 'News';
		if ( $paged_override ) {
			if ( $paged_override > 1 )
				$title .= ', Seite ' . $paged_override;
		} elseif ( 1 != $fbk->did_news_query ) {
			$title .= ', Seite ' . $fbk->did_news_query;
		}
	} elseif ( is_archive() ) {
		$title = fbk_get_category_meta( $fbk_query->detailed_taxonomy . '_title' );
		$append_sitename = false;
	} elseif ( is_singular( 'school' ) ) {
		$title = fbk_get_category_meta( 'school_title' );
		$append_sitename = false;
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		$title = apply_filters( 'single_post_title', $post->post_title, $post );
	}

	if ( $title && $append_sitename )
		$title = ( 'right' == $sitename_location ? $title . ' ' . trim($sep) . ' ' . get_bloginfo( 'name' ) : get_bloginfo( 'name' ) . ' ' . trim($sep) . ' ' . $title );
	elseif ( ! $title )
		$title = get_bloginfo( 'name' );

	if ( $echo )
		echo $title;
	else
		return $title;
}

/**
 * fbk_tt_get_the_excerpt
 *	Returns the post excerpt for the given post_id. Will auto-generate an excerpt even if the post is not the current post of the loop.
 *
 * @arg $post_id	int	ID of the post
 *
 * @return string
 */
function fbk_tt_get_the_excerpt( $post_id ) {
	if ( ! $post_id || in_the_loop() && $post_id == $GLOBALS['post']->ID )
		return get_the_excerpt();

	// Hacking wp_trim_excerpt() / get_the_content() to avoid having to start a loop
	global $post, $page, $pages;

	$backup_post = $post;
	$backup_page = $page;
	$backup_pages = $pages;

	$post = get_post( $post_id );
	$pages = array( $post->post_content );
	$page = 1;
	
	$excerpt = get_the_excerpt();

	$post = $backup_post;
	$page = $backup_page;
	$pages = $backup_pages;
	// End hack.
	
	return $excerpt;
}

/**
 * fbk_tt_the_slice_navigation
 *	Returns or echoes a school's internal navbar
 *
 * @arg $echo	bool	true to echo, false to return as string
 */
function fbk_tt_the_slice_navigation( $echo = true ) {
	global $post, $fbk_cf_slices;
	$str = "<table><tbody><tr>";
	$permalink = get_permalink();
	foreach ( $fbk_cf_slices as $slug ) {
		if ( ! fbk_tt_has_slice( $slug, $post->ID ) )
			continue;
		$classes = array( 'sni-'.$slug );
		if ( $slug == FBK_DEFAULT_SLICE )
			$classes[] = 'active';
		$str .= "\n<td class='sni-$slug'><a href='" . $permalink . "#$slug'>" . stripslashes(get_option( 'fbk_slice_label_' . $slug )) . "</a></td>";
	}
	if ( 'open' == $post->comment_status )
		$str .= "<td class='sni-comments'><a href='" . $permalink . "#comments'></a></td>";
	$str .= "\n</tr></tbody></table>";
	if ( $echo )
		echo $str;
	return $str;
}

/**
 * fbk_tt_get_school_data_array
 *	Returns an array with all the school's relevant data for use by front-end scripts such as MemBox.
 *
 * @arg $post	object	school post object
 *
 * @return array
 */
function fbk_tt_get_school_data_array( $post ) {
	global $fbk_cf_boxes;
	if ( ! $post )
		return array();
	$data = array(
		'id' => $post->ID,
		'lbl' => $post->post_title,
		'url' => str_replace( home_url(), '', get_permalink($post->ID) ),
	);

	$school_meta = fbk_get_school_meta( $post->ID );
	foreach ( $school_meta['courses'] as $meta_id => $course ) {
		// Constraints as set by $course['dur']
		if ( ! empty( $course['dur'][0] ) )
			$iMin = (int) $course['dur'][0] - 1;
		else
			$iMin = 0;
		if ( ! empty( $course['dur'][1] ) )
			$iMax = (int) $course['dur'][1] - 1;
		else
			$iMax = 99;

		// Constraints as set by $course['cost']
		$blocks = $blocked_weeks = array();
		$blockers = 0;
		if ( $course['cost'] ) {
			if ( 's' == $course['cost']['period'] ) {
				$iMax = 11;
				$iMin = 0;
			}
			foreach ( $course['cost'] as $i => $stack ) {
				if ( 'period' !== $i && 'tuition' == $stack['type'] && 'add' != $stack['calc'] ) {
					$blockers++;
					$_min = min(array_keys($stack['values']));
					if ( $_min > $iMin )
						for ( $j = $iMin; $j < $_min; $j++ )
							@$blocks[$j]++;
					foreach ( $stack['values'] as $week => $val )
						if ( ! $val )
							while ( $week <= $iMax && empty($stack['values'][$week]) )
								@$blocks[$week++]++;
				}
			}
		}
		foreach ( $blocks as $week => $block )
			if ( $block == $blockers )
				$blocked_weeks[] = $week;

		$data[ 'c-' . $meta_id ] = $course['name']
		 . "\t" . $course['cost']['period'] . "\t$iMin\t$iMax\t," . implode( ',', $blocked_weeks ) . ",";
	}

	if ( fbk_tt_has_slice( FBK_ACCOMMODATION_SLUG, $post->ID ) ) {
		$mask = array(
			's' => 16,	'sc' => 1,
			'd' => 32,	'br' => 2,
			't' => 64,	'hb' => 4,
			'm' => 128,	'fb' => 8
		);
		foreach ( $school_meta['accommodation'] as $meta_id => $acc ) {
			$rb = 0;
			if ( ! empty($acc['cost']) )
				foreach ( $acc['cost'] as $stack )
					if ( ! empty($stack['values']) && 'add' != $stack['calc'] )
						foreach ( $stack['values'] as $r => $row ) {
							$rb |= $mask[$r];
							foreach ( array_keys($row) as $b )
								$rb |= $mask[$b];
						}

			$data[ 'a-' . $meta_id ] = ( $acc['name'] ? $acc['name'] : $fbk_cf_boxes['accommodation']['fields']['type']['opts'][$acc['type']] )
			 . "\t" . ($rb < 16 ? 0 : '') . dechex($rb);
		}
	}

	foreach ( $school_meta['fees'] as $meta_id => $svc ) {
		$data[ 'f-' . $meta_id ] = $svc['key'] . "\t" . $svc['desc'];
	}
	
	return $data;
}

/**
 * Calculates intervals for the time periods specified. The higher the period index, the higher its priority.
 *
 * @arg $_intervals array( datestr, datestr ) - if datestr is empty, will use sensible end-of-year limits
 * @arg $transparent bool - if false, the highest-priority period covers all others opaquely.
 * @arg $years array - widen end-of-year limits to include these years
 *
 * @return $transparent == false
 *	? array( start_date, end_date, period_index )
 *	: array( start_date, end_date, array_of_period_indices )
 */
function fbk_tt_get_intervals_by_timeline( $_intervals, $transparent = false, $years = array() ) {
	$result = array();

	foreach ( $_intervals as $key => $_interval ) {
		if ( empty($_interval[0]) && empty($_interval[1]) ) {
			$_intervals[$key] = array();
		} elseif ( empty($_interval[0]) ) {
			$d = new DateTime( $_interval[1] );
			$_intervals[$key] = array( 1 => $d );
			$years[] = $d->format('Y');
		} elseif ( empty($_interval[1]) ) {
			$d = new DateTime( $_interval[0] );
			$_intervals[$key] = array( 0 => $d );
			$years[] = $d->format('Y');
		} else {
			$_intervals[$key] = array(
				new DateTime( $_interval[0] ),
				new DateTime( $_interval[1] )
			);
			$years[] = $_intervals[$key][0]->format('Y');
			$years[] = $_intervals[$key][1]->format('Y');
		}
	}

	$years = array_unique( $years );
	if ( empty($years) )
		$years = array( date('Y') );
	$limits = array(
		new DateTime( '1.1.' . min($years) ),
		new DateTime( '31.12.' . max($years) )
	);

	foreach ( $_intervals as $key => $_interval )
		foreach ( $limits as $i => $d )
			if ( ! isset( $_interval[$i] ) )
				$_intervals[$key][$i] = clone $d;

	if ( 1 == count($_intervals) ) {
		$result[] = array( $_intervals[0][0], $_intervals[0][1], $transparent ? array(0) : 0 );
	} else {
		$datemash = array();
		foreach ( $_intervals as $key => $overlay ) {
			$datemash[$key+1] = $overlay[0];
			$datemash[-($key+1)] = $overlay[1];
		}
		asort( $datemash );

		/* $datemash is now a timeline: Each entry specifies a date (the array value), which period it applies to ( abs(array key) ) and whether it's
		*	the start or the end of said period ( signum(array key) ).
		* The following loop walks this timeline, working it into an array of [ start_date, end_date, period_key ] tuples.
		* Periods are prioritized by their position in the original input only; any advanced sorting will have to be done beforehand (esp. on calc=add!)
		*/

		$stack = array();
		foreach ( $datemash as $key => $date ) {
			if ( $key > 0 ) { // the start of a period
				if ( false !== end($stack) ) { // There's already a period running
					if ( $transparent ) {
						if ( $datemash[$prevkey] < $date ) {
							$date->modify( '-1 day' );
							$result[] = array( clone $datemash[$prevkey], clone $date, $stack );
							$date->modify( '+1 day' );
						}
						$stack[] = $key - 1;
					} elseif ( current($stack) < $key ) { // The running period is lower priority than the new one, close it... 
						if ( $datemash[$prevkey] < $date ) { // Disregard simultaneous starts
							$date->modify('-1 day'); // Border overlap protection - back...
							$result[] = array( clone $datemash[$prevkey], clone $date, current($stack)-1 );
							$date->modify('+1 day'); //... and forth again.
						}
						$stack[] = $key; // ... then open the new period
					} else { // The running period is higher priority than the new one
						$inserted = false;
						while ( prev($stack) !== false ) { // Insert period into stack without starting it
							if ( current($stack) < $key ) {
								array_splice( $stack, key($stack)+1, 0, $key );
								$inserted = true;
								break;
							}
						}
						if ( ! $inserted )
							array_unshift( $stack, $key );
						$key = $prevkey; // Jedi mind trick: You didn't see nothing didn't happen.
					}
				} else { // Open the new period
					if ( $transparent )
						$stack[] = $key - 1;
					else
						$stack[] = $key;
				}
			} else { // the end of a period
				if ( $transparent ) {
					if ( $datemash[$prevkey] < $date ) {
						$result[] = array( clone $datemash[$prevkey], clone $date, $stack );
						$datemash[$key]->modify('+1 day');
					}
					unset( $stack[ array_search( -$key - 1, $stack ) ] );
				} elseif ( end($stack) == -$key ) { // The end of the current period, close it...
					if ( $datemash[$prevkey] < $date ) { // Disregard simultaneous endings
						$result[] = array( clone $datemash[$prevkey], clone $date, -$key-1 );
						$datemash[$key]->modify('+1 day'); // Border overlap protection
					}
					array_pop( $stack ); // ... and remove traces
				} else { // The end of a lower-priority period than the current one
					unset( $stack[ array_search( -$key, $stack ) ] ); // Remove the period
					$key = $prevkey; // Jedi mind trick
				}
			}
			$prevkey = $key;
		}
	}

	return $result;
}

/**
 * fbk_calc_getFullCol
 *	Return an array of price values
 *
 * @arg $col	array	The price data as given from the initial data set
 * @arg $dur	array	Tuple of [mininum duration, maximum duration]
 * @arg $calc	string	Type of calculation used in $col ('pw', 'tot', ...)
 * @arg $max_entries	int	Maximum number of entries to calculate
 * @arg $total	bool	true to return total prices, false to return in the calculation type given in $calc
 *
 * @return array	Results are indexed 0-based, thus the first week's price is at index 0, the second at index 1, etc.
 */
function fbk_calc_getFullCol( $col, $dur, $calc, $max_entries = 12, $total = true ) {
	$result = array();
	if ( ! count( $col ) )
		return $result;
	
	if ( 'tot' == $calc || 'un' == $calc )
		$total = false;

	$start = empty($dur[0]) ? 0 : ( (int) $dur[0] ) - 1;
	$weeks = empty($dur[1]) ? 20 : (int) $dur[1];

	if ( ! isset($col[$start]) ) {
		for ( $i = $start-1; $i >= 0; $i-- )
			if ( isset($col[$i]) ) {
				$set = $i;
				break;
			}
		if ( ! isset($set) )
			for ( $i = $start; $i < $start + $weeks; $i++ )
				if ( isset($col[$i]) ) {
					$start = $set = $i;
					break;
				}
		if ( ! isset($set) )
			return $result;
	} else {
		$set = $start;
	}

	for ( $i = $start, $count = 0; $i < $weeks && ( $count < $max_entries || ! $max_entries ); $i++ ) {
		if ( isset($col[$i]) ) {
			$set = $i;
			$result[$i] = $col[$i];
		} else {
			if ( 'tot' == $calc )
				$result[$i] = $col[$set] * ($i+1) / ($set+1);
			else
				$result[$i] = $col[$set];
		}

		if ( $total )
			if ( 'nth' == $calc && isset( $result[$i-1] ) )
				$result[$i] += $result[$i-1];
			else
				$result[$i] *= ($i+1);
		if ( $result[$i] )
			$count++;
	}
	return $result;
}

/**
 * fbk_the_search_tax_select
 *	Outputs a <select> containing the given taxonomy's terms
 *
 * @arg $taxonomy	string	The requested taxonomy's slug (ie 'category', 'loc')
 * @arg $args	array	Influences the terms used, several HTML attributes, and which items, if any, should be pre-selected
 */
function fbk_the_search_tax_select( $taxonomy, $args = array() ) {
	global $wp_query;
	
	$defaults = array(
		'empty' => '',
		'field' => 'slug',
		'multi' => false,
		'id' => '',
		'attrib' => '',
		'args' => array( 'orderby' => 'name' ),
		'terms' => false,
		'selected' => false
	);
	$args = wp_parse_args( $args, $defaults );
	
	if ( ! is_array($args['terms']) )
		$args['terms'] = get_terms( $taxonomy, $args['args'] );
	
	foreach ( $args['terms'] as $term )
		$opts[$term->{$args['field']}] = $term->name;
	if ( $args['selected'] ) {
		$selection = (array) $args['selected'];
	} elseif ( $tax_query = $wp_query->tax_query->queries ) {
		foreach ( $tax_query as $tq )
			if ( $taxonomy == $tq['taxonomy'] || ( 'cat' == $taxonomy && 'category' == $tq['taxonomy'] ) )
				$selection = (array) $tq['terms'];
	} elseif ( 'category' == $taxonomy || 'tag' == $taxonomy ) {
		$selection = $wp_query->get( $taxonomy . '__in' );
		if ( $args['multi'] )
			$taxonomy .= '__in';
	} else {
		$selection = explode( ',', $wp_query->get( $taxonomy ) );
	}

	if ( $args['multi'] ) {
		$tax_name = $taxonomy . '[]';
		$multi = "multiple='multiple' data-csv='$args[multi]'";
	} else {
		$tax_name = $taxonomy;
		$multi = "";
	}
	
	echo "<select id='$args[id]' name='$taxonomy" . ($args['multi'] ? '[]' : '') . "' $multi $args[attrib]>"
	. "<option value=''>$args[empty]</option>";

	foreach ( $opts as $key => $name ) {
		if ( in_array( $key, $selection ) )
			$sel = " selected='selected'";
		else
			$sel = "";
		echo "<option$sel value='$key'>$name</option>";
	}
	
	echo "</select>";
}

/**
 * fbk_get_adjacent_link
 *	Returns a previous/next post or paged posts link depending on the current request.
 *
 * @arg $format	string	The input to be infused with the relevant variables. The following substitutions are available:
 *	%_% with href
 *	%#% with page number (in case of an archive) or post ID (in case of a singular post)
 *	%<>% with 'prev' or 'next', respectively, in accordance with the $older parameter,
 *	%t% with title,
 * @arg $older	bool	Whether to link backwards in time (true) or forwards (false)
 * @post_types	array	Which post types to include in the search
 *
 * @return string	If an adjacent post/page is not available, returns "".
 */
function fbk_get_adjacent_link( $format, $older = false, $post_types = array('post') ) {
	global $fbk, $wp_query, $paged, $post;
	$replace = array();
	
	if ( is_singular() && in_array( $post->post_type, $post_types ) ) {
		$prev = $older;
		$adjacent = get_adjacent_post( false, false, $prev );
		if ( $adjacent ) {
			$replace['href'] = get_permalink( $adjacent );
			$replace['p'] = $adjacent->ID;
			$replace['t'] = apply_filters( 'the_title', $adjacent->post_title );
		}
	} else {
		$prev = ! $older;
		if ( $fbk->did_news_query )
			$p = $fbk->did_news_query;
		else
			$p = $paged;
		if ( ! $p )
			$p = 1;
		if ( $prev && $p > 1 ) {
			$replace['href'] = previous_posts( 0, false );
			$replace['p'] = $p-1;
			$replace['t'] = fbk_the_title( false, null, null, $p-1 );
		} elseif ( ! $prev && $p < $wp_query->max_num_pages ) {
			$replace['href'] = next_posts( 0, false );
			$replace['p'] = $p+1;
			$replace['t'] = fbk_the_title( false, null, null, $p+1 );
		}
	}
	
	if ( ! $replace )
		return '';
	
	return str_replace(
		array(
			'%<>%',
			'%_%',
			'%#%',
			'%t%'
		),
		array(
			$older ? 'prev' : 'next',
			$replace['href'],
			$replace['p'],
			$replace['t']
		),
		$format
	);
}

/**
 * fbk_date
 *	Converts date strings
 *
 * @arg $date_str	string | int	input date in any format readable by strtotime(), or a Unix timestamp
 * @arg $format	string	Output format (see php.net/date)
 *
 * @return string
 */
function fbk_date( $date_str, $format = '' ) {
	if ( ! $format )
		$format = get_option( 'date_format' );

	if ( is_int($date_str) )
		$date = $date_str;
	else
		$date = strtotime( $date_str );
	if ( ! $date )
		$date = time();

	// Performance demands we turn off l10n - and here's where it bites us.
	if ( ! is_admin() || FBK_AJAX ) {
		$months = array( 1 => 'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember' );
		$month_shorts = array( 1 => 'Jan', 'Feb', 'März', 'April', 'Mai', 'Juni', 'Juli', 'Aug', 'Sept', 'Okt', 'Nov', 'Dez' );
		$days = array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );
		$format = preg_replace( "/(?<!\\\\)F/", backslashit( $months[date('n',$date)] ), $format );
		$format = preg_replace( "/(?<!\\\\)M/", backslashit( $month_shorts[date('n',$date)] ), $format );
		$format = preg_replace( "/(?<!\\\\)l/", backslashit( $days[date('w',$date)] ), $format );
		return date( $format, $date );
	}

	return date_i18n( $format, $date );
}

/**
 * get_current_offers
 *	Returns all currently active offers
 *
 * @arg $args	array	Change what's returned
 */
function get_current_offers( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'post_status' => 'publish',
		'meta_key' => '_fbk_offer_end',
		'orderby' => 'meta_value',
		'order' => 'ASC',
		'posts_per_page' => -1
	));
	$args['meta_query'][] = array(
		'key' => '_fbk_offer_end',
		'value' => date('Y-m-d'),
		'compare' => '>',
		'type' => 'DATE'
	);
	$args['post_type'] = 'offer';

	$q = new WP_Query( $args );
	return $q->posts;
}

/**
 * get_connect_category
 *	Return the category dictated by the majority of an offer's connected schools
 *
 * @arg $offer	post object	The offer you're trying to categorise
 * @arg $return	string	The term field you're looking for, or "term" to return the full term
 */
function get_connect_category( $offer, $return = 'term' ) {
	if ( is_object($offer) )
		$offer = $offer->ID;
	$offer = (int) $offer;
	$schools = get_post_meta( $offer, '_school_connect' );
	$cats = array();
	foreach ( $schools as $school ) {
		$c = get_the_category( $school );
		if ( $c )
			@$cats[$c[0]->term_id]++;
	}
	if ( ! $cats )
		return false;
	asort( $cats );
	end($cats);
	$cat = get_term( key( $cats ), 'category' );

	if ( 'term' == $return )
		return $cat;
	else
		return $cat->$return;
}

/**
 * get_offer_thumbnail
 *	Return the HTML for the thumbnail of a school linked to the given offer
 *
 * @arg $offer	post object	The offer you're trying to get an image for
 * @arg $size	string	The size of the image
 */
function get_offer_thumbnail( $offer, $size = 'thumbnail' ) {
	if ( is_object( $offer ) )
		$offer_id = $offer->ID;
	else
		$offer_id = (int) $offer;
	$schools = get_post_meta( $offer_id, '_school_connect' );
	$thumbnail = false;
	foreach ( $schools as $school )
		if ( $thumbnail = fbk_get_first_gallery_image( $school, $size ) )
			break;
	return $thumbnail;
}

/**
 * fbk_get_first_gallery_image
 *	Return the HTML for the first of the gallery images of a post, to be used e.g. as a thumbnail
 *
 * @arg $post_id	int	The post's ID
 * @arg $size	string	The size of the image
 * @arg $title	bool	Whether to populate the <img>'s title attribute
 * @arg $suppress_hijacked	bool	Whether to exclude shared galleries
 */
function fbk_get_first_gallery_image( $post_id = 0, $size = FBK_IMAGESIZE_TEASER, $title = false, $suppress_hijacked = false ) {
	global $post, $wpdb;
	if ( ! $post_id )
		$post_id = $post->ID;

	if ( $suppress_hijacked ) {
		$first = $wpdb->get_row( "SELECT * FROM $wpdb->posts"
		. " WHERE post_parent=$post_id AND post_type='attachment' AND post_mime_type LIKE 'image/%'"
		. " ORDER BY menu_order, ID DESC LIMIT 1" );
	} else {
		$atts = fbk_get_images( $post_id );
		$first = reset($atts);
	}
	
	if ( ! $first )
		return '';
	
	if ( true !== $title )
		$image_attr = array( 'title' => $title ? $title : '' );
	else
		$image_attr = array();
	$image_html = wp_get_attachment_image( $first->ID, $size, false, $image_attr );
	return $image_html;
}

/**
 * fbk_get_images
 *	Return the image attachment 'posts' of the given post, including shared galleries
 *
 * @arg $post_id	int	The post's ID
 * @arg $location	string	Filter images by location, e.g. returning only images used in the gallery, or only those used in the first slice of a school
 *
 * @return array of post objects
 */
function fbk_get_images( $post_id = 0, $location = false, $return_null = false ) {
	global $wpdb, $_fbk_usort_pid;
	static $cache_all = array(), $cache_by_location = array();
	$return_as_array = false;

	if ( ! $post_id )
		$post_id = array( $GLOBALS['post']->ID );
	elseif ( ! is_array( $post_id ) )
		$post_id = array( (int) $post_id );
	else
		$return_as_array = true;

	$ids_all = $ids_location = $ids_hijack = $hijacks = $att_ids = array();
	foreach ( $post_id as $id ) {
		if ( ! isset( $cache_all[$id] ) ) {
			$ids_all[] = $id;
			$cache_all[$id] = array();
		} elseif ( $location && ! isset( $cache_by_location[$id] ) ) {
			$ids_location[] = $id;
			$cache_by_location[$id] = array();
		}
	}

	if ( ! empty($ids_all) ) {
		foreach ( $ids_all as $id )
			if ( $id_hijack = get_post_meta( $id, '_fbk_gallery_hijack', true ) )
				$ids_hijack[$id] = $id_hijack;

		$ids_in = implode( ',', array_merge( $ids_all, $ids_hijack ) );
		$atts = $wpdb->get_results(
			"select * from $wpdb->posts "
			. "where post_parent in ( $ids_in ) and post_type = 'attachment' and post_mime_type LIKE 'image/%' "
			. "order by post_parent, menu_order, ID DESC"
		);
		update_post_caches( $atts, 'attachment', false, true );

		foreach ( $atts as $att ) {
			if ( in_array( $att->post_parent, $ids_all ) )
				$cache_all[ $att->post_parent ][] = $att;
			if ( false !== $id = array_search( $att->post_parent, $ids_hijack ) )
				$hijacks[$id][] = $att;
			$att_ids[] = $att->ID;
		}

		foreach ( $hijacks as $id => $h_atts ) {
			$_fbk_usort_pid = $id;
			usort( $h_atts, '_fbk_usort_by_menu_order_x' );
			if ( ! empty( $cache_all[$id] ) )
				$cache_all[$id] = array_merge( $cache_all[$id], $h_atts );
			else
				$cache_all[$id] = $h_atts;
		}
	}

	if ( ! $location && $return_null )
		return null;
	elseif ( ! $location && $return_as_array )
		return array_intersect_key( $cache_all, array_flip( $post_id ) );
	elseif ( ! $location )
		return isset( $cache_all[$post_id[0]] ) ? $cache_all[$post_id[0]] : array();

	$ids_location = array_merge( $ids_all, $ids_location );
	
	if ( ! empty( $ids_location ) ) {
		foreach ( $ids_location as $id ) {
			foreach ( $cache_all[$id] as $key => $att ) {
				$meta_key = '_fbk_use_attachment_in' . ($att->post_parent == $id ? '' : "_$id");
				if ( ! $att_loc = get_post_meta( $att->ID, $meta_key, true ) )
					$att_loc = FBK_GALLERY_SLUG;
				$cache_by_location[$id][$att_loc][] =& $cache_all[$id][$key];
			}
		}
	}

	if ( $return_null )
		return null;
	elseif ( ! $return_as_array )
		return isset( $cache_by_location[$post_id[0]][$location] ) ? $cache_by_location[$post_id[0]][$location] : array();
	
	$output = array();
	foreach ( array_intersect_key( $cache_by_location, array_flip( $post_id ) ) as $id => $atts_by_loc ) {
		if ( isset( $atts_by_loc[$location] ) )
			$output += $atts_by_loc[$location];
	}
	return $output;
}



/******************************************************
 * Cache and helper functions
 *
*******************************************************/


/**
 * Get a school's currency, either as the international currency code (eg USD) or in the human-readable symbol (eg $)
 *
 * args:
 * $post_id, int, mandatory outside of loop: ID of the school
 * $verbose, bool, optional: false to use currency code rather than currency symbol
 */
function fbk_tt_get_currency( $post_id = 0, $verbose = true ) {
	global $post, $fbk_cf_prefix;
	static $cache;
	if ( ! $post_id )
		$post_id = $post->ID;
	if ( ! isset( $cache[$post_id] ) ) {
		$currs = wp_get_object_terms( $post_id, 'currency' );
		if ( ! $currs )
			return false;
		$cache[$post_id] = $currs[0];
	}
	return $verbose ? $cache[$post_id]->name : strtolower($cache[$post_id]->slug);
}

/**
 * Get a school's multi-line meta objects (courses, accommdation, fees)
 *
 * @arg $school_id	int	school's post ID
 * @arg $type		string	object type (one of the identifiers used in $fbk_cf_boxes where $fbk_cf_boxes[$type]['type'] == 'lines'
 * @arg $_no_recursion	internal parameter, do not use
 *
 * @return array	If $type is given, returns an array of sorted meta objects of that type, indexed by meta key
 *			Else, returns an array ( $type => array of meta objects )
 */
function fbk_get_school_meta( $school_id = 0, $type = '', $_no_recursion = false ) {
	global $post, $wpdb, $fbk_cf_prefix, $fbk_cf_boxes;
	if ( ! $school_id )
		$school_id = $post->ID;
	if ( ! $school_id )
		return array();

	$meta = wp_cache_get( $school_id, 'school_meta' );

	if ( ! $meta ) {
		$meta = array();
		$is_admin = is_admin();

		foreach ( array( 'courses', 'accommodation' ) as $object_type ) {
			$all_objects = array();
			$hidden = array();

			if ( empty($fbk_cf_boxes[$object_type]['shared']) || $_no_recursion ) {
				$meta[$object_type] = array();
				$all_objects = $wpdb->get_results( "SELECT * FROM `wp_fbk_$object_type` WHERE `post_id` = $school_id ORDER BY `_id`", ARRAY_A );
				if ( $all_objects )
					foreach ( $all_objects as $object )
						if ( ! $object['hidden'] || $is_admin )
							$meta[$object_type][ $object['meta_id'] ] = array_map( 'maybe_unserialize', $object );
				continue;
			}

			$own_objects = $wpdb->get_results( "SELECT * FROM `wp_fbk_$object_type` WHERE `post_id` = $school_id ORDER BY `_id`", ARRAY_A );
			if ( $own_objects ) {
				foreach ( $own_objects as $key => $object ) {
					if ( ! $object['hidden'] || $is_admin ) {
						$all_objects[$object['_id']] = array_map( 'maybe_unserialize', $object );
						$all_objects[$object['_id']]['is_foreign'] = false;
						$all_objects[$object['_id']]['shared_with'] = array();
					} else {
						$hidden[] = $object['_id'];
					}
				}
			}

			foreach ( get_post_meta( $school_id, "_foreign_$object_type" ) as $foreign ) {
				$foreign_object_all = fbk_get_school_meta( $foreign['school_id'], $object_type, true );
				if ( ! array_key_exists( $foreign['object_id'], $foreign_object_all ) )
					continue;

				$order_noconflict = $foreign['order'];
				while ( array_key_exists( $order_noconflict, $all_objects ) || in_array( $order_noconflict, $hidden ) )
					$order_noconflict++;
				if ( $foreign['order'] != $order_noconflict ) {
					$foreign_conflicting = $foreign;
					$foreign['order'] = $order_noconflict;
					update_post_meta( $school_id, "_foreign_$object_type", $foreign, $foreign_conflicting );
					unset( $foreign_conflicting );
				}

				$all_objects[$foreign['order']] = $foreign_object_all[$foreign['object_id']];

				$all_objects[$foreign['order']]['_id'] = $foreign['order'];
				$all_objects[$foreign['order']]['is_foreign'] = true;
				$all_objects[$foreign['order']]['shared_with'] = array( $foreign['school_id'] );

				foreach ( get_post_meta( $foreign['school_id'], "_shared_$object_type" ) as $shareset )
					if ( $foreign['object_id'] == $shareset['object_id'] && $school_id != $shareset['school_id'] )
						$all_objects[$foreign['order']]['shared_with'][] = $shareset['school_id'];
			}

			ksort( $all_objects );

			foreach ( $all_objects as $object ) {
				$meta[$object_type][ $object['meta_id'] ] = $object;
			}

			foreach ( get_post_meta( $school_id, "_shared_$object_type" ) as $shareset ) {
				if ( array_key_exists( $shareset['object_id'], $meta[$object_type] ) ) {
					$meta[$object_type][ $shareset['object_id'] ]['shared_with'][] = $shareset['school_id'];
				}
			}
		}

		foreach ( $fbk_cf_boxes as $object_type => $content )
			if ( 'lines' == $content['type'] && ! in_array( $object_type, array( 'courses', 'accommodation' ) ) )
				$other[$object_type] = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE post_id = $school_id AND meta_key = '{$fbk_cf_prefix}{$object_type}'", ARRAY_A );
		if ( ! empty($other) ) {
			foreach ( $other as $object_type => $results ) {
				$meta[$object_type] = array();
				foreach ( $results as $_result ) {
					$result = unserialize( $_result['meta_value'] );
					$result['_original_meta_value'] = $result;
					$result['meta_id'] = $_result['meta_id'];
					$result['post_id'] = $_result['post_id'];
					$meta[$object_type][ $_result['meta_id'] ] = $result;
				}

				if ( ! empty($fbk_cf_boxes[$object_type]['shared']) && ! $_no_recursion ) {
					foreach ( $meta[$object_type] as $object_id => $object ) {
						$meta[$object_type][$object_id]['is_foreign'] = false;
						$meta[$object_type][$object_id]['shared_with'] = array();
					}
					foreach ( get_post_meta( $school_id, "_shared_$object_type" ) as $shareset )
						if ( array_key_exists( $shareset['object_id'], $meta[$object_type] ) )
							$meta[$object_type][ $shareset['object_id'] ]['shared_with'][] = $shareset['school_id'];

					foreach ( get_post_meta( $school_id, "_foreign_$object_type" ) as $foreign ) {
						$foreign_object_all = fbk_get_school_meta( $foreign['school_id'], $object_type, true );
						$meta[$object_type][ $foreign['object_id'] ] = $foreign_object_all[ $foreign['object_id'] ];
						$meta[$object_type][ $foreign['object_id'] ]['is_foreign'] = true;
						$meta[$object_type][ $foreign['object_id'] ]['shared_with'] = array( $foreign['school_id'] );
						foreach ( get_post_meta( $foreign['school_id'], "_shared_$object_type" ) as $shareset )
							if ( $shareset['object_id'] == $foreign['object_id'] && $shareset['school_id'] != $school_id )
								$meta[$object_type][ $foreign['object_id'] ]['shared_with'][] = $shareset['school_id'];
					}
				}

				uasort( $meta[$object_type], '_fbk_linesort_by_id' );
			}
		}

		wp_cache_add( $school_id, $meta, 'school_meta' );
	}

	if ( $type && array_key_exists( $type, $meta ) )
		return $meta[$type];
	elseif ( ! $type )
		return $meta;
	else
		return false;
}

function fbk_cf_get_meta_object_name( $type, $object ) {
	global $fbk_cf_boxes;
	if ( 'courses' == $type ) {
		return $object['name'];
	} elseif ( 'accommodation' == $type ) {
		if ( $object['name'] )
			return $object['name'];
		else
			return $fbk_cf_boxes[$type]['fields']['type']['opts'][ $object['type'] ];
	} elseif ( 'fees' == $type ) {
		return $object['key'];
	} else {
		return reset($object);
	}
}

/**
 * Removes empty rows and empty cols from $matrix.
 * If $alter_args is true, will also remove the corresponding entries from the axes.
 */
function fbk_cf_matrix_reduce( &$matrix, &$x_axis, &$y_axis, $alter_matrix = false, $alter_axes = false ) {
	$empty_x = array_fill_keys( array_flip($x_axis), 0 );
	$empty_y = array_fill_keys( array_flip($y_axis), 0 );
	foreach ( $x_axis as $x => $col )
		foreach ( $y_axis as $y => $row )
			if ( empty($matrix[$x][$y]) ) {
				$empty_x[$x]++;
				$empty_y[$y]++;
			}
	$exclude_x = array_keys( $empty_x, count($y_axis) );
	$exclude_y = array_keys( $empty_y, count($x_axis) );

	$r_matrix = $matrix;
	$r_x_axis = $x_axis;
	$r_y_axis = $y_axis;

	foreach ( $exclude_x as $x ) {
		unset( $r_x_axis[$x] );
		unset( $r_matrix[$x] );
	}
	foreach ( $exclude_y as $y ) {
		unset( $r_y_axis[$y] );
		foreach ( array_keys($r_x_axis) as $x )
			unset( $r_matrix[$x][$y] );
	}
	
	if ( $alter_matrix )
		$matrix = $r_matrix;
	if ( $alter_axes ) {
		$x_axis = $r_x_axis;
		$y_axis = $r_y_axis;
	}

	return $r_matrix;
}

function fbk_tt_has_slice( $slug, $post_id ) {
	global $post, $fbk_cf_slices, $fbk_cf_prefix;
	if ( ! in_array( $slug, $fbk_cf_slices ) )
		return false;
	if ( ! $post_id )
		$post_id = $post->ID;

	switch ( $slug ) {
		case FBK_DESC_SLUG:
			return true;
		case FBK_ACCOMMODATION_SLUG:
			$accs = fbk_get_school_meta( $post_id, 'accommodation' );
			// Pre-1.9.1, empty accs used to be saved with empty values. This procedure does a legacy fix for these data sets.
			if ( 1 == count($accs) ) {
				$acc = reset($accs);
				return ! ( empty($acc['name']) && empty($acc['cost']) && empty($acc['desc']) );
			}
			return (bool) $accs;
		case FBK_OTHER_SLUG:
			return	(bool) fbk_get_school_meta( $post_id, 'fees' )
				 || (bool) get_post_meta( $post_id, $fbk_cf_prefix . 'leisure', true )
				 || (bool) get_post_meta( $post_id, '_school_connect' );
		case FBK_GALLERY_SLUG:
			return (bool) fbk_get_images( $post_id, FBK_GALLERY_SLUG );
	}
}

/**
 * callback for array_map
 * converts all numeric items to integers or floats and empty strings to 0.
 */
function _fbk_integerize( $array_element ) {
	if ( is_array( $array_element ) )
		$array_element = array_map( '_fbk_integerize', $array_element );
	elseif ( is_numeric( $array_element ) && strpos( $array_element, '.' ) )
		$array_element = (float) $array_element;
	elseif ( is_numeric( $array_element ) || '' === $array_element )
		$array_element = (int) $array_element;
	elseif ( is_string( $array_element ) && preg_match( '~^\d+,\d+$~', $array_element ) )
		$array_element = (float) str_replace( ',', '.', $array_element );
	return $array_element;
}

?>
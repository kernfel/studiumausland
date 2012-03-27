<?php
/*
Plugin Name: Felix' Sitemap Generator
Description: Generates an XML sitemap for use by search engine crawlers
Author: Felix Kern
License: GPL2
*/
/**
 * @package Studium_Ausland
 */

class FBK_Sitemap_Generator {
	private $regenerating = false;
	private $changed = false;

	public $urls;

	function __construct( $filename ) {
		$this->file = $filename;
		$this->home_url = home_url();

		add_action( 'do_robots', array( &$this, 'do_robots' ) );

		add_action( 'wp_loaded', array( &$this, 'maybe_regenerate' ) );

		add_action( 'fbk_cache_deleted', array( &$this, 'cache_update' ), 10, 2 );

		add_action( 'fbk_offer_end', array( &$this, 'offer_end' ) );
		add_action( 'fbk_school_connect_remove_link', array( &$this, 'offer_change' ) );
		add_action( 'fbk_school_connect_add_link', array( &$this, 'offer_change' ) );

		add_action( 'post_updated', array( &$this, 'post_updated_final' ), 10, 3 );

		register_shutdown_function( array( &$this, 'write' ) );
	}

	function maybe_regenerate() {
		if ( ! file_exists( $this->file ) )
			$this->regenerate();
	}

	function do_robots() {
		if ( 'yes' == get_option( 'fbk_aidu_disallow' ) && class_exists( 'Content4Partners' ) ) {
			$c4p = new Content4Partners;
			$opts = $c4p->getOptions();
			$page = get_post( $opts['page_id'] );
			if ( $page && ! is_wp_error($page) )
				echo "Disallow: /$page->post_name/*" . PHP_EOL;
		}
		echo "Sitemap: " . home_url( str_replace( ABSPATH, '', $this->file ) ) . PHP_EOL;
	}

	function cache_update( $cache_type, $cache_id ) {
		global $fbk_termlink_cat_override, $fbk_termlink_frontend;
		if ( 'school' == $cache_type ) {
			$p = get_post( $cache_id );
			if ( 'publish' == $p->post_status )
				$this->add_link( array( 'link' => get_permalink( $p->ID ), 'post' => $p ), 'school' );
			else {
				$this->remove_link( $p );
			}
		} elseif ( 0 === strpos( $cache_type, 'loc-' ) ) {
			$fbk_termlink_cat_override = get_term( substr( $cache_type, 4 ), 'category' );
			$term = get_term( $cache_id, 'loc' );
			$link = get_term_link( $term );
			if ( $term->parent ) {
				if ( $this->get_objects_in_terms( $fbk_termlink_cat_override, $term ) )
					$this->add_link( $link, 'loc' );
				else
					$this->remove_link( $link );
			} else {
				$cities = get_term_children( $term->term_id, 'loc' );
				$added = false;
				foreach ( $cities as $city_id ) {
					if ( $this->get_objects_in_terms( $fbk_termlink_cat_override, get_term($city_id,'loc') ) ) {
						$this->add_link( $link, 'loc' );
						$added = true;
						break;
					}
				}
				if ( ! $added )
					$this->remove_link( $link );
			}
		} elseif ( 'cat' == $cache_type ) {
			$fbk_termlink_frontend = true;
			$this->add_link( get_term_link( (int) $cache_id, 'category' ), 'category' );
			$fbk_termlink_frontend = false;
		}
	}

	// Offer changes. Linked schools are done via cache update hook, so no need to deal with these here.
	function offer_end( $post_id ) {
		$this->remove_link( get_post($post_id) );
	}
	function offer_change( $post_id ) {
		if ( fbk_is_public_offer( $post_id ) ) {
			$p =& get_post( $post_id );
			$this->add_link( array( 'post' => $p, 'link' => get_permalink($post_id) ), $p->post_type );
		}
	}

	function post_updated_final( $post_id, $post, $post_before ) {
		if ( ! in_array( $post->post_type, array('post','page','offer') ) )
			return;

		$linkball = array(
			'link' => get_permalink( $post_id ),
			'post' => $post
		);

		if ( 'offer' == $post->post_type && ! fbk_is_public_offer($post_id) || 'publish' != $post->post_status )
			$this->remove_link( $linkball );
		elseif ( 'publish' == $post->post_status )
			if ( $this->get_prio( $post->post_type . '-' . $post->post_name ) )
				$this->add_link( $linkball, $post->post_type . '-' . $post->post_name );
			else
				$this->add_link( $linkball, $post->post_type );
	}

	/**
	 * Add a link to the sitemap.
	 * @param link string|array The link to add or an array ['link' => $link, 'post' => $post]. In the second case, all other links to
	 	the referenced object are removed, which could not be done with the link alone.
	 * @param priority mixed A priority (0..1) for the link. If a non-numeric string is passed, $priority is used as index to this::priorities
	 	and this::changefreqs to determine prio and changefreq, respectively.
	 * @param changefreq boolean|string
	 */
	function add_link( $link, $priority = false, $changefreq = false ) {
		if ( ! $this->regenerating && ! isset( $this->urls ) && ! $this->parse() )
			return false;

		$post = false;

		if ( is_array( $link ) ) {
			$arg1 = $link;
			$link = $arg1['link'];
			$post = $arg1['post'];
			if ( empty( $arg1['ignore_duplicates'] ) ) {
				foreach ( $this->urls as $key => $url ) {
					if ( isset($url['object']) && $url['object'] == $post->post_type && $url['id'] == $post->ID ) {
						unset( $this->urls[$key] );
					}
				}
			}
		}

		if ( isset( $this->urls[$link] ) )
			$url = $this->urls[$link];
		else
			$url = array();

		if ( $priority && is_numeric($priority) )
			$url['priority'] = $priority;
		elseif ( $priority && is_string($priority) ) {
			if ( false !== ( $_prio = $this->get_prio( $priority ) ) )
				$url['priority'] = $_prio;
			if ( ! $changefreq && false !== ( $_freq = $this->get_changefreq( $priority ) ) )
				$url['changefreq'] = $_freq;
		}

		if ( $changefreq )
			$url['changefreq'] = $changefreq;

		if ( $post ) {
			$url['object'] = $post->post_type;
			$url['id'] = $post->ID;
		}

		$url['lastmod'] = date('Y-m-d');

		$this->urls[$link] = $url;

		$this->changed = true;
	}

	function remove_link( $link ) {
		if ( ! $this->regenerating && ! isset( $this->urls ) && ! $this->parse() )
			return false;

		if ( is_object( $link ) )
			$link = array( 'post' => $link );

		if ( is_array( $link ) ) {
			foreach ( $this->urls as $key => $url ) {
				if ( isset($url['object']) && $url['object'] == $link['post']->post_type && $url['id'] == $link['post']->ID ) {
					$this->changed |= isset( $this->urls[$key] );
					unset( $this->urls[$key] );
				}
			}
		} else {
			$this->changed |= isset( $this->urls[$link] );
			unset( $this->urls[$link] );
		}
	}

	function regenerate( $progress_callback = false ) {
		global $fbk, $fbk_termlink_cat_override, $fbk_termlink_frontend;

		$this->urls = array();
		$this->regenerating = true;
		$fbk_termlink_frontend = true;

		$include_categories = array();
		foreach ( $fbk->cats as $cat )
			$include_categories[] = $cat->term_id;

		$args = array(
			'post_type' => array( 'post', 'school', 'page', 'offer' ),
			'post_status' => 'publish',
			'posts_per_page' => 25,
			'paged' => 1
		);
		$q = new WP_Query;
		$locs = array();
		while ( $posts =& $q->query( $args ) ) {
			foreach ( $posts as $post ) {
				if ( $progress_callback )
					call_user_func( $progress_callback, 'Reading: ' . $post->post_type . '/' . $post->ID );

				$prio = $this->get_prio( $post->post_type . '-' . $post->post_name );
				if ( false === $prio )
					$prio = $this->get_prio( $post->post_type );
				$changefreq = $this->get_changefreq( $post->post_type . '-' . $post->post_name );
				if ( false === $changefreq )
					$changefreq = $this->get_changefreq( $post->post_type );

				if ( 'school' == $post->post_type ) {
					$terms = array();
					$_terms = wp_get_object_terms( $post->ID, array( 'loc', 'category' ) );
					foreach ( $_terms as $term )
						$terms[$term->taxonomy] = $term;
					if ( ! empty($terms['category']) && ! in_array( $terms['category']->term_id, $include_categories ) )
						continue;
					if ( $terms['loc'] && $terms['category']
					 && empty( $locs[$terms['category']->term_id][$terms['loc']->term_id] ) ) {
						$locs[$terms['category']->term_id][$terms['loc']->term_id] = $terms['loc'];
					}
				}

				$this->add_link(
					array( 'link' => get_permalink( $post->ID ), 'post' => $post, 'ignore_duplicates' => true ),
					$prio,
					$changefreq,
					false
				);
			}
			++$args['paged'];
		}

		if ( $progress_callback )
			call_user_func( $progress_callback, 'All posts read, moving on to categories' );

		foreach ( $fbk->cats as $cat ) {
			$this->add_link(
				get_term_link( $cat ),
				$this->get_prio( 'category' ),
				$this->get_changefreq( 'category' ),
				false
			);
		}

		if ( $progress_callback )
			call_user_func( $progress_callback, 'Categories read, moving on to cities' );

		$countries = array();
		foreach ( $locs as $cat_id => $cities ) {
			$fbk_termlink_cat_override = get_term( $cat_id, 'category' );
			foreach ( $cities as $city ) {
				$this->add_link(
					get_term_link( $city ),
					$this->get_prio( 'loc' ),
					$this->get_changefreq( 'loc' ),
					false
				);
				if ( $city->parent && empty( $countries[$cat_id][$city->parent] ) )
					$countries[$cat_id][$city->parent] = get_term( $city->parent, 'loc' );
			}
		}

		if ( $progress_callback )
			call_user_func( $progress_callback, 'Cities read, moving on to countries' );

		foreach ( $countries as $cat_id => $_countries ) {
			$fbk_termlink_cat_override = get_term( $cat_id, 'category' );
			foreach ( $_countries as $country )
				$this->add_link(
					get_term_link( $country ),
					$this->get_prio( 'loc' ),
					$this->get_changefreq( 'loc' ),
					false
				);
		}

		if ( $progress_callback )
			call_user_func( $progress_callback, 'Countries read, writing data' );

		$this->changed = true;
		$this->regenerating = false;
		$fbk_termlink_frontend = false;
		
		return true;
	}

	function write() {
		if ( ! isset( $this->urls ) || ! $this->changed )
			return false;

		$f = fopen( $this->file, 'wb' );
		fwrite( $f,
'<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
	xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:fbk="' . $this->home_url . '/xmlns/fbk.sitemap">' . PHP_EOL
		);
		$home = $this->home_url . '/';
		foreach ( $this->urls as $loc => $values ) {
			if ( $loc == $home )
				$values['lastmod'] = date('Y-m-d');
			fwrite( $f,
				'<url>' . PHP_EOL
				. '<loc>' . htmlentities($loc) . '</loc>' . PHP_EOL
				. ( !empty($values['lastmod']) ? '<lastmod>' . $values['lastmod'] . '</lastmod>' . PHP_EOL : '' )
				. ( !empty($values['changefreq']) ? '<changefreq>' . $values['changefreq'] . '</changefreq>' . PHP_EOL : '' )
				. ( !empty($values['priority']) ? '<priority>' . $values['priority'] . '</priority>' . PHP_EOL : '' )
				. ( !empty($values['object']) ? '<fbk:object>' . $values['object'] . '</fbk:object>' . PHP_EOL : '' )
				. ( !empty($values['id']) ? '<fbk:id>' . $values['id'] . '</fbk:id>' . PHP_EOL : '' )
				. '</url>' . PHP_EOL
			);
		}
		fwrite( $f, '</urlset>' . PHP_EOL );
		fclose( $f );
	}

	private function parse() {
		if ( ! file_exists( $this->file ) ) {
			$this->err = "Invalid file: $this->file";
			return false;
		}

		$sitemap = fopen( $this->file, 'rb' );

		$parser = xml_parser_create( 'UTF-8' );
		xml_set_element_handler( $parser, array( &$this, 'start_el' ), array( &$this, 'end_el' ) );
		xml_set_character_data_handler( $parser, array( &$this, 'char_data' ) );

		while ( $data = fread( $sitemap, 4096 ) ) {
			if ( ! xml_parse( $parser, $data, feof( $sitemap ) ) ) {
				$this->err =
					xml_error_string( xml_get_error_code($parser) )
					. ' on line '
					. xml_get_current_line_number($parser);
				return false;
			}
		}
		xml_parser_free( $parser );
		fclose( $sitemap );

		return true;
	}
	function start_el( $parser, $element_name ) {
		switch ( $element_name ) {
			case 'FBK:OBJECT':
			case 'FBK:ID':
				$element_name = substr( $element_name, 4 );
			case 'LOC':
			case 'LASTMOD':
			case 'CHANGEFREQ':
			case 'PRIORITY':
				$this->process = $element_name;
				break;
			case 'URL':
				$this->buffer = array();
			default:
				$this->process = false;
				break;
		}
	}
	function end_el( $parser, $element_name ) {
		if ( 'URL' == $element_name ) {
			$this->urls[ $this->key ] = $this->buffer;
			$this->buffer = $this->key = false;
		}
		$this->process = false;
	}
	function char_data( $parser, $data ) {
		if ( 'LOC' == $this->process )
			$this->key = html_entity_decode( $data );
		elseif ( $this->process )
			$this->buffer[ strtolower($this->process) ] = $data;
	}

	/**
	 * Returns an array of object ids referenced by all given terms.
	 * Pass either an array of terms or each term separately.
	 */
	function get_objects_in_terms( $term ) {
		global $wpdb;
		if ( is_array( $term ) )
			$terms = array_values( $term );
		else
			$terms = func_get_args();
	
		$query = "SELECT p.ID FROM $wpdb->posts p";
		$where = " WHERE p.post_status = 'publish'";
		foreach ( $terms as $i => $t ) {
			$query .= " JOIN $wpdb->term_relationships tr_$i ON tr_$i.object_id = p.ID";
			$query .= " JOIN $wpdb->term_taxonomy tt_$i ON tt_$i.term_taxonomy_id = tr_$i.term_taxonomy_id";
			$where .= " AND tt_$i.term_id = $t->term_id AND tt_$i.taxonomy = '$t->taxonomy'";
		}
		return $wpdb->get_results( $query . $where . ' GROUP BY p.ID' );
	}

	function get_prio( $type ) {
		$prio = get_option( 'fbk_sitemap_prio_' . $type );
		$defaults = array(
			'school' => 0.9,
			'post' => 0.5,
			'page' => 0.3,
			'offer' => 1,
			'page-news' => 0.7,
			'page-home' => 1,
			'category' => 0.7,
			'loc' => 0.7
		);
		if ( false === $prio )
			return array_key_exists( $type, $defaults ) ? $defaults[$type] : false;
		else
			return (float) str_replace( ',', '.', $prio );
	}

	function get_changefreq( $type ) {
		$freq = get_option( 'fbk_sitemap_freq_' . $type );
		$defaults = array(
			'school' => 'monthly',
			'post' => 'never',
			'page' => 'never',
			'offer' => 'never',
			'page-news' => 'weekly',
			'page-home' => 'weekly',
			'category' => 'monthly',
			'loc' => 'monthly'
		);
		if ( false === $freq )
			return array_key_exists( $type, $defaults ) ? $defaults[$type] : false;
		else
			return $freq;
	}
}

global $fbk_sitemap_gen;
$fbk_sitemap_gen = new FBK_Sitemap_Generator( ABSPATH . '/sitemap.xml' );

?>
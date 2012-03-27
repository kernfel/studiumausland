<?php
/**
 * @package Studium_Ausland
 */

class FBK_Query {

	/* str $taxonomy
	 * taxonomy name
	**/
	public $taxonomy;

	/* str $detailed_taxonomy
	 * taxonomy pseudo-name: 'city' or 'country' for loc
	**/
	public $detailed_taxonomy;

	/* str $term
	 * The term object of the given taxonomy
	**/
	public $term;

	/* bool $hot
	 * Whether the query has been altered in any way in the most recent query
	**/
	private $hot = false;
	
	/* array $schools
	 * The array of school posts in the requested taxonomy
	 */
	public $schools;
	
	/* object $page
	 * The post object of the description page for the requested taxonomy
	 */
	public $page;
	
	public $wp_query;
	
	public $view;
	public $rows = array();
	public $row = 0;
	public $col = 0;
	public $is_loop_ready = false;
	
	public $lang_separated = false;
	public $langs = array();
	public $lang;
	public $separated_schools = array();
	
	public $ajax = false;
	public $ajax_data;
	
	public $category;
	
	public $languages = array();
	public $countries = array();
	public $cities = array();

	function __construct( &$wp_query, $ajax_data = false ) {
		add_action( 'pre_get_posts', array( &$this, 'parse_request' ) );
		add_filter( 'the_posts', array( &$this, 'check_request' ), 0, 2 );
		$this->wp_query =& $wp_query;
		if ( $ajax_data ) {
			$this->ajax_data = $ajax_data;
			$this->ajax = true;
		}
	}
	
	public function parse_request( $q ) {
		global $wp, $fbk;
		if ( $q->get( 'suppress_filters' ) )
			return;
		if ( $q !== $this->wp_query )
			return;

		if ( empty( $this->request ) )
			$this->request = explode( '/', $wp->request );

		if ( $q->is_feed ) {
			if ( ! $q->get('post_type') )
				$q->set( 'post_type', array('post','offer') );
		} elseif ( $q->is_search || isset($q->query['s']) ) {
			$this->handle_search( $q );
		} elseif ( $q->is_tax || $q->is_category || $q->is_page && isset( $q->query['pagename'] ) && false !== strpos( $q->query['pagename'], '/' ) ) {
			$path = array_reverse( $this->request );

			if ( $this->ajax )
				$found = $this->set( 'cat' == $this->ajax_data['obj'] ? 'category' : $this->ajax_data['obj'], $this->ajax_data['id'], 'term_id' );
			else
				$found = $this->match_query( $path );

			if ( ! $found ) { // Let standard behaviour take care of 404.
				header( 'HTTP/1.1 404 Not Found', true, 404 );
				return $q->set_404();
			}

			$this->prepare( $q );
			$this->hot = true;
		} elseif ( $q->is_page() && 'news' == @$q->query['pagename'] && 1 == @$q->query['paged'] ) {
			$this->redir( 'news' );
		}
	}
	
	public function check_request( $posts, $q ) {
		global $wp;

		if ( $q !== $this->wp_query )
			return $posts;

		$hot = $this->hot;
		$this->hot = false;
		if ( $q->is_preview ) {
			if ( $cat = wp_get_object_terms( $posts[0]->ID, 'category' ) )
				$this->category = $cat[0];
			return $posts;
		} elseif ( $q->is_singular && !empty($posts) && 'school' == $posts[0]->post_type ) {
			$pl = get_permalink( $posts[0]->ID );
			$pl = str_replace( home_url(), '', $pl );
			$realpath = trim( $pl, '/' );
			if ( $wp->request != $realpath ) // incorrect path
				$this->redir( $realpath );
			$cat = wp_get_object_terms( $posts[0]->ID, 'category' );
		} elseif ( $q->is_404 && isset($q->query['s']) ) {
			$q->is_search = true;
			$q->is_404 = false;
		} elseif ( $hot ) {
			if( ! $this->fetch_schools( true ) ) {
				// Oops, no schools here. Let's try the next higher taxonomy.
				array_pop( $this->request );
				if ( empty( $this->request ) ) {
					$q->set_404();
					return $posts;
				}
				return $q->get_posts();
			}

			if ( count( $posts ) )
				$this->page = $posts[0];
			
			if ( 'category' == $this->taxonomy ) {
				$cat = $this->term;
				$realpath = $cat->slug;
			} else {
				$cat = $this->get_category();
				$realpath = $cat->slug;
				if ( 'country' == $this->detailed_taxonomy ) {
					$realpath .= '/' . $this->term->slug;
				} else {
					$country = get_term( $this->term->parent, 'loc' );
					$realpath .= '/' . $country->slug . '/' . $this->term->slug;
				}
			}

			if ( $wp->request != $realpath ) // faulty country path or trailing slashes
				$this->redir( $realpath );
		}

		if ( isset($cat) && is_array( $cat ) )
			$this->category = $cat[0];
		elseif ( isset($cat) && is_object( $cat ) )
			$this->category = $cat;

		if ( isset($this->category) && 'uni' == $this->category->slug ) {
			// Block /uni.
			$q->set_404();
		}

		return $posts;
	}
	
	function redir( $path = '', $code = 301 ) {
		if ( ! $this->ajax ) {
			wp_redirect( site_url( '/' . $path ), $code );
			die();
		}
	}
	
	function fetch_schools( $tentative = false, $nocaching = false ) {
		if ( $tentative ) { // Run a minimal query to check for existence only
			if ( ! isset( $this->tentative_school_count ) ) {
				global $wpdb;
				$query = "
					select count(*) from $wpdb->posts p
					join $wpdb->term_relationships as tr_cat on tr_cat.object_id = p.ID
					join $wpdb->term_taxonomy as tt_cat on tt_cat.term_taxonomy_id = tr_cat.term_taxonomy_id
				";
	
				$where = "where p.post_type = 'school' and p.post_status = 'publish' ";
	
				$cat = 'category' == $this->taxonomy ? $this->term : $this->get_category();
				$where .= " and tt_cat.term_id = $cat->term_id and tt_cat.taxonomy = 'category' ";
	
				if ( 'loc' == $this->taxonomy ) {
					$query .= "
						join $wpdb->term_relationships as tr on tr.object_id = p.ID
						join $wpdb->term_taxonomy as tt on tt.term_taxonomy_id = tr.term_taxonomy_id
					";
					if ( 'city' == $this->detailed_taxonomy )
						$where .= " and tt.term_id = {$this->term->term_id} and tt.taxonomy = 'loc' ";
					elseif ( 'country' == $this->detailed_taxonomy )
						$where .= " and tt.parent = {$this->term->term_id} and tt.taxonomy = 'loc' ";
				}

				$this->tentative_school_count = $wpdb->get_var( $query . $where );
			}

			return (bool) $this->tentative_school_count;
		}

		$tax_query = array();
		$query = array(
			'suppress_filters' => true,
			'post_type' => 'school',
			'post_status' => 'publish'
		);

		if ( 'category' == $this->taxonomy ) {
			$query['posts_per_page'] = 1; // Huh. When does this even happen?
		} else {
			$category = $this->get_category();
			$tax_query[] = array(
				'taxonomy' => 'category',
				'terms' => array( $category->term_id ),
				'include_children' => true,
				'field' => 'id'
			);
			$query['nopaging'] = true;
		}
		$tax_query[] = array(
			'taxonomy' => $this->taxonomy,
			'terms' => array( $this->term->term_id  ),
			'include_children' => true,
			'field' => 'id'
		);
		$query['tax_query'] = $tax_query;

		if ( $nocaching )
			$query['cache_results'] = false;

		$q = new WP_Query($query);
		$schools =& $q->posts;
		
		if ( ! $schools ) {
			$this->schools = $schools;
			return false;
		}

		foreach ( $schools as $school )
			$this->schools[$school->ID] = $school;

		return true;
	}
	
	function has_schools() {
		if ( isset( $this->tentative_school_count ) && empty( $this->schools ) )
			return (bool) $this->tentative_school_count;
		else
			return ! empty( $this->schools );
	}
	
	function has_page() {
		return ! empty( $this->page );
	}
	
	function get_category() {
		if ( $this->ajax )
			return get_term( $this->ajax_data['rel'], 'category' );
		$category = false;
		foreach ( $this->request as $candidate ) {
			$category = get_term_by( 'slug', $candidate, 'category' );
			if ( $category )
				break;
		}
		if ( ! $category )
			$category = $GLOBALS['fbk']->default_cat;
		return $category;
	}
	
	/**
	 * Search the given array for strings that match a taxonomy slug, giving precedence to low-level taxonomies.
	 * Returns the first matching element's taxonomy and will pursue no further.
	 * Populates internal data.
	 *
	 * @uses FBK_Query::set()
	 *
	 * @param array $fragments Array of strings to be matched against taxonomy slugs
	 */
	function match_query( $fragments ) {
		foreach ( array( 'loc', 'category' ) as $tax )
			foreach ( $fragments as $fragment )
				if ( $this->set( $tax, $fragment ) )
					return $tax;
		return false;
	}

	/**
	 * Prime a wp_query object with the necessary data for a request
	 * that returns all desc pages in the given taxonomy while still identifying as tax/cat archive
	 *
	 * @param WP_Query &$q
	 */
	function prepare( &$q ) {
		if ( $this->taxonomy ) {
			$robots = $q->is_robots;
			$q->init_query_flags();
			$q->is_robots = $robots;
			$q->is_archive = true;
			if ( 'category' == $this->taxonomy )
				$q->is_category = true;
			elseif ( 'loc' == $this->taxonomy )
				$q->is_tax = true;

			$q->set( 'pagename', '' );
			$q->set( 'name', '' );
			$q->set( 'term', '' );
			$q->set( 'taxonomy', '' );
			$q->set( 'category_name', '' );
			$q->set( 'loc', '' );
			if ( ! $q->get( 'meta_query' ) )
				$q->set('meta_query', '');

			$q->set( 'post_type', 'desc' );
			$q->set( 'tax_query', array( array(
				'taxonomy' => $this->taxonomy,
				'terms' => array( $this->term->term_id  ),
				'include_children' => false,
				'field' => 'id'
			)));
			if ( empty( $q->tax_query ) )
				$q->tax_query = new WP_Tax_Query( $q->get('tax_query') );
		}
		return $q;
	}

	/**
	 * Set the internal values to the term and taxonomy specified
	 *
	 * returns true if the term exists within this taxonomy
	 * returns false and does nothing if the term does not exist within this taxonomy
	 *
	 * @param string $taxonomy
	 * @param object|string|int $term
	 * @param string $term_type Optional, used as the first argument of get_term_by in case $term is a string
	 */
	function set( $taxonomy, $term, $term_type = 'slug' ) {
		if ( is_object( $term ) )
			$this->term = $term;
		elseif ( is_string( $term ) )
			$this->term = get_term_by( $term_type, $term, $taxonomy );
		else
			$this->term = get_term( (int) $term, $taxonomy );
		if ( $this->term ) {
			$this->taxonomy = $taxonomy;
			if ( 'loc' == $taxonomy )
				$this->detailed_taxonomy = $this->term->parent ? 'city' : 'country';
			else
				$this->detailed_taxonomy = $this->taxonomy;
			return true;
		}
		return false;
	}
	
	public function get_loop( $args ) {
		$schools = $this->get_schools( $args );
		$this->wp_query->rewind_posts();
		$this->wp_query->posts = $schools;
		$this->wp_query->post_count = count( $schools );
	}
	
	public function get_schools( $args ) {

		$args = wp_parse_args( $args, array( 'language' => 0, 'country' => 0, 'city' => 0, 'sort_alphabetical' => true ) );
		extract( $args );
		if ( ! $language && ! $country && ! $city ) {
			if ( empty($this->schools) && $this->has_schools() )
				$this->fetch_schools();
			$return = $this->schools;
		} else {
			$this->_do_taxes();
			$return = array();
			foreach ( $this->schools as $school ) {
				if ( 
					( ! $language || $school->lang->term_id == $language )
					&& ( ! $country || $school->country->term_id == $country )
					&& ( ! $city || $school->loc->term_id == $city )
				)
					$return[] = $school;
			}
		}
		if ( $sort_alphabetical )
			usort( $return, '_fbk_usort_by_post_title' );
		return $return;
	}
	
	public function get_languages() {
		if ( ! $this->has_schools() )
			return array();

		if ( empty ( $this->languages ) )
			$this->_do_taxes();

		return $this->languages;
	}
	
	public function get_countries( $language = 0 ) {
		if ( ! $this->has_schools() )
			return array();

		if ( empty ( $this->countries ) )
			$this->_do_taxes();

		if ( $language ) {
			if ( is_object( $language ) )
				$language = $language->term_id;
			$countries = array();
			foreach ( $this->countries as $key => $country )
				if ( ! empty( $country->langs[$language] ) )
					$countries[$key] = $country;
			return $countries;
		} else {
			return $this->countries;
		}
	}
	
	public function get_cities( $language = 0 ) {
		if ( ! $this->has_schools() )
			return array();

		if ( empty ( $this->cities ) )
			$this->_do_taxes();

		if ( $language ) {
			if ( is_object( $language ) )
				$language = $language->term_id;
			$cities = array();
			foreach ( $this->cities as $key => $city )
				if ( ! empty( $city->langs[$language] ) )
					$cities[$key] = $city;
			return $cities;
		} else {
			return $this->cities;
		}
	}
	
	private function _do_taxes() {
		if ( ! empty( $this->languages ) )
			return;

		if ( empty($this->schools) )
			$this->fetch_schools( false, true );

		// We don't need category here, but what the hell, prime the cache - it's likely gonna be used anyway, and it costs little extra.
		$terms = wp_get_object_terms( array_keys($this->schools), array( 'lang', 'loc', 'category' ), array( 'fields' => 'all_with_object_id' ) );
		foreach ( $terms as $term ) {
			$this->schools[ $term->object_id ]->{$term->taxonomy} = $term;
			if ( 'loc' == $term->taxonomy && $term->parent ) {
				$this->schools[$term->object_id]->_country = $_countries[] = $term->parent;
			}
		}

		$countries_noindex = get_terms( 'loc', array( 'include' => array_unique($_countries), 'get' => 'all' ) );
		foreach ( $countries_noindex as $country )
			$countries[$country->term_id] = $country;
		unset( $countries_noindex );

		foreach ( $this->schools as &$school ) {
			$lang = $school->lang;
			$city = $school->loc;
			$country = $school->country = $countries[$school->_country];
			unset( $school->_country );

			if ( ! isset( $this->languages[$lang->term_id] ) )
				$this->languages[$lang->term_id] = clone $lang;
			$this->languages[$lang->term_id]->countries[$country->term_id] = true;

			if ( ! isset( $this->countries[$country->term_id] ) )
				$this->countries[$country->term_id] = clone $country;
			$this->countries[$country->term_id]->langs[$lang->term_id] = true;

			if ( ! isset( $this->cities[$city->term_id] ) )
				$this->cities[$city->term_id] = clone $city;
			$this->cities[$city->term_id]->langs[$lang->term_id] = true;
		}

		update_post_caches( $this->schools, 'school', false, true );
	}
	
	private function handle_search( &$q ) {
		global $fbk;
		$q->is_search = true;
		$post_type = $q->get( 'post_type' );
		if ( 'post' == $post_type ) {
			$q->set( 'orderby', 'date' );
			$q->set( 'order', 'DESC' );
			$q->set( 'post_type', 'post' );
		} elseif ( 'offer' == $post_type ) {
			$q->set( 'meta_key', '_fbk_offer_end' );
			$q->set( 'orderby', 'meta_value' );
			$q->set( 'order', 'ASC' );
			$q->set( 'meta_query', array(array(
				'key' => '_fbk_offer_end',
				'value' => date('Y-m-d'),
				'compare' => '>',
				'type' => 'DATE'
			)));
			$q->parse_tax_query( $q->query_vars );
		} else {
			$q->set( 'post_type', 'school' );
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
			if ( $q->get( 'bu' ) ) {
				add_filter( 'posts_join', array( &$this, 'search_join' ), 10, 2 );
				add_filter( 'posts_where', array( &$this, 'search_where' ), 10, 2 );
			}
		}

		if ( 'simple' == $q->get('search_type') ) {
			$searchterms = explode( ' ', $q->get('s') );
			$category_substitutions = array();
			foreach ( $fbk->cats as $cat ) {
				$sterms = get_term_meta( $cat->term_id, 'fbk_search_terms', true );
				$category_substitutions[$cat->term_id] = $sterms ? $sterms : array();
			}
			$match_taxonomies = array(
				'lang' => get_terms( 'lang' ),
				'loc' => get_terms( 'loc' ),
				'course_tag' => get_terms( 'course_tag' )
			);
			$replacements = array();
			foreach ( $searchterms as $i => $searchterm ) {
				$searchterm = mb_strtolower( $searchterm );
				foreach ( $category_substitutions as $c => $subs ) {
					foreach ( $subs as $sub ) {
						if ( $sub == $searchterm ) {
							$replacements['cat'][] = $c;
							unset( $searchterms[$i] );
							break 2;
						}
					}
				}
				foreach ( $match_taxonomies as $tax => $terms ) {
					foreach ( $terms as $term_id => $term ) {
						if ( empty($term->name__lowercase) )
							$terms[$term_id]->name__lowercase = mb_strtolower( $term->name );
						if ( $term->slug == $searchterm || $term->name__lowercase == $searchterm ) {
							if ( 'loc' == $tax && $term->parent ) {
								$replacements['city'][] = $term->slug;
								$country[] = $term->parent;
							} elseif ( $term->name__lowercase == $searchterm ) { // Don't match on country slug, they're too short
								$replacements[$tax][] = $term->slug;
							}
							unset( $searchterms[$i] );
							break 2;
						}
					}
				}
			}
			if ( $replacements ) {
				foreach ( $replacements as $qv => $replacement )
					$q->set( $qv, implode( ',', $replacement ) );
				if ( defined('FBK_AJAX') && FBK_AJAX ) {
					$this->search_query = 
					$s = array(
						'search_type' => 'simple',
						's' => implode( ' ', $searchterms ),
						'cat' => $q->get('cat'),
						'course_tag' => $q->get('course_tag'),
						'post_type' => $q->get('post_type'),
						'lang' => $q->get('lang'),
						'loc' => $q->get('loc'),
						'city' => $q->get('city')
					);
					if ( $s['city'] && isset( $country ) ) {
						$country = get_term( reset($country), 'loc' );
						$s['loc'] = $country->slug;
					}
					$this->search_query = http_build_query( $s );
				}
				sanitize_search_q( $q );
				$q->parse_tax_query( $q->query_vars );
				$q->set( 's', implode( ' ', $searchterms ) );
			}
		}
	}
	
	function search_join( $join, $q ) {
		global $wpdb;
		if ( $this->wp_query !== $q )
			return $join;
		if ( $q->get( 'bu' ) )
			$join .= " JOIN {$wpdb->prefix}fbk_courses ON {$wpdb->prefix}fbk_courses.post_id = $wpdb->posts.ID ";
		return $join;
	}
	
	function search_where( $where, $q ) {
		global $wpdb;
		if ( $this->wp_query !== $q )
			return $where;
		if ( $q->get( 'bu' ) )
			$where .= " AND {$wpdb->prefix}fbk_courses.bu = 1 AND {$wpdb->prefix}fbk_courses.hidden = 0 ";
		return $where;
	}
}

?>
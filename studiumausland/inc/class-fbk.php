<?php
/**
 * @package Studium_Ausland
 */


class FBK {
	
	/**
	 * Stores the category term items as sorted in __construct
	 */
	public $cats = array();
	public $default_cat;
	
	/**
	 * Stores the countries for each category
	 */
	public $countries = array();
	
	public $desc_images = array();
	
	public $did_news_query = false;
	
	public $sb_objects = array(
		FBK_SIDEBAR_INDEX =>  array(
			'index',
			'page',
			'search',
			'post', // Single news post
			'news', // News archive
			'offer'
		),
		FBK_SIDEBAR_DEFAULT => array(
			'cat',
			'loc',
			'school'
		)
	);
	
	function __construct() {
		add_action( 'init', array( &$this, 'populate_cats' ) );
		$this->default_cat = get_term( get_option('default_category'), 'category' );
	}

	function populate_cats() {
		$all_cats = get_terms( 'category', array( 'get' => 'all' ) );
		$this->cats = $cats = array();
		foreach ( $all_cats as $cat ) {
			if ( 'yes' == get_term_meta( $cat->term_id, 'fbk_use_cat', true ) ) {
				$pos = (int) get_term_meta( $cat->term_id, 'fbk_category_order', true );
				while ( array_key_exists( $pos, $cats ) )
					$pos++;
				$cats[$pos] = $cat;
			}
		}
		ksort( $cats );

		if ( empty($cats) ) {
			$this->cats = array();
			return;
		}

		$pagewidth = get_option( 'fbk_pagewidth' );
		$tabwidth = (int) ( $pagewidth / count($cats) );
		$tabwidth_first = $tabwidth + ( $pagewidth % count($cats) );

		foreach ( array_values($cats) as $i => $cat ) {
			$cat->fbk_width = $i ? $tabwidth : $tabwidth_first;
			$this->cats[$cat->slug] = $cat;
		}
	}

	function get_sidebar_type() {
		if ( isset( $this->sidebar_type ) ) {
			$type = $this->sidebar_type;
		} else {
			// See also $this->sb_objects.
			if (
				is_front_page() 		// obj 'index'
				 || is_home()			// obj 'news'
				 || is_search()			// obj 'search'
				 || is_404()
				 || is_page()			// obj 'page'
				 || is_singular( 'post' )	// obj 'post'
				 || is_singular( 'offer' )	// obj 'offer'
				 || $this->did_news_query	// obj 'news', 2. Variante
			)
				$type = FBK_SIDEBAR_INDEX;
			else /*if (
				is_category()			// obj 'cat'
				|| is_taxonomy()		// obj 'loc',
				|| is_singular( 'school' )	// obj 'school'
			) */
				$type = FBK_SIDEBAR_DEFAULT;
		}

		return $this->sidebar_type = $type;
	}
	
	function set_sidebar_type( $type ) {
		$this->sidebar_type = $type;
	}
	
	function do_news_query( $page = 1, $force = false ) {
		global $wp_query;
		if ( ! $this->did_news_query || $force ) {
			if ( ! $page )
				$page = 1;
			$this->news =& $wp_query->query( array(
				'post_type' => 'post',
				'post_status' => 'publish',
				'posts_per_page' => 6,
				'paged' => $page
			));
			$this->did_news_query = $page;

			if ( 1 == $page )
				$this->offers = get_current_offers();
			else
				$this->offers = array();
		}
		return $wp_query->found_posts;
	}
}
?>
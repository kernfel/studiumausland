<?php 
/**
 * @package Studium_Ausland
 */

if ( ! defined( 'FBK_CACHE_DIR' ) ) // For use by the AJAX access point
	define( 'FBK_CACHE_DIR', dirname(dirname(__FILE__)) . '/cache' );

class FBK_Cache {
	public $last_file;

	function __construct( $wp_independent = false ) {
		if ( ! is_dir( FBK_CACHE_DIR ) )
			mkdir( FBK_CACHE_DIR );

		if ( $wp_independent )
			return $this->independent = true;

		$this->independent = false;

		add_action( 'post_updated', array( &$this, 'post_updated' ), 10, 3 );
		add_action( 'save_post', array( &$this, 'save_post' ), 10, 2 );
		add_action( 'before_delete_post', array( &$this, 'save_post' ) );

		add_action( 'fbk_offer_end', array( &$this, 'offer_change' ) );
		add_action( 'fbk_offer_start', array( &$this, 'offer_change' ) );

		add_action( 'fbk_school_connect_remove_link', array( &$this, 'connect_change' ), 10, 2 );
		add_action( 'fbk_school_connect_add_link', array( &$this, 'connect_change' ), 10, 2 );

		// For the page menu only:
		add_action( 'wp_update_nav_menu', array( &$this, 'menu_change' ) );
		add_action( 'wp_update_nav_menu_item', array( &$this, 'menu_change' ) );
		add_action( 'wp_delete_nav_menu', array( &$this, 'menu_change' ) );
	}

	function add( $type, $id, $content ) {
		if ( $this->independent || 'yes' == get_option( 'fbk_cache' ) ) {
			$file_name = FBK_CACHE_DIR . "/$type-$id";
			$overwritten = file_exists( $file_name );
			$file = fopen( $file_name, 'wb' );
			fwrite( $file, $content );
			fclose( $file );
			if ( $overwritten && ! $this->independent )
				do_action( 'fbk_cache_deleted', $type, $id );
		}
	}

	function get_e( $type, $id = 0 ) {
		if ( $this->has( $type, $id ) ) {
			echo file_get_contents( $this->last_file );
			return true;
		}
		return false;
	}

	function get( $type, $id = 0 ) {
		if ( $this->has( $type, $id ) )
			return file_get_contents( $this->last_file );
		return false;
	}

	function has( $type, $id = 0 ) {
		if ( $this->independent || 'yes' == get_option( 'fbk_cache' ) ) {
			$file_name = FBK_CACHE_DIR . "/$type-$id";
			if ( file_exists( $file_name ) ) {
				$this->last_file = $file_name;
				return filemtime( $file_name );
			}
		}
		return false;
	}

	function rec() {
		ob_start();
	}

	function done( $type, $id = 0, $flush = true ) {
		$content = ob_get_contents();
		$this->add( $type, $id, $content );

		if ( $flush )
			ob_end_flush();
		else
			ob_end_clean();
		return $content;
	}

	function post_updated( $ID, $post_after, $post_before ) {
		if ( 'school' == $post_after->post_type && ( $post_before->post_name != $post_after->post_name || $post_before->post_title != $post_after->post_title )
		 && 'publish' == $post_before->post_status && 'publish' == $post_after->post_status ) {
			foreach ( wp_get_object_terms( $ID, 'category' ) as $cat )
				$this->delete( 'menu', $cat->term_id );
		}
	}

	function save_post( $ID, $post = false ) {
		if ( ! $post )
			$post =& get_post( $ID );
		global $fbk;
		if ( 'school' == $post->post_type ) {
			$this->delete( 'school', $ID );
			$linked_schools = get_post_meta( $ID, '_school_connect' );
			if ( $linked_schools )
				$this->delete( 'school', $linked_schools );
			foreach ( wp_get_object_terms( $ID, array( 'category', 'loc' ) ) as $term )
				$terms[$term->taxonomy] = $term;
			if ( empty($terms) || empty($terms['category']) || empty($terms['loc']) )
				return;
			$this->delete( 'loc-' . $terms['category']->term_id, $terms['loc']->term_id );
			$this->delete( 'loc-' . $terms['category']->term_id, $terms['loc']->parent );
		} elseif ( 'offer' == $post->post_type && ( fbk_is_public_offer( $ID ) || fbk_was_public_offer( $ID ) ) ) {
			$this->offer_change( $ID );
		} elseif ( 'desc' == $post->post_type ) {
			if ( $locs = wp_get_object_terms( $ID, 'loc' ) ) {
				$locs = array( $locs[0] );
				if ( $locs[0]->parent )
					$locs[] = get_term( $locs[0]->parent, 'loc' );
				foreach ( $fbk->cats as $cat )
					foreach ( $locs as $loc )
						$this->delete( 'loc-' . $cat->term_id, $loc->term_id );
			} elseif ( $cats = wp_get_object_terms( $ID, 'category' ) ) {
				$this->delete( 'cat', $cats[0]->term_id );
			}
		}
	}

	function connect_change( $source_id, $target_id ) {
		$source =& get_post( $source_id );
		$target =& get_post( $target_id );
		if ( 'school' == $source->post_type )
			$this->delete( 'school', $source_id );
		if ( 'school' == $target->post_type )
			$this->delete( 'school', $source_id );
	}

	function offer_change( $offer_id ) {
		$this->delete( 'school', get_post_meta( $offer_id, '_school_connect' ) );
		$this->delete( 'menu', 'pages' );
	}

	function menu_change( $menu_id ) {
		$this->delete( 'menu', 'pages' );
	}

	private $already_deleted = array();

	function delete( $type, $ids = 0 ) {
		foreach ( (array) $ids as $id ) {
			$file_name = FBK_CACHE_DIR . "/$type-$id";

			if ( ! in_array( $file_name, $this->already_deleted ) ) {
				if ( file_exists( $file_name ) )
					unlink( $file_name );
				$this->already_deleted[] = $file_name;
			}

			do_action( 'fbk_cache_deleted', $type, $id );
		}
	}

	function flush( $type = '' ) {
		foreach ( scandir( FBK_CACHE_DIR ) as $file )
			if ( is_file( FBK_CACHE_DIR . '/' . $file ) )
				if ( ! $type || ( $type && 0 === strpos( $file, $type . '-' ) ) )
					unlink( FBK_CACHE_DIR . '/' . $file );
		do_action( 'fbk_cache_deleted', $type, '__all' );
	}
}
?>
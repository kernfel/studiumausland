<?php
/**
 * @package Studium_Ausland
 */

if ( ! class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class FBK_User_List_Table extends WP_List_Table {
	function __construct( $args = array() ) {
		$defaults = array(
			'base_file' => 'tools.php',
			'page_slug' => 'quote_list',
			'type' => 'full'
		);
		$args = wp_parse_args( $args, $defaults );

		$this->base_file = $args['base_file'];
		$this->page_slug = $args['page_slug'];
		$this->type = 'widget' == $args['type'] ? 'widget' : 'full';

		parent::__construct( array(
			'singular' => 'Interessent',
			'plural' => 'Interessenten',
			'ajax' => false
		));
	}

	function column_default( $item, $column_name ) {
		return @$item->$column_name;
	}

	function column_date_added( $item ) {
		$date = date( 'j.n.Y', $item->date_added );
		if ( $item->last_visit && 'full' == $this->type )
			$date .= "<br>(Zuletzt gesehen am " . date( 'j.n.Y', $item->last_visit ) . ")";
		return $date;
	}

	function column_name( $item ) {
		return "$item->salutation $item->first_name $item->last_name";
	}

	function column_contact( $item ) {
		if ( 'mail' == $item->contact_method )
			return "<b>$item->email</b><br>$item->phone";
		else
			return "<b>$item->phone</b><br>$item->email";
	}

	function column_address( $item ) {
		$address = array();
		if ( $item->street || $item->city || $item->state ) {
			if ( $item->street )
				$address[] = $item->street;
			if ( $item->city )
				$address[] = ( $item->postalcode ? $item->postalcode . " " : "" ) . $item->city;
			if ( $item->state )
				$address[] = $item->state;
			if ( $item->country )
				$address[] = $item->country;
		}
		return implode( '<br />', $address );
	}

	function column_quote_html( $item ) {
		$quote_links = $this->get_quote_links( $item );
		if ( $quote_links ) {
			$table = "<table><tbody>";
			foreach ( $quote_links as $link )
				$table .= "<tr class='$link[class]'><td>$link[added]</td><td class='$link[cat]'><a href='$link[href]'>$link[label]</a></td></tr>";
			$table .= "</tbody></table>";
		} else {
			$table = "Keine Kostenvoranschläge gefunden.";
		}
		return $table;
	}

	private $quote_link_cache = array();

	function get_quote_links( $user ) {
		$hash = md5(serialize($user));
		if ( ! array_key_exists( $hash, $this->quote_link_cache ) ) {
			global $fbk_quotes;
			$quotes = $fbk_quotes->get_quotes( $user->user_id );
			if ( $quotes ) {
				$quotes = array_reverse( $quotes );
				foreach ( $quotes as $quote ) {
					$school = get_post( $quote->school_id );
					$courses = fbk_get_school_meta( $school->ID, 'courses' );
					$classes = array();
					if ( $quote->chosen )
						$classes[] = 'chosen-quote';
					if ( $quote->assist_requested )
						$classes[] = 'assist-requested';
					$cat = wp_get_object_terms( $school->ID, 'category' );
					$this->quote_link_cache[$hash][] = array(
						'href' => admin_url(
							"/$this->base_file?page_type=single&amp;page=$this->page_slug&amp;user_id=$quote->user_id&amp;quote_id=$quote->quote_id"
						),
						'label' => $school->post_title . " | "
							. ( !empty($quote->course_id) ? $courses[$quote->course_id]['name'] . " | " : "" )
							. "$quote->duration "
							. ( 's' == @$courses[$quote->course_id]->cost['period'] ? 'Semester' : (1==$quote->duration ? 'Woche' : 'Wochen') ),
						'class' => implode( ' ', $classes ),
						'added' => date( 'j.n.Y', $quote->date_added ),
						'cat' => $cat ? 'c-' . $cat[0]->slug : ''
					);
				}
			} else {
				$this->quote_link_cache[$hash] = false;
			}
		}
		return $this->quote_link_cache[$hash];
	}

	function get_columns() {
		$columns = array(
			'name' => 'Name',
			'quote_html' => 'Kostenvoranschläge',
			'contact' => 'Kontakt',
			'address' => 'Adresse',
			'date_added' => 'Registriert am'
		);
		return $columns;
	}

	function get_hidden_columns() {
		if ( 'full' == $this->type )
			return array();
		else
			return array(
				'contact',
				'address',
				'date_added'
			);
	}

	function get_sortable_columns() {
		if ( 'widget' == $this->type )
			return array();
		else
			return array(
				'name' => array( 'last_name', false ),
				'date_added' => array( 'date_added', false ),
				'quote_html' => array( 'quotes', false )
			);
	}

	function prepare_items() {
		global $wpdb;

		$per_page_map = array( 'full' => 10, 'widget' => 5 );
		$per_page = $per_page_map[ $this->type ];

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns()
		);

		$current_page = $this->get_pagenum();

		switch ( @$_REQUEST['orderby'] ) {
			default:
			case 'quotes':
				$order_clause = " GROUP BY u.user_id ORDER BY max(q.date_added) " . ( 'asc' == @strtolower($_REQUEST['order']) ? 'ASC' : 'DESC' ) . " ";
				break;
			case 'last_name':
			case 'date_added':
				$order_clause = " ORDER BY u." . ((string)$_REQUEST['orderby']) . " " . ( 'desc' == @strtolower($_REQUEST['order']) ? 'DESC' : 'ASC' ) . " ";
				break;
		}

		$search_clause = "";
		if ( ! empty($_REQUEST['s']) ) {
			$s = mysql_real_escape_string( strtolower($_REQUEST['s']), $wpdb->dbh );
			$search_terms = explode( ' ', $s );
			foreach ( $search_terms as $search )
				$search_clause .= " AND ( ( LOWER(u.first_name) LIKE '%%$search%%' ) OR ( LOWER(u.last_name) LIKE '%%$search%%' ) ) ";
		}

		$query = $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS DISTINCT u.* FROM `" . FBK_Quotes::USER_TABLE . "` AS u "
			. " JOIN `" . FBK_Quotes::QUOTES_TABLE . "` AS q ON u.user_id = q.user_id "
			. " WHERE u.parent_user = '' " . $search_clause
			. " $order_clause LIMIT %d, %d",
			( $current_page - 1 ) * $per_page,
			$per_page
		);
		$this->items = $wpdb->get_results( $query );
		$total_items = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		));
	}

	function display() {
		if ( $this->type == 'widget' ) {
			extract( $this->_args );
?>
<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
	<thead>
	<tr>
		<?php $this->print_column_headers(); ?>
	</tr>
	</thead>

	<tbody id="the-list"<?php if ( $singular ) echo " class='list:$singular'"; ?>>
		<?php $this->display_rows_or_placeholder(); ?>
	</tbody>
</table>
<?php
		} else {
			parent::display();
		}
	}

	function extra_tablenav( $which ) {
		if ( 'top' != $which || 'widget' == $this->type )
			return;

?>
<form method="get" action="<?= admin_url( '/' . $this->base_file ) ?>">
<input type="hidden" name="page" value="<?= $this->page_slug ?>">
<?php $this->search_box( __( 'Search' ), 'name' ) ?>
</form>
<?php
	}
}
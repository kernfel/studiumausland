<?php
/**
 * @package Studium_Ausland
 */

add_action( 'admin_menu', 'fbk_qa_register_menu' );
add_action( 'wp_dashboard_setup', 'fbk_qa_register_widget' );
add_action( 'load-tools_page_quote_list', 'fbk_qa_attachment_redir' );

function fbk_qa_register_menu() {
	add_submenu_page( 'tools.php', 'Kostenvoranschläge', 'Kostenvoranschläge', 'publish_posts', 'quote_list', 'fbk_qa_quote_list' );
}

function fbk_qa_register_widget() {
	wp_add_dashboard_widget( 'quote_admin', 'Die neusten Kostenvoranschläge', 'fbk_qa_widget' );
}

function fbk_qa_widget() {
	require_once( FBK_INC_DIR . '/user-list-table.php' );
	$user_list_table = new FBK_User_List_Table( array( 'type' => 'widget' ) );
	$user_list_table->prepare_items();
	$user_list_table->display();
	echo "<p class='alignright'><a href='" . admin_url( '/tools.php?page=quote_list' ) . "'>Alle Kostenvoranschläge ansehen &raquo;</a></p><br class='clear' />";
}

function fbk_qa_quote_list() {
	if ( isset( $_REQUEST['page_type'] ) && 'single' == $_REQUEST['page_type'] && fbk_qa_single_quote() )
		return;
?>
<div class="wrap"><h2>Empfangene Kostenvoranschlags-Anfragen</h2>
<?php
	require_once( FBK_INC_DIR . '/user-list-table.php' );
	$user_list_table = new FBK_User_List_Table;
	$user_list_table->prepare_items();
	$user_list_table->display();
?>
</div>
<?php
}

function fbk_qa_single_quote() {
	global $fbk_quotes;
	$valid = false;
	if ( isset($_REQUEST['quote_id']) && isset($_REQUEST['user_id']) ) {
		$quote = $fbk_quotes->get_quote( $_REQUEST['quote_id'], $_REQUEST['user_id'] );
		$user = $fbk_quotes->get_user( $_REQUEST['user_id'] );
		if ( $user && $quote )
			$valid = true;
		if ( array_key_exists( 'HTTP_REFERER', $_SERVER ) && false !== strpos( $_SERVER['HTTP_REFERER'], 'tools.php?page=quote_list' ) )
			$list_url = $_SERVER['HTTP_REFERER'];
		else
			$list_url = admin_url( '/tools.php?page=quote_list' );
	}

	if ( ! $valid )
		return false;
?>
<div class="wrap"><h2>Kostenvoranschlag für <?= $user->first_name ?> <?= $user->last_name ?></h2>
<?php
?>
<p><a href='<?= $list_url ?>'>&laquo; Zurück zur Liste</a></p>
<p>Unstimmigkeiten entdeckt? <a href='<?= admin_url( '/post.php?action=edit&post=' . $quote->school_id ) ?>'>Schule bearbeiten &raquo;</a></p>
<p>Kontaktiere <?php
	if ( 'phone' == $user->contact_method )
		$pref = array( '(bevorzugt)', '' );
	else
		$pref = array( '', ' (bevorzugt)' );
	echo "$user->first_name $user->last_name unter Tel. $user->phone $pref[0] oder E-Mail <a href='mailto:$user->email'>$user->email</a>$pref[1].";
?></p>
<?php	$fbk_quotes->get_quote_html( $user, $quote, false, false ); ?>
<div class="clear"></div>
<p><?= $user->salutation ?> <?= $user->first_name ?> <?= $user->last_name ?> hat diesen Kostenvoranschlag am <?= date( 'j.n.Y \\u\\m H:i', $quote->date_added ) ?> Uhr beantragt.</p>
<?php
	$fields = array(
		'street' => 'Straße',
		'postalcode' => 'PLZ',
		'city' => 'Ort',
		'state' => 'Bundesland',
		'country' => 'Land',
		'nationality' => 'Nationalität',
		'birthdate' => 'Geburtsdatum',
		'job' => 'Beruf',
		'lang_level' => 'Sprachniveau (0..5)',
		'experience' => 'Lernerfahrungen',
		'comments' => 'Bemerkungen'
	);
	$table = array();
	foreach ( $fields as $field => $label )
		if ( ! empty($user->$field) )
			$table[$field] = "<tr><td>$label</td><td>" . $user->$field . "</td></tr>";
	if ( $table && ! ( 1 == count($table) && isset($table['country']) ) )
		echo "<table class='fbk-user-info'><thead><tr><th colspan='2'>Weitere Angaben zu $user->first_name $user->last_name</th></tr></thead>",
		"<tbody>" . implode( '', $table ) . "</tbody></table>";
?>
<p><a href="<?= add_query_arg( 'type', 'csv' ) ?>">Datensatz zu diesem Interessenten/Kostenvoranschlag herunterladen &raquo;</a></p>
<?php
	return true;
}

function fbk_qa_attachment_redir() {
	global $fbk_quotes;
	if ( isset($_REQUEST['type']) && 'pdf' == $_REQUEST['type'] && isset($_REQUEST['quote_id']) && isset($_REQUEST['user_id']) ) {
		$user = $fbk_quotes->get_user( $_REQUEST['user_id'] );
		$quote = $fbk_quotes->get_quote( $_REQUEST['quote_id'] );
		if ( ! $user || ! $quote )
			return;
		$fbk_quotes->get_quote_pdf( $quote, $user );
		die;
	} elseif ( isset($_REQUEST['type']) && 'csv' == $_REQUEST['type'] && isset($_REQUEST['quote_id']) && isset($_REQUEST['user_id']) ) {
		$user = $fbk_quotes->get_user( $_REQUEST['user_id'] );
		$quote = $fbk_quotes->get_quote( $_REQUEST['quote_id'] );
		if ( ! $user || ! $quote )
			return;
		$filename = 'Datensatz_' . utf8_decode( $user->last_name . '_' . $user->first_name ) . '.csv';
		header( 'Content-Type: text/csv; charset=CP1252' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $fbk_quotes->get_csv( $user, $quote );
		die;
	}
}

?>
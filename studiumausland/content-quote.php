<?php
/**
 * @package Studium_Ausland
 */

global $fbk_quotes;

$user = $fbk_quotes->get_user( $_REQUEST['u'], $_REQUEST['q'] );

if ( ! empty($_REQUEST['refresh_key']) && ( $user || $fbk_quotes->err == FBK_Quotes::U_ACCESS_KEY_EXPIRED ) ) {
	$fbk_quotes->refresh_access_key( $_REQUEST['u'] );
	echo "<p class='ybox'>Ein neuer Zugangscode wurde an Ihre E-Mail-Adresse versandt. Bitte sehen Sie in Ihrer Mailbox nach "
	. "und ersetzen Sie alle alten Links und Favoriten mit dem neuen Zugangscode. Der bisherige Zugangscode wurde deaktiviert.</p>";
	return;
}

if ( $user ) {
	$fbk_quotes->get_quote_html( $user );
?>
<form style="display: none;" id="push-quote" class="prepared">
 <table>
  <tbody>
	<tr><th colspan="2" scope="rowgroup">Mit den folgenden Angaben erleichtern Sie uns die Arbeit:</th></tr>
	<tr><th>Geburtsdatum</th><td><input name="birthdate" value="<?= esc_attr($user->birthdate) ?>"></td></tr>
	<tr><th>Beruf</th><td><input name="job" value="<?= esc_attr($user->job) ?>"></td></tr>
	<tr><th>Sprachkenntnisse</th><td><select name="lang_level"><?php
	foreach ( array(
			0 => "Keine Vorkenntnisse",
			1 => "Grundkenntnisse",
			2 => "Untere Mittelstufe",
			3 => "Mittelstufe",
			4 => "Obere Mittelstufe",
			5 => "Fortgeschrittene"
		) as $key => $value
	)
		echo "<option value='$key' " . ( $user->lang_level == $key ? "selected" : "" ) . ">$value</option>";
	?></select></td></tr>
	<tr><th>Bisherige Lernerfahrungen</th><td><textarea name="experience"><?= esc_textarea( $user->experience ) ?></textarea></td></tr>
	<tr><th>Sonstiges, Bemerkungen</th><td><textarea name="comments"><?= esc_textarea( $user->comments ) ?></textarea></th></tr>
  </tbody>
  <tfoot>
	<tr><td colspan="2">
	Beachten Sie, dass dies eine unverbindliche Anfrage ist. Sobald wir Ihre Anfrage erhalten haben, werden wir uns bei Ihnen
	melden, um allf&auml;llige Fragen zu kl&auml;ren. Erst, wenn Sie sich festgelegt haben und alles klar ist,
	schicken wir Ihnen ein Formular zu, mit dem Sie sich verbindlich anmelden.</td></tr>
	<tr><td colspan="2"><input type="button" class="cancel" value="Abbrechen"><input type="submit" value="Absenden &raquo;" class="floatright"></td></tr>
  </tfoot>
 </table>
</form>
<?php
} else {
	if ( $fbk_quotes->err == FBK_Quotes::U_NO_SUCH_USER )
		echo "<p class='ybox'>Fehler: Ungültige Benutzer-ID</p>";
	elseif ( $fbk_quotes->err == FBK_Quotes::U_INVALID_ACCESS_KEY )
		echo "<p class='ybox'>Fehler: Ungültiger Zugangscode. Bitte vergewissern Sie sich, dass Sie die gesamte URL eingegeben haben. "
		. "Diese sollte etwa die Form <code>" . home_url( '/quote?u=123&q=' ) . base_convert( 1322559030, 10, 36 ) . "</code> haben.</p>";
	elseif ( $fbk_quotes->err == FBK_Quotes::U_ACCESS_KEY_EXPIRED ) {
		$request_new = home_url( '/quote?' ) . $fbk_quotes->get_refresh_query( $_REQUEST['u'] );
		echo "<p class='ybox'>Warnung: Ihr Zugangscode ist abgelaufen. "
		. "<a href='$request_new'>Klicken Sie bitte hier, um einen neuen Zugangscode anzufordern.</a></p>";
	}
}
<?php
/**
 * @package Studium_Ausland
 * @template Anfrageformular
 */

require_once( dirname(__FILE__).'/lib/recaptchalib.php' );

if ( ! FBK_AJAX || isset($_GET['action']) && 'fbk_ajaxnav' == $_GET['action'] ) {
	the_title( '<h1>', '</h1>' );
	the_content();
}
$form_head = '<form id="request" method="post" action="' . get_permalink() . '" accept-charset="utf-8">';

if ( isset( $_POST['manual_submit'] ) ) {
	$captcha_response = recaptcha_check_answer(
		stripslashes(get_option( 'fbk_recaptcha_privkey' )),
		$_SERVER["REMOTE_ADDR"],
		@$_POST["recaptcha_challenge_field"],
		@$_POST["recaptcha_response_field"]
	);
	$req_valid = true;
	foreach ( array( 'first_name', 'last_name', 'email', 'phone' ) as $req )
		if ( empty( $_POST[$req] ) )
			$req_valid = false;
	if ( $captcha_response->is_valid && $req_valid ) {
		global $fbk_quotes;

		$user = $fbk_quotes->create_new( $_POST );
		
		echo "<p class='ybox'>Vielen Dank für Ihr Interesse, $user->salutation $user->last_name. Wir werden Ihnen in Kürze einen Kostenvoranschlag zukommen lassen",
		" oder uns anderweitig melden. Bitte beachten Sie, dass Ihre Anfrage manuell bearbeitet wird, es kann also unter Umständen etwas",
		" dauern, bis wir soweit sind. Wir danken für Ihr Verständnis.</p>";

		return;
	} elseif ( $req_valid ) {
		if ( FBK_AJAX ) {
			echo '__rcfail__';
			return;
		} else {
			echo $formhead, "<p class='ybox'>Sie haben die Captcha-Aufgabe am unteren Ende des Formulars nicht richtig gelöst. Bitte versuchen Sie es nochmals!</p>";
		}
	} else {
		echo $formhead, "<p class='ybox'>Bitte füllen Sie alle mit einem <span class='req'>*</span> markierten Felder aus!</p>";
	}
} else {
	echo $form_head;
}
?>
 <table>
  <tbody>
	<tr><th colspan="4" scope="rowgroup">Programmwahl</th></tr>
	<tr>
	  <th>Schule</th>
	  <td><input type="text" name="school_name" tabindex="2" value="<?= esc_attr(@$_POST['school_name']) ?>"></td>
	  <th>Kurs</th>
	  <td><input type="text" name="course_name" tabindex="2" value="<?= esc_attr(@$_POST['course_name']) ?>"></td>
	</tr>
	<tr>
	  <th>Unterkunft</th>
	  <td><input type="text" name="accommodation_name" tabindex="2" value="<?= esc_attr(@$_POST['accommodation_name']) ?>"></td>
	  <th>Zimmer &amp; Verpflegung</th>
	  <td>
	    <select name="accommodation_room" tabindex="2"><?php
		foreach ( array(
				's' => 'Einzelzimmer',
				'd' => 'Doppelzimmer',
				't' => 'Zweibettzimmer',
				'm' => 'Mehrbettzimmer'
			) as $key => $value )
			echo "<option value='$key'" . ($key == @$_POST['accommdation_room'] ? ' selected' : '') . ">$value</option>";
	    ?></select><br>
	    <select name="accommodation_board" tabindex="2"><?php
		foreach ( array(
				'sc' => 'Ohne Verpflegung',
				'br' => 'Frühstück',
				'hb' => 'Halbpension',
				'fb' => 'Vollpension'
			) as $key => $value )
			echo "<option value='$key'" . ($key == @$_POST['accommdation_board'] ? ' selected' : '') . ">$value</option>";
	    ?></select>
	  </td>
	</tr>
	<tr>
	  <th>Kursbeginn</th>
	  <td><input type="date" name="course_start" tabindex="2" value="<?= esc_attr(@$_POST['course_start']) ?>"></td>
	  <th>Kursdauer</th>
	  <td><select name="course_duration" tabindex="2"><option value='1'>1 Woche</option><?php
		for ( $i = 2; $i < 55; $i++ )
			echo "<option value='$i'" . ($i == @$_POST['course_duration'] ? ' selected' : '') . ">$i Wochen</option>";
	  ?></select></td>
	</tr>
  </tbody>
  <tbody>
	<tr><th colspan="4" scope="rowgroup">Ihre Kontaktdaten</th></tr>
	<tr>
	  <th>Anrede</th>
	  <td>
	    <select name="salutation" tabindex="4">
		<option value="Frau" <?php if ( 'Frau' == @$_POST['salutation'] ) echo 'selected'; ?>>Frau</option>
		<option value="Herr" <?php if ( 'Herr' == @$_POST['salutation'] ) echo 'selected'; ?>>Herr</option>
	    </select>
	  </td>
	  <th>Straße</th>
	  <td><input type="text" name="street" tabindex="6" value="<?= esc_attr(@$_POST['street']) ?>"></td>
	</tr>
	<tr>
	  <th><span class="required">*</span> Vorname</th>
	  <td><input type="text" name="first_name" required tabindex="4" value="<?= esc_attr(@$_POST['first_name']) ?>"></td>
	  <th>PLZ</th>
	  <td><input type="text" name="postalcode" tabindex="6" value="<?= esc_attr(@$_POST['postalcode']) ?>"></td>
	</tr>
	<tr>
	  <th><span class="required">*</span> Nachname</th>
	  <td><input type="text" name="last_name" required tabindex="4" value="<?= esc_attr(@$_POST['last_name']) ?>"></td>
	  <th>Ort</th>
	  <td><input type="text" name="city" tabindex="6" value="<?= esc_attr(@$_POST['city']) ?>"></td>
	</tr>
	<tr>
	  <th><span class="required">*</span> E-Mail</th>
	  <td><input type="email" name="email" required tabindex="4" value="<?= esc_attr(@$_POST['email']) ?>"></td>
	  <th>Bundesland</th>
	  <td><input type="text" name="state" tabindex="6" value="<?= esc_attr(@$_POST['state']) ?>"></td>
	</tr>
	<tr>
	  <th><span class="required">*</span> Telefon</th>
	  <td><input type="tel" name="phone" required tabindex="4" value="<?= esc_attr(@$_POST['phone']) ?>"></td>
	  <th>Land</th>
	  <td>
	    <select name="country" tabindex="6">
		<option value="Deutschland" <?php if ( 'Deutschland' == @$_POST['country'] ) echo 'selected'; ?>>Deutschland</option>
		<option value="Österreich" <?php if ( 'Österreich' == @$_POST['country'] ) echo 'selected'; ?>>Österreich</option>
		<option value="Schweiz" <?php if ( 'Schweiz' == @$_POST['country'] ) echo 'selected'; ?>>Schweiz</option>
	    </select>
	  </td>
	</tr>
	<tr>
	  <th>Bevorzugte Kontaktmethode</th>
	  <td>
	    <select name="contact_method" tabindex="4">
		<option value="phone" <?php if ( 'phone' == @$_POST['contact_method'] ) echo 'selected'; ?>>Telefon</option>
		<option value="mail" <?php if ( 'mail' == @$_POST['contact_method'] ) echo 'selected'; ?>>E-Mail</option>
	    </select>
	  </td>
	  <th>Nationalität</th>
	  <td>
	    <i>Diese Angabe ist wegen der unterschiedlichen Einreisebestimmungen wichtig.</i><br>
	    <input type="text" name="nationality" tabindex="6" value="<?= esc_attr(@$_POST['nationality']) ?>">
	  </td>
	</tr>
  </tbody>
  <tbody class="manual">
	<tr><th colspan="4" scope="rowgroup">Weitere Angaben</th></tr>
	<tr>
	  <th>Geburtsdatum</th>
	  <td><input type="date" name="birthdate" tabindex="8" value="<?= esc_attr(@$_POST['birthdate']) ?>"></td>
	  <th>Beruf</th>
	  <td><input type="text" name="job" tabindex="8" value="<?= esc_attr(@$_POST['job']) ?>"></td>
	</tr>
	<tr>
	  <th>Sprachkenntnisse</th>
	  <td colspan="3">
	    <select name="lang_level" tabindex="8"><?php
		foreach ( array(
				0 => 'Keine Vorkenntnisse',
				1 => 'Grundkenntnisse',
				2 => 'Untere Mittelstufe',
				3 => 'Mittelstufe',
				4 => 'Obere Mittelstufe',
				5 => 'Fortgeschrittene'
			) as $key => $value )
			echo "<option value='$key'" . ($key == @$_POST['lang_level'] ? ' selected' : '') . ">$value</option>";
	    ?></select>
	  </td>
	</tr>
	<tr>
	  <th>Bisherige Lernerfahrungen</th>
	  <td colspan="3"><textarea name="experience" tabindex="8"><?= esc_textarea(@$_POST['experience']) ?></textarea></td>
	</tr>
	<tr>
	  <th>Sonstiges, Bemerkungen</th>
	  <td colspan="3"><textarea name="comments" tabindex="8"><?= esc_textarea(@$_POST['comments']) ?></textarea></td>
	</tr>
  </tbody>
  <tfoot>
	<tr><td colspan="4">
	  Bitte füllen Sie alle mit <span class="required">*</span> markierten Felder aus.
	  <br>Ihre Daten werden selbstverständlich vertraulich behandelt und unter keinen Umständen weitergegeben.
	</td></tr>
	<tr><td colspan="4">
	  <input type="checkbox" name="newsletter"<?= isset($_POST['manual_submit']) && empty($_POST['newsletter']) ? '' : ' checked' ?> tabindex="10" id="cb_newsletter">
	  <label for="cb_newsletter">Ja, ich möchte den Studium-Ausland-Newsletter per E-Mail erhalten.</label>
	</td></tr>
	<tr><td colspan="4">
	  <div id="captcha"><?php if ( ! FBK_AJAX )
		echo recaptcha_get_html( stripslashes(get_option( 'fbk_recaptcha_pubkey' )) );
	  ?></div>
	</td></tr>
	<tr><td colspan="4">
	  <input type="hidden" name="manual_submit" value="1">
	  <input type="submit" value="Absenden" style="float:right;" tabindex="10">
	</td></tr>
  </tfoot>
 </table>
</form>
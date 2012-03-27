<?php
/**
 * @package Studium_Ausland
 * @template Kontaktformular
 */

require_once( dirname(__FILE__).'/lib/recaptchalib.php' );

$err = $success = false;

if ( isset( $_POST['contact-form'] ) || isset($_POST['action']) && 'fbk_cform'==$_POST['action'] ) {
	$response = fbk_cform_validate();
	if ( ! $response ) {
		the_title( '<h1>', '</h1>' );
		echo "<p>Vielen Dank für Ihre Anfrage. Wir werden uns so bald wie möglich darum kümmern und uns bei Ihnen melden. "
		. "Stöbern Sie gerne in der Zwischenzeit etwas in unserem breiten Angebot!</p>";
		$success = true;
	} elseif ( 1 == $response ) { // Missing req
		$err = true;
	} elseif ( 2 == $response ) { // Incorrect recaptcha
		if ( ! isset( $_POST['contact-form'] ) ) {
			echo "__rcfail__";
			$success = true;
		} else $err = true;
	}
}

if ( ! $success ) :
	if ( ! isset( $_POST['action'] ) || 'fbk_cform' != $_POST['action'] ) {
		the_title( '<h1>', '</h1>' );
		the_content();
	}
	if ( $err )
		echo "<p class='error'>Bitte füllen Sie alle Kontaktdaten aus und lösen Sie die Spamschutz-Aufgabe nochmals.</p>";
?>
<form action="" method="post" id="contact" accept-charset="utf-8">
 <table>
  <thead><tr><th colspan="2"><?php the_title(); ?></th></tr></thead>
  <tbody>
	<tr>
	  <th>Bitte beschreiben Sie Ihr Anliegen:</th>
	  <td><textarea name="request" rows="10" cols="40"><?php if(isset($_POST['request'])) echo esc_textarea($_POST['request']); ?></textarea></td>
	</tr>
	<tr>
	  <th>Bevorzugte Kontaktmethode</th>
	  <td><select name="contact_method"><option value="phone">Telefon<option value="mail">E-Mail</select></td>
	</tr>
  <tbody>
	<tr><th colspan="2" scope="rowgroup">Kontaktdaten</th></tr>
  <?php if ( fbk_ua_supports( 'placeholder' ) ) : ?>
	<tr>
	  <th>Anschrift</th>
	  <td><div class="flex-vertical">
	   <select name="salutation">
		<option value="Frau">Frau
		<option value="Hr.">Herr
	   </select>
	   <div>
	    <input type="text" name="first_name" placeholder="Vorname" required>
	    <input type="text" name="last_name" placeholder="Nachname" required>
	   </div>
	   <input type="text" name="primary_address_street" placeholder="Straße" required>
	   <div class="flex">
	    <input type="number" step="1" min="1000" max="99999" name="primary_address_postalcode" placeholder="PLZ" required>
	    <input type="text" name="primary_address_city" placeholder="Ort" class="flex-one" required>
	   </div>
	   <div class="flex">
	    <input type="text" name="primary_address_state" placeholder="Bundesland" class="flex-one">
	    <select name="primary_address_country">
		<option value="Deutschland">Deutschland
		<option value="Österreich">Österreich
		<option value="Schweiz">Schweiz
	    </select>
	   </div>
	  </div></td>
	</tr>
<?php else : ?>
	<tr>
	  <th>Anrede<br>Vorname<br>Nachname</th>
	  <td>
	   <div class="flex-vertical">
	    <select name="salutation">
		<option value="Frau">Frau
		<option value="Hr.">Herr
	    </select>
	    <input type="text" name="first_name" required>
	    <input type="text" name="last_name" required>
	   </div>
	  </td>
	</tr>
	<tr>
	  <th>Straße</th>
	  <td><input type="text" name="primary_address_street" required></td>
	</tr>
	<tr>
	  <th>PLZ, Ort</th>
	  <td>
	    <input type="number" step="1" min="1000" max="99999" name="primary_address_postalcode" required>
	    <input type="text" name="primary_address_city" required>
	  </td>
	</tr>
	<tr>
	  <th>Bundesland, Land</th>
	  <td>
	    <input type="text" name="primary_address_state">
	    <select name="primary_address_country">
		<option value="Deutschland">Deutschland
		<option value="Österreich">Österreich
		<option value="Schweiz">Schweiz
	    </select>
	  </td>
	</tr>
<?php endif; ?>
	<tr>
	  <th>E-Mail</th>
	  <td><input type="email" name="email" required></td>
	</tr>
	<tr>
	  <th>Telefon</th>
	  <td>
	   <div class="flex-vertical">
		<input type="tel" name="phone_home" placeholder="Festnetz/Hauptnummer" required>
		<input type="tel" name="phone_mobile" placeholder="Mobil/Zweitnummer">
	   </div>
	  </td>
	</tr>
  </tbody>
  <tbody>
	<tr><th colspan="2" scope="rowgroup">Spamschutz</th></tr>
	<tr>
	  <td colspan="2">
	    <div id="captcha"><?php if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX )
		echo recaptcha_get_html( stripslashes(get_option( 'fbk_recaptcha_pubkey' )) );
	    ?></div>
	  </td>
	</tr>
  </tbody>
  <tfoot><tr><td colspan="2" style="text-align: right;"><input type="submit" value="Absenden" name="contact-form"></td></tr></tfoot>
 </table>


</form>
<?php
endif;

function fbk_cform_validate() {
	$captcha_response = recaptcha_check_answer( stripslashes(get_option( 'fbk_recaptcha_privkey' )), $_SERVER["REMOTE_ADDR"], @$_POST["recaptcha_challenge_field"], @$_POST["recaptcha_response_field"] );
	if ( ! $captcha_response->is_valid )
		return 2;
	foreach ( array(
		'first_name' => 'Vorname',
		'last_name' => 'Nachname',
		'primary_address_street' => 'Straße',
		'primary_address_postalcode' => 'Postleitzahl',
		'primary_address_city' => 'Ort',
		'email' => 'E-Mail',
		'phone_home' => 'Telefon')
	as $key => $label )
		if ( empty($_POST[$key]) )
			return 1;

	$mail_map = array(
		'salutation' => "Anrede\t\t",
		'first_name' => "Vorname\t",
		'last_name' => "Nachname\t",
		'primary_address_street' => "Straße\t\t",
		'primary_address_postalcode' => "Postleitzahl\t",
		'primary_address_city' => "Ort\t\t",
		'primary_address_state' => "Bundesland\t",
		'primary_address_country' => "Land\t\t",
		'email' => "E-Mail\t\t",
		'phone_home' => "Telefon 1\t",
		'phone_mobile' => "Telefon 2\t",
		'contact_method' => "Kontaktmethode",
		'request' => "Nachricht/Anfrage: -v-----v-----v-----v-\n\n"
	);
	$mailtext = '';
	foreach ( $mail_map as $key => $heading )
		if ( ! empty($_POST[$key]) )
			$mailtext .= "\n\n$heading\t$_POST[$key]";

	mail(
		q_encode_angle_address(stripslashes(get_option( 'fbk_mail_to' ))),
		"Kontaktformular Studium-Ausland: =?UTF-8?B?" . base64_encode( $_POST['first_name'] . ' ' . $_POST['last_name'] ) . "?=",
		chunk_split(base64_encode( $mailtext )),
		"Content-Transfer-Encoding: base64\n"
		. "Content-Type: text/plain; charset=utf-8\n"
		. "From: " . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_internal' )))
	);
	return 0;
}
?>
<?php
/**
 * @package Studium_Ausland
 */

add_action( 'admin_menu', 'fbk_am_register' );
function fbk_am_register() {
#	add_submenu_page( 'tools.php', 'Testzone', 'Testzone', 'update_core', 'testzone', 'fbk_am_menu_testzone' );

	add_menu_page( 'Allgemeine Einstellungen', 'Studium Ausland', 'publish_posts', 'fbk_theme_opts', 'fbk_theme_options', null, 81 );

	add_submenu_page( 'fbk_theme_opts', 'Allgemeine Einstellungen', 'Allgemein', 'publish_posts', 'fbk_theme_opts', 'fbk_theme_options' );
	add_submenu_page( 'fbk_theme_opts', 'E-Mail-Einstellungen', 'E-Mail', 'publish_posts', 'fbk_theme_opts_email', 'fbk_theme_options' );
	add_submenu_page( 'fbk_theme_opts', 'Einstellungen für Kostenvoranschläge', 'Kostenvoranschläge', 'publish_posts', 'fbk_theme_opts_quotes', 'fbk_theme_options' );
	add_submenu_page( 'fbk_theme_opts', 'Cache-Einstellungen', 'Cache', 'manage_options', 'fbk_theme_opts_cache', 'fbk_theme_options' );
	add_submenu_page( 'fbk_theme_opts', 'Suchmaschinenoptimierung', 'SEO', 'manage_options', 'fbk_theme_opts_seo', 'fbk_theme_options' );
}

function fbk_am_menu_testzone() {
	global $wpdb, $fbk;
?>
<div class="wrap"><h2>Testzone</h2>
<pre>
<?php
	echo "Move along, there's nothing to see here.";
?>
</pre>
</div>
<?php
}

function fbk_theme_options() {
	global $fbk_cf_slices, $submenu;
	if ( ! empty($_POST) )
		$message = fbk_theme_options_save();
	else
		$message = '';

	$titles = array(
		'fbk_theme_opts' => 'Allgemeine Einstellungen',
		'fbk_theme_opts_email' => 'E-Mail-Einstellungen',
		'fbk_theme_opts_quotes' => 'Einstellungen für Kostenvoranschläge',
		'fbk_theme_opts_sugar' => 'Sugar-Einstellungen',
		'fbk_theme_opts_cache' => 'Cache-Einstellungen',
		'fbk_theme_opts_seo' => 'Suchmaschinenoptimierung'
	)
?>
<div class="wrap"><div id="icon-options-general" class="icon32"><br></div><h2><?= $titles[$_REQUEST['page']] ?></h2>
<?= $message ?>
<form method="post">
<input type="hidden" name="page" value="<?= $_REQUEST['page'] ?>">
<?php wp_nonce_field( 'edit-' . $_REQUEST['page'] ); ?>
<table class="form-table">
<tbody>
<?php
	switch ( str_replace( 'fbk_theme_opts_', '', $_REQUEST['page'] ) ) :
		default:
			foreach ( $fbk_cf_slices as $slug )
				echo "<tr><th><label for='fbk_slice_label_$slug'>Anzeigename Reiter \"" . ucfirst($slug) . "\"</label></th>",
				"<th><input type='text' name='fbk_slice_label_$slug' id='fbk_slice_label_$slug' class='regular-text' value='" . esc_attr(stripslashes(get_option( 'fbk_slice_label_' . $slug ))) . "'></td></tr>";
?>
<tr>
 <th><label for="fbk_fb_pageid">ID der Facebook-Seite</label></th>
 <td>
  <input type="text" class="regular-text" name="fbk_fb_pageid" id="fbk_fb_pageid" value="<?= esc_attr(get_option( 'fbk_fb_pageid' )) ?>">
  <span class="description">Die ID oder der "Benutzername" der mit Studium Ausland verknüpften Facebook-Seite</span>
 </td>
</tr>
<tr>
 <th><label for="fbk_analytics_id">Google Analytics ID</label></th>
 <td>
  <input type="text" class="regular-text" name="fbk_analytics_id" id="fbk_analytics_id" value="<?= esc_attr(stripslashes(get_option( 'fbk_analytics_id' ))) ?>">
 </td>
</tr>
<tr>
 <th><label for="fbk_recaptcha_pubkey">Recaptcha: Public key</label></th>
 <td>
  <input type="text" class="regular-text" name="fbk_recaptcha_pubkey" id="fbk_recaptcha_pubkey" value="<?= esc_attr(stripslashes(get_option( 'fbk_recaptcha_pubkey' ))) ?>">
 </td>
</tr>
<tr>
 <th><label for="fbk_recaptcha_privkey">Recaptcha: Private key</label></th>
 <td>
  <input type="text" class="regular-text" name="fbk_recaptcha_privkey" id="fbk_recaptcha_privkey" value="<?= esc_attr(stripslashes(get_option( 'fbk_recaptcha_privkey' ))) ?>">
 </td>
</tr>
<tr>
 <th><label for="fbk_pagewidth">Seitenbreite</label></th>
 <td>
  <input type="text" class="small-text" name="fbk_pagewidth" id="fbk_pagewidth" value="<?= esc_attr(get_option( 'fbk_pagewidth' )) ?>">px
  <span class="description">Dieser Wert wird verwendet, um die Breite der Kategorie-Tabs zu berechnen.</span>
 </td>
</tr>
<?php
			break;
		case 'email':
?>
<tr>
 <th><label for="fbk_mail_to">Empfänger der Formulardaten</label></th>
 <td>
  <input type="text" class="regular-text" name="fbk_mail_to" id="fbk_mail_to" value="<?= esc_attr(stripslashes(get_option( 'fbk_mail_to' ))) ?>">
  <span class="description">An diese Adresse(n) werden die Formulardaten geschickt.</span>
 </td>
</tr>
<tr>
 <th><label for="fbk_mail_from_quotes">E-Mail-Absender: Kostenvoranschlag</label></th>
 <td><input type="text" class="regular-text" name="fbk_mail_from_quotes" id="fbk_mail_from_quotes" value="<?= esc_attr(stripslashes(get_option( 'fbk_mail_from_quotes' ))) ?>"></td>
</tr>
<tr>
 <th><label for="fbk_mail_from_newsletter">E-Mail-Absender: Newsletter</label></th>
 <td><input type="text" class="regular-text" name="fbk_mail_from_newsletter" id="fbk_mail_from_newsletter" value="<?= esc_attr(stripslashes(get_option( 'fbk_mail_from_newsletter' ))) ?>"></td>
</tr>
<tr>
 <th><label for="fbk_mail_from_internal">E-Mail-Absender: Intern</label></th>
 <td><input type="text" class="regular-text" name="fbk_mail_from_internal" id="fbk_mail_from_internal" value="<?= esc_attr(stripslashes(get_option( 'fbk_mail_from_internal' ))) ?>"></td>
</tr>
<tr>
 <th><label for="fbk_mail_signature">E-Mail-Signatur</label></th>
 <td>
  <span class="description">Diese Signatur wird an alle Kunden-E-Mails angehängt.</span>
  <textarea type="text" rows="10" class="large-text" name="fbk_mail_signature" id="fbk_mail_signature"><?= esc_textarea(stripslashes(get_option( 'fbk_mail_signature' ))) ?></textarea>
 </td>
</tr>
<?php
			break;
		case 'quotes':
?>
<tr>
 <th><label for="fbk_quote_noauto">Automatischen Versand der Kostenvoranschläge unterdrücken</label></th>
 <td>
  <input type="checkbox" name="fbk_quote_noauto" id="fbk_quote_noauto" <?php if ( 'yes' == get_option( 'fbk_quote_noauto' ) ) echo 'checked="checked"' ?>>
  <input type="hidden" name="__checkboxes[]" value="fbk_quote_noauto">
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_access_duration">Zugangsdauer</label></th>
 <td>
  <input type="text" class="small-text" name="fbk_quote_access_duration" id="fbk_quote_access_duration" value="<?= esc_attr(get_option( 'fbk_quote_access_duration' )) ?>">
  Tage
  <span class="description">So lange bleibt der Zugangsschlüssel für einen Kostenvoranschlag gültig.
  Wird der Kostenvoranschlag nach dieser Zeit erneut aufgerufen, wird ein neuer Schlüssel generiert und per E-Mail verschickt.</span>
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_remind_after">Erinnern nach</label></th>
 <td>
  <input type="text" class="small-text" name="fbk_quote_remind_after" id="fbk_quote_remind_after" value="<?= esc_attr(get_option( 'fbk_quote_remind_after' )) ?>">
  Tagen
  <span class="description">Hat ein/e Interessent/in keine manuelle Beratung beantragt, wird er/sie nach dieser Zeitspanne an diese Möglichkeit erinnert.</span>
 </td>
</tr>
<tr>
 <th>
  <label for="fbk_quote_present_subject">E-Mail an Interessenten bei Erhalt der Voranschlags-Anfrage</label>
  <br>
  <span class="description">Hinweis: Die E-Mail sollte mindestens die Adresse zum Kostenvoranschlag (Platzhalter {url}) enthalten, da Kunden diese auf keine
   andere Weise erhalten.</span>
 </th>
 <td>
  <label for="fbk_quote_present_subject">
   Betreff:
   <input type="text" class="regular-text" name="fbk_quote_present_subject" id="fbk_quote_present_subject" value="<?= esc_attr(stripslashes(get_option( 'fbk_quote_present_subject' ))) ?>">
  </label>
  <br>
  <label for="fbk_quote_present_text">
   Nachricht:
   <textarea type="text" rows="10" class="large-text" name="fbk_quote_present_text" id="fbk_quote_present_text"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_present_text' ))) ?></textarea>
  </label>
  <br>
  <span class="description">Die Signatur (siehe E-Mail-Einstellungen) wird automatisch angehängt. Folgende Platzhalter werden unterstützt:
   <ul>
    <li><code>{url}</code> für die URL zum Kostenvoranschlag</li>
    <li><code>{first_name}</code>, <code>{last_name}</code>, <code>{salutation}</code>, <code>{email}</code>, <code>{phone}</code> für die entsprechenden Benutzerdaten</li>
    <li><code>{masculine_r}</code> für ein &bdquo;r&ldquo;, falls der Benutzer männlich ist</li>
    <li><code>{access_duration}</code> für die Zugangsdauer (siehe oben)</li>
    <li><code>{school}</code>, <code>{course}</code>, <code>{accommodation}</code> für die entsprechenden Daten des Kostenvoranschlags</li>
    <li><code>{duration}</code> für die gewünschte Dauer, z.B. &bdquo;12 Wochen&ldquo; oder &bdquo;1 Semester&ldquo;</li>
    <li><code>{start}</code> für das Anfangsdatum.</li>
   </ul>
  </span>
 </td>
</tr>
<tr>
 <th>
  <label for="fbk_quote_refresh_subject">E-Mail an Interessenten, wenn ein neuer Zugangscode beantragt wurde</label>
  <br>
  <span class="description">Hinweis: Die E-Mail sollte mindestens die Adresse zum Kostenvoranschlag (Platzhalter {url}) enthalten, da Kunden diese auf keine
   andere Weise erhalten.</span>
 </th>
 <td>
  <label for="fbk_quote_refresh_subject">
   Betreff:
   <input type="text" class="regular-text" name="fbk_quote_refresh_subject" id="fbk_quote_refresh_subject" value="<?= esc_attr(stripslashes(get_option( 'fbk_quote_refresh_subject' ))) ?>">
  </label>
  <br>
  <label for="fbk_quote_refresh_text">
   Nachricht:
   <textarea type="text" rows="10" class="large-text" name="fbk_quote_refresh_text" id="fbk_quote_refresh_text"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_refresh_text' ))) ?></textarea>
  </label>
  <br>
  <span class="description">Die Signatur (siehe E-Mail-Einstellungen) wird automatisch angehängt. Folgende Platzhalter werden unterstützt:
   <ul>
    <li><code>{url}</code> für die URL zum Kostenvoranschlag</li>
    <li><code>{first_name}</code>, <code>{last_name}</code>, <code>{salutation}</code>, <code>{email}</code>, <code>{phone}</code> für die entsprechenden Benutzerdaten</li>
    <li><code>{masculine_r}</code> für ein &bdquo;r&ldquo;, falls der Benutzer männlich ist</li>
    <li><code>{access_duration}</code> für die Zugangsdauer (siehe oben)</li>
    <li><code>{num_kv}</code> für die Anzahl durch diesen Benutzer beantragter Kostenvoranschläge</li>
    <li><code>{plural_n}</code> für ein &bdquo;n&ldquo;, falls mehrere Kostenvoranschläge vorhanden sind</li>
    <li><code>{kv_plural}</code> für &bdquo;Kostenvoranschlag&ldquo; bzw. &bdquo;Kostenvoranschläge&ldquo;</li>
   </ul>
  </span>
 </td>
</tr>
<tr>
 <th>
  <label for="fbk_quote_reminder_subject">E-Mail an Interessenten zur Erinnerung an die Möglichkeit der persönlichen Beratung</label>
 </th>
 <td>
  <label for="fbk_quote_reminder_subject">
   Betreff:
   <input type="text" class="regular-text" name="fbk_quote_reminder_subject" id="fbk_quote_reminder_subject" value="<?= esc_attr(stripslashes(get_option( 'fbk_quote_reminder_subject' ))) ?>">
  </label>
  <br>
  <label for="fbk_quote_reminder_text_single">
   Nachricht (einzelner Kostenvoranschlag):
   <textarea type="text" rows="10" class="large-text" name="fbk_quote_reminder_text_single" id="fbk_quote_reminder_text_single"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_reminder_text_single' ))) ?></textarea>
  </label>
  <br>
  <label for="fbk_quote_reminder_text_multi">
   Nachricht (mehrere Kostenvoranschläge):
   <textarea type="text" rows="10" class="large-text" name="fbk_quote_reminder_text_multi" id="fbk_quote_reminder_text_multi"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_reminder_text_multi' ))) ?></textarea>
  </label>
  <br>
  <span class="description">Die Signatur (siehe E-Mail-Einstellungen) wird automatisch angehängt. Folgende Platzhalter werden unterstützt:
   <ul>
    <li><code>{url}</code> für die URL zum Kostenvoranschlag</li>
    <li><code>{first_name}</code>, <code>{last_name}</code>, <code>{salutation}</code>, <code>{email}</code>, <code>{phone}</code> für die entsprechenden Benutzerdaten</li>
    <li><code>{masculine_r}</code> für ein &bdquo;r&ldquo;, falls der Benutzer männlich ist</li>
    <li><code>{access_duration}</code> für die Zugangsdauer (siehe oben)</li>
    <li><code>{remind_after}</code> für die Anzahl Tage, nach der erinnert wird (siehe oben)</li>
    <li><code>{num_kv}</code> für die Anzahl durch diesen Benutzer beantragter Kostenvoranschläge</li>
    <li><code>{plural_n}</code> für ein &bdquo;n&ldquo;, falls mehrere Kostenvoranschläge vorhanden sind</li>
    <li><code>{kv_plural}</code> für &bdquo;Kostenvoranschlag&ldquo; bzw. &bdquo;Kostenvoranschläge&ldquo;</li>
   </ul>
  </span>
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_pdf_header">PDF, Kopfzeile (unter dem Logo)</label></th>
 <td>
  <input type="text" class="regular-text" name="fbk_quote_pdf_header" id="fbk_quote_pdf_header" value="<?= esc_attr(stripslashes(get_option( 'fbk_quote_pdf_header' ))) ?>">
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_pdf_addrblock">PDF, Adressblock</label></th>
 <td>
  <textarea type="text" rows="10" class="large-text" name="fbk_quote_pdf_addrblock" id="fbk_quote_pdf_addrblock"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_pdf_addrblock' ))) ?></textarea>
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_pdf_disclaimer_euro">PDF, Disclaimer bei Euro-Preisen</label></th>
 <td>
  <textarea type="text" rows="10" class="large-text" name="fbk_quote_pdf_disclaimer_euro" id="fbk_quote_pdf_disclaimer_euro"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_pdf_disclaimer_euro' ))) ?></textarea>
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_pdf_disclaimer_foreign">PDF, Disclaimer bei Fremdwährungen</label></th>
 <td>
  <textarea type="text" rows="10" class="large-text" name="fbk_quote_pdf_disclaimer_foreign" id="fbk_quote_pdf_disclaimer_foreign"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_pdf_disclaimer_foreign' ))) ?></textarea>
 </td>
</tr>
<tr>
 <th><label for="fbk_quote_pdf_footer">PDF, Fußzeile</label></th>
 <td>
  <textarea type="text" rows="10" class="large-text" name="fbk_quote_pdf_footer" id="fbk_quote_pdf_footer"><?= esc_textarea(stripslashes(get_option( 'fbk_quote_pdf_footer' ))) ?></textarea>
 </td>
</tr>
<?php
			break;
		case 'cache':
?>
<tr>
 <th>Server-Cache verwenden</th>
 <td>
  <label for="fbk_cache">
   <input type="checkbox" name="fbk_cache" id="fbk_cache" <?= 'yes' == get_option( 'fbk_cache' ) ? 'checked' : '' ?> value="yes">
   <input type="hidden" name="__checkboxes[]" value="fbk_cache">
   Speichere die HTML-Version der meisten statischen Inhalte auf dem Server ab, um die Ladezeiten zu verkürzen.
  </label><br>
  <span class="description">Hinweis: Ajax-Anfragen aus dem Frontend ignorieren diese Anweisung aus Performancegründen. Es ist daher nötig,
   den Cache zu leeren, um ihn komplett zu deaktivieren.</span>
 </td>
</tr>
<tr style="background-color: #eee;">
 <th><label for="fbk_do_reset_cache">Server-Cache zurücksetzen</label></th>
 <td>
  <input type="checkbox" id="fbk_do_reset_cache" name="fbk_do[]" value="reset_cache">
  <span class="description">Löscht alle gecachten Dateien vom Server.</span>
 </td>
</tr>
<tr style="background-color: #eee;">
 <th><label for="fbk_do_rebuild_color_css">Farb-CSS regenerieren</label></th>
 <td>
  <input type="checkbox" id="fbk_do_rebuild_color_css" name="fbk_do[]" value="rebuild_color_css">
  <span class="description">Erneuert die Datei <code><?= FBK_COLORS_CSS_FILE ?></code> aus den Farbeinstellungen der Kategorien,
  der <code>color-template.css</code> und der <code>color-overrides.css</code>.</span>
 </td>
</tr>
<tr>
 <th>Minimierte Versionen von <code>style.css</code> und <code>layout.css</code> verwenden</th>
 <td>
  <label for="fbk_use_min_css">
   <input type="checkbox" name="fbk_use_min_css" id="fbk_use_min_css" <?= 'yes' == get_option( 'fbk_use_min_css' ) ? 'checked' : '' ?> value="yes">
   <input type="hidden" name="__checkboxes[]" value="fbk_use_min_css">
   Verwende die minimierten Dateien <code>style-min.css</code> und <code>layout-min.css</code> statt ihrer Elternversionen.
  </label>
 </td>
</tr>
<tr style="background-color: #eee;">
 <th><label for="fbk_do_minify_style_css"><code>style.css</code> neu minimieren</label></th>
 <td>
  <input type="checkbox" id="fbk_do_minify_style_css" name="fbk_do[]" value="minify_style_css">
  <span class="description">Erneuert die Datei <code>style-min.css</code> aus der <code>style.css</code>.</span>
 </td>
</tr>
<tr style="background-color: #eee;">
 <th><label for="fbk_do_minify_layout_css"><code>layout.css</code> neu minimieren</label></th>
 <td>
  <input type="checkbox" id="fbk_do_minify_layout_css" name="fbk_do[]" value="minify_layout_css">
  <span class="description">Erneuert die Datei <code>layout-min.css</code> aus der <code>layout.css</code>.</span>
 </td>
</tr>
<?php
			break;
		case 'seo':
			global $fbk_sitemap_gen;
			$sitemap_types = array(
				'school' => 'Schulen',
				'page' => 'Seiten',
				'offer' => 'Sonderangebote',
				'page-news' => 'News-Übersicht',
				'post' => 'News-Artikel',
				'page-home' => 'Frontseite',
				'category' => 'Kategorieseiten',
				'loc' => 'Stadt- und Landesseiten'
			);
?>
<tr>
 <th>ab-in-den-urlaub.de</th>
 <td>
  <label for="fbk_aidu_disallow">
   <input type="checkbox" name="fbk_aidu_disallow" id="fbk_aidu_disallow" <?= 'yes' == get_option( 'fbk_aidu_disallow' ) ? 'checked' : '' ?> value="yes">
   <input type="hidden" name="__checkboxes[]" value="fbk_aidu_disallow">
   Vertreibe Suchmaschinen-Crawler aus den Unterseiten des Content4Partner-Plugins
  </label>
 </td>
</tr>
<tr>
 <th>Sitemap: Prioritäten</th>
 <td>
  <ul>
<?php
			foreach ( $sitemap_types as $index => $label ) :
?>
   <li>
    <input type="text" class="small-text" id="fbk_sitemap_prio_<?= $index ?>" name="fbk_sitemap_prio_<?= $index ?>" value="<?= esc_attr( $fbk_sitemap_gen->get_prio( $index ) ) ?>">
    <label for="fbk_sitemap_prio_<?= $index ?>"><?= $label ?></label>
   </li>
<?php
			endforeach;
?>
  </ul>
 </td>
</tr>
<tr>
 <th>Sitemap: Änderungshäufigkeit</th>
 <td>
  <ul>
<?php
			foreach ( $sitemap_types as $index => $label ) :
?>
   <li>
    <select id="fbk_sitemap_freq_<?= $index ?>" name="fbk_sitemap_freq_<?= $index ?>">
<?php 				foreach ( array( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ) as $freq ) : ?>
     <option <?php if ( $freq == $fbk_sitemap_gen->get_changefreq( $index ) ) echo "selected"; ?>><?= $freq ?></option>
<?php				endforeach; ?>
    </select>
    <label for="fbk_sitemap_freq_<?= $index ?>"><?= $label ?></label>
   </li>
<?php
			endforeach;
?>
  </ul>
 </td>
</tr>
<?php
			break;
	endswitch;
?>
</tbody>
</table>
<?php submit_button(); ?>
</form>
</div>
<?php
}

function fbk_theme_options_save() {
	global $wpdb, $fbk_cache, $fbk_sitemap_gen;
	check_admin_referer( 'edit-' . $_REQUEST['page'] );
	$msg = $error = array();
	if ( isset( $_POST['fbk_do'] ) ) {
		$root = get_stylesheet_directory();

		if ( in_array( 'reset_cache', $_POST['fbk_do'] ) ) {
			$fbk_cache->flush();
			$msg[] = 'Cache geleert.';
		}

		if ( in_array( 'rebuild_color_css', $_POST['fbk_do'] ) ) {
			fbk_rebuild_color_css();
		}

		if ( in_array( 'minify_style_css', $_POST['fbk_do'] ) ) {
			file_put_contents( $root . '/style-min.css', fbk_compress_css( file_get_contents( $root . '/style.css' ) ) );
		}

		if ( in_array( 'minify_layout_css', $_POST['fbk_do'] ) ) {
			file_put_contents( $root . '/layout-min.css', fbk_compress_css( file_get_contents( $root . '/layout.css' ) ) );
		}

		unset( $_POST['fbk_do'] );
	}

	if ( isset( $_POST['__checkboxes'] ) ) {
		foreach ( $_POST['__checkboxes'] as $key ) {
			if ( isset( $_POST[$key] ) ) {
				update_option( $key, 'yes' );
				unset( $_POST[$key] );
			} else {
				update_option( $key, 'no' );
			}
		}
	}

	$numeric_fields = array(
		'fbk_quote_access_duration',
		'fbk_quote_remind_after'
	);
	foreach ( $_POST as $key => $value ) {
		if ( 0 === strpos( $key, 'fbk_' ) ) {
			if ( in_array( $key, $numeric_fields ) )
				$value = (int) $value;
			$previous_value = stripslashes(get_option( $key ));
			update_option( $key, $value );

			if ( $previous_value != $value ) {
				if ( 'fbk_pagewidth' == $key ) {
					$fbk_cache->delete( 'footer' );
				} elseif ( 0 === strpos( $key, 'fbk_slice_label' ) ) {
					$fbk_cache->flush( 'schools' );
				} elseif ( 0 === strpos( $key, 'fbk_sitemap' ) ) {
					$regenerate_sitemap = true;
				}
			}
		}
	}

	if ( ! empty($regenerate_sitemap) ) {
		$fbk_sitemap_gen->regenerate();
	}
	
	$msg[] = 'Änderungen gespeichert.';

	$message = '<div class="updated"><p>' . implode( '</p><p>', $msg ) . '</p></div>';
	if ( $error )
		$message .= '<div class="error">' . implode( '</p><p>', $error ) . '</p></div>';
	return $message;
}

?>
<?php
/**
 * @package Studium_Ausland
 *
 * Database update procedures
 */

$fbk_db_version = get_option( 'fbk_db_version' );

if ( $fbk_db_version == '1.4' ) {
	function fbk_db_update_1_5() {
		global $wpdb;
		$courses = $wpdb->get_results( "SELECT `meta_id`, `cost`, `_future` FROM wp_fbk_courses WHERE `cost` <> ''" );

		$TRANS_types = array( 'mat', 'tuition', 'tuition' );

		foreach ( $courses as $course ) {
			$cost = unserialize( $course->cost );
			if ( $cost && empty($cost['period']) ) {
				$updated = array(
					'period' => isset($cost[1]) && in_array( $cost[1]['calc'], array('ps','stot') ) ? 's' : 'w'
				);

				$to_update = array( $cost );
#				if ( ! empty($course->_future) ) {
#					$future = unserialize( $course->_future );
#					if ( isset( $future['cost'] ) )
#						$to_update[] = $future['cost'];
#				}

				foreach ( $to_update as $cost_index => $_cost ) {
					foreach ( $_cost as $i => $stack ) {
						$stack['type'] = $TRANS_types[$i];

						if ( 0 == $cost_index && empty($stack['to']) && 2 == count($to_update) )
							$stack['to'] = '31.12.2011';
						elseif ( 1 == $cost_index && empty($stack['from']) )
							$stack['from'] = '01.01.2012';

						foreach ( $stack['values'] as $j => $v ) {
							if ( is_string($v) ) {
					    			if ( '' === $v )
					    				unset( $stack['values'][$j] );
					    			elseif ( false === strpos( $v, ',' ) && false === strpos( $v, '.' ) )
					    				$stack['values'][$j] = (int) $v;
					    			else
					    				$stack['values'][$j] = str_replace( ',', '.', $v );
							}
						}
						if ( ! empty( $stack['values'] ) )
							$updated[] = $stack;
					}
				}

				$query = "UPDATE wp_fbk_courses SET `cost` = '" . serialize($updated) . "' WHERE `meta_id` = $course->meta_id";
				$wpdb->query( $query );
			}
		}
		$wpdb->query( 'ALTER TABLE wp_fbk_courses DROP `meta_key`, DROP `disp`, DROP `_future`' );
		$wpdb->query( 'OPTIMIZE TABLE wp_fbk_courses' );

		$accs = $wpdb->get_results( "SELECT `meta_id`, `cost`, `cost-future` FROM wp_fbk_accommodation WHERE `cost` <> ''" );

		foreach ( $accs as $acc ) {
			$cost = unserialize( $acc->cost );
			if ( $cost ) {
				$updated = array();
				$to_update = array( $cost );
				$need_update = false;

#				if ( ! empty( $acc->{'cost-future'} ) ) {
#					$future = unserialize( $acc->{'cost-future'} );
#					if ( ! empty($future) )
#						$to_update[] = $future;
#					$need_update = true;
#				}

				foreach ( $to_update as $cost_index => $_cost ) {
					foreach ( $_cost as $i => $matrix ) {
						if ( 0 == $cost_index && empty($matrix['to']) && 2 == count($to_update) )
							$matrix['to'] = '31.12.2011';
						elseif ( 1 == $cost_index && empty($matrix['from']) )
							$matrix['from'] = '01.01.2012';

						if ( 'cp' == $matrix['calc'] ) {
							$matrix['calc'] = $_cost[0]['calc'];
							$need_update = true;
						}
						if ( ! empty($matrix['values']) )
							$updated[] = $matrix;
					}
				}

				if ( $need_update ) {
					$query = "UPDATE wp_fbk_accommodation SET `cost` = '" . serialize($updated) . "' WHERE `meta_id` = $acc->meta_id";
					$wpdb->query( $query );
				}
			}
		}
		$wpdb->query( 'ALTER TABLE wp_fbk_accommodation DROP `meta_key`, DROP `disp`, DROP `fee-future`, DROP `cost-future`' );
		$wpdb->query( 'OPTIMIZE TABLE wp_fbk_accommodation' );

		$quotes = $wpdb->get_results( "SELECT `quote_id`, `school_id`, `fees` FROM wp_fbk_quotes WHERE `fees` <> ''" );
		foreach ( $quotes as $quote ) {
			$q_fees = unserialize( $quote->fees );
			$s_fees = fbk_get_school_meta( $quote->school_id, 'fees' );
			$fee_ids = array();
			foreach ( $s_fees as $s_f )
				if ( in_array( $s_f['meta_key'], $q_fees ) )
					$fee_ids[] = $s_f['meta_id'];
			if ( $fee_ids )
				$wpdb->query( "UPDATE wp_fbk_quotes SET `fees` = '" . implode( ',', $fee_ids ) . "' WHERE `quote_id` = $quote->quote_id" );
		}

		delete_option( 'fbk_db_version' );
		add_option( 'fbk_db_version', '1.5', null, 'yes' );
	}
	add_action( 'admin_init', 'fbk_db_update_1_5' );

} elseif ( $fbk_db_version == '1.5' ) {
	function fbk_db_update_1_6() {
		global $wpdb;
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}fbk_users` "
		. "ADD `date_added` INT NOT NULL, "
		. "ADD `last_visit` INT NOT NULL, "
		. "ADD `converted` INT(1) NOT NULL, "
		. "ADD `sugar_id` CHAR(36) NOT NULL" );
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}fbk_quotes` "
		. "ADD `date_added` INT NOT NULL, "
		. "ADD `chosen` INT(1) NOT NULL DEFAULT 0" );
		update_option( 'fbk_db_version', '1.6' );
	}
	add_action( 'admin_init', 'fbk_db_update_1_6' );
} elseif ( $fbk_db_version == '1.6' ) {
	function fbk_db_update_1_7() {
		global $wpdb;
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}fbk_quotes` "
		. "ADD `assist_requested` INT(1) NOT NULL DEFAULT 0" );
		update_option( 'fbk_db_version', '1.7' );
	}
	add_action( 'admin_init', 'fbk_db_update_1_7' );
} elseif ( $fbk_db_version == '1.7' ) {
	function fbk_db_update_1_8() {
		$default_settings = array(
			'fbk_slice_label_schule' => 'Übersicht &amp; Kurse',
			'fbk_slice_label_unterkunft' => 'Unterkunft',
			'fbk_slice_label_diverses' => 'Diverses',
			'fbk_slice_label_bilder' => 'Bilder',
			'fbk_fb_pageid' => 'studium.ausland',
			'fbk_analytics_id' => 'UA-6436808-1',
			'fbk_recaptcha_pubkey' => '6LdBPL0SAAAAAHhiog_rl3KHJsIGD8fdlmw8FUrK',
			'fbk_recaptcha_privkey' => '6LdBPL0SAAAAAC2IXKoR3nbUxVp-W65-kPkuLejT',
			'fbk_mail_to' => 'zdenek.cefelin@aupairagentur-cefelin.de, mareike@studium-ausland.eu',
			'fbk_mail_from_quotes' => '"Studium Ausland" <kostenvoranschlag@studium-ausland.eu>',
			'fbk_mail_from_newsletter' => '"Studium-Ausland-Newsletter" <newsletter@studium-ausland.eu>',
			'fbk_mail_from_internal' => 'internal@studium-ausland.eu',
			'fbk_mail_signature' => "Mit freundlichen Grüßen,\n\nIhr Studium-Ausland-Team\n\nStudium Ausland\nKurfürstenstraße 34\n"
				. "10785 Berlin\nhttp://www.studium-ausland.eu\nTel: +49 30 26 39 97 56\nFax: +49 30 26 36 73 62",
			'fbk_quote_access_duration' => 5,
			'fbk_quote_remind_after' => 2,
			'fbk_quote_pdf_header' => 'Studium Ausland | Kurfürstenstr. 34 | 10785 Berlin',
			'fbk_quote_pdf_addrblock' => "Studium Ausland\nKurfürstenstraße 34\n10785 Berlin, Deutschland\n\nTelefon: +49 (30) 26367360\n"
				. "Fax: +49 (89) 26367362\nWeb: www.studium-ausland.eu\nE-Mail: cefelin@studium-ausland.eu",
			'fbk_quote_pdf_disclaimer_euro' => "Unser Angebot ist unverbindlich und ohne Gewähr. Bei uns zahlen Sie keine Vermittlungsprovision. "
				. "Alle Preise sind Originalpreise des Anbieters.\n\nMit freundlichen Grüßen,\nZdenek Cefelin",
			'fbk_quote_pdf_disclaimer_foreign' => "Unser Angebot ist unverbindlich und ohne Gewähr. Bei uns zahlen Sie keine Vermittlungsprovision. "
				. "Alle Preise sind Originalpreise des Anbieters. Einzige Ausnahme ist der zum aktuellen Tageskurs umgerechnete Euro-Preis,"
				. " der lediglich zu Ihrer Orientierung dient. Verbindlich sind nur die Angaben in der angegebenen Originalwährung."
				. "\n\nMit freundlichen Grüßen,\nZdenek Cefelin",
			'fbk_quote_pdf_footer' => "Agentur Studium Ausland  |  Inhaber: Zdenek Cefelin  |  Kurfürstenstr. 34  |  10785 Berlin\n"
				. "Telefon: +49 (30) 26367360  |  Fax: +49 (89) 26367362  |  www.studium-ausland.eu  |  cefelin@studium-ausland.eu",
			'fbk_sugar_url' => 'http://www.aupairagentur-cefelin.de/sugar/aupair/index.php',
			'fbk_sugar_campaign' => '129ade59-31e1-1e03-809c-4d7e355a97c6',
			'fbk_sugar_user' => '812588fb-1a21-e0ec-cf67-4bd000ffa1e5',
			'fbk_sugar_db_host' => 'rdbms.strato.de',
			'fbk_sugar_db_name' => 'DB839702',
			'fbk_sugar_db_username' => 'U839702',
			'fbk_sugar_db_password' => 'PW839702'
		);
		foreach ( $default_settings as $key => $value ) {
			update_option( $key, $value );
		}
		update_option( 'fbk_db_version', '1.8' );
	}
	add_action( 'init', 'fbk_db_update_1_8' );
} elseif ( $fbk_db_version == '1.8' ) {
	add_action( 'init', 'fbk_rebuild_navmenus' );
	update_option( 'fbk_db_version', '1.9' );
} elseif ( $fbk_db_version == '1.9' ) {
	add_action( 'init', 'fbk_db_update_1_10' );
	function fbk_db_update_1_10() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'termmeta';
		$sql = "CREATE TABLE " . $table_name . " (
		  meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  term_id bigint(20) unsigned NOT NULL DEFAULT '0',
		  meta_key varchar(255) DEFAULT NULL,
		  meta_value longtext,
		  PRIMARY KEY (meta_id),
		  KEY term_id (term_id),
		  KEY meta_key (meta_key)	  
		);";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		update_option( 'fbk_db_version', '1.10' );
	}
}

if ( $fbk_db_version == '1.10' && ! function_exists( 'fbk_compress_css' ) ) {
	function fbk_compress_css( $in ) { return $in; }
	function fbk_resync_external_menu(){}
	define( 'FBK_COLORS_CSS_FILE', 'colors.generated.css' );
	$GLOBALS['fbk_category_nicernames'] = array( // Back compat
		'sprachkurs' => 'Sprachschulen',
		'50plus' => 'Sprachschulen',
		'junior' => 'Jugendcamps',
		'praktikum' => 'Praktikumsanbieter',
		'highschool' => 'Highschools',
		'uni' => 'Colleges'
	);
}

if ( $fbk_db_version == '1.10' ) {
	add_action( 'init', 'fbk_db_update_1_11' );
	function fbk_db_update_1_11() {
		$default_settings = array(
			'fbk_quote_present_subject' => 'Ihr Kostenvoranschlag | Studium Ausland',
			'fbk_quote_present_text' => "Sehr geehrte{masculine_r} {salutation} {last_name},"
				 . "\n\nVielen Dank für Ihr Interesse! Ihr Kostenvoranschlag ist unter der Adresse"
				 . "\n\n{url}\n\n"
				 . "online verfügbar. Wenn Sie möchten, können Sie ihn auch als PDF herunterladen und ausdrucken, den Link dazu"
				 . " finden Sie beim Online-Kostenvoranschlag.\n\n"
				 . "Beachten Sie bitte, dass der Zugangscode aus Sicherheitsgründen nach {access_duration}"
				 . " Tagen verfällt. Sie können Ihn danach selbstverständlich verlängern, aber wenn Sie die Kostenvoranschläge"
				 . " länger benötigen, ist es sicher bequemer, die PDF-Dateien herunterzuladen.\n\n"
				 . "Wir hoffen, dass Ihnen der Kostenvoranschlag eine gute Entscheidungsgrundlage bietet. Bei Fragen wenden Sie sich"
				 . " gerne an uns!",
			'fbk_quote_refresh_subject' => 'Neuer Zugangscode | Studium Ausland',
			'fbk_quote_refresh_text' => "Sehr geehrte{masculine_r} {salutation} {last_name},"
				 . "\n\nIhr Zugangscode wurde eneuert. Sie erreichen Ihre Kostenvoranschläge nun unter der"
				 . " folgenden Adresse:\n\n{url}\n\n"
				 . "Beachten Sie bitte, dass der Zugangscode aus Sicherheitsgründen nach {access_duration}"
				 . " Tagen verfällt. Falls Sie die Kostenvoranschläge länger benötigen, laden Sie am besten die PDF-Dateien herunter.",
			'fbk_quote_reminder_subject' => 'Ihr Kostenvoranschlag - Zur Erinnerung | Studium Ausland',
			'fbk_quote_reminder_text_multi' => "Sehr geehrte{masculine_r} {salutation} {last_name},"
				 . "\n\nSie haben bei uns vor {remind_after} Tagen einige Kostenvoranschläge beantragt. "
				 . "Wir hoffen, dass diese Ihnen eine gute Entscheidungsgrundlage gegeben haben. Übrigens: Wenn"
				 . " Sie sich für ein Programm entschieden haben oder eine persönliche Beratung wünschen, "
				 . "klicken Sie einfach auf den leuchtend gelben Knopf unterhalb eines Kostenvoranschlags, "
				 . "um mit uns in Kontakt zu treten oder uns mit der Buchung zu beauftragen!"
				 . "\n\nZur Erinnerung: Ihre gesammelten Kostenvoranschläge finden Sie unter der Adresse <{url}>. "
				 . "Bitte steigen Sie auch über diese Adresse in die Webseite ein, wenn Sie weitere Programme hinzufügen möchten.",
			'fbk_quote_reminder_text_single' => "Sehr geehrte{masculine_r} {salutation} {last_name},"
				 . "\n\nSie haben bei uns vor {remind_after} Tagen einen Kostenvoranschlag beantragt. "
				 . "Wir hoffen, dass dieser Ihnen eine gute Entscheidungsgrundlage gegeben hat. Übrigens: Falls Sie an weiteren Programmen interessiert sind, "
				 . "fügen Sie diese auf unserer Webseite als weitere Kostenvoranschläge hinzu. Und wenn"
				 . " Sie sich für ein Programm entschieden haben oder eine persönliche Beratung wünschen, "
				 . "klicken Sie einfach auf den leuchtend gelben Knopf unterhalb eines Kostenvoranschlags, "
				 . "um mit uns in Kontakt zu treten oder uns mit der Buchung zu beauftragen!"
				 . "\n\nZur Erinnerung: Ihre gesammelten Kostenvoranschläge finden Sie unter der Adresse <{url}>. "
				 . "Bitte steigen Sie auch über diese Adresse in die Webseite ein, wenn Sie weitere Programme hinzufügen möchten."
		);
		foreach ( $default_settings as $key => $value ) {
			update_option( $key, $value );
		}

		global $wpdb;
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}fbk_users` ADD `parent_user` INT NOT NULL" );
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}fbk_courses` ADD `hidden` INT(1) NOT NULL" );
		$wpdb->query( "ALTER TABLE `{$wpdb->prefix}fbk_accommodation` ADD `hidden` INT(1) NOT NULL" );

		update_option( 'fbk_db_version', '1.11' );
	}
} elseif ( '1.11' == $fbk_db_version ) {
	add_action( 'init', 'fbk_db_update_1_12' );
	function fbk_db_update_1_12() {
		global $wpdb, $fbk_cf_prefix;
		$defaults = array(
			'eur' => '€',
			'gbp' => '£',
			'usd' => 'US$',
			'cad' => 'CAD',
			'aud' => 'AU$',
			'nzd' => 'NZ$',
			'cny' => 'Renminbi',
			'jpy' => 'Yen',
			'chf' => 'CHF'
		);
		foreach ( $defaults as $slug => $name ) {
			wp_insert_term( $name, 'currency', compact( 'slug' ) );
		}

		$internals = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$fbk_cf_prefix}internal' AND meta_value <> '' " );
		foreach ( $internals as $int ) {
			$meta_value = unserialize( $int->meta_value );
			if ( empty($meta_value['curr']) )
				continue;
			wp_set_object_terms( $int->post_id, strtolower($meta_value['curr']), 'currency' );
		}

		update_option( 'fbk_db_version', '1.12' );
	}
} elseif ( '1.12' == $fbk_db_version ) {
	add_action( 'init', 'fbk_db_update_1_13' );
	function fbk_db_update_1_13() {
		global $wpdb;
		$wpdb->query( 'ALTER TABLE wp_fbk_users DROP COLUMN sugar_id' );
		$obsolete_options = array(
			'fbk_sugar_url',
			'fbk_sugar_campaign',
			'fbk_sugar_user',
			'fbk_sugar_db_host',
			'fbk_sugar_db_name',
			'fbk_sugar_db_username',
			'fbk_sugar_db_password'
		);
		foreach ( $obsolete_options as $opt )
			delete_option( $opt );
		update_option( 'fbk_db_version', '1.13' );
	}
}
?>
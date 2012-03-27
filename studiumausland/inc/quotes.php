<?php
/**
 * @package Studium_Ausland
 */

class FBK_Quotes {

	const U_NO_SUCH_USER = 1;
	const U_INVALID_ACCESS_KEY = 2;
	const U_ACCESS_KEY_EXPIRED = 4;

	const USER_TABLE = 'wp_fbk_users';
	const QUOTES_TABLE = 'wp_fbk_quotes';
	private $user_formats = array(
		'user_id' => '%d',
		'access_key' => '%s',
		'salutation' => '%s',
		'first_name' => '%s',
		'last_name' => '%s',
		'email' => '%s',
		'phone' => '%s',
		'contact_method' => '%s',
		'street' => '%s',
		'postalcode' => '%s',
		'city' => '%s',
		'state' => '%s',
		'country' => '%s',
		'nationality' => '%s',
		'birthdate' => '%s',
		'job' => '%s',
		'lang_level' => '%d',
		'experience' => '%s',
		'comments' => '%s',
		'newsletter' => '%d',
		'date_added' => '%d',
		'last_visit' => '%d',
		'converted' => '%d',
		'parent_user' => '%d'
	);
	private $quote_formats = array(
		'quote_id' => '%d',
		'user_id' => '%d',
		'school_id' => '%d',
		'course_id' => '%d',
		'acc_id' => '%d',
		'room' => '%s',
		'board' => '%s',
		'fees' => '%s',
		'start' => '%s',
		'start_raw' => '%s',
		'duration' => '%d',
		'date_added' => '%d',
		'chosen' => '%d',
		'assist_requested' => '%d'
	);

	private $r_axis = array( 's' => 'Einzelzimmer', 'd' => 'Doppelzimmer', 't' => 'Zweibettzimmer', 'm' => 'Mehrbettzimmer' );
	private $b_axis = array( 'sc' => 'ohne Verpflegung', 'br' => 'Frühstück', 'hb' => 'Halbpension', 'fb' => 'Vollpension' );

	private $per_week = 0;

	function __construct() {
		add_action( 'fbk_quote_reminder_cron', array( &$this, 'send_reminder' ) );
		add_action( 'fbk_quote_push', array( &$this, 'push_async_hook' ) );
	}

	function create_new( $data ) {
		$user = $this->add_user( $data );

		if ( isset($data['manual_submit']) ) {
			$this->push_to_manual( $user, $data );
		} elseif ( 'yes' == get_option( 'fbk_quote_noauto' ) ) {
			$quote = $this->add_quote( $data, $user->user_id );
			$this->push( $user, $quote );
		} else {
			$quote = $this->add_quote( $data, $user->user_id );
			
			$replace = $this->get_replacement_token_array( compact( 'user', 'quote' ) );

			$subject = str_ireplace( array_keys($replace), $replace, stripslashes(get_option( 'fbk_quote_present_subject' )) );
			$body = str_ireplace( array_keys($replace), $replace, stripslashes(get_option( 'fbk_quote_present_text' )) );

			mail(
				'"=?UTF-8?B?' . base64_encode($user->first_name.' '.$user->last_name) . '?=" <' . $user->email . '>',
				'=?UTF-8?B?' . base64_encode( $subject ) . '?=',
				chunk_split(base64_encode( $body . "\n\n" . stripslashes(get_option( 'fbk_mail_signature' )) )),
				'From: ' . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_quotes' )))
				 . PHP_EOL . 'Content-Transfer-Encoding: base64'
				 . PHP_EOL . 'Content-Type: text/plain; charset="utf-8"'
			);
			if ( ! $quote->is_automatable ) {
				$this->request_manual_assist( $user, $quote );
			}
		}

		return $user;
	}

	function add_user( $data ) {
		global $wpdb;

		foreach ( $this->user_formats as $field => $format ) {
			$user_data[$field] = empty($data[$field]) ? '' : $data[$field];
			$formats[] = $format;
		}
		$user_data['newsletter'] = empty( $data['newsletter'] ) ? 0 : 1;


		// Find 'parent' user in case any mostly-similar user entry already exists

		$parent_user_query = "SELECT * FROM " . self::USER_TABLE
		. " WHERE parent_user = '' AND LOWER(email) = %s AND LOWER(first_name) = %s AND LOWER(last_name) = %s";
		$parent_user = $wpdb->get_results( $wpdb->prepare(
			$parent_user_query,
			strtolower( $user_data['email'] ),
			strtolower( $user_data['first_name'] ),
			strtolower( $user_data['last_name'] )
		));
		if ( $parent_user && ! is_wp_error( $parent_user ) ) {
			if ( 1 == count($parent_user) ) {
				$parent = $parent_user[0];
			} else { // User has unconsolidated duplicates from pre-1.13
				$parent_prio = array();
				foreach ( $parent_user as $i => $_p ) {
					if ( $_p->converted ) {
						$parent = $_p;
						break;
					}
					$parent_prio[$i] =
						str_pad( strlen( $_p->street . $_p->city . $_p->postalcode . $_p->country . $_p->nationality ), 5, '0', STR_PAD_LEFT )
						. $_p->date_added;
				}
				if ( empty($parent) ) {
					asort( $parent_prio );
					end( $parent_prio );
					$parent = $parent_user[ key($parent_prio) ];
				}
				// Update the non-primary parents to be siblings instead
				foreach ( $parent_user as $_p ) {
					if ( $_p->user_id != $parent->user_id ) {
						$this->update_user( $_p, array( 'parent_user' => $parent->user_id ), null, false );
						$_q = $this->get_quotes( $_p->user_id );
						if ( $_q )
							foreach ( $_q as $quote )
								$this->update_quote( $quote, array( 'user_id' => $parent->user_id ) );
					}
				}
			}

			// update the (primary) parent's info if possible/necessary
			$update = array();
			foreach ( array( 'street', 'postalcode', 'city', 'country', 'nationality', 'job', 'lang_level' ) as $field )
				if ( empty( $parent->$field ) && ! empty( $user_data[$field] ) )
					$update[$field] = $user_data[$field];
			foreach ( array( 'experience', 'comments' ) as $field ) {
				if ( ! empty($user_data[$field]) && $parent->$field )
					$update[$field] = $parent->$field . "\n\n" . $user_data[$field];
				elseif ( ! empty($user_data[$field]) )
					$update[$field] = $user_data[$field];
			}

			if ( $update )
				$parent = $this->update_user( $parent, $update, null, false );

			// Reduce data set to a minimum
			$user_data = array( 'parent_user' => $parent->user_id );
		}

		$table_status = $wpdb->get_row( "SHOW TABLE STATUS LIKE '" . self::USER_TABLE . "'" );
		$_user_id = $table_status->Auto_increment;
		$inserted = time();
		$user_data['date_added'] = $inserted;
		$user_data['access_key'] = base_convert( $inserted * $_user_id, 10, 36 );

		$wpdb->insert( self::USER_TABLE, $user_data, $formats );
		$user_id = $wpdb->insert_id;

		if ( $user_id != $_user_id ) {
			$user_data['access_key'] = base_convert( $inserted * $user_id, 10, 36 );
			$wpdb->update(
				self::USER_TABLE,
				array( 'access_key' => $user_data['access_key'] ),
				array( 'user_id' => $user_id ),
				'%s',
				'%d'
			);
		}
		$user_data['user_id'] = $user_id;

		if ( isset($parent) ) {
			$user = $this->sanitize_user( $parent, $user_id, $user_data['access_key'] );
		} else {
			$user = $this->sanitize_user( $user_data );
			if ( 'yes' != get_option( 'fbk_quote_noauto' ) )
				wp_schedule_single_event( $inserted + ( get_option( 'fbk_quote_remind_after' ) * 86400 ), 'fbk_quote_reminder_cron', array( $user_id ) );
		}

		return $user;
	}

	function add_quote( $data, $user_id ) {
		global $wpdb;
		if ( ! empty($data['fees']) )
			foreach ( $data['fees'] as $fee_key )
				$data['_fees'][] = preg_replace( '/^f-/', '', $fee_key );
		$quote = array(
			'user_id' => $user_id,
			'school_id' => (int) $data['school'],
			'course_id' => (int) preg_replace( '/^c-/', '', $data['course'] ),
			'acc_id' => (int) preg_replace( '/^a-/', '', $data['acc'] ),
			'room' => ! empty($data['acc']) ? $data['room'] : '',
			'board' => ! empty($data['acc']) ? $data['board'] : '',
			'fees' => ! empty($data['fees']) ? implode( ',', $data['_fees'] ) : '',
			'start' => $this->get_monday_near( $data['start'] ),
			'start_raw' => $data['start'],
			'duration' => ( (int) $data['duration'] ) + 1,
			'date_added' => time()
		);
		$format = array();
		foreach ( array_keys( $quote ) as $key )
			$format[] = $this->quote_formats[$key];
		$wpdb->insert( self::QUOTES_TABLE, $quote, $format );
		$quote['quote_id'] = $wpdb->insert_id;
		return $this->sanitize_quote( $quote, true );
	}

	function get_quote_url( $user ) {
		if ( is_int( $user ) )
			$user = $this->get_user( $user );
		if ( ! $user || ! is_object( $user ) )
			return '';
		return home_url( "/quote?u=$user->_queried_id&q=$user->_queried_access_key" );
	}

	function update_user( $user, $new_data = array(), $allow_access_key_change = false, $is_visit = true ) {
		global $wpdb;
		$data = $formats = array();

		if ( is_object( $user ) )
			$user_id = $user->user_id;
		else
			$user_id = (int) $user;

		if ( $is_visit )
			$new_data['last_visit'] = time();

		foreach ( $new_data as $key => $value ) {
			if ( array_key_exists( $key, $this->user_formats ) && 'user_id' != $key && ( 'access_key' != $key || $allow_access_key_change ) ) {
				$data[$key] = $value;
				$formats[$key] = $this->user_formats[$key];
			}
		}

		$wpdb->update( self::USER_TABLE, $data, array( 'user_id' => $user_id ), $formats, '%d' );

		if ( is_object( $user ) )
			foreach ( $data as $key => $value )
				$user->$key = $value;
		else
			$user = $this->get_user( $user_id );

		return $user;
	}

	function update_quote( $quote, $new_data ) {
		global $wpdb;
		$data = $formats = array();
		if ( ! isset( $quote->quote_id ) )
			return false;
		foreach ( $new_data as $key => $value ) {
			if ( array_key_exists( $key, $this->quote_formats ) && 'quote_id' != $key ) {
				$data[$key] = $value;
				$formats[$key] = $this->quote_formats[$key];
			}
		}
		if ( $data ) {
			$wpdb->update( self::QUOTES_TABLE, $data, array( 'quote_id' => $quote->quote_id ), $formats, '%d' );
			return true;
		}
		return false;
	}

	private function get_monday_near( $date ) {
		if ( ! empty( $date ) ) {
			try {
				$_start = new DateTime( $date );
				if ( $_start->format( 'N' ) > 5 ) // Saturday or Sunday
					$_start->modify( 'next Monday' );
				elseif ( $_start->format( 'N' ) > 1 ) // Tuesday to Friday
					$_start->modify( 'last Monday' );
				$start = $_start->format( 'd.m.Y' );
			} catch ( Exception $e ) {
				$start = '';
			}
		} else {
			$start = '';
		}

		return $start;
	}

	function push_async( $user_id, $quote_id ) {
		wp_schedule_single_event( time(), 'fbk_quote_push', array( $user_id, $quote_id ) );
		spawn_cron();
	}

	function push_async_hook( $user_id, $quote_id ) {
		$user = $this->get_user( $user_id );
		$quote = $this->get_quote( $quote_id );
		$this->push( $user, $quote );
	}

	function push( $user, $quote, $force_resubmit = false ) {
		if ( ! is_object( $quote ) )
			$quote = $this->get_quote( $quote, $user->user_id );

		if ( $user->converted ) {
			if ( ! $quote->chosen )
				$this->update_quote( $quote, array( 'chosen' => 1 ) );
			elseif ( ! $force_resubmit ) // Don't double submit
				return false;
		}

		if ( 'yes' == get_option( 'fbk_quote_noauto' ) )
			$tagline = "Beantragter Kostenvoranschlag";
		else
			$tagline = "Ausgewählter Kostenvoranschlag";

		$mailtext = "
--------------------------------------------
$tagline
--------------------------------------------

";
		$mailtext .= $this->get_user_mailtext( $user ) . "\n\n---------------------\n\n" . $this->get_quote_mailtext( $quote );

		$pdf = $this->get_quote_pdf( $quote, $user, false );

		$parts = array(
			array(
				'type' => 'body',
				'content' => $mailtext
			),
			array(
				'type' => 'application/octet-stream',
				'filename' => 'Kostenvoranschlag_' . utf8_decode( $user->last_name . '_' . $user->first_name ) . '.pdf',
				'content' => $pdf->Output('', 'S')
			),
			array(
				'type' => 'text/csv',
				'filename' => 'Datensatz_' . utf8_decode( $user->last_name . '_' . $user->first_name ) . '.csv',
				'content' => $this->get_csv( $user, $quote )
			)
		);
		list( $headers, $message ) = $this->get_multipart_mail( $parts );
		$headers .= PHP_EOL . "From: " . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_internal' )));

		mail(
			q_encode_angle_address(stripslashes(get_option( 'fbk_mail_to' ))),
			'Anfrage Studium-Ausland, =?UTF-8?B?' . base64_encode( $user->first_name . ' ' . $user->last_name ) . '?=',
			$message,
			$headers
		);
	}

	function push_to_manual( $user, $data ) {
		$mailtext = "
-------------------------------------------------------------------------
Nicht-automatische Bearbeitung - Kunde hat noch keinen Kostenvoranschlag!
-------------------------------------------------------------------------

";
		$mailtext .= $this->get_user_mailtext( $user ) . "\n\n------------------------";
		$translate = array(
			'school_name' => 'Schule:',
			'course_name' => 'Kurs:',
			'accommodation_name' => 'Unterkunft:',
			'accommodation_room' => 'Zimmer:',
			'accommodation_board' => 'Verpflegung:',
			'course_start' => 'Kursbeginn:',
			'course_duration' => 'Kursdauer:'
		);
		$v_translate = array(
			'accommodation_room' => $this->r_axis,
			'accommodation_board' => $this->b_axis
		);

		foreach ( $translate as $field_name => $label ) {
			$value = $data[$field_name];
			if ( array_key_exists( $field_name, $v_translate ) )
				$value = $v_translate[$field_name][$value];
			$mailtext .= "\n\n$label\t$value";
		}

		$_data = array(
			'duration' => $data['course_duration'],
			'start' => $this->get_monday_near( $data['course_start'] ),
		);

		$parts = array(
			array(
				'type' => 'body',
				'content' => $mailtext
			),
			array(
				'type' => 'text/csv',
				'filename' => 'Datensatz_' . utf8_decode( $user->last_name . '_' . $user->first_name ) . '.csv',
				'content' => $this->get_csv( $user, $_data )
			)	
		);
		list( $headers, $message ) = $this->get_multipart_mail( $parts );
		$headers .= PHP_EOL . "From: " . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_internal' )));

		mail(
			q_encode_angle_address(stripslashes(get_option( 'fbk_mail_to' ))),
			'Anfrage Studium-Ausland, =?UTF-8?B?' . base64_encode( $user->first_name . ' ' . $user->last_name ) . '?=',
			$message,
			$headers
		);
	}

	function request_manual_assist( $user, $quote ) {
		if ( $quote->assist_requested )
			return false;

		$mailtext = "
--------------------------------------------------------------------------------------
Automatische Bearbeitung - Kunde hat einen unvollständigen Kostenvoranschlag erhalten!
--------------------------------------------------------------------------------------
Betrachte den Kostenvoranschlag unter <"
 . admin_url( "/tools.php?page_type=single&page=quote_list&user_id=$quote->user_id&quote_id=$quote->quote_id" ) . ">.

";
		$mailtext .= $this->get_user_mailtext( $user )
		 . "\n\n------------------------\n\n"
		 . $this->get_quote_mailtext( $quote );

		$mailtext .= "\n\nDer automatisch generierte Kostenvoranschlag enthält nicht automatisch verarbeitbare Zeilen"
		. " mit Verweis auf baldige manuelle Bearbeitung. Bitte melde dich bald zurück!";

		$parts = array(
			array(
				'type' => 'body',
				'content' => $mailtext
			),
			array(
				'type' => 'text/csv',
				'filename' => 'Datensatz_' . utf8_decode( $user->last_name . '_' . $user->first_name ) . '.csv',
				'content' => $this->get_csv( $user, $quote )
			)
		);
		list( $headers, $message ) = $this->get_multipart_mail( $parts );
		$headers .= PHP_EOL . 'From: ' . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_internal' )));

		mail(
			q_encode_angle_address(stripslashes(get_option( 'fbk_mail_to' ))),
			'=?UTF-8?B?' . base64_encode( "Unvollständiger Kostenvoranschlag Studium-Ausland, $user->first_name $user->last_name" ) . '?=',
			$message,
			$headers
		);

		$this->update_quote( $quote, array( 'assist_requested' => 1 ) );
	}

	private function get_multipart_mail( $parts ) {
		$mime_boundary = "==Multipart_Boundary_" . md5(time());

		$headers = 'MIME-Version: 1.0'
		. PHP_EOL . 'Content-Type: multipart/mixed; boundary="' . $mime_boundary . '"';

		$message = "";
		foreach ( $parts as $part ) {
			$message .= "--$mime_boundary";
			if ( 'body' == $part['type'] ) {
				$message .= PHP_EOL . 'Content-Type: text/plain; charset="utf-8"';
			} else {
				$message .= PHP_EOL . 'Content-Type: ' . $part['type'] . '; name="' . $part['filename'] . '"'
				. PHP_EOL . 'Content-Disposition: attachment; filename="' . $part['filename'] . '"';
			}
			$message .= PHP_EOL . 'Content-Transfer-Encoding: base64'
			. PHP_EOL . PHP_EOL . chunk_split(base64_encode( $part['content'] )) . PHP_EOL;
		}

		return array( $headers, $message );
	}

	private function get_csv( $user, $quote ) {
		if ( is_array( $user ) )
			$user = (object) $user;
		if ( is_array( $quote ) )
			$quote = (object) $quote;
		@$data = array(
			'Anrede' => $user->salutation,
			'Vorname' => $user->first_name,
			'Nachname' => $user->last_name,
			'E-Mail' => $user->email,
			'Telefon' => $user->phone,
			'Adresse 1' => $user->street,
			'PLZ' => $user->postalcode,
			'Stadt' => $user->city,
			'Land' => $user->country,
			'Nationalität' => $user->nationality,
			'Geburtsdatum' => $user->birthdate,
			'Wunschstart' => $quote->start,
			'Aufenthaltsdauer' => $quote->duration,
			'Programm' => 'Sprachkurs',
			'Status' => 'Neu'
		);
		$csv = '"' . implode( '","', array_keys($data) ) . '"'
		. "\r\n\"" . implode( '","', $data ) . '"';
		return iconv( 'UTF-8', 'CP1252//TRANSLIT', $csv );
	}

	private function get_user_mailtext( $user ) {
		$translate = array(
			'salutation' => "Anrede:",
			'first_name' => "Vorname:",
			'last_name' => "Nachname:",
			'email' => "E-Mail:",
			'phone' => "Telefon:",
			'contact_method' => "Kontaktmethode:",
			'street' => "Straße:",
			'postalcode' => "PLZ:",
			'city' => "Ort:",
			'state' => "Bundesland:",
			'country' => "Land:",
			'nationality' => "Nationalität:",
			'birthdate' => "Geburtsdatum:",
			'job' => "Beruf:",
			'lang_level' => "Sprachniveau (0-5):",
			'experience' => "Bisherige Erfahrungen:\n",
			'comments' => "Bemerkungen:\n"
		);
		$text = '';
		foreach ( $translate as $key => $label )
			if ( isset($user->$key) && '' !== $user->$key )
				$text .= $label . "\t" . $user->$key . "\n\n";
		return $text;
	}

	private function get_quote_mailtext( $quote ) {
		$strings = array();
		$school = get_post( $quote->school_id );
		$school_meta = fbk_get_school_meta( $quote->school_id );
		$strings['school'] = $school->post_title . ' <' . get_permalink( $school->ID ) . '>';
		foreach( $school_meta['courses'] as $_course )
			if ( $_course['meta_id'] == $quote->course_id ) {
				$strings['course'] = $_course['name'];
				$strings['duration'] = $quote->duration
				 . ( ! empty($_course['cost']) && in_array( $_course['cost'][1]['calc'], array( 'ps', 'stot' ) ) ? " Semester" : " Woche(n)" );
				break;
			}

		if ( $quote->acc_id )
			foreach ( $school_meta['accommodation'] as $_acc )
				if ( $_acc['meta_id'] == $quote->acc_id ) {
					$strings['acc'] = $_acc['name'];
					if ( $quote->room && $quote->board ) {
						$strings['acc'] .= ', '
						. $this->r_axis[$quote->room] . ', '
						. $this->b_axis[$quote->board];
					}
					break;
				}

		if ( $quote->fees ) {
			foreach ( $school_meta['fees'] as $fee )
				if ( in_array( $fee['meta_id'], $quote->fees ) )
					@$strings['fees'] .= "\n- " . $fee['key'] . ($fee['desc'] ? " ($fee[desc])" : "");
		}

		$strings['start'] = $quote->start . ( $quote->start != $quote->start_raw ? " (Orig. $quote->start_raw)" : "" );

		$translate = array(
			'school' => "Schule:",
			'course' => "Kurs:",
			'acc' => "Unterkunft:",
			'fees' => "Weitere Leistungen:",
			'start' => "Kursbeginn:",
			'duration' => "Kursdauer:"
		);
		$text = '';
		foreach ( $translate as $key => $label )
			if ( isset($strings[$key]) )
				$text .= $label . "\t" . $strings[$key] . "\n\n";
		return $text;
	}

	function get_quote( $quote_id, $user_id = false ) {
		global $wpdb;
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::QUOTES_TABLE . " WHERE quote_id = %d", $quote_id ) );
		if ( ! $quote || $user_id && $quote->user_id != $user_id )
			return false;
		else
			return $this->sanitize_quote( $quote );
	}

	function get_quotes( $user_id ) {
		global $wpdb;
		$quotes = array();
		$_quotes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::QUOTES_TABLE . " WHERE user_id = %d ORDER BY date_added", $user_id ) );
		foreach ( $_quotes as $_quote )
			$quotes[ $_quote->quote_id ] = $this->sanitize_quote( $_quote );
		return $quotes;
	}

	private function sanitize_quote( $quote, $check_automatability = false ) {
		if ( is_array( $quote ) )
			$quote = (object) $quote;
		if ( $quote->fees && is_string( $quote->fees ) )
			$quote->fees = explode( ',', $quote->fees );
		foreach ( $this->quote_formats as $key => $format ) {
			if ( '%d' == $format )
				$quote->$key = (int) $quote->$key;
		}

		if ( $check_automatability ) {
			$quote->is_automatable = true;
			if ( ! empty($quote->course_id) ) {
				$courses = fbk_get_school_meta( $quote->school_id, 'courses' );
				if ( empty($courses[$quote->course_id]['cost']) )
					$quote->is_automatable = false;
			}
			if ( $quote->is_automatable && ! empty($quote->acc_id) ) {
				$accs = fbk_get_school_meta( $quote->school_id, 'accommodation' );
				if ( empty($accs[$quote->acc_id]['cost']) )
					$quote->is_automatable = false;
			}
		}

		return $quote;
	}

	function get_quote_html( $user, $quote = 0, $with_title = true, $foldout_wrapped = true ) {
		global $wpdb;

		if ( is_array( $user ) ) {
			$user_id = $user['user_id'];
			$user = (object) $user;
		} elseif ( is_object( $user ) ) {
			$user_id = $user->user_id;
		} else {
			$user_id = (int) $user;
			$user = $this->get_user( $user_id );
			if ( ! $user )
				return false;
		}

		if ( is_object( $quote ) ) {
			$quote_id = $quote->quote_id;
			$quotes = array( $quote );
		} else {
			$quote_id = (int) $quote;
			$quotes = $this->get_quotes( $user_id );
			if ( $quote_id )
				$quotes = array( $quotes[$quote_id] );
		}

		if ( $with_title )
			echo '<h1>' . ( count($quotes)>1 ? 'Kostenvoranschläge' : 'Kostenvoranschlag' ) . " für $user->first_name $user->last_name</h1>";

		foreach ( $quotes as $quote ) {
			$quote_data = $this->get_quote_data( $quote );
			$numcols = count($quote_data['column_headings']);

			if ( is_admin() )
				$pdf_url = admin_url( "/tools.php?page=quote_list&amp;user_id=$user->user_id&amp;quote_id=$quote->quote_id&amp;type=pdf" );
			else
				$pdf_url = $this->get_quote_url( $user ) . "&amp;pdf=$quote->quote_id";

			echo "<section id='quote-$quote->quote_id' class='quote foldout c-" . $quote_data['category']->slug . "'>";
			if ( $foldout_wrapped )
				echo "<header><h2>" . strtok( $quote_data['header'], "\n" ) . "</h2></header><div class='foldout-outer'><div class='foldout-inner'>";
			echo "<table class='quote'>"
			. "<thead><tr><th colspan='$numcols'>"
			 . "<a class='pdf-quote' href='$pdf_url' target='_blank'>Als PDF »</a>"
			 . str_replace( "\n", "<br>", $quote_data['header'] )
			. "</th></tr>"
			. "<tr>";
			foreach ( $quote_data['column_headings'] as $col_heading )
				echo "<th>$col_heading</th>";
			echo "</tr></thead>";
			foreach ( array( 'course' => 'Kurs', 'acc' => 'Unterkunft', 'fees' => 'Weitere Leistungen', 'total' => 'Gesamt' ) as $tbody => $heading ) {
				if ( isset( $quote_data[$tbody] ) ) {
					echo "<tbody class='quote-$tbody'>";
					if ( $heading )
						echo "<tr><th colspan='$numcols' scope='rowgroup'>$heading</th></tr>";
					foreach ( $quote_data[$tbody] as $row ) {
						echo "<tr>";
						if ( 'error' == $row['type'] ) {
							echo "<th class='label error' colspan='$numcols'>$row[label]</th>";
						} else {
							foreach ( array_keys($quote_data['column_headings']) as $col ) {
								if ( 'label' == $col )
									echo "<th class='$col'>";
								else
									echo "<td class='$col'>";

								if ( empty($row[$col]) )
									echo isset($row[$col]) && 0 === $row[$col] ? '&ndash;' : '&nbsp;';
								elseif ( is_int($row[$col]) )
									echo $row[$col] . ',&ndash;';
								elseif ( is_float($row[$col]) )
									echo number_format( $row[$col], 2, ',', ' ' );
								else
									echo $row[$col];

								echo 'label' == $col ? "</th>" : "</td>";
							}
						}
						echo "</tr>";
					}
					echo "</tbody>";
				}
			}
			echo "<tfoot>";
			if ( array_key_exists( 'cash', $quote_data['column_headings'] ) )
				echo "<tr><td colspan='$numcols'>* Diese Beträge werden nicht in Rechnung gestellt, sondern sind nach Bedarf vor Ort zu bezahlen.</td></tr>";
			echo "<tr><td colspan='$numcols'><a href='$pdf_url' target='_blank'>Diesen Kostenvoranschlag als PDF ansehen, herunterladen oder ausdrucken »</a></td></tr>"
			. "</tfoot></table>";
			if ( $foldout_wrapped ) {
				if ( $quote->chosen )
					echo "<p class='ybox'>Sie haben zu diesem Kostenvoranschlag bereits eine Anfrage verschickt.</p>";
				else
					echo "<div class='quote-pick'><input type='button' value='Persönliche Beratung zu diesem Programm anfordern &raquo;' class='floatright open-push-quote'></div>";
				echo "</div></div>";
			}
			echo "</section>";
		}
	}

	function get_quote_pdf( $quote, $user, $output_to_browser = true ) {
		$quote_data = $this->get_quote_data( $quote );
		require( FBK_INC_DIR . '/pdf.php' );
		$pdf = new Quote_PDF();
		$pdf->generate_quote(
			$quote_data,
			$user,
			fbk_tt_get_currency( $quote->school_id )
		);
		if ( $output_to_browser )
			$pdf->Output(
				preg_replace( array('/\\s/', '/[^a-zA-Z0-9._-]/'), array('_', ''), strtok( $quote_data['header'], "\n" ) ) . '.pdf',
				'I'
			);
		else
			return $pdf;
	}

/**
 * @return array(
	'course' => quote,
	'acc' => quote,
	'fees' => quote,
	'total' => quote,
	'header' => string,
	'column_headings' => array( strings ),
	'category' => object (WP term)
   )
 * 	quote: array( array(
 *		'label' => string,	-- label
 *		'type' => string	-- 'item' | 'total' | 'error'
 *		['item' => int|float],	-- subtotal
 *		['cash' => int|float],	-- Totals column, to be paid at the school
 *		['bill' => int|float]	-- Totals column, to be paid by invoice/upon booking
 *	))
*/
	private function get_quote_data( $quote ) {
		$data = array();
		$cash_total = $bill_total = 0;
		$has_pw = false;
		$has_errors = false;

		$school = get_post( $quote->school_id );
		$school_meta = fbk_get_school_meta( $quote->school_id );
		$currency = fbk_tt_get_currency( $school->ID );
		$currency_code = fbk_tt_get_currency( $school->ID, false );
		$duration_weeks = $quote->duration;

		if ( empty($school_meta['courses'][$quote->course_id]) ) {
			$data['course'] = array( array(
				'label' => 'Der Kurs scheint nicht mehr verfügbar zu sein.'
				 . ' Das tut uns Leid. Ihre Anfrage ist zur manuellen Bearbeitung weitergeleitet worden;'
				 . ' wir melden uns demnächst bei Ihnen zurück.',
				'type' => 'error'
			));
			$has_errors = true;
		} elseif ( empty($school_meta['courses'][$quote->course_id]['cost']) ) {
			$data['course'] = array( array(
				'label' => wptexturize( 'Der Preis für den Kurs "' . $school_meta['courses'][$quote->course_id]['name']
				 . '" kann leider nicht automatisch bearbeitet werden.' )
				 . ' Das tut uns Leid. Ihre Anfrage ist zur manuellen Bearbeitung weitergeleitet worden;'
				 . ' wir melden uns demnächst bei Ihnen zurück.',
				'type' => 'error'
			));
			$has_errors = true;
		} else {
			$course =& $school_meta['courses'][$quote->course_id];
			$course_prices = $this->get_course_price( $course, $quote->start, $quote->duration );

			$labels = array(
				'base' => wptexturize($course['name'])
					. ( $course['hpw'] ? ", $course[hpw] Lektionen "
					 . ( $course['mpl'] ? "à $course[mpl] Minuten " : "" )
					 . "pro Woche" : "" ),
				'mat' => 'Lehrmaterial',
				'reg' => 'Einschreibegebühr'
			);

			if ( 's' == $course['cost']['period'] && ! empty( $course['dur'][0] ) ) {
				$duration_weeks = $course['dur'][0] * $quote->duration;
				if ( ! empty( $course['dur'][1] ) && $duration_weeks > $course['dur'][1] )
					$labels['base'] .= " (Hinweis: Die Kursdauer sollte höchstens " . (int)( $course['dur'][1] / $course['dur'][0] ) . " Semester betragen.)";
			} else {
				if ( ! empty($course['dur'][0]) && $duration_weeks < $course['dur'][0] )
					$labels['base'] .= " (Hinweis: Die Kursdauer sollte mindestens " . $course['dur'][0] . " Wochen betragen.)";
				elseif ( ! empty($course['dur'][1]) && $duration_weeks > $course['dur'][1] )
					$labels['base'] .= " (Hinweis: Die Kursdauer sollte höchstens " . $course['dur'][1] . " Wochen betragen.)";
			}

			if ( empty($course_prices['base']['total']) ) {
				$data['course'] = array( array(
					'label' => wptexturize( 'Der Preis für den Kurs "' . $course['name'] . '" kann leider nicht automatisch bearbeitet werden.' )
					 . ' Das tut uns Leid. Ihre Anfrage ist zur manuellen Bearbeitung weitergeleitet worden;'
					 . ' wir melden uns demnächst bei Ihnen zurück.',
					'type' => 'error'
				));
				$has_errors = true;
			} else {
				foreach ( $course_prices as $key => $p ) {
					if ( ! $p )
						continue;
					$data['course'][] = array(
						'label' => $labels[$key],
						'type' => 'item',
						'item' => isset($p['pw']) ? $p['pw'] : '',
						'bill' => $p['total']
					);
					$bill_total += $p['total'];
					if ( isset($p['pw']) )
						$has_pw = true;
				}
			}
		}

		if ( $quote->acc_id ) {
			if ( empty($school_meta['accommodation'][$quote->acc_id]) ) {
				$data['acc'] = array( array(
					'label' => 'Die Unterkunft scheint nicht mehr verfügbar zu sein.'
					 . ' Das tut uns Leid. Ihre Anfrage ist zur manuellen Bearbeitung weitergeleitet worden;'
					 . ' wir melden uns demnächst bei Ihnen zurück.',
					'type' => 'error'
				));
				$has_errors = true;
			} elseif ( empty($school_meta['accommodation'][$quote->acc_id]['cost']) ) {
				$data['acc'] = array( array(
					'label' => wptexturize( 'Der Preis für die Unterkunft "' . $school_meta['accommodation'][$quote->acc_id]['name']
					 . '" kann leider nicht automatisch bearbeitet werden.' )
					 . ' Das tut uns Leid. Ihre Anfrage ist zur manuellen Bearbeitung weitergeleitet worden;'
					 . ' wir melden uns demnächst bei Ihnen zurück.',
					'type' => 'error'
				));
				$has_errors = true;
			} else {
				$acc =& $school_meta['accommodation'][$quote->acc_id];
				$acc_prices = $this->get_acc_price( $acc, $quote->room, $quote->board, $quote->start, $duration_weeks );

				if ( empty($acc['name']) )
					$acc['name'] = $GLOBALS['fbk_cf_boxes']['accommodation']['fields']['type']['opts'][ $acc['type'] ];

				$labels = array(
					'base' => wptexturize($acc['name'])
					 . ( $quote->room ? ', ' . $this->r_axis[$quote->room] : '' )
					 . ( $quote->board ? ', ' . $this->b_axis[$quote->board] : '' ),
					'reg' => 'Unterkunftsgebühr',
					'total' => 'Unterkunftspreis gesamt'
				);

				if ( empty( $acc_prices['base']['total'] ) ) {
					$data['acc'] = array( array(
						'label' => wptexturize( 'Der Preis für die Unterkunft "' . $acc['name'] . '" kann leider nicht automatisch bearbeitet werden.' )
						 . ' Das tut uns Leid. Ihre Anfrage ist zur manuellen Bearbeitung weitergeleitet worden;'
						 . ' wir melden uns demnächst bei Ihnen zurück.',
						'type' => 'error'
					));
					$has_errors = true;
				} else {
					foreach ( $acc_prices as $key => $p ) {
						if ( ! $p )
							continue;
						$data['acc'][] = array(
							'label' => $labels[$key],
							'type' => 'item',
							'item' => isset($p['pw']) ? $p['pw'] : '',
							'bill' => $p['total']
						);
						if ( isset($p['pw']) )
							$has_pw = true;
						$bill_total += $p['total'];
					}
				}
			}
		}

		if ( $quote->fees ) {
			foreach ( $school_meta['fees'] as $fee ) {
				if ( ! in_array( $fee['meta_id'], $quote->fees ) )
					continue;
				$price = $this->get_fee_price( $fee, $quote->duration );
				$fee_type = ( 1 === strpos( $fee['type'], 'b' ) ? 'bill' : 'cash' );
				${$fee_type . '_total'} += $price['total'];
				$data['fees'][] = array(
					'label' => $fee['key'] . ( $fee['desc'] ? " ($fee[desc])" : "" ),
					'type' => 'item',
					'item' => isset($price['pw']) ? $price['pw'] : '',
					$fee_type => $price['total']
				);
				if ( isset($price['pw']) )
					$has_pw = true;
			}
		}

		$data['total'] = array(
			array(
				'label' => "Gesamtbetrag in $currency",
				'type' => 'total',
				'cash' => $cash_total,
				'bill' => $bill_total
			)
		);
		if ( 'eur' != $currency_code )
			$data['total'][] = array(
				'label' => "Gesamtbetrag in ca. €",
				'type' => 'total',
				'cash' => (int) $this->convert( $cash_total, $currency_code ),
				'bill' => (int) $this->convert( $bill_total, $currency_code )
			);

		foreach ( wp_get_object_terms( $quote->school_id, 'loc' ) as $_loc )
			$city = $_loc;
		$data['header'] = "Kostenvoranschlag $school->post_title, $city->name\n"
		. $quote->duration . ' ' . ( 's' == @$course['cost']['period'] ? 'Semester' : ( 'Woche' . ( $quote->duration > 1 ? 'n' : '' ) ) )
		. ' ab ' . fbk_date( $quote->start, 'j. M Y' );

		$data['column_headings'] = array( 'label' => 'Leistung' );
		if ( $has_pw )
			$data['column_headings']['item'] = 'Zwischensumme';
		if ( $cash_total )
			$data['column_headings']['cash'] = 'Vor Ort*';
		if ( $bill_total )
			$data['column_headings']['bill'] = "Preis ($currency)";

		foreach ( wp_get_object_terms( $quote->school_id, 'category' ) as $_cat ) {
			$data['category'] = $_cat;
			break;
		}

		if ( $has_errors && empty($quote->assist_requested) )
			$this->request_manual_assist( $this->get_user($quote->user_id), $quote );

		return $data;
	}

	function convert( $price, $from_currency, $to_currency = 'eur' ) {
		$_expected_date = new DateTime;
		if ( $_expected_date->format( 'h' ) < 16 )
			$_expected_date->modify( '-1 day' );
		$expected_date = $_expected_date->format('Y-m-d');

		if ( $cached = get_option( 'fbk_currencies' ) ) {
			if ( is_array($cached) && $cached['date'] == $expected_date )
				$rates = $cached['rates'];
		}

		if ( ! isset($rates) ) {
			$rates = array();
			$reader = new XMLReader;
			$reader->open( 'http://www.ecb.int/stats/eurofxref/eurofxref-daily.xml' );
			while ( $reader->read() ) {
				if ( $reader->name == 'Cube' && $reader->getAttribute('currency') ) {
					do {
						$_rates[ strtolower($reader->getAttribute( 'currency' )) ] = $reader->getAttribute( 'rate' );
					} while ( $reader->next( 'Cube' ) && $reader->nodeType == XMLReader::ELEMENT );
					break;
				}
			}
			$reader->close();

			foreach ( get_terms( 'currency', array( 'get' => 'all' ) ) as $curr ) {
				$code = strtolower( $curr->slug );
				if ( ! array_key_exists( $code, $_rates ) || 'eur' == $code )
					$rates[$code] = 1;
				else
					$rates[$code] = (float) $_rates[$code];
			}

			update_option( 'fbk_currencies', array( 'date' => $expected_date, 'rates' => $rates ) );
		}

		return $price * $rates[$to_currency] / $rates[$from_currency];
	}

	function get_user( $user_id, $access_key = false, $ignore_expiry = false ) {
		global $wpdb;
		$this->err = 0;

		$user_id = (int) $user_id;
		$_user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::USER_TABLE . " WHERE user_id = %d", $user_id ) );

		if ( ! $_user ) {
			$this->err = self::U_NO_SUCH_USER;
			return false;
		}

		if ( false !== $access_key && $_user->access_key != $access_key ) {
			$this->err = self::U_INVALID_ACCESS_KEY;
			return false;
		}

		// Use parent, if available
		if ( ! empty( $_user->parent_user ) ) {
			$parent = $this->get_user( $_user->parent_user );
			if ( ! $parent )
				$this->err = 0; // Don't err on a failed parent query
		}

		if ( ! empty($parent) )
			$user = $this->sanitize_user( $parent, $user_id, $_user->access_key );
		else
			$user = $this->sanitize_user( $_user );

		if ( false === $access_key )
			return $user;

		// Set last_visit
		$this->update_user( $user );

		if ( ! $ignore_expiry ) {
			// Max validity period is 2*access_duration, extended from access_duration with each visit. Try not to be a hassle.
			$access_key_time = base_convert( $user->_queried_access_key, 36, 10 ) / $user_id;
			$now = time();
			$expire_after = get_option( 'fbk_quote_access_duration' ) * 86400;
			if ( $access_key_time < $now - 2 * $expire_after
			 || $user->last_visit && $access_key_time < $now - $expire_after && $user->last_visit < $now - $expire_after ) {
				$this->err = self::U_ACCESS_KEY_EXPIRED;
				$this->last_user_queried = $user;
				return false;
			}
		}

		return $user;
	}

	function sanitize_user( $user, $queried_id = false, $queried_access_key = false ) {
		if ( is_array( $user ) )
			$user = (object) $user;
		elseif ( ! is_object( $user ) )
			return false;

		$user->_queried_id = $queried_id ? $queried_id : $user->user_id;
		$user->_queried_access_key = $queried_access_key ? $queried_access_key : $user->access_key;

		return $user;
	}

	function get_refresh_query( $user_id ) {
		if ( ! isset($this->last_user_queried) || ! $this->last_user_queried->_queried_id == $user_id )
			$user = $this->get_user( $user_id );
		else
			$user = $this->last_user_queried;
		return "u=$user->_queried_id&q=$user->_queried_access_key&refresh_key=1";
	}

	function refresh_access_key( $user_id ) {
		global $wpdb;
		if ( ! $user = $this->get_user( $user_id ) )
			return false;

		$new_key = base_convert( time() * $user_id, 10, 36 );

		$user = $this->update_user( $user->_queried_id, array( 'access_key' => $new_key ), true );

		$quotes = $this->get_quotes( $user->user_id );
		$replace = $this->get_replacement_token_array( array( 'user' => $user, 'num_quotes' => count($quotes) ) );

		$subject = str_ireplace( array_keys($replace), $replace, stripslashes(get_option( 'fbk_quote_refresh_subject' )) );
		$body = str_ireplace( array_keys($replace), $replace, stripslashes(get_option( 'fbk_quote_refresh_text' )) );

		mail(
			'"=?UTF-8?B?' . base64_encode($user->first_name.' '.$user->last_name) . '?=" <' . $user->email . '>',
			'=?UTF-8?B?' . base64_encode( $subject ) . '?=',
			chunk_split(base64_encode( $body . "\n\n" . stripslashes(get_option( 'fbk_mail_signature' )) )),
			'From: ' . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_quotes' )))
			 . PHP_EOL . 'Content-Transfer-Encoding: base64'
			 . PHP_EOL . 'Content-Type: text/plain; charset="utf-8"'
		);
	}

	function send_reminder( $user_id ) {
		$user = $this->get_user( $user_id );
		if ( ! $user || $user->converted )
			return;
		$quotes = $this->get_quotes( $user_id );
		foreach ( $quotes as $quote )
			if ( $quote->assist_requested )
				return;

		$replace = $this->get_replacement_token_array( array( 'user' => $user, 'num_quotes' => count($quotes) ) );

		$subject = str_ireplace( array_keys($replace), $replace, stripslashes(get_option( 'fbk_quote_reminder_subject' )) );
		$body = str_ireplace(
			array_keys($replace),
			$replace,
			stripslashes(get_option(
				1 == count($quotes)
					? 'fbk_quote_reminder_text_single'
					: 'fbk_quote_reminder_text_multi'
			))
		);

		mail(
			'"=?UTF-8?B?' . base64_encode($user->first_name.' '.$user->last_name) . '?=" <' . $user->email . '>',
			'=?UTF-8?B?' . base64_encode( $subject ) . '?=',
			chunk_split(base64_encode( $body . "\n\n" . stripslashes(get_option( 'fbk_mail_signature' )) )),
			'From: ' . q_encode_angle_address(stripslashes(get_option( 'fbk_mail_from_quotes' )))
			 . PHP_EOL . 'Content-Transfer-Encoding: base64'
			 . PHP_EOL . 'Content-Type: text/plain; charset="utf-8"'
		);
	}

	private function get_replacement_token_array( $args = array() ) {
		$defaults = array(
			'quote' => false,
			'user' => false,
			'num_quotes' => false
		);
		extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

		$replace = array(
			'{access_duration}' => get_option( 'fbk_quote_access_duration' ),
			'{remind_after}' => get_option( 'fbk_quote_remind_after' )
		);

		if ( $user ) {
			$replace['{url}'] = $this->get_quote_url( $user );
			$replace['{salutation}'] = $user->salutation;
			$replace['{first_name}'] = $user->first_name;
			$replace['{last_name}'] = $user->last_name;
			$replace['{email}'] = $user->email;
			$replace['{phone}'] = $user->phone;
			$replace['{masculine_r}'] = ( 'Frau' == $user->salutation ? '' : 'r' );
		}

		if ( $quote ) {
			$school =& get_post( $quote->school_id );
			$school_meta = fbk_get_school_meta( $quote->school_id );

			$replace['{school}'] = $school->post_title;
			$replace['{course}'] = ( ! empty($quote->course_id) && ! empty($school_meta['courses'][$quote->course_id])
				? fbk_cf_get_meta_object_name( 'courses', $school_meta['courses'][ $quote->course_id ] )
				: '' );
			$replace['{accommodation}'] = ( ! empty($quote->acc_id) && ! empty($school_meta['accommodation'][$quote->acc_id])
				? fbk_cf_get_meta_object_name( 'accommodation', $school_meta['accommodation'][$quote->acc_id] )
				: '' );
			$replace['{duration}'] = (
				! empty($quote->course_id)
				&& isset($school_meta['courses'][$quote->course_id]['cost']['period'])
				&& 's' == $school_meta['courses'][$quote->course_id]['cost']['period']
					? "$quote->duration Semester"
					: ( $quote->duration == 1 ? "1 Woche" : "$quote->duration Wochen" )
				);
			$replace['{start}'] = $quote->start ? $quote->start : $quote->start_raw;
		}

		if ( $num_quotes ) {
			$replace['{plural_n}'] = ( 1 == $num_quotes ? '' : 'n' );
			$replace['{kv_plural}'] = ( 1 == $num_quotes ? 'Kostenvoranschlag' : 'Kostenvoranschläge' );
			$replace['{num_kv}'] = (int) $num_quotes;
		}

		return $replace;
	}

	private function get_course_price( $course, $start, $duration ) {
		$result = array( 'base' => false, 'mat' => false, 'reg' => false );
		$stack = array();
		$matfee_pw = true;

		if ( ! empty($course['cost']) ) {
			foreach ( $course['cost'] as $i => $col ) {
				if ( 'period' === $i )
					continue;
				if ( 'mat' == $col['type'] ) {
					$n = $this->get_col_total( $col, $duration );
					@$result['mat']['total'] += $n;
					if ( $this->per_week && $matfee_pw ) {
						@$result['mat']['pw'] += $this->per_week;
					} elseif ( $n ) {
						unset( $result['mat']['pw'] );
						$matfee_pw = false;
					}
				} else {
					$stack[] = $col;
				}
			}
		}

		$result['base']['total'] = $this->get_seasoned_price( $stack, $start, $duration );
		if ( $this->per_week )
			$result['base']['pw'] = $this->per_week;

		if ( ! empty($course['fee']) )
			$result['reg']['total'] = (int) $course['fee'];

		return $result;
	}

	private function get_acc_price( $acc, $room, $board, $start, $duration ) {
		$result = array( 'base' => false, 'reg' => false );
		$cols = array();

		if ( ! empty($acc['cost']) ) {
			foreach ( $acc['cost'] as $field ) {
				if ( isset( $field['values'][$room][$board] ) ) {
					$field['values'] = $field['values'][$room][$board];
					$cols[] = $field;
				}
			}
		}

		if ( $cols ) {
			$result['base']['total'] = $this->get_seasoned_price( $cols, $start, $duration );
			if ( $this->per_week )
				$result['base']['pw'] = $this->per_week;
		}

		// Add placement fee
		if ( ! empty($acc['fee']) )
			$result['reg']['total'] = (int) $acc['fee'];

		return $result;
	}

	private function get_fee_price( $fee, $duration ) {
		if ( 0 === strpos( $fee['type'], 'w' ) )
			return array(
				'total' => $fee['cost'] * $duration,
				'pw' => (int) $fee['cost']
			);
		else
			return array( 'total' => (int) $fee['cost'] );
	}

	private function get_seasoned_price( $cols, $start, $duration ) {
		$duration = (int) $duration;
		if ( $start && count($cols) > 1 ) {
			$start_date = new DateTime( $start );
			$totals = $_intervals = $perweeks = $additional_years = array();
			$result = $_per_week = 0;
			$has_pw = true;

			foreach ( $cols as $i => $col ) {
				if ( isset($col['type']) && 'mat' == $col['type'] ) {
					if ( ( ! $col['to'] || new DateTime($col['to']) > $start_date ) && ( ! $col['from'] || new DateTime($col['from']) < $start_date ) )
						$result += $this->get_col_total( $col, $duration );
				} else {
					$index = 'add' == $col['calc'] ? 'add' : 'base';
					$_intervals[$index][] = @array( $col['from'], $col['to'] );
					$totals[$index][] = $this->get_col_total( $col, $duration );
					$perweeks[$index][] = $this->per_week;
				}
			}

			foreach ( array( 'base', 'add' ) as $_calc ) {
				if ( empty( $_intervals[$_calc] ) )
					break;

				if ( 'base' == $_calc ) { // Set up start/end dates
					if ( ! empty($_intervals['add']) )
						foreach ( $_intervals['add'] as $tuple )
							foreach ( $tuple as $datestr )
								if ( ! empty($datestr) ) {
									$d = new DateTime( $datestr );
									$additional_years[] = $d->format( 'Y' );
								}
					$intervals = fbk_tt_get_intervals_by_timeline( $_intervals[$_calc], false, $additional_years );
					while ( ($up = ($intervals[0][0] > $start_date)) || ($down = ($intervals[ count($intervals) - 1 ][1] < $start_date)) ) {
						if ( $up && $down )
							return $totals['base'][0];
						$start_date->modify( $up ? '+1 year' : '-1 year' );
					}
					$end_date = clone $start_date;
					$end_date->modify( "+$duration weeks" );
				} else {
					$intervals = fbk_tt_get_intervals_by_timeline( $_intervals[$_calc], true );
				}

				$weeks_done = 0;
				foreach ( $intervals as $i => $tuple ) {
					if ( $start_date < $tuple[1] && $end_date > $tuple[0] ) {
						$_start = max( $start_date, $tuple[0] );
						$_end = min( $end_date, $tuple[1] );

						$weeks_done += $weeks = (int) round( ( $_end->format('U') - $_start->format('U') ) / (7*24*3600) );
						if ( 'add' == $_calc ) {
							foreach ( $tuple[2] as $j ) {
								$result += $totals[$_calc][$j] * $weeks/$duration;
								if ( $has_pw && $perweeks[$_calc][$j] && $weeks == $duration )
									$_per_week += $perweeks[$_calc][$j];
								elseif ( $weeks )
									$has_pw = false;
							}
						} else {
							$result += $totals[$_calc][ $tuple[2] ] * $weeks/$duration;
							if ( $has_pw && $perweeks[$_calc][ $tuple[2] ] && $weeks == $duration  )
								$_per_week += $perweeks[$_calc][ $tuple[2] ];
							elseif ( $weeks )
								$has_pw = false;
						}
					}
				}

				if ( 'base' == $_calc && $weeks_done < $duration )
					$result += $totals[0] * ($duration-$weeks_done)/$duration;
			}

			if ( $has_pw )
				$this->per_week = $_per_week;
			else
				$this->per_week = 0;
			return $result;
		} elseif ( count($cols) ) {
			return $this->get_col_total( reset($cols), $duration );
		} else {
			return 0;
		}
	}

	private function get_col_total( $data, $weeks ) {
		$this->per_week = 0;
		$set = 0;
		if ( empty($data['values']) || ! is_array($data['values']) )
			return 0;

		ksort( $data['values'] );

		// Retrieve the highest index below $weeks
		foreach ( array_keys( $data['values'] ) as $_week ) {
			if ( $_week >= $weeks )
				break;
			$anchor = $_week;
		}
		// If no useful entry is found below, take the first above
		if ( ! isset( $anchor ) || 0 == $data['values'][$anchor] )
			$anchor = $_week;
		// If the anchor is a break condition, bail.
		if ( 0 == $data['values'][$anchor] )
			return 0;

		switch ( $data['calc'] ) {
			case 'nth':
				if ( $anchor >= $weeks ) {
					$unclipped = $data['values'][$anchor] * $weeks / ( $anchor+1 );
				} else {
					$unclipped = $data['values'][$anchor] * ( $weeks - $anchor );
					reset( $data['values'] );
					$trailing_week = key( $data['values'] ) - 1;
					foreach ( $data['values'] as $_week => $value ) {
						$unclipped += $value * ( $_week - $trailing_week );
						$trailing_week = $_week;
					}
				}
				break;
			case 'pw':
			case 'add':
				$unclipped = $data['values'][$anchor] * $weeks;
				$this->per_week = $data['values'][$anchor] * 1;
				break;
			case 'tot':
				$unclipped = $data['values'][$anchor] * $weeks / ( $anchor+1 );
				break;
			case 'un':
				if ( $anchor >= $weeks ) // Don't search upwards for unique costs, they don't apply lower
					$unclipped = 0;
				else
					$unclipped = $data['values'][$anchor];
				break;
		}

		return $unclipped;
	}
}

$GLOBALS['fbk_quotes'] = new FBK_Quotes;

?>
<?php
/**
 * @package Studium_Ausland
 */

require_once( get_stylesheet_directory() . '/lib/fpdf/fpdf.php' );

class Quote_PDF extends FPDF {
	function generate_quote( $quote_data, $user, $currency ) {
		$header = iconv( 'UTF-8', 'CP1252', $quote_data['header'] );

		$this->SetMargins( 20, 20 );
		$this->SetFont( 'Helvetica', '', 8 );
		$this->SetAuthor( 'Agentur Studium Ausland, Berlin' );
		$this->SetTitle( strtok( $header, "\n" ) );
		$this->AddPage();

		// Logo & Addresses
		$this->Image( get_stylesheet_directory() . '/img/logo-bare.png', 21, null, -300, -300, 'png', home_url() );
		$this->Ln( 1 );
		$this->Cell( 0, 5, iconv( 'UTF-8', 'CP1252', stripslashes(get_option( 'fbk_quote_pdf_header' )) ) );
		$this->SetXY( 140, 20 );
		$this->MultiCell( 0, 4, iconv( 'UTF-8', 'CP1252', stripslashes(get_option( 'fbk_quote_pdf_addrblock' )) ) );
		$this->SetXY( 20, 42 );
		$this->setFont( '', '', 9 );
		$this->MultiCell( 0, 4, iconv( 'UTF-8', 'CP1252', "$user->salutation\n$user->first_name $user->last_name"
			. ($user->street ? "\n$user->street" : "" )
			. ($user->city ? "\n$user->postalcode $user->city" : "" )
			. "\n$user->country" )
		);

		// Header
		$this->Ln( 6 );
		$y = $this->GetY();
		$this->Line( 20, $y, 190, $y );
		$y += 2;
		$this->SetXY( 160, $y );
		$this->Cell( 0, 4, 'Berlin, ' . date('d.m.Y'), 0, 0, 'R' );
		$this->SetXY( 20, $y );
		$this->SetFont( '', '', 11 );
		$this->MultiCell( 120, 5, $header, '', 'L' );
		$y = $this->getY() + 2;
		$this->Line( 20, $y, 190, $y );
		$this->Ln( 10 );

		// Table
		$this->SetFont( '', '', 10 );
		$this->SetLineWidth( 0.1 );

		$widths = array();
		$i = 0;
		foreach ( $quote_data['column_headings'] as $k => $col ) {
			if ( 'label' != $k )
				$widths[ ++$i ] = $this->GetStringWidth( $col ) + 4;
			$quote_data['column_headings'][$k] = iconv( 'UTF-8', 'CP1252', html_entity_decode( $col, ENT_NOQUOTES, 'UTF-8' ) );
		}
		$widths[0] = 170 - array_sum( $widths );
		ksort( $widths );

		$this->thead = array(
			array_values( $quote_data['column_headings'] ),
			$widths
		);
		$this->row( $this->thead[0], $this->thead[1], 'thead' );

		$this->in_table = true;
		foreach ( array( 'course' => 'Kurs', 'acc' => 'Unterkunft', 'fees' => 'Weitere Leistungen', 'total' => 'Gesamt' ) as $tbody => $theading ) {
			if ( isset( $quote_data[$tbody] ) ) {
				$_row = array_fill( 0, count($quote_data['column_headings']), '' );
				$_row[0] = iconv( 'UTF-8', 'CP1252', html_entity_decode( $theading, ENT_NOQUOTES, 'UTF-8' ) );
				$this->row( $_row, $widths, 'rowgroupHead' );
				foreach ( $quote_data[$tbody] as $row ) {
					if ( 'error' == $row['type'] ) {
						$this->row(
							array( iconv( 'UTF-8', 'CP1252', html_entity_decode( $row['label'], ENT_NOQUOTES, 'UTF-8' ) ) ),
							array(0),
							'error'
						);
					} else {
						$_row = array();
						foreach ( array_keys($quote_data['column_headings']) as $col ) {
							if ( empty($row[$col]) )
								$_row[] = isset($row[$col]) && 0 === $row[$col] ? '–' : '';
							elseif ( is_int($row[$col]) )
								$_row[] = $row[$col] . ',–';
							elseif ( is_float($row[$col]) )
								$_row[] = number_format( $row[$col], 2, ',', ' ' );
							else
								$_row[] = iconv( 'UTF-8', 'CP1252', html_entity_decode( $row[$col], ENT_NOQUOTES, 'UTF-8' ) );
						}
						$this->row( $_row, $widths, $row['type'] );
					}
				}
			}
		}
		$this->in_table = false;

		$this->Cell( 0, 1, '', 'T' );
		$this->SetFont( '', 'I' );

		if ( isset( $cols['cash'] ) ) {
			$this->Ln( 3 );
			$this->Write( 4, "* Diese Beträge werden nicht in Rechnung gestellt, sondern sind nach Bedarf vor Ort zu bezahlen." );
		}

		// Disclaimer
		$this->Ln( 8 );
		if ( $this->GetY() > 248 )
			$this->AddPage();
		if ( iconv( 'UTF-8', 'CP1252', $currency ) == '€' )
			$this->MultiCell( 0, 4, iconv( 'UTF-8', 'CP1252', stripslashes(get_option( 'fbk_quote_pdf_disclaimer_euro' )) ) );
		else
			$this->MultiCell( 0, 4, iconv( 'UTF-8', 'CP1252', stripslashes(get_option( 'fbk_quote_pdf_disclaimer_foreign' )) ) );
	}

	function rowgroupHead( $text ) {
		$this->SetFillColor( 220 );
		$this->Cell( 0, 10, $text, 1, 1, 'L', true );
	}

	function row( $contents, $widths, $type ) {
		$line_height = 8;
		$borderTopBottom = '';
		$align = 'R';
		switch ( $type ) {
			case 'thead':
				$this->setFont( '', 'B' );
				$this->SetFillColor( 200 );
				$line_height = 10;
				$align = 'C';
				$borderTopBottom = 'T';
				break;
			case 'rowgroupHead':
				$this->setFont( '', '' );
				$this->SetFillColor( 220 );
				$borderTopBottom = 'T';
				break;
			case 'item':
			default:
				$this->setFont( '', '' );
				$this->SetFillColor( 255 );
				break;
			case 'subtotal':
				$this->setFont( '', '' );
				$this->SetFillColor( 245 );
				break;
			case 'total':
				$this->setFont( '', 'B' );
				$this->SetFillColor( 240 );
				break;
			case 'error':
				$this->setFont( '', '' );
				$this->SetFillColor( 240, 255, 255 );
				$line_height = 10;
				$borderTopBottom = 'T';
				break;
		}
		foreach ( $contents as $i => $text )
			if ( $this->GetStringWidth( $text ) > $widths[$i] ) {
				$x = $this->GetX();
				$y = $this->GetY();
				$borderLeftRight = ($i+1 < count($contents) ? 'L' : 'LR');
				$this->Cell(
					$widths[$i],
					2,
					'',
					( strpos($borderTopBottom,'T')===false ? '' : 'T' ) . $borderLeftRight,
					0,
					'L',
					true
				);
				$this->SetXY( $x, $y+2 );
				$this->MultiCell(
					$widths[$i],
					$line_height - 4,
					$text,
					($i+1 < count($contents) ? 'L' : 'LR'),
					$i ? $align : 'L',
					true
				);
				$this->Cell(
					$widths[$i],
					2,
					'',
					( strpos($borderTopBottom,'B')===false ? '' : 'B' ) . $borderLeftRight,
					2,
					'L',
					true
				);
				$line_height = $this->GetY() - $y;
				$this->SetXY( $x + $widths[$i], $y );
				$floor = max( $this->GetY(), @$floor );
			} else {
				$this->Cell(
					$widths[$i],
					$line_height,
					$text,
					$borderTopBottom . ($i+1 < count($contents) ? 'L' : 'LR'),
					0,
					$i ? $align : 'L',
					true
				);
			}
		$this->Ln();
		if ( isset($y) && $this->GetY() < $y + $line_height )
			$this->SetY( $y + $line_height );
	}

	function Header() {
		$this->SetY( 10 );
		$this->SetFont( '', '', 8 );
		$this->SetTextColor( 54, 95, 145 );
		$this->Cell( 0, 4, 'Seite ' . $this->PageNo(), 0, 0, 'C' );
		$this->SetXY( $this->rMargin, $this->tMargin );
	}

	function Footer() {
		$this->SetFont( '', '', 8 );
		$this->SetTextColor( 54, 95, 145 );
		$this->SetXY( 20, -20 );
		$this->MultiCell( 0, 4, iconv( 'UTF-8', 'CP1252', stripslashes(get_option( 'fbk_quote_pdf_footer' )) ), 0, 'C' );
	}

	function AcceptPageBreak() {
		if ( $this->in_table ) {
			$this->Cell( 0, 1, '', 'T', 1 );
			$this->AddPage();

			$family = $this->FontFamily;
			$style = $this->FontStyle.($this->underline ? 'U' : '');
			$fontsize = $this->FontSizePt;
			$lw = $this->LineWidth;
			$dc = $this->DrawColor;
			$fc = $this->FillColor;
			$tc = $this->TextColor;
			$cf = $this->ColorFlag;

			$this->row( $this->thead[0], $this->thead[1], 'thead' );

			if ( $family )
				$this->SetFont( $family, $style, $fontsize );
			$this->SetLineWidth( $lw );
			$this->SetDrawColor( $dc );
			if($this->FillColor!=$fc) {
				$this->FillColor = $fc;
				$this->_out($fc);
			}
			$this->TextColor = $tc;
			$this->ColorFlag = $cf;

			return false;
		}
		return true;
	}
}

?>
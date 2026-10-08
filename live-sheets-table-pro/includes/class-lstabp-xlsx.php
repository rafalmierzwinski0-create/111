<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Xlsx {
	public static function is_available() {
		return class_exists( 'ZipArchive' );
	}

	public static function build( $headers, $rows, $title = '' ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'lstabp_no_zip', __( 'This server cannot build Excel files.', 'live-sheets-table-pro' ) );
		}

		$path = tempnam( get_temp_dir(), 'lstabp-xlsx' );

		if ( ! $path ) {
			return new WP_Error( 'lstabp_no_temp', __( 'Could not create a temporary file for the download.', 'live-sheets-table-pro' ) );
		}

		@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- Best effort; a server that refuses it still gets the file deleted straight after sending.

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $path );

			return new WP_Error( 'lstabp_zip_failed', __( 'Could not build the Excel file.', 'live-sheets-table-pro' ) );
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types() );
		$zip->addFromString( '_rels/.rels', self::root_rels() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook( $title ) );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels() );
		$zip->addFromString( 'xl/styles.xml', self::styles() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::sheet( $headers, $rows ) );

		$zip->close();

		return $path;
	}

	protected static function sheet( $headers, $rows ) {
		$headers = array_values( (array) $headers );
		$widths  = array();
		$xml     = '';
		$line    = 1;

		if ( $headers ) {
			$xml .= self::row( $headers, $line, true, $widths );
			$line++;
		}

		foreach ( (array) $rows as $row ) {
			$xml .= self::row( array_values( (array) $row ), $line, false, $widths );
			$line++;
		}

		$cols = '';
		foreach ( $widths as $index => $width ) {
			$cols .= '<col min="' . ( $index + 1 ) . '" max="' . ( $index + 1 ) . '" width="' . $width . '" customWidth="1"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. ( $headers ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '' )
			. ( $cols ? '<cols>' . $cols . '</cols>' : '' )
			. '<sheetData>' . $xml . '</sheetData>'
			. '</worksheet>';
	}

	protected static function row( $cells, $line, $head, &$widths ) {
		$xml = '<row r="' . (int) $line . '">';

		foreach ( $cells as $index => $value ) {
			$value     = (string) $value;
			$reference = self::column_name( $index ) . (int) $line;
			$style     = $head ? ' s="1"' : '';

			$width = min( 60, max( 9, self::text_length( $value ) + 2 ) );
			if ( ! isset( $widths[ $index ] ) || $width > $widths[ $index ] ) {
				$widths[ $index ] = $width;
			}

			if ( '' === $value ) {
				continue;
			}

			if ( ! $head && self::is_plain_number( $value ) ) {
				$xml .= '<c r="' . $reference . '"' . $style . '><v>'
					. self::number( $value )
					. '</v></c>';
				continue;
			}

			$xml .= '<c r="' . $reference . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
				. self::escape( $value )
				. '</t></is></c>';
		}

		return $xml . '</row>';
	}

	protected static function is_plain_number( $value ) {
		if ( ! preg_match( '/^[-+]?[0-9][0-9\s\x{00A0}\x{202F}.,]*$/u', $value ) ) {
			return false;
		}

		return LSTAB_Renderer::looks_numeric( $value );
	}

	protected static function number( $value ) {
		$number = LSTAB_Renderer::to_number( $value );

		$text = rtrim( rtrim( number_format( $number, 8, '.', '' ), '0' ), '.' );

		return '' === $text ? '0' : $text;
	}

	protected static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	public static function column_name( $index ) {
		$index = max( 0, (int) $index );
		$name  = '';

		do {
			$name  = chr( 65 + ( $index % 26 ) ) . $name;
			$index = intdiv( $index, 26 ) - 1;
		} while ( $index >= 0 );

		return $name;
	}

	protected static function escape( $value ) {
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );

		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	protected static function content_types() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	protected static function root_rels() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	protected static function workbook( $title ) {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="' . self::escape( self::tab_name( $title ) ) . '" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	protected static function tab_name( $title ) {
		$title = trim( (string) preg_replace( '#[\\\\/?*\[\]:]#', ' ', $title ) );
		$title = (string) preg_replace( '/\s+/u', ' ', $title );

		if ( '' === $title ) {
			return __( 'Table', 'live-sheets-table-pro' );
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 31, 'UTF-8' ) : substr( $title, 0, 31 );
	}

	protected static function workbook_rels() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	protected static function styles() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '</cellXfs>'
			. '</styleSheet>';
	}
}

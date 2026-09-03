<?php
/**
 * Writing a real Excel workbook, without a library.
 *
 * CSV is a fine interchange format and a poor gift. Excel reads it with the
 * list separator of whoever opens it, so a file written with commas arrives in
 * Poland as one column of text per row; the numbers arrive as text too, so the
 * first thing anybody tries — select the price column and read the total —
 * gives nothing. Both are then blamed on the plugin that produced the file.
 *
 * An .xlsx file is a zip of XML, and the part of the format needed to write one
 * flat sheet is small enough to do here: about a hundred lines, no dependency
 * to keep up to date, and no third-party code shipped to customers.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal SpreadsheetML writer.
 */
class LSTABP_Xlsx {

	/**
	 * Whether this server can build one.
	 *
	 * A zip is not optional in the format, and PHP's zip extension is not
	 * guaranteed on cheap hosting. Where it is missing the download is not
	 * offered at all, rather than offered and then failing.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Build a workbook from a prepared table.
	 *
	 * @param array<int,string>            $headers Column headings.
	 * @param array<int,array<int,string>> $rows    Rows.
	 * @param string                       $title   Sheet tab name.
	 * @return string|WP_Error Path to the finished file.
	 */
	public static function build( $headers, $rows, $title = '' ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'lstabp_no_zip', __( 'This server cannot build Excel files.', 'live-sheets-table-pro' ) );
		}

		/*
		 * Not wp_tempnam(): that lives in the dashboard's own includes, and a
		 * visitor downloading a table is not in the dashboard. get_temp_dir()
		 * is core's answer to where a file may be written and is loaded on
		 * every request.
		 */
		$path = tempnam( get_temp_dir(), 'lstabp-xlsx' );

		if ( ! $path ) {
			return new WP_Error( 'lstabp_no_temp', __( 'Could not create a temporary file for the download.', 'live-sheets-table-pro' ) );
		}

		// Readable only by the account that will read it back a line later. The
		// temporary directory may be one the web server serves, and a table can
		// hold a price list not everyone is meant to see.
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

	/**
	 * The spreadsheet itself.
	 *
	 * @param array<int,string>            $headers Column headings.
	 * @param array<int,array<int,string>> $rows    Rows.
	 * @return string
	 */
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
			// The heading row stays put while somebody scrolls a long price
			// list, which is the first thing anybody does to one.
			. ( $headers ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '' )
			. ( $cols ? '<cols>' . $cols . '</cols>' : '' )
			. '<sheetData>' . $xml . '</sheetData>'
			. '</worksheet>';
	}

	/**
	 * One row of cells.
	 *
	 * @param array<int,string> $cells  Cell values.
	 * @param int               $line   1-based row number.
	 * @param bool              $head   Whether to draw it as a heading.
	 * @param array<int,float>  $widths Column widths, by reference.
	 * @return string
	 */
	protected static function row( $cells, $line, $head, &$widths ) {
		$xml = '<row r="' . (int) $line . '">';

		foreach ( $cells as $index => $value ) {
			$value     = (string) $value;
			$reference = self::column_name( $index ) . (int) $line;
			$style     = $head ? ' s="1"' : '';

			// Wide enough to read without being wide enough to need scrolling
			// sideways for one long note.
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

	/**
	 * Whether a cell should arrive as a number rather than as text.
	 *
	 * Only when the cell is nothing but a number: "120,00" and "1 215,50"
	 * qualify, "12,00 zł" does not. The plugin knows how to read the second as
	 * a number for sorting, but writing it as one here would quietly drop the
	 * currency from somebody's price list — a spreadsheet is allowed to be
	 * tidier than the sheet it came from, not different from it.
	 *
	 * @param string $value Cell value.
	 * @return bool
	 */
	protected static function is_plain_number( $value ) {
		if ( ! preg_match( '/^[-+]?[0-9][0-9\s\x{00A0}\x{202F}.,]*$/u', $value ) ) {
			return false;
		}

		return LSTAB_Renderer::looks_numeric( $value );
	}

	/**
	 * A spreadsheet-formatted number in the one form the file format takes.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	protected static function number( $value ) {
		$number = LSTAB_Renderer::to_number( $value );

		// Enough places for money and for coordinates, and rtrim so a whole
		// number is not written as 120.00000000.
		$text = rtrim( rtrim( number_format( $number, 8, '.', '' ), '0' ), '.' );

		return '' === $text ? '0' : $text;
	}

	/**
	 * Characters, not bytes, so a Polish or Chinese heading is measured right.
	 *
	 * @param string $value Cell value.
	 * @return int
	 */
	protected static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * A column's letter: 0 is A, 26 is AA.
	 *
	 * @param int $index Zero-based column index.
	 * @return string
	 */
	public static function column_name( $index ) {
		$index = max( 0, (int) $index );
		$name  = '';

		do {
			$name  = chr( 65 + ( $index % 26 ) ) . $name;
			$index = intdiv( $index, 26 ) - 1;
		} while ( $index >= 0 );

		return $name;
	}

	/**
	 * Text as XML content.
	 *
	 * A sheet can hold control characters — a stray tab or a form feed pasted
	 * from somewhere else — and XML cannot. Excel refuses the whole file over
	 * one of them, so they are dropped here rather than carried through.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	protected static function escape( $value ) {
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );

		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * The parts list every reader opens first.
	 *
	 * @return string
	 */
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

	/**
	 * Package relationships.
	 *
	 * @return string
	 */
	protected static function root_rels() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	/**
	 * The workbook, holding one sheet.
	 *
	 * @param string $title Sheet tab name.
	 * @return string
	 */
	protected static function workbook( $title ) {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="' . self::escape( self::tab_name( $title ) ) . '" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	/**
	 * A sheet tab name Excel will accept.
	 *
	 * Excel refuses a handful of characters in a tab name and refuses anything
	 * over 31 characters, and it refuses the file rather than the name.
	 *
	 * @param string $title Proposed name.
	 * @return string
	 */
	protected static function tab_name( $title ) {
		$title = trim( (string) preg_replace( '#[\\\\/?*\[\]:]#', ' ', $title ) );
		$title = (string) preg_replace( '/\s+/u', ' ', $title );

		if ( '' === $title ) {
			return __( 'Table', 'live-sheets-table-pro' );
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 31, 'UTF-8' ) : substr( $title, 0, 31 );
	}

	/**
	 * Workbook relationships.
	 *
	 * @return string
	 */
	protected static function workbook_rels() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	/**
	 * Two styles: ordinary, and the bold one the heading row uses.
	 *
	 * @return string
	 */
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

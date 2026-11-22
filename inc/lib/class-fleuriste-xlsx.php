<?php
/**
 * Tiny Excel (.xlsx) writer: several sheets, bold/money/date styles, formulas,
 * column widths and a frozen header row. No external library needed (uses ZipArchive).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Excel workbook.
 */
class Fleuriste_XLSX {

	// Cell styles (index into cellXfs below).
	const NORMAL     = 0;
	const BOLD       = 1;
	const MONEY      = 2;
	const MONEY_BOLD = 3;
	const DATE       = 4;
	const TITLE      = 5;
	const HEADER     = 6;
	const MUTED      = 7;
	const INT_BOLD   = 8;
	const DATETIME   = 9;
	const HEADER_R   = 10;

	/**
	 * Sheets: name, rows, widths, freeze.
	 *
	 * @var array
	 */
	private $sheets = array();

	/**
	 * Money number format, e.g. "Rs "#,##0.
	 *
	 * @var string
	 */
	private $money_format;

	/**
	 * Constructor.
	 *
	 * @param string $currency Currency prefix shown in money cells.
	 * @param int    $decimals Decimals for money.
	 */
	public function __construct( $currency = '', $decimals = 0 ) {
		$num                = '#,##0' . ( $decimals > 0 ? '.' . str_repeat( '0', $decimals ) : '' );
		$this->money_format = ( '' !== $currency ? '"' . str_replace( '"', '', $currency ) . ' "' : '' ) . $num;
	}

	/**
	 * Add a sheet.
	 *
	 * Each row is a list of cells. A cell is a plain value, or an array with
	 * v (value), s (style), f (formula, without "="), t ('d' = unix timestamp as date).
	 *
	 * @param string $name   Sheet name.
	 * @param array  $rows   Rows.
	 * @param array  $widths Column widths (characters).
	 * @param int    $freeze Number of top rows to keep in view (0 = none).
	 */
	public function add_sheet( $name, $rows, $widths = array(), $freeze = 0 ) {
		$name           = substr( preg_replace( '/[\\\\\/\?\*\[\]:]/', ' ', $name ), 0, 31 );
		$this->sheets[] = compact( 'name', 'rows', 'widths', 'freeze' );
	}

	/**
	 * Column letters for a 0-based index.
	 *
	 * @param int $i Index.
	 * @return string
	 */
	public static function col( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = intdiv( $i - 1, 26 ) ) {
			$s = chr( 65 + ( ( $i - 1 ) % 26 ) ) . $s;
		}
		return $s;
	}

	/**
	 * Escape XML text.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function x( $s ) {
		$s = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $s );
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Sheet XML.
	 *
	 * @param array $sheet Sheet.
	 * @return string
	 */
	private function sheet_xml( $sheet ) {
		$tz  = wp_timezone();
		$out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		if ( $sheet['freeze'] ) {
			$out .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . (int) $sheet['freeze'] . '" topLeftCell="A' . ( (int) $sheet['freeze'] + 1 ) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
		}
		if ( $sheet['widths'] ) {
			$out .= '<cols>';
			foreach ( array_values( $sheet['widths'] ) as $i => $w ) {
				$out .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (float) $w . '" customWidth="1"/>';
			}
			$out .= '</cols>';
		}
		$out .= '<sheetData>';
		foreach ( array_values( $sheet['rows'] ) as $r => $row ) {
			$out .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( (array) $row ) as $c => $cell ) {
				if ( null === $cell || '' === $cell ) {
					continue;
				}
				$cell  = is_array( $cell ) ? $cell : array( 'v' => $cell );
				$v     = isset( $cell['v'] ) ? $cell['v'] : '';
				$style = isset( $cell['s'] ) ? (int) $cell['s'] : self::NORMAL;
				$ref   = self::col( $c ) . ( $r + 1 );
				if ( isset( $cell['t'] ) && 'd' === $cell['t'] && is_numeric( $v ) ) {
					// Unix timestamp → Excel serial date in the shop's time zone.
					$offset = $tz->getOffset( new DateTime( '@' . (int) $v ) );
					$v      = ( (int) $v + $offset ) / 86400 + 25569;
					$style  = isset( $cell['s'] ) ? $style : self::DATE;
					$out   .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $v . '</v></c>';
				} elseif ( isset( $cell['f'] ) ) {
					$out .= '<c r="' . $ref . '" s="' . $style . '"><f>' . self::x( $cell['f'] ) . '</f>' . ( is_numeric( $v ) ? '<v>' . $v . '</v>' : '' ) . '</c>';
				} elseif ( is_int( $v ) || is_float( $v ) ) {
					$out .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $v . '</v></c>';
				} else {
					$out .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::x( $v ) . '</t></is></c>';
				}
			}
			$out .= '</row>';
		}
		return $out . '</sheetData><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
	}

	/**
	 * Styles XML.
	 *
	 * @return string
	 */
	private function styles_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="3"><numFmt numFmtId="164" formatCode="' . self::x( $this->money_format ) . '"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/><numFmt numFmtId="166" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
			. '<fonts count="5"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="15"/><color rgb="FF2B1D21"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><i/><sz val="10"/><color rgb="FF7A6A6E"/><name val="Calibri"/></font></fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF9E4F63"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top style="thin"><color rgb="FF9E4F63"/></top><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="11">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="top"/></xf>'
			. '<xf numFmtId="164" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'
			. '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="left" vertical="top"/></xf>'
			. '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
			. '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="1" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"><alignment horizontal="left"/></xf>'
			. '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="left" vertical="top"/></xf>'
			. '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="right" vertical="center"/></xf>'
			. '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	/**
	 * Build the file and return its bytes.
	 *
	 * @return string
	 */
	public function build() {
		$tmp = wp_tempnam( 'fleuriste-xlsx' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );
		$n     = count( $this->sheets );
		$types = '';
		$rels  = '';
		$list  = '';
		foreach ( $this->sheets as $i => $sheet ) {
			$k      = $i + 1;
			$types .= '<Override PartName="/xl/worksheets/sheet' . $k . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$rels  .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
			$list  .= '<sheet name="' . self::x( $sheet['name'] ) . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
			$zip->addFromString( 'xl/worksheets/sheet' . $k . '.xml', $this->sheet_xml( $sheet ) );
		}
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $types . '</Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>' . $list . '</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . ( $n + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $this->styles_xml() );
		$zip->close();
		$bytes = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $tmp );
		return $bytes;
	}
}

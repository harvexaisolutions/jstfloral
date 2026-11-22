<?php
/**
 * Tiny PDF writer for printable reports, invoices and receipts: A4 landscape or
 * portrait, Helvetica, text, filled boxes, lines, a JPEG logo and page numbers.
 * No external library needed.
 *
 * Coordinates are in points from the top-left corner of the page.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PDF document.
 */
class Fleuriste_PDF {

	/**
	 * Page width in points.
	 *
	 * @var float
	 */
	public $w = 841.89;

	/**
	 * Page height in points.
	 *
	 * @var float
	 */
	public $h = 595.28;

	/**
	 * Content stream of each page.
	 *
	 * @var string[]
	 */
	private $pages = array();

	/**
	 * Character widths (1/1000 em) for codes 32–126.
	 *
	 * @var array
	 */
	private static $widths = array(
		'F1' => array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 ),
		'F2' => array( 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584 ),
	);

	/**
	 * Images (JPEG data, width, height in pixels).
	 *
	 * @var array[]
	 */
	private $images = array();

	/**
	 * Constructor.
	 *
	 * @param string $orientation 'L' (landscape, default) or 'P' (portrait).
	 */
	public function __construct( $orientation = 'L' ) {
		if ( 'P' === $orientation ) {
			$this->w = 595.28;
			$this->h = 841.89;
		}
	}

	/**
	 * Load an image file for use with image(). JPEG is used as is; PNG/GIF/WebP are
	 * converted to JPEG on a white background when the GD extension is available.
	 *
	 * @param string $file Path to the image.
	 * @return int|false Image key, or false when it can't be used.
	 */
	public function load_image( $file ) {
		if ( ! $file || ! is_readable( $file ) ) {
			return false;
		}
		$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $info ) {
			return false;
		}
		if ( IMAGETYPE_JPEG === $info[2] && ( ! isset( $info['channels'] ) || 3 === (int) $info['channels'] ) ) {
			$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagejpeg' ) ) {
			$src = @imagecreatefromstring( file_get_contents( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! $src ) {
				return false;
			}
			$img = imagecreatetruecolor( imagesx( $src ), imagesy( $src ) );
			imagefill( $img, 0, 0, imagecolorallocate( $img, 255, 255, 255 ) );
			imagecopy( $img, $src, 0, 0, 0, 0, imagesx( $src ), imagesy( $src ) );
			ob_start();
			imagejpeg( $img, null, 90 );
			$data = ob_get_clean();
			$info = array( imagesx( $img ), imagesy( $img ) );
		} else {
			return false;
		}
		$this->images[] = array( $data, (int) $info[0], (int) $info[1] );
		return count( $this->images ) - 1;
	}

	/**
	 * Size of a loaded image in pixels.
	 *
	 * @param int $key Image key.
	 * @return int[] width, height.
	 */
	public function image_size( $key ) {
		return array( $this->images[ $key ][1], $this->images[ $key ][2] );
	}

	/**
	 * Draw a loaded image.
	 *
	 * @param int   $key Image key from load_image().
	 * @param float $x   Left.
	 * @param float $y   Top.
	 * @param float $w   Width.
	 * @param float $h   Height.
	 */
	public function image( $key, $x, $y, $w, $h ) {
		$this->out( sprintf( 'q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $w, $h, $x, $this->h - $y - $h, $key ) );
	}

	/**
	 * Start a new page.
	 */
	public function add_page() {
		$this->pages[] = '';
	}

	/**
	 * Number of pages so far.
	 *
	 * @return int
	 */
	public function page_count() {
		return count( $this->pages );
	}

	/**
	 * UTF-8 → Windows-1252 (the encoding of the built-in PDF fonts).
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function enc( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' );
		$c = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', $s ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $c ? preg_replace( '/[^\x20-\x7E]/', '?', $s ) : $c;
	}

	/**
	 * Width of a text in points.
	 *
	 * @param string $s    Text (UTF-8).
	 * @param float  $size Font size.
	 * @param bool   $bold Bold?
	 * @return float
	 */
	public function width( $s, $size, $bold = false ) {
		$map = self::$widths[ $bold ? 'F2' : 'F1' ];
		$w   = 0;
		$raw = self::enc( $s );
		$len = strlen( $raw );
		for ( $i = 0; $i < $len; $i++ ) {
			$o  = ord( $raw[ $i ] );
			$w += ( $o >= 32 && $o <= 126 ) ? $map[ $o - 32 ] : ( 0x85 === $o ? 1000 : ( 0xA0 === $o ? 278 : 556 ) );
		}
		return $w * $size / 1000;
	}

	/**
	 * Shorten a text to fit a width, adding "…".
	 *
	 * @param string $s    Text.
	 * @param float  $max  Width in points.
	 * @param float  $size Font size.
	 * @param bool   $bold Bold?
	 * @return string
	 */
	public function fit( $s, $max, $size, $bold = false ) {
		$s = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) );
		if ( $this->width( $s, $size, $bold ) <= $max ) {
			return $s;
		}
		while ( '' !== $s && $this->width( $s . '…', $size, $bold ) > $max ) {
			$s = function_exists( 'mb_substr' ) ? mb_substr( $s, 0, -1 ) : substr( $s, 0, -1 );
		}
		return rtrim( $s ) . '…';
	}

	/**
	 * Hex colour → "r g b".
	 *
	 * @param string $hex Colour.
	 * @return string
	 */
	private static function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return sprintf( '%.3F %.3F %.3F', hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
	}

	/**
	 * Write text.
	 *
	 * @param float  $x     Left (or right edge when $align is 'R', centre for 'C').
	 * @param float  $y     Baseline from the top.
	 * @param string $s     Text.
	 * @param float  $size  Font size.
	 * @param bool   $bold  Bold?
	 * @param string $color Hex colour.
	 * @param string $align L, R or C.
	 */
	public function text( $x, $y, $s, $size = 9, $bold = false, $color = '#2B1D21', $align = 'L' ) {
		if ( 'R' === $align ) {
			$x -= $this->width( $s, $size, $bold );
		} elseif ( 'C' === $align ) {
			$x -= $this->width( $s, $size, $bold ) / 2;
		}
		$t = str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', ' ' ), self::enc( $s ) );
		$this->out( sprintf( 'BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, self::rgb( $color ), $x, $this->h - $y, $t ) );
	}

	/**
	 * Filled rectangle.
	 *
	 * @param float  $x     Left.
	 * @param float  $y     Top.
	 * @param float  $w     Width.
	 * @param float  $h     Height.
	 * @param string $color Hex colour.
	 */
	public function box( $x, $y, $w, $h, $color ) {
		$this->out( sprintf( '%s rg %.2F %.2F %.2F %.2F re f', self::rgb( $color ), $x, $this->h - $y - $h, $w, $h ) );
	}

	/**
	 * Line.
	 *
	 * @param float  $x1    Start x.
	 * @param float  $y1    Start y.
	 * @param float  $x2    End x.
	 * @param float  $y2    End y.
	 * @param string $color Hex colour.
	 * @param float  $width Line width.
	 */
	public function line( $x1, $y1, $x2, $y2, $color = '#EBDCDD', $width = 0.6 ) {
		$this->out( sprintf( '%s RG %.2F w %.2F %.2F m %.2F %.2F l S', self::rgb( $color ), $width, $x1, $this->h - $y1, $x2, $this->h - $y2 ) );
	}

	/**
	 * Append to the current page.
	 *
	 * @param string $s PDF operators.
	 */
	private function out( $s ) {
		if ( ! $this->pages ) {
			$this->add_page();
		}
		$this->pages[ count( $this->pages ) - 1 ] .= $s . "\n";
	}

	/**
	 * Build the file. "{nb}" in any text is replaced by the number of pages.
	 *
	 * @param string $title Document title.
	 * @return string
	 */
	public function build( $title = '' ) {
		$objects = array();
		$n       = count( $this->pages );
		$kids    = array();
		// 1 catalog, 2 pages, 3–4 fonts, then page + content pairs, then info, then images.
		$img_base = 6 + $n * 2;
		$xobjects = '';
		foreach ( $this->images as $k => $img ) {
			$xobjects .= '/Im' . $k . ' ' . ( $img_base + $k ) . ' 0 R ';
			$objects[ $img_base + $k ] = '<< /Type /XObject /Subtype /Image /Width ' . $img[1] . ' /Height ' . $img[2] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $img[0] ) . " >>\nstream\n" . $img[0] . "\nendstream";
		}
		$xobjects = $xobjects ? ' /XObject << ' . $xobjects . '>>' : '';
		foreach ( $this->pages as $i => $content ) {
			$content = str_replace( '{nb}', (string) $n, $content );
			$page_id = 5 + $i * 2;
			$kids[]  = $page_id . ' 0 R';
			$stream  = function_exists( 'gzcompress' ) ? gzcompress( $content ) : $content;
			$filter  = function_exists( 'gzcompress' ) ? '/Filter /FlateDecode ' : '';
			$objects[ $page_id ]     = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobjects . ' >> /Contents %d 0 R >>', $this->w, $this->h, $page_id + 1 );
			$objects[ $page_id + 1 ] = '<< ' . $filter . '/Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
		}
		$info_id      = 5 + $n * 2;
		$objects[1]   = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2]   = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $n . ' >>';
		$objects[3]   = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4]   = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[ $info_id ] = '<< /Title <FEFF' . strtoupper( bin2hex( function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $title, 'UTF-16BE', 'UTF-8' ) : self::enc( $title ) ) ) . '> /Producer (Fleuriste) /CreationDate (D:' . gmdate( 'YmdHis' ) . 'Z) >>';
		ksort( $objects );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $id => $body ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$max  = max( array_keys( $objects ) );
		$pdf .= "xref\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";
		for ( $i = 1; $i <= $max; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . ( $max + 1 ) . " /Root 1 0 R /Info $info_id 0 R >>\nstartxref\n$xref\n%%EOF";
		return $pdf;
	}
}

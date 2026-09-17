<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Document_Parser {

	/**
	 * Main entry point to parse a file and return extracted plain text.
	 *
	 * @param string $file_path Absolute path to temporary uploaded file.
	 * @param string $file_name Original filename.
	 * @return string Extracted text content.
	 * @throws Exception If file type is unsupported or unreadable.
	 */
	public static function parse_file( $file_path, $file_name ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new Exception( 'File tidak dapat dibaca atau tidak ditemukan.' );
		}

		$ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		switch ( $ext ) {
			case 'txt':
			case 'md':
			case 'json':
				return self::parse_txt( $file_path );

			case 'csv':
				return self::parse_csv( $file_path );

			case 'docx':
				return self::parse_docx( $file_path );

			case 'xlsx':
				return self::parse_xlsx( $file_path );

			case 'pdf':
				return self::parse_pdf( $file_path );

			case 'doc':
				return self::parse_doc_legacy( $file_path );

			default:
				throw new Exception( "Format file '.{$ext}' belum didukung. Format yang didukung: PDF, DOCX, XLSX, CSV, TXT, MD." );
		}
	}

	/**
	 * Parse plain text or markdown files.
	 */
	private static function parse_txt( $file_path ) {
		$content = file_get_contents( $file_path );
		return trim( wp_strip_all_tags( $content ) );
	}

	/**
	 * Parse CSV files into readable text lines or Markdown table format.
	 */
	private static function parse_csv( $file_path ) {
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			throw new Exception( 'Gagal membuka file CSV.' );
		}

		$rows = array();
		while ( ( $data = fgetcsv( $handle, 4096, ',' ) ) !== false ) {
			$clean_data = array_map( 'trim', $data );
			if ( array_filter( $clean_data ) ) {
				$rows[] = implode( ' | ', $clean_data );
			}
		}
		fclose( $handle );

		if ( empty( $rows ) ) {
			return '';
		}

		return implode( "\n", $rows );
	}

	/**
	 * Parse Microsoft Word (.docx) files using native PHP ZipArchive.
	 */
	private static function parse_docx( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'PHP Extension ZipArchive tidak aktif pada server hosting Anda.' );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) !== true ) {
			throw new Exception( 'Gagal membuka file DOCX.' );
		}

		$xml_content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( ! $xml_content ) {
			throw new Exception( 'Dokumen DOCX kosong atau tidak memiliki isi teks yang valid.' );
		}

		// Replace paragraph tags with newlines to preserve structure
		$xml_content = str_replace( array( '</w:p>', '</w:tr>', '<w:br/>', '<w:br>' ), "\n", $xml_content );
		
		// Strip all XML tags and decode HTML entities
		$text = strip_tags( $xml_content );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );

		// Clean up multiple empty lines
		$lines = array_filter( array_map( 'trim', explode( "\n", $text ) ) );

		return implode( "\n", $lines );
	}

	/**
	 * Parse Microsoft Excel (.xlsx) files using native PHP ZipArchive.
	 */
	private static function parse_xlsx( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'PHP Extension ZipArchive tidak aktif pada server hosting Anda.' );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) !== true ) {
			throw new Exception( 'Gagal membuka file XLSX.' );
		}

		// Read shared strings map
		$shared_strings = array();
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $shared_xml ) {
			$xml = simplexml_load_string( $shared_xml );
			if ( $xml && isset( $xml->si ) ) {
				foreach ( $xml->si as $val ) {
					if ( isset( $val->t ) ) {
						$shared_strings[] = (string) $val->t;
					} elseif ( isset( $val->r ) ) {
						$text_parts = array();
						foreach ( $val->r as $run ) {
							if ( isset( $run->t ) ) $text_parts[] = (string) $run->t;
						}
						$shared_strings[] = implode( '', $text_parts );
					} else {
						$shared_strings[] = '';
					}
				}
			}
		}

		// Read worksheet 1
		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();

		if ( ! $sheet_xml ) {
			throw new Exception( 'File XLSX kosong atau tidak memiliki worksheet yang dapat dibaca.' );
		}

		$xml = simplexml_load_string( $sheet_xml );
		if ( ! $xml || ! isset( $xml->sheetData->row ) ) {
			return '';
		}

		$rows = array();
		foreach ( $xml->sheetData->row as $row ) {
			$row_cells = array();
			foreach ( $row->c as $cell ) {
				$type = (string) $cell['t'];
				$value = isset( $cell->v ) ? (string) $cell->v : '';

				if ( $type === 's' && isset( $shared_strings[ (int) $value ] ) ) {
					$cell_text = $shared_strings[ (int) $value ];
				} else {
					$cell_text = $value;
				}

				$row_cells[] = trim( $cell_text );
			}

			if ( array_filter( $row_cells ) ) {
				$rows[] = implode( " | ", $row_cells );
			}
		}

		return implode( "\n", $rows );
	}

	/**
	 * Parse PDF files using native PHP stream extraction & de-compression.
	 */
	private static function parse_pdf( $file_path ) {
		$content = file_get_contents( $file_path );
		if ( empty( $content ) ) {
			throw new Exception( 'File PDF kosong.' );
		}

		// Method A: Extract text from PDF streams
		$text = '';

		// Find streams
		preg_match_all( '/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $stream ) {
				// Decompress FlateDecode stream if possible
				$decompressed = @gzuncompress( $stream );
				if ( ! $decompressed ) {
					$decompressed = @gzinflate( $stream );
				}

				$data_to_parse = $decompressed ? $decompressed : $stream;

				// Extract Tj / TJ strings
				preg_match_all( '/\((.*?)\)\s*Tj/s', $data_to_parse, $tj_matches );
				if ( ! empty( $tj_matches[1] ) ) {
					foreach ( $tj_matches[1] as $str ) {
						$text .= self::clean_pdf_string( $str ) . ' ';
					}
					$text .= "\n";
				}

				preg_match_all( '/\[(.*?)\]\s*TJ/s', $data_to_parse, $tj_array_matches );
				if ( ! empty( $tj_array_matches[1] ) ) {
					foreach ( $tj_array_matches[1] as $arr ) {
						preg_match_all( '/\((.*?)\)/s', $arr, $sub_str );
						if ( ! empty( $sub_str[1] ) ) {
							foreach ( $sub_str[1] as $s ) {
								$text .= self::clean_pdf_string( $s );
							}
							$text .= ' ';
						}
					}
					$text .= "\n";
				}
			}
		}

		// Method B: Fallback regex for raw text enclosed in parentheses if stream parsing yielded little text
		if ( mb_strlen( trim( $text ) ) < 30 ) {
			preg_match_all( '/\((.*?)\)/', $content, $raw_matches );
			if ( ! empty( $raw_matches[1] ) ) {
				$raw_strings = array();
				foreach ( $raw_matches[1] as $str ) {
					$cleaned = self::clean_pdf_string( $str );
					if ( strlen( $cleaned ) > 3 && ! preg_match( '/^[\/\\0-9A-Z]+$/', $cleaned ) ) {
						$raw_strings[] = $cleaned;
					}
				}
				$text = implode( "\n", array_unique( $raw_strings ) );
			}
		}

		$cleaned_text = trim( preg_replace( '/[ \t]+/', ' ', $text ) );
		$lines = array_filter( array_map( 'trim', explode( "\n", $cleaned_text ) ) );

		if ( empty( $lines ) ) {
			throw new Exception( 'Gagal mengekstrak teks dari file PDF. Pastikan file PDF merupakan dokumen teks (bukan hasil scan foto/gambar).' );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Parse legacy .doc binary format fallback.
	 */
	private static function parse_doc_legacy( $file_path ) {
		$content = file_get_contents( $file_path );
		// Extract ASCII/UTF-8 printable characters from binary stream
		preg_match_all( '/[\x20-\x7E\x0A\x0D]{4,}/', $content, $matches );
		if ( ! empty( $matches[0] ) ) {
			$text = implode( "\n", array_map( 'trim', $matches[0] ) );
			return trim( $text );
		}
		throw new Exception( 'Format .doc lama tidak didukung secara penuh. Disarankan untuk menyimpan dokumen sebagai .docx terlebih dahulu.' );
	}

	/**
	 * Helper to clean PDF escaped strings.
	 */
	private static function clean_pdf_string( $str ) {
		$str = str_replace( array( '\\(', '\\)', '\\\\' ), array( '(', ')', '\\' ), $str );
		return preg_replace( '/[^\x20-\x7E\x0A\x0D]/', '', $str );
	}
}

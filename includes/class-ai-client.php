<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_AI_Client {
	
	public static function generate_content( $prompt, $ai_model_str ) {
		// $ai_model_str is format "provider:model" e.g., "openai:gpt-4o-mini"
		$parts = explode( ':', $ai_model_str );
		$provider = isset( $parts[0] ) ? $parts[0] : 'anthropic';
		$model    = isset( $parts[1] ) ? $parts[1] : 'claude-3-5-haiku-20241022';
		
		$max_tokens  = (int) get_option( 'aaag_max_tokens', 8192 );
		$temperature = (float) get_option( 'aaag_temperature', 0.7 );

		switch ( $provider ) {
			case 'openai':
				return self::generate_openai( $model, $prompt, $max_tokens, $temperature );
			case 'gemini':
				return self::generate_gemini( $model, $prompt, $max_tokens, $temperature );
			case 'anthropic':
			default:
				return self::generate_anthropic( $model, $prompt, $max_tokens, $temperature );
		}
	}

	private static function generate_anthropic( $model, $prompt, $max_tokens, $temperature ) {
		$api_key = get_option( 'aaag_api_key' );
		if ( empty( $api_key ) ) {
			throw new Exception( 'Anthropic API Key is missing. Silakan isi di menu Settings.' );
		}

		$url = 'https://api.anthropic.com/v1/messages';
		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'temperature'=> $temperature,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $prompt )
			)
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'timeout'     => 120,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'WP HTTP Error: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API Error';
			throw new Exception( "Anthropic API Error ($response_code): $error_msg" );
		}

		if ( ! isset( $data['content'][0]['text'] ) ) {
			throw new Exception( 'Invalid API response format from Anthropic.' );
		}
		
		if ( isset( $data['stop_reason'] ) && $data['stop_reason'] === 'max_tokens' ) {
			throw new Exception( 'Generation terpotong karena mencapai batas Max Tokens.' );
		}

		return $data['content'][0]['text'];
	}

	private static function generate_openai( $model, $prompt, $max_tokens, $temperature ) {
		$api_key = get_option( 'aaag_openai_api_key' );
		if ( empty( $api_key ) ) {
			throw new Exception( 'OpenAI API Key is missing. Silakan isi di menu Settings.' );
		}

		$url = 'https://api.openai.com/v1/chat/completions';
		
		// OpenAI standard max_tokens parameter handles output limit
		$body = array(
			'model'       => $model,
			'max_tokens'  => min($max_tokens, 16384),
			'messages'    => array(
				array( 'role' => 'user', 'content' => $prompt )
			)
		);
		
		// o1 and o3 models do not support temperature parameter
		if ( strpos( $model, 'o1' ) !== 0 && strpos( $model, 'o3' ) !== 0 ) {
			$body['temperature'] = $temperature;
		}

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout'     => 120,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'WP HTTP Error: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API Error';
			throw new Exception( "OpenAI API Error ($response_code): $error_msg" );
		}

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			throw new Exception( 'Invalid API response format from OpenAI.' );
		}

		if ( isset( $data['choices'][0]['finish_reason'] ) && $data['choices'][0]['finish_reason'] === 'length' ) {
			throw new Exception( 'Generation terpotong karena mencapai batas Max Tokens OpenAI.' );
		}

		return $data['choices'][0]['message']['content'];
	}

	private static function generate_gemini( $model, $prompt, $max_tokens, $temperature ) {
		$api_key = get_option( 'aaag_gemini_api_key' );
		if ( empty( $api_key ) ) {
			throw new Exception( 'Gemini API Key is missing. Silakan isi di menu Settings.' );
		}

		// Google Gemini API URL
		$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
		
		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt )
					)
				)
			),
			'generationConfig' => array(
				'temperature' => $temperature,
				'maxOutputTokens' => $max_tokens
			)
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 120,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'WP HTTP Error: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API Error';
			throw new Exception( "Gemini API Error ($response_code): $error_msg" );
		}

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			throw new Exception( 'Invalid API response format from Gemini.' );
		}

		if ( isset( $data['candidates'][0]['finishReason'] ) && $data['candidates'][0]['finishReason'] === 'MAX_TOKENS' ) {
			throw new Exception( 'Generation terpotong karena mencapai batas Max Tokens Gemini.' );
		}

		return $data['candidates'][0]['content']['parts'][0]['text'];
	}


	public static function test_anthropic_connection( $passed_key = '' ) {
		try {
			$api_key = ! empty( $passed_key ) ? trim( $passed_key ) : trim( get_option( 'aaag_api_key' ) );
			if ( empty( $api_key ) ) {
				update_option( 'aaag_anthropic_connected', 0 );
				update_option( 'aaag_verified_anthropic_models', array() );
				return array( 'success' => false, 'message' => 'API Key Anthropic belum diisi.' );
			}
			
			$all_supported = array(
				'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
				'claude-3-opus-20240229'     => 'Claude 3 Opus',
			);

			// 1. Primary Check: Official Anthropic /v1/models API endpoint
			$models_url = 'https://api.anthropic.com/v1/models';
			$response = wp_remote_get( $models_url, array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'timeout' => 12,
			) );

			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( $code === 200 && isset( $body['data'] ) && is_array( $body['data'] ) ) {
					$available_ids = array_column( $body['data'], 'id' );
					$verified = array();
					$labels   = array();
					foreach ( $all_supported as $mid => $lbl ) {
						if ( in_array( $mid, $available_ids, true ) ) {
							$verified[] = $mid;
							$labels[]   = $lbl;
						}
					}
					// If custom endpoint or version returned list, ensure verified list is populated
					if ( empty( $verified ) ) {
						$verified = array_keys( $all_supported );
						$labels   = array_values( $all_supported );
					}
					update_option( 'aaag_anthropic_connected', 1 );
					update_option( 'aaag_verified_anthropic_models', $verified );
					return array(
						'success' => true,
						'message' => 'Anthropic API Terhubung! Model yang aktif: ' . implode( ', ', $labels )
					);
				} elseif ( $code === 401 ) {
					update_option( 'aaag_anthropic_connected', 0 );
					update_option( 'aaag_verified_anthropic_models', array() );
					return array( 'success' => false, 'message' => 'Anthropic API Key tidak valid (401 Unauthorized).' );
				}
			}

			// 2. Fallback Check: Lightweight message test probe
			$test_models = array( 'claude-3-haiku-20240307', 'claude-3-5-sonnet-20241022', 'claude-3-7-sonnet-20250219' );
			$connected = false;
			$last_error = '';

			foreach ( $test_models as $m ) {
				$msg_resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
					'body'    => wp_json_encode( array(
						'model'      => $m,
						'max_tokens' => 1,
						'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) )
					) ),
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'content-type'      => 'application/json',
					),
					'timeout' => 8,
				) );

				if ( ! is_wp_error( $msg_resp ) ) {
					$c = wp_remote_retrieve_response_code( $msg_resp );
					if ( $c === 200 || $c === 429 ) {
						$connected = true;
						break;
					} elseif ( $c === 401 ) {
						update_option( 'aaag_anthropic_connected', 0 );
						update_option( 'aaag_verified_anthropic_models', array() );
						return array( 'success' => false, 'message' => 'Anthropic API Key tidak valid (401 Unauthorized).' );
					} else {
						$b = json_decode( wp_remote_retrieve_body( $msg_resp ), true );
						$last_error = isset( $b['error']['message'] ) ? $b['error']['message'] : "HTTP $c";
					}
				}
			}

			if ( $connected ) {
				update_option( 'aaag_anthropic_connected', 1 );
				update_option( 'aaag_verified_anthropic_models', array_keys( $all_supported ) );
				return array(
					'success' => true,
					'message' => 'Anthropic API Terhubung! Model Anthropic Claude siap digunakan.'
				);
			}

			update_option( 'aaag_anthropic_connected', 0 );
			update_option( 'aaag_verified_anthropic_models', array() );
			return array( 'success' => false, 'message' => 'Gagal terhubung ke Anthropic: ' . ( $last_error ? $last_error : 'Tidak ada respon dari server.' ) );
		} catch (Exception $e) {
			update_option( 'aaag_anthropic_connected', 0 );
			update_option( 'aaag_verified_anthropic_models', array() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	public static function test_openai_connection( $passed_key = '' ) {
		try {
			$api_key = ! empty( $passed_key ) ? trim( $passed_key ) : trim( get_option( 'aaag_openai_api_key' ) );
			if ( empty( $api_key ) ) {
				update_option( 'aaag_openai_connected', 0 );
				update_option( 'aaag_verified_openai_models', array() );
				return array( 'success' => false, 'message' => 'API Key OpenAI belum diisi.' );
			}
			
			$all_supported = array(
				'gpt-4o-mini'        => 'GPT-4o Mini',
				'gpt-4o'             => 'GPT-4o',
				'chatgpt-4o-latest'  => 'ChatGPT-4o Latest',
				'o3-mini'            => 'o3-mini',
				'o1-mini'            => 'o1-mini',
				'o1'                 => 'o1'
			);

			$models_url = 'https://api.openai.com/v1/models';
			$response = wp_remote_get( $models_url, array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 12,
			) );

			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( $code === 200 && isset( $body['data'] ) && is_array( $body['data'] ) ) {
					$available_ids = array_column( $body['data'], 'id' );
					$verified = array();
					$labels   = array();
					foreach ( $all_supported as $mid => $lbl ) {
						if ( in_array( $mid, $available_ids, true ) ) {
							$verified[] = $mid;
							$labels[]   = $lbl;
						}
					}
					if ( empty( $verified ) ) {
						$verified = array_keys( $all_supported );
						$labels   = array_values( $all_supported );
					}
					update_option( 'aaag_openai_connected', 1 );
					update_option( 'aaag_verified_openai_models', $verified );
					return array(
						'success' => true,
						'message' => 'OpenAI API Terhubung! Model yang aktif: ' . implode( ', ', $labels )
					);
				} elseif ( $code === 401 ) {
					update_option( 'aaag_openai_connected', 0 );
					update_option( 'aaag_verified_openai_models', array() );
					return array( 'success' => false, 'message' => 'OpenAI API Key tidak valid (401 Unauthorized).' );
				}
			}

			update_option( 'aaag_openai_connected', 0 );
			update_option( 'aaag_verified_openai_models', array() );
			return array( 'success' => false, 'message' => 'Gagal terhubung ke server OpenAI.' );
		} catch (Exception $e) {
			update_option( 'aaag_openai_connected', 0 );
			update_option( 'aaag_verified_openai_models', array() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	public static function test_gemini_connection( $passed_key = '' ) {
		try {
			$api_key = ! empty( $passed_key ) ? trim( $passed_key ) : trim( get_option( 'aaag_gemini_api_key' ) );
			if ( empty( $api_key ) ) {
				update_option( 'aaag_gemini_connected', 0 );
				update_option( 'aaag_verified_gemini_models', array() );
				return array( 'success' => false, 'message' => 'API Key Gemini belum diisi.' );
			}
			
			$all_supported = array(
				'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
				'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
				'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
				'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
				'gemini-1.5-flash'      => 'Gemini 1.5 Flash',
				'gemini-1.5-pro'        => 'Gemini 1.5 Pro'
			);

			$models_url = "https://generativelanguage.googleapis.com/v1beta/models?key={$api_key}";
			$response = wp_remote_get( $models_url, array(
				'timeout' => 12,
			) );

			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( $code === 200 && isset( $body['models'] ) && is_array( $body['models'] ) ) {
					$raw_names = array_column( $body['models'], 'name' );
					$available_ids = array_map( function( $name ) {
						return str_replace( 'models/', '', $name );
					}, $raw_names );

					$verified = array();
					$labels   = array();
					foreach ( $all_supported as $mid => $lbl ) {
						if ( in_array( $mid, $available_ids, true ) ) {
							$verified[] = $mid;
							$labels[]   = $lbl;
						}
					}
					if ( empty( $verified ) ) {
						$verified = array_keys( $all_supported );
						$labels   = array_values( $all_supported );
					}
					update_option( 'aaag_gemini_connected', 1 );
					update_option( 'aaag_verified_gemini_models', $verified );
					return array(
						'success' => true,
						'message' => 'Gemini API Terhubung! Model yang aktif: ' . implode( ', ', $labels )
					);
				} elseif ( $code === 400 || $code === 403 ) {
					$err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'API Key tidak valid.';
					update_option( 'aaag_gemini_connected', 0 );
					update_option( 'aaag_verified_gemini_models', array() );
					return array( 'success' => false, 'message' => "Gemini API Error ($code): $err" );
				}
			}

			update_option( 'aaag_gemini_connected', 0 );
			update_option( 'aaag_verified_gemini_models', array() );
			return array( 'success' => false, 'message' => 'Gagal terhubung ke server Google Gemini.' );
		} catch (Exception $e) {
			update_option( 'aaag_gemini_connected', 0 );
			update_option( 'aaag_verified_gemini_models', array() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}
}

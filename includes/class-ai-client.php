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
			$api_key = ! empty( $passed_key ) ? $passed_key : get_option( 'aaag_api_key' );
			if ( empty( $api_key ) ) {
				update_option( 'aaag_anthropic_connected', 0 );
				update_option( 'aaag_verified_anthropic_models', array() );
				return array( 'success' => false, 'message' => 'API Key Anthropic belum diisi.' );
			}
			
			$all_models = array(
				'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
				'claude-3-opus-20240229'     => 'Claude 3 Opus',
			);

			$verified_models = array();
			$verified_labels = array();
			$last_error = '';

			foreach ( $all_models as $model_id => $label ) {
				$url = 'https://api.anthropic.com/v1/messages';
				$body = array(
					'model'      => $model_id,
					'max_tokens' => 5,
					'messages'   => array(
						array( 'role' => 'user', 'content' => 'Hi' )
					)
				);
				$args = array(
					'body'    => wp_json_encode( $body ),
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'content-type'      => 'application/json',
					),
					'timeout' => 8,
				);
				
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					$last_error = $response->get_error_message();
					continue;
				}
				
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$body_data     = json_decode( $response_body, true );
				
				if ( $response_code === 200 || $response_code === 429 ) {
					$verified_models[] = $model_id;
					$verified_labels[] = $label;
				} elseif ( $response_code === 401 ) {
					// Invalid API key for all models
					update_option( 'aaag_anthropic_connected', 0 );
					update_option( 'aaag_verified_anthropic_models', array() );
					return array( 'success' => false, 'message' => 'Anthropic API Key tidak valid (401 Unauthorized).' );
				} else {
					$last_error = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : "HTTP $response_code";
				}
			}
			
			if ( ! empty( $verified_models ) ) {
				update_option( 'aaag_anthropic_connected', 1 );
				update_option( 'aaag_verified_anthropic_models', $verified_models );
				return array( 
					'success' => true, 
					'message' => 'Anthropic API Terhubung! Model yang aktif di akun Anda: ' . implode( ', ', $verified_labels ) 
				);
			} else {
				update_option( 'aaag_anthropic_connected', 0 );
				update_option( 'aaag_verified_anthropic_models', array() );
				return array( 'success' => false, 'message' => "Gagal terhubung ke Anthropic: $last_error" );
			}
		} catch (Exception $e) {
			update_option( 'aaag_anthropic_connected', 0 );
			update_option( 'aaag_verified_anthropic_models', array() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	public static function test_openai_connection( $passed_key = '' ) {
		try {
			$api_key = ! empty( $passed_key ) ? $passed_key : get_option( 'aaag_openai_api_key' );
			if ( empty( $api_key ) ) {
				update_option( 'aaag_openai_connected', 0 );
				update_option( 'aaag_verified_openai_models', array() );
				return array( 'success' => false, 'message' => 'API Key OpenAI belum diisi.' );
			}
			
			$all_models = array(
				'gpt-4o-mini'        => 'GPT-4o Mini',
				'gpt-4o'             => 'GPT-4o',
				'chatgpt-4o-latest'  => 'ChatGPT-4o Latest',
				'o3-mini'            => 'o3-mini',
				'o1-mini'            => 'o1-mini',
				'o1'                 => 'o1'
			);

			$verified_models = array();
			$verified_labels = array();
			$last_error = '';

			foreach ( $all_models as $model_id => $label ) {
				$url = 'https://api.openai.com/v1/chat/completions';
				$body = array(
					'model'      => $model_id,
					'max_tokens' => 5,
					'messages'   => array(
						array( 'role' => 'user', 'content' => 'Hi' )
					)
				);
				$args = array(
					'body'    => wp_json_encode( $body ),
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'timeout' => 8,
				);
				
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					$last_error = $response->get_error_message();
					continue;
				}
				
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$body_data     = json_decode( $response_body, true );
				
				if ( $response_code === 200 || $response_code === 429 ) {
					$verified_models[] = $model_id;
					$verified_labels[] = $label;
				} elseif ( $response_code === 401 ) {
					update_option( 'aaag_openai_connected', 0 );
					update_option( 'aaag_verified_openai_models', array() );
					return array( 'success' => false, 'message' => 'OpenAI API Key tidak valid (401 Unauthorized).' );
				} else {
					$last_error = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : "HTTP $response_code";
				}
			}

			if ( ! empty( $verified_models ) ) {
				update_option( 'aaag_openai_connected', 1 );
				update_option( 'aaag_verified_openai_models', $verified_models );
				return array( 
					'success' => true, 
					'message' => 'OpenAI API Terhubung! Model yang aktif di akun Anda: ' . implode( ', ', $verified_labels ) 
				);
			} else {
				update_option( 'aaag_openai_connected', 0 );
				update_option( 'aaag_verified_openai_models', array() );
				return array( 'success' => false, 'message' => "Gagal terhubung ke OpenAI: $last_error" );
			}
		} catch (Exception $e) {
			update_option( 'aaag_openai_connected', 0 );
			update_option( 'aaag_verified_openai_models', array() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	public static function test_gemini_connection( $passed_key = '' ) {
		try {
			$api_key = ! empty( $passed_key ) ? $passed_key : get_option( 'aaag_gemini_api_key' );
			if ( empty( $api_key ) ) {
				update_option( 'aaag_gemini_connected', 0 );
				update_option( 'aaag_verified_gemini_models', array() );
				return array( 'success' => false, 'message' => 'API Key Gemini belum diisi.' );
			}
			
			$all_models = array(
				'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
				'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
				'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
				'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
				'gemini-1.5-flash'      => 'Gemini 1.5 Flash',
				'gemini-1.5-pro'        => 'Gemini 1.5 Pro'
			);

			$verified_models = array();
			$verified_labels = array();
			$last_error = '';

			foreach ( $all_models as $model_id => $label ) {
				$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_id}:generateContent?key={$api_key}";
				$body = array(
					'contents' => array(
						array(
							'parts' => array(
								array( 'text' => 'Hi' )
							)
						)
					),
					'generationConfig' => array(
						'maxOutputTokens' => 5
					)
				);
				$args = array(
					'body'    => wp_json_encode( $body ),
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'timeout' => 8,
				);
				
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					$last_error = $response->get_error_message();
					continue;
				}
				
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$body_data     = json_decode( $response_body, true );
				
				if ( $response_code === 200 || $response_code === 429 ) {
					$verified_models[] = $model_id;
					$verified_labels[] = $label;
				} elseif ( $response_code === 400 || $response_code === 403 ) {
					if ( isset( $body_data['error']['status'] ) && $body_data['error']['status'] === 'PERMISSION_DENIED' ) {
						update_option( 'aaag_gemini_connected', 0 );
						update_option( 'aaag_verified_gemini_models', array() );
						return array( 'success' => false, 'message' => 'Gemini API Key tidak valid atau tidak memiliki izin akses.' );
					}
					$last_error = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : "HTTP $response_code";
				} else {
					$last_error = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : "HTTP $response_code";
				}
			}

			if ( ! empty( $verified_models ) ) {
				update_option( 'aaag_gemini_connected', 1 );
				update_option( 'aaag_verified_gemini_models', $verified_models );
				return array( 
					'success' => true, 
					'message' => 'Gemini API Terhubung! Model yang aktif di akun Anda: ' . implode( ', ', $verified_labels ) 
				);
			} else {
				update_option( 'aaag_gemini_connected', 0 );
				update_option( 'aaag_verified_gemini_models', array() );
				return array( 'success' => false, 'message' => "Gagal terhubung ke Gemini: $last_error" );
			}
		} catch (Exception $e) {
			update_option( 'aaag_gemini_connected', 0 );
			update_option( 'aaag_verified_gemini_models', array() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}
}

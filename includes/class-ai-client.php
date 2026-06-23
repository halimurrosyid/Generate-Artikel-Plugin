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

	public static function test_anthropic_connection() {
		try {
			$api_key = get_option( 'aaag_api_key' );
			if ( empty( $api_key ) ) {
				return array( 'success' => false, 'message' => 'API Key is missing.' );
			}
			
			$models_to_try = array(
				'claude-3-5-haiku-20241022',
				'claude-3-5-sonnet-20241022',
				'claude-3-7-sonnet-20250219',
				'claude-3-opus-20240229',
				'claude-3-haiku-20240307'
			);

			$last_error = '';
			foreach ( $models_to_try as $model ) {
				$url = 'https://api.anthropic.com/v1/messages';
				$body = array(
					'model'      => $model,
					'max_tokens' => 10,
					'messages'   => array(
						array( 'role' => 'user', 'content' => 'Hello' )
					)
				);
				$args = array(
					'body'    => wp_json_encode( $body ),
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'content-type'      => 'application/json',
					),
					'timeout' => 15,
				);
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					return array( 'success' => false, 'message' => $response->get_error_message() );
				}
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				if ( $response_code === 200 ) {
					return array( 'success' => true, 'message' => "Connection successful! (Model tested: $model)" );
				} else {
					$body_data = json_decode( $response_body, true );
					$err = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : 'Unknown error';
					$last_error = "API Error ($response_code) for $model: $err (Raw: " . esc_html(substr($response_body, 0, 300)) . ")";
					// If not a 404 (model not found/not enabled for user key), break because key or other settings are incorrect.
					if ( $response_code !== 404 ) {
						break;
					}
				}
			}
			return array( 'success' => false, 'message' => $last_error );
		} catch (Exception $e) {
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	public static function test_openai_connection() {
		try {
			$api_key = get_option( 'aaag_openai_api_key' );
			if ( empty( $api_key ) ) {
				return array( 'success' => false, 'message' => 'OpenAI API Key is missing.' );
			}
			
			$models_to_try = array(
				'gpt-4o-mini',
				'gpt-4o'
			);

			$last_error = '';
			foreach ( $models_to_try as $model ) {
				$url = 'https://api.openai.com/v1/chat/completions';
				$body = array(
					'model'      => $model,
					'max_tokens' => 10,
					'messages'   => array(
						array( 'role' => 'user', 'content' => 'Hello' )
					)
				);
				$args = array(
					'body'    => wp_json_encode( $body ),
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'timeout' => 15,
				);
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					return array( 'success' => false, 'message' => $response->get_error_message() );
				}
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				if ( $response_code === 200 ) {
					return array( 'success' => true, 'message' => "Connection successful! (Model tested: $model)" );
				} else {
					$body_data = json_decode( $response_body, true );
					$err = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : 'Unknown error';
					$last_error = "API Error ($response_code) for $model: $err (Raw: " . esc_html(substr($response_body, 0, 300)) . ")";
					if ( $response_code !== 404 ) {
						break;
					}
				}
			}
			return array( 'success' => false, 'message' => $last_error );
		} catch (Exception $e) {
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	public static function test_gemini_connection() {
		try {
			$api_key = get_option( 'aaag_gemini_api_key' );
			if ( empty( $api_key ) ) {
				return array( 'success' => false, 'message' => 'Gemini API Key is missing.' );
			}
			
			$models_to_try = array(
				'gemini-1.5-flash',
				'gemini-1.5-pro',
				'gemini-2.0-flash'
			);

			$last_error = '';
			foreach ( $models_to_try as $model ) {
				$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
				$body = array(
					'contents' => array(
						array(
							'parts' => array(
								array( 'text' => 'Hello' )
							)
						)
					),
					'generationConfig' => array(
						'maxOutputTokens' => 10
					)
				);
				$args = array(
					'body'    => wp_json_encode( $body ),
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'timeout' => 15,
				);
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					return array( 'success' => false, 'message' => $response->get_error_message() );
				}
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				if ( $response_code === 200 ) {
					return array( 'success' => true, 'message' => "Connection successful! (Model tested: $model)" );
				} else {
					$body_data = json_decode( $response_body, true );
					$err = isset( $body_data['error']['message'] ) ? $body_data['error']['message'] : 'Unknown error';
					$last_error = "API Error ($response_code) for $model: $err (Raw: " . esc_html(substr($response_body, 0, 300)) . ")";
					if ( $response_code !== 404 ) {
						break;
					}
				}
			}
			return array( 'success' => false, 'message' => $last_error );
		} catch (Exception $e) {
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}
}

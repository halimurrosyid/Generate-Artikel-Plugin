<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAAG_Updater {
	private $plugin_file;
	private $plugin_slug;
	private $current_version;
	private $update_url = 'https://raw.githubusercontent.com/halimurrosyid/Generate-Artikel-Plugin/main/update.json';

	public function __construct( $plugin_file, $current_version ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );
		$this->current_version = $current_version;

		// Hook into both transient set and get filters for instant detection
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup_info' ), 20, 3 );
	}

	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		try {
			// Fetch update information from GitHub raw json (with cache busting)
			$check_url = add_query_arg( 't', time(), $this->update_url );
			$response  = wp_remote_get( $check_url, array( 
				'timeout'    => 10,
				'sslverify'  => false // Allow checking even if server has SSL resolution issues
			) );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return $transient;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $data ) || ! isset( $data['version'] ) || ! isset( $data['package'] ) ) {
				return $transient;
			}

			// If new version is higher, inject update info
			if ( version_compare( $this->current_version, $data['version'], '<' ) ) {
				$slug_dir = dirname( $this->plugin_slug );
				if ( '.' === $slug_dir || empty( $slug_dir ) ) {
					$slug_dir = 'indahweb-ai-auto-article';
				}

				$obj = new stdClass();
				$obj->slug        = $slug_dir;
				$obj->plugin      = $this->plugin_slug;
				$obj->new_version = $data['version'];
				$obj->tested      = '6.7';
				$obj->package     = $data['package'];
				$obj->url         = 'https://github.com/halimurrosyid/Generate-Artikel-Plugin';

				if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
					$transient->response = array();
				}
				$transient->response[ $this->plugin_slug ] = $obj;
			}
		} catch ( Exception $e ) {
			// Fail silently to prevent critical errors on user site
		}

		return $transient;
	}

	public function plugin_popup_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( isset( $args->slug ) && $args->slug === 'indahweb-ai-auto-article' ) {
			try {
				$response = wp_remote_get( $this->update_url, array( 'timeout' => 5, 'sslverify' => false ) );
				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( ! empty( $data ) ) {
						$res = new stdClass();
						$res->name           = 'AI Auto Article Generator';
						$res->slug           = 'indahweb-ai-auto-article';
						$res->version        = $data['version'];
						$res->author         = 'Mujaddid Halimurrosyid';
						$res->homepage       = 'https://indahweb.com';
						$res->download_link  = $data['package'];
						$res->sections       = array(
							'description' => 'Generates automatic articles using AI based on provided titles, templates, and knowledge bases.',
							'changelog'   => isset( $data['changelog'] ) ? $data['changelog'] : 'New updates and stability enhancements.'
						);
						return $res;
					}
				}
			} catch ( Exception $e ) {
				// Fail silently
			}
		}

		return $result;
	}
}

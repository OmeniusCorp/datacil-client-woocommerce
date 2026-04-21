<?php
/**
 * Endpoints AJAX: bridge entre frontend JS y cliente PHP.
 *
 * Defensa: cada endpoint exige nonce (anti-CSRF WP) y, cuando aplica,
 * capability. La API key de Datacil nunca viaja al browser.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Datacil_Ajax {

	public static function init() {
		// Checkout/public: permitido para usuarios no logueados (woocommerce_nopriv).
		add_action( 'wp_ajax_datacil_validate',        array( __CLASS__, 'validate' ) );
		add_action( 'wp_ajax_nopriv_datacil_validate', array( __CLASS__, 'validate' ) );

		// Admin only: dashboard.
		add_action( 'wp_ajax_datacil_credits',         array( __CLASS__, 'credits' ) );
		add_action( 'wp_ajax_datacil_credits_history', array( __CLASS__, 'credits_history' ) );
		add_action( 'wp_ajax_datacil_costs',           array( __CLASS__, 'costs' ) );
	}

	/**
	 * Valida cedula/RUC. Nonce scoped a "datacil_validate".
	 * Rate-limit basico por IP via transients (5 por minuto).
	 */
	public static function validate() {
		check_ajax_referer( 'datacil_validate', 'nonce' );

		$ip  = self::client_ip();
		$key = 'datacil_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 30 ) {
			wp_send_json( array(
				'success' => false,
				'message' => __( 'Demasiadas validaciones. Espera un minuto.', 'datacil-woocommerce' ),
			), 429 );
		}
		set_transient( $key, $hits + 1, 60 );

		$vat = isset( $_POST['vat'] ) ? sanitize_text_field( wp_unslash( $_POST['vat'] ) ) : '';
		$client = new Datacil_Client();
		$result = $client->validate_vat( $vat );

		wp_send_json( $result );
	}

	public static function credits() {
		self::require_admin_capability();
		check_ajax_referer( 'datacil_admin', 'nonce' );
		$client = new Datacil_Client();
		wp_send_json( $client->get_credits() );
	}

	public static function credits_history() {
		self::require_admin_capability();
		check_ajax_referer( 'datacil_admin', 'nonce' );
		$client = new Datacil_Client();
		wp_send_json( $client->get_credits_history() );
	}

	public static function costs() {
		self::require_admin_capability();
		check_ajax_referer( 'datacil_admin', 'nonce' );
		$client = new Datacil_Client();
		wp_send_json( $client->get_costs() );
	}

	private static function require_admin_capability() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json( array(
				'success' => false,
				'message' => __( 'No autorizado.', 'datacil-woocommerce' ),
			), 403 );
		}
	}

	private static function client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return trim( $parts[0] );
		}
		return (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
	}
}

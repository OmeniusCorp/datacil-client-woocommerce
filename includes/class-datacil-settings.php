<?php
/**
 * Pagina de ajustes de Datacil en WooCommerce.
 * Pestaña integrada como tab en WC → Ajustes.
 *
 * La subclase de `WC_Settings_Page` se carga LAZY dentro del callback del
 * filtro `woocommerce_get_settings_pages`. Si se declara a nivel de
 * `require_once`, WP fatalea porque WC solo carga su clase base cuando
 * construye la pagina de ajustes (no en plugins_loaded).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Datacil_Settings {

	const OPTION_KEY = 'datacil_wc_settings';

	public static function init() {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'register_page' ) );
	}

	public static function register_page( $pages ) {
		// WC solo carga WC_Settings_Page cuando va a renderizar settings.
		// Aqui ya esta disponible. Evita fatal si WC no esta cargado aun.
		if ( ! class_exists( 'WC_Settings_Page', false ) ) {
			return $pages;
		}
		if ( ! class_exists( 'Datacil_Settings_Page', false ) ) {
			require_once DATACIL_WC_PATH . 'includes/class-datacil-settings-page.php';
		}
		$pages[] = new Datacil_Settings_Page();
		return $pages;
	}

	/**
	 * Sanitiza el array de ajustes antes de persistir.
	 */
	public static function sanitize( $input ) {
		$out = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $input ) ) $input = array();

		if ( isset( $input['api_url'] ) )     $out['api_url']     = esc_url_raw( trim( (string) $input['api_url'] ) );
		if ( isset( $input['api_key'] ) )     $out['api_key']     = sanitize_text_field( trim( (string) $input['api_key'] ) );
		if ( isset( $input['api_version'] ) ) $out['api_version'] = sanitize_text_field( (string) $input['api_version'] );
		if ( isset( $input['api_country'] ) ) $out['api_country'] = sanitize_text_field( (string) $input['api_country'] );
		if ( isset( $input['api_timeout'] ) ) $out['api_timeout'] = max( 1, min( 60, (int) $input['api_timeout'] ) );

		// Checkboxes: si el input no viene (unchecked), setear 'no'.
		$out['autofill']       = ! empty( $input['autofill'] ) ? 'yes' : 'no';
		$out['block_existing'] = ! empty( $input['block_existing'] ) ? 'yes' : 'no';

		return $out;
	}
}

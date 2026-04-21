<?php
/**
 * Plugin Name:       Datacil for WooCommerce
 * Plugin URI:        https://datacil.com
 * Description:       Valida cedulas y RUC de Ecuador en WooCommerce. Auto-completa datos del cliente desde el SRI/Registro Civil. Dashboard de creditos.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Datacil
 * Author URI:        https://datacil.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       datacil-woocommerce
 * Domain Path:       /languages
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 */

// Bloqueo de acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DATACIL_WC_VERSION', '1.0.0' );
define( 'DATACIL_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DATACIL_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'DATACIL_WC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Arranque del plugin: exige WooCommerce activo antes de cargar clases.
 * Prioridad 20 en plugins_loaded para asegurar que WC (prioridad 10) ya
 * registro sus autoloads. La subclase `WC_Settings_Page` se carga lazy
 * dentro del filtro `woocommerce_get_settings_pages` (ver settings.php).
 */
add_action( 'plugins_loaded', 'datacil_wc_bootstrap', 20 );

function datacil_wc_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'datacil_wc_missing_woocommerce_notice' );
		return;
	}

	require_once DATACIL_WC_PATH . 'includes/class-datacil-client.php';
	require_once DATACIL_WC_PATH . 'includes/class-datacil-settings.php';
	require_once DATACIL_WC_PATH . 'includes/class-datacil-dashboard.php';
	require_once DATACIL_WC_PATH . 'includes/class-datacil-ajax.php';
	require_once DATACIL_WC_PATH . 'includes/class-datacil-checkout.php';
	require_once DATACIL_WC_PATH . 'includes/class-datacil-blocks.php';

	Datacil_Settings::init();
	Datacil_Dashboard::init();
	Datacil_Ajax::init();
	Datacil_Checkout::init();
	Datacil_Blocks::init();
}

/**
 * Traducciones: cargar en `init` para evitar warning
 * `_load_textdomain_just_in_time was called incorrectly` (WP 6.7+).
 */
add_action( 'init', 'datacil_wc_load_textdomain' );

function datacil_wc_load_textdomain() {
	load_plugin_textdomain( 'datacil-woocommerce', false, dirname( DATACIL_WC_BASENAME ) . '/languages' );
}

function datacil_wc_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Datacil for WooCommerce requiere WooCommerce activo.', 'datacil-woocommerce' );
	echo '</p></div>';
}

/**
 * Hook de activacion. Setea defaults si no existen.
 */
register_activation_hook( __FILE__, 'datacil_wc_activate' );

function datacil_wc_activate() {
	$defaults = array(
		'api_url'       => 'https://api.datacil.com',
		'api_version'   => 'v1',
		'api_country'   => 'ecuador',
		'api_timeout'   => 10,
		'api_key'       => '',
		'autofill'      => 'yes',
		'block_existing' => 'no',
	);
	if ( false === get_option( 'datacil_wc_settings' ) ) {
		add_option( 'datacil_wc_settings', $defaults );
	}
}

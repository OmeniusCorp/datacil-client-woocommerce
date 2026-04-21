<?php
/**
 * Subclase WC_Settings_Page. Cargada LAZY desde class-datacil-settings.php
 * para evitar fatal si WC no ha cargado su clase base todavia.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'WC_Settings_Page', false ) ) return;

class Datacil_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'datacil';
		$this->label = __( 'Datacil', 'datacil-woocommerce' );
		parent::__construct();
	}

	public function get_settings( $current_section = '' ) {
		$settings = get_option( Datacil_Settings::OPTION_KEY, array() );

		return array(
			array(
				'title' => __( 'Datacil — Validacion de Identificacion EC', 'datacil-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Conecta tu cuenta para validar cedulas y RUC. Consume creditos de tu plan Datacil.', 'datacil-woocommerce' ),
				'id'    => 'datacil_wc_title',
			),
			array(
				'title'    => __( 'API URL', 'datacil-woocommerce' ),
				'type'     => 'text',
				'id'       => Datacil_Settings::OPTION_KEY . '[api_url]',
				'default'  => 'https://api.datacil.com',
				'value'    => $settings['api_url'] ?? 'https://api.datacil.com',
				'desc_tip' => __( 'URL base del servicio Datacil.', 'datacil-woocommerce' ),
			),
			array(
				'title'    => __( 'API Key', 'datacil-woocommerce' ),
				'type'     => 'password',
				'id'       => Datacil_Settings::OPTION_KEY . '[api_key]',
				'value'    => $settings['api_key'] ?? '',
				'desc_tip' => __( 'Crea la key en el panel Datacil. Si limitas la key a origenes, incluye el dominio de tu tienda.', 'datacil-woocommerce' ),
			),
			array(
				'title'   => __( 'Version API', 'datacil-woocommerce' ),
				'type'    => 'select',
				'id'      => Datacil_Settings::OPTION_KEY . '[api_version]',
				'options' => array( 'v1' => 'v1' ),
				'value'   => $settings['api_version'] ?? 'v1',
				'default' => 'v1',
			),
			array(
				'title'   => __( 'Pais', 'datacil-woocommerce' ),
				'type'    => 'select',
				'id'      => Datacil_Settings::OPTION_KEY . '[api_country]',
				'options' => array( 'ecuador' => 'Ecuador' ),
				'value'   => $settings['api_country'] ?? 'ecuador',
				'default' => 'ecuador',
			),
			array(
				'title'             => __( 'Timeout (segundos)', 'datacil-woocommerce' ),
				'type'              => 'number',
				'id'                => Datacil_Settings::OPTION_KEY . '[api_timeout]',
				'value'             => $settings['api_timeout'] ?? 10,
				'default'           => 10,
				'custom_attributes' => array( 'min' => 1, 'max' => 60 ),
			),
			array(
				'title'   => __( 'Auto-completar en checkout', 'datacil-woocommerce' ),
				'type'    => 'checkbox',
				'id'      => Datacil_Settings::OPTION_KEY . '[autofill]',
				'value'   => $settings['autofill'] ?? 'yes',
				'default' => 'yes',
				'desc'    => __( 'Muestra un boton "Validar" junto al campo de identificacion y rellena direccion/contacto.', 'datacil-woocommerce' ),
			),
			array(
				'title'   => __( 'Bloquear identificacion ya registrada', 'datacil-woocommerce' ),
				'type'    => 'checkbox',
				'id'      => Datacil_Settings::OPTION_KEY . '[block_existing]',
				'value'   => $settings['block_existing'] ?? 'no',
				'default' => 'no',
				'desc'    => __( 'Si otro cliente ya tiene esa cedula/RUC, evita crear duplicados.', 'datacil-woocommerce' ),
			),
			array( 'type' => 'sectionend', 'id' => 'datacil_wc_title' ),
		);
	}

	/**
	 * Guarda ajustes. WC postea los campos con `name="datacil_wc_settings[X]"`,
	 * PHP los colapsa en `$_POST['datacil_wc_settings']` como array asociativo.
	 */
	public function save() {
		$raw = isset( $_POST[ Datacil_Settings::OPTION_KEY ] )
			? wp_unslash( $_POST[ Datacil_Settings::OPTION_KEY ] )
			: array();
		update_option(
			Datacil_Settings::OPTION_KEY,
			Datacil_Settings::sanitize( $raw )
		);
	}
}

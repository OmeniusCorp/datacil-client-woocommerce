<?php
/**
 * Integracion con WooCommerce Blocks Checkout (Gutenberg).
 *
 * El hook `woocommerce_billing_fields` NO aplica al bloque — requiere la
 * API `woocommerce_register_additional_checkout_field` (WC 8.9+).
 *
 * Limitaciones: el boton "Validar" inline y autofill avanzado solo
 * funcionan en el checkout clasico (shortcode). En Blocks solo se
 * captura y valida el VAT; para autofill manual el cliente debe
 * escribir los datos o la tienda debe usar el shortcode clasico.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Datacil_Blocks {

	/** ID del campo en el namespace de la tienda. */
	const FIELD_ID = 'datacil/vat';

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'register_field' ) );

		// Copia el campo del address a meta `_billing_vat` para que el
		// resto del plugin (admin, emails, columnas) lo encuentre
		// en el mismo key usado por el flujo clasico.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'mirror_to_billing_meta' ), 10, 2 );
	}

	public static function register_field() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			// WC < 8.9: no esta disponible la API. El campo solo existira
			// en checkout clasico (woocommerce_billing_fields).
			return;
		}

		$settings = get_option( Datacil_Settings::OPTION_KEY, array() );
		if ( ( $settings['autofill'] ?? 'yes' ) !== 'yes' ) return;

		woocommerce_register_additional_checkout_field( array(
			'id'                => self::FIELD_ID,
			'label'             => __( 'Cedula o RUC', 'datacil-woocommerce' ),
			'location'          => 'address', // billing + shipping address forms
			'type'              => 'text',
			'required'          => false,      // condicional por pais (validate_callback)
			'attributes'        => array(
				'autocomplete' => 'off',
				'maxlength'    => '13',
				'pattern'      => '\d{10}|\d{13}',
				'placeholder'  => '0102030405',
			),
			'sanitize_callback' => array( __CLASS__, 'sanitize_vat' ),
			'validate_callback' => array( __CLASS__, 'validate_vat' ),
		) );
	}

	public static function sanitize_vat( $field_value ) {
		return preg_replace( '/\D/', '', (string) $field_value );
	}

	/**
	 * Exige 10/13 digitos solo cuando el address es de Ecuador.
	 * Si el cliente factura desde otro pais, el campo es opcional.
	 */
	public static function validate_vat( $field_value, $fields ) {
		$country = isset( $fields['country'] ) ? strtoupper( (string) $fields['country'] ) : '';
		if ( 'EC' !== $country ) return true;

		$v = preg_replace( '/\D/', '', (string) $field_value );
		if ( empty( $v ) ) {
			return new WP_Error(
				'datacil_vat_required',
				__( 'Ingresa tu cedula o RUC para facturacion en Ecuador.', 'datacil-woocommerce' )
			);
		}
		if ( 10 !== strlen( $v ) && 13 !== strlen( $v ) ) {
			return new WP_Error(
				'datacil_vat_invalid',
				__( 'La cedula debe tener 10 digitos o el RUC 13.', 'datacil-woocommerce' )
			);
		}
		return true;
	}

	/**
	 * Tras crear la orden desde Blocks, copiamos el VAT del address a
	 * `_billing_vat` para unificar storage entre bloque y clasico.
	 *
	 * @param \WC_Order $order
	 */
	public static function mirror_to_billing_meta( $order, $request ) {
		try {
			$billing = $order->get_meta( '_wc_billing', true );
			if ( is_array( $billing ) && isset( $billing[ self::FIELD_ID ] ) ) {
				$vat = self::sanitize_vat( $billing[ self::FIELD_ID ] );
				if ( $vat ) {
					$order->update_meta_data( '_billing_vat', $vat );
					$order->save_meta_data();

					$customer_id = $order->get_customer_id();
					if ( $customer_id ) {
						update_user_meta( $customer_id, 'billing_vat', $vat );
					}
				}
			}
		} catch ( \Throwable $e ) {
			// no-critico
		}
	}
}

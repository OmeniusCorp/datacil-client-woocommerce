<?php
/**
 * Integracion con el checkout de WooCommerce:
 * - Inyecta campo "Cedula/RUC" en billing (condicional a pais=EC via JS).
 * - Enqueue JS + nonce para validacion en vivo.
 * - Persiste `_billing_vat` en orden + meta del usuario.
 * - Muestra el VAT en admin order, cuenta del cliente y emails.
 * - Valida checkout: formato + duplicado (opcional).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Datacil_Checkout {

	const VAT_KEY = 'billing_vat';

	public static function init() {
		// Campo checkout (billing)
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'add_vat_field' ), 10, 2 );
		// Tambien disponible en pagina de direcciones de Mi Cuenta
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'tweak_checkout_field_class' ), 20 );

		// Assets
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Validacion y persistencia
		add_action( 'woocommerce_checkout_process',           array( __CLASS__, 'validate_on_submit' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'persist_vat' ) );
		add_action( 'woocommerce_checkout_update_user_meta',  array( __CLASS__, 'persist_user_vat' ), 10, 2 );

		// Mostrar VAT en admin order (despues de billing address)
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_admin_order' ) );
		// Mostrar en pagina de detalle del pedido (cliente) + thank you
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'append_to_formatted_address' ), 10, 2 );
		// Incluir en emails
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'add_to_email_meta' ), 10, 3 );
		// Columna en listado de pedidos admin (opcional pero util)
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_admin_list_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_admin_list_column' ), 20, 2 );
		// HPOS (WC 8+) orders table
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_admin_list_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_admin_list_column_hpos' ), 20, 2 );

		// Perfil de usuario admin (/wp-admin/user-edit.php) — seccion billing
		add_filter( 'woocommerce_customer_meta_fields', array( __CLASS__, 'add_user_meta_field' ) );
	}

	/**
	 * Inyecta `billing_vat` en la seccion "Customer billing address" del
	 * perfil de usuario admin y "Mi Cuenta → Detalles de la cuenta".
	 */
	public static function add_user_meta_field( $fields ) {
		if ( ! isset( $fields['billing']['fields'] ) ) return $fields;

		// Insertar despues de billing_last_name preservando orden del resto.
		$new = array();
		foreach ( $fields['billing']['fields'] as $key => $field ) {
			$new[ $key ] = $field;
			if ( 'billing_last_name' === $key ) {
				$new['billing_vat'] = array(
					'label'       => __( 'Cedula / RUC', 'datacil-woocommerce' ),
					'description' => __( '10 digitos para cedula, 13 para RUC.', 'datacil-woocommerce' ),
				);
			}
		}
		// Si no existia billing_last_name, anexar al final.
		if ( ! isset( $new['billing_vat'] ) ) {
			$new['billing_vat'] = array(
				'label'       => __( 'Cedula / RUC', 'datacil-woocommerce' ),
				'description' => __( '10 digitos para cedula, 13 para RUC.', 'datacil-woocommerce' ),
			);
		}
		$fields['billing']['fields'] = $new;
		return $fields;
	}

	/**
	 * Inyecta el campo VAT en billing. Si se pasa pais y no es EC, lo omite.
	 * WC refresca los billing fields cuando el cliente cambia pais via AJAX.
	 */
	public static function add_vat_field( $fields, $country = '' ) {
		$settings = get_option( Datacil_Settings::OPTION_KEY, array() );
		if ( ( $settings['autofill'] ?? 'yes' ) !== 'yes' ) {
			return $fields;
		}

		// Si WC esta filtrando para un pais distinto a EC, omitir el campo.
		// El JS tambien oculta via CSS para la experiencia sin recargar.
		if ( $country !== '' && strtoupper( $country ) !== 'EC' ) {
			return $fields;
		}

		$fields[ self::VAT_KEY ] = array(
			'label'       => __( 'Cedula o RUC', 'datacil-woocommerce' ),
			'placeholder' => __( '0102030405 o 0102030405001', 'datacil-woocommerce' ),
			'required'    => false, // Obligatorio solo si country=EC; lo reforzamos en validate_on_submit
			'class'       => array( 'form-row-wide', 'datacil-vat-field' ),
			'priority'    => 25,
			'clear'       => true,
		);
		return $fields;
	}

	/**
	 * Asegura que nuestro campo tenga la clase datacil-vat-field tambien
	 * cuando el filtro anterior lo modifico otro plugin.
	 */
	public static function tweak_checkout_field_class( $fields ) {
		if ( isset( $fields['billing'][ self::VAT_KEY ] ) ) {
			$classes = (array) ( $fields['billing'][ self::VAT_KEY ]['class'] ?? array() );
			if ( ! in_array( 'datacil-vat-field', $classes, true ) ) {
				$classes[] = 'datacil-vat-field';
			}
			$fields['billing'][ self::VAT_KEY ]['class'] = $classes;
		}
		return $fields;
	}

	public static function enqueue_assets() {
		if ( ! function_exists( 'is_checkout' ) ) return;
		if ( ! is_checkout() && ! is_account_page() ) return;

		$settings = get_option( Datacil_Settings::OPTION_KEY, array() );
		if ( ( $settings['autofill'] ?? 'yes' ) !== 'yes' ) return;

		wp_enqueue_style( 'datacil-wc', DATACIL_WC_URL . 'assets/css/datacil.css', array(), DATACIL_WC_VERSION );
		wp_enqueue_script( 'datacil-wc', DATACIL_WC_URL . 'assets/js/datacil-checkout.js', array( 'jquery' ), DATACIL_WC_VERSION, true );
		wp_localize_script( 'datacil-wc', 'DatacilWC', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'datacil_validate' ),
			'countryRequired' => 'EC',
			'i18n'            => array(
				'validate'       => __( 'Validar', 'datacil-woocommerce' ),
				'validating'     => __( 'Validando...', 'datacil-woocommerce' ),
				'success'        => __( 'Datos cargados', 'datacil-woocommerce' ),
				'failure'        => __( 'No se pudo validar', 'datacil-woocommerce' ),
				'invalid_length' => __( 'Ingresa 10 digitos (cedula) o 13 (RUC).', 'datacil-woocommerce' ),
			),
		) );
	}

	/**
	 * Valida formato del VAT y opcionalmente bloquea duplicados SOLO cuando
	 * el pais de facturacion es EC.
	 */
	public static function validate_on_submit() {
		$country = isset( $_POST['billing_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) ) : '';
		if ( 'EC' !== $country ) return;

		$vat = isset( $_POST['billing_vat'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_POST['billing_vat'] ) ) : '';
		if ( empty( $vat ) ) {
			wc_add_notice( __( 'Ingresa tu cedula o RUC para facturacion en Ecuador.', 'datacil-woocommerce' ), 'error' );
			return;
		}
		if ( 10 !== strlen( $vat ) && 13 !== strlen( $vat ) ) {
			wc_add_notice( __( 'La cedula/RUC debe tener 10 o 13 digitos.', 'datacil-woocommerce' ), 'error' );
			return;
		}

		$settings = get_option( Datacil_Settings::OPTION_KEY, array() );
		if ( 'yes' !== ( $settings['block_existing'] ?? 'no' ) ) return;

		$existing = get_users( array(
			'meta_key'   => 'billing_vat',
			'meta_value' => $vat,
			'number'     => 1,
			'fields'     => 'ID',
			'exclude'    => array( get_current_user_id() ),
		) );
		if ( ! empty( $existing ) ) {
			wc_add_notice(
				sprintf( __( 'La identificacion %s ya esta registrada con otra cuenta.', 'datacil-woocommerce' ), $vat ),
				'error'
			);
		}
	}

	public static function persist_vat( $order_id ) {
		if ( ! isset( $_POST['billing_vat'] ) ) return;
		$vat = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['billing_vat'] ) );
		if ( empty( $vat ) ) return;

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( '_billing_vat', $vat );
			$order->save();
		} else {
			update_post_meta( $order_id, '_billing_vat', $vat );
		}
	}

	public static function persist_user_vat( $customer_id, $data ) {
		if ( ! $customer_id ) return;
		if ( isset( $_POST['billing_vat'] ) ) {
			$vat = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['billing_vat'] ) );
			if ( $vat ) update_user_meta( $customer_id, 'billing_vat', $vat );
		}
	}

	/**
	 * Bloque de detalle en la edicion de orden (admin), debajo del billing.
	 */
	public static function render_admin_order( $order ) {
		$vat = $order->get_meta( '_billing_vat' );
		if ( empty( $vat ) ) return;
		echo '<p><strong>' . esc_html__( 'Cedula / RUC', 'datacil-woocommerce' ) . ':</strong> ' . esc_html( $vat ) . '</p>';
	}

	/**
	 * Agrega la linea VAT al final del address formateado (thank you, emails,
	 * pagina "Ver orden" del cliente).
	 */
	public static function append_to_formatted_address( $address, $order ) {
		$vat = is_object( $order ) ? $order->get_meta( '_billing_vat' ) : '';
		if ( ! empty( $vat ) ) {
			$address['billing_vat'] = $vat;
		}
		return $address;
	}

	public static function add_to_email_meta( $fields, $sent_to_admin, $order ) {
		$vat = is_object( $order ) ? $order->get_meta( '_billing_vat' ) : '';
		if ( ! empty( $vat ) ) {
			$fields['billing_vat'] = array(
				'label' => __( 'Cedula / RUC', 'datacil-woocommerce' ),
				'value' => $vat,
			);
		}
		return $fields;
	}

	public static function add_admin_list_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_number' === $key || 'order_status' === $key ) {
				$new['datacil_vat'] = __( 'Cedula/RUC', 'datacil-woocommerce' );
			}
		}
		if ( ! isset( $new['datacil_vat'] ) ) $new['datacil_vat'] = __( 'Cedula/RUC', 'datacil-woocommerce' );
		return $new;
	}

	public static function render_admin_list_column( $column, $post_id ) {
		if ( 'datacil_vat' !== $column ) return;
		$vat = get_post_meta( $post_id, '_billing_vat', true );
		echo esc_html( $vat ?: '—' );
	}

	public static function render_admin_list_column_hpos( $column, $order ) {
		if ( 'datacil_vat' !== $column ) return;
		$vat = is_object( $order ) ? $order->get_meta( '_billing_vat' ) : '';
		echo esc_html( $vat ?: '—' );
	}
}

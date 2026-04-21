<?php
/**
 * Cliente HTTP para la API de Datacil.
 *
 * SEGURIDAD: Este cliente vive server-side (PHP en WordPress). La API key
 * nunca se envia al browser. Frontend siempre invoca via WP AJAX con nonce.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Datacil_Client {

	/** @var array<string,mixed> */
	private $settings;

	public function __construct() {
		$this->settings = get_option( 'datacil_wc_settings', array() );
	}

	/**
	 * Ejecuta GET contra un endpoint relativo ej. "ecuador/data/cedula/0102030405".
	 *
	 * @return array{success:bool, status:int, data?:mixed, message?:string}
	 */
	public function get( $path ) {
		$base    = rtrim( (string) ( $this->settings['api_url'] ?? '' ), '/' );
		$version = (string) ( $this->settings['api_version'] ?? 'v1' );
		$apikey  = (string) ( $this->settings['api_key'] ?? '' );
		$timeout = (int) ( $this->settings['api_timeout'] ?? 10 );

		if ( empty( $base ) || empty( $apikey ) ) {
			return array(
				'success' => false,
				'status'  => 0,
				'message' => __( 'Datacil no configurado. Completa URL y API Key.', 'datacil-woocommerce' ),
			);
		}

		$url = $base . '/' . $version . '/' . ltrim( $path, '/' );

		$response = wp_remote_get( $url, array(
			'timeout' => $timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $apikey,
				'Accept'        => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'status'  => 0,
				'message' => $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $body, true );

		if ( $status >= 400 ) {
			return array(
				'success' => false,
				'status'  => (int) $status,
				'message' => isset( $parsed['message'] ) ? (string) $parsed['message'] : $this->http_status_message( (int) $status ),
			);
		}

		return array(
			'success' => true,
			'status'  => (int) $status,
			'data'    => is_array( $parsed ) ? ( $parsed['data'] ?? $parsed ) : null,
		);
	}

	/**
	 * Valida cedula (10 digitos) o RUC (13 digitos).
	 * Retorna estructura lista para poblar campos de billing/partner.
	 */
	public function validate_vat( $vat ) {
		$vat = preg_replace( '/\D/', '', (string) $vat );

		if ( 10 === strlen( $vat ) ) {
			$endpoint = "ecuador/data/cedula/{$vat}";
		} elseif ( 13 === strlen( $vat ) ) {
			$endpoint = "ecuador/data/ruc/{$vat}";
		} else {
			return array(
				'success' => false,
				'message' => __( 'El numero debe tener 10 (cedula) o 13 digitos (RUC).', 'datacil-woocommerce' ),
			);
		}

		$result = $this->get( $endpoint );

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'message' => $result['message'] ?? __( 'No se pudo validar la identificacion.', 'datacil-woocommerce' ),
			);
		}

		$data   = is_array( $result['data'] ) ? $result['data'] : array();
		$name   = (string) ( $data['name'] ?? '' );
		$addr   = is_array( $data['address'] ?? null ) ? $data['address'] : array();
		$cont   = is_array( $data['contact'] ?? null ) ? $data['contact'] : array();

		return array(
			'success' => true,
			'message' => sprintf( __( 'Identificacion validada: %s', 'datacil-woocommerce' ), $name ),
			'data'    => array(
				'vat'       => $vat,
				'name'      => $name,
				'street'    => (string) ( $addr['street'] ?? '' ),
				'city'      => (string) ( $addr['city'] ?? '' ),
				'state'     => (string) ( $addr['state'] ?? '' ),
				'email'     => (string) ( $cont['email'] ?? '' ),
				'phone'     => (string) ( $cont['phone'] ?? '' ),
				'cellphone' => (string) ( $cont['cellphone'] ?? '' ),
				'country'   => 'EC',
			),
		);
	}

	public function get_credits()          { return $this->get( 'usage/credits' ); }
	public function get_credits_history()  { return $this->get( 'usage/credits/history' ); }
	public function get_costs()            { return $this->get( 'usage/costs' ); }

	private function http_status_message( $status ) {
		$map = array(
			400 => __( 'Formato invalido.', 'datacil-woocommerce' ),
			401 => __( 'API Key invalida.', 'datacil-woocommerce' ),
			402 => __( 'Creditos insuficientes.', 'datacil-woocommerce' ),
			403 => __( 'Acceso denegado (verifica los origenes permitidos de la key).', 'datacil-woocommerce' ),
			404 => __( 'Identificacion no encontrada.', 'datacil-woocommerce' ),
			429 => __( 'Demasiadas solicitudes. Espera unos segundos.', 'datacil-woocommerce' ),
			500 => __( 'Error interno del servicio. Reintenta en unos minutos.', 'datacil-woocommerce' ),
		);
		return $map[ $status ] ?? sprintf( __( 'Error HTTP %d', 'datacil-woocommerce' ), $status );
	}
}

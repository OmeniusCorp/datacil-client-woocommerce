<?php
/**
 * Dashboard admin: muestra balance de creditos, historial y costos por endpoint.
 * Menu bajo WooCommerce → Datacil.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Datacil_Dashboard {

	public static function init() {
		add_action( 'admin_menu',            array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Datacil', 'datacil-woocommerce' ),
			__( 'Datacil', 'datacil-woocommerce' ),
			'manage_woocommerce',
			'datacil-dashboard',
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_datacil-dashboard' !== $hook ) return;
		wp_enqueue_style( 'datacil-wc-admin', DATACIL_WC_URL . 'assets/css/datacil.css', array(), DATACIL_WC_VERSION );
		wp_enqueue_script( 'datacil-wc-admin', DATACIL_WC_URL . 'assets/js/datacil-dashboard.js', array( 'jquery' ), DATACIL_WC_VERSION, true );
		wp_localize_script( 'datacil-wc-admin', 'DatacilAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'datacil_admin' ),
			'i18n'    => array(
				'loading' => __( 'Cargando...', 'datacil-woocommerce' ),
				'error'   => __( 'Error al obtener datos.', 'datacil-woocommerce' ),
				'balance' => __( 'Balance de creditos', 'datacil-woocommerce' ),
				'history' => __( 'Historial de consumo', 'datacil-woocommerce' ),
				'costs'   => __( 'Costo por endpoint', 'datacil-woocommerce' ),
				'empty'   => __( 'Sin registros.', 'datacil-woocommerce' ),
			),
		) );
	}

	public static function render() {
		$settings = get_option( Datacil_Settings::OPTION_KEY, array() );
		$configured = ! empty( $settings['api_url'] ) && ! empty( $settings['api_key'] );
		?>
		<div class="wrap datacil-admin">
			<h1><?php esc_html_e( 'Datacil — Dashboard', 'datacil-woocommerce' ); ?></h1>

			<?php if ( ! $configured ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Configura la URL y API Key en', 'datacil-woocommerce' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=datacil' ) ); ?>">
							<?php esc_html_e( 'WooCommerce → Ajustes → Datacil', 'datacil-woocommerce' ); ?>
						</a>.
					</p>
				</div>
			<?php else : ?>
				<div class="datacil-grid">
					<div class="datacil-card" id="datacil-balance-card">
						<h2><?php esc_html_e( 'Balance', 'datacil-woocommerce' ); ?></h2>
						<p class="datacil-big" id="datacil-balance">—</p>
					</div>
					<div class="datacil-card" id="datacil-costs-card">
						<h2><?php esc_html_e( 'Costos por endpoint', 'datacil-woocommerce' ); ?></h2>
						<table id="datacil-costs-table" class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Servicio', 'datacil-woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Creditos', 'datacil-woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
					<div class="datacil-card datacil-card-wide" id="datacil-history-card">
						<h2><?php esc_html_e( 'Historial', 'datacil-woocommerce' ); ?></h2>
						<table id="datacil-history-table" class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Fecha', 'datacil-woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Servicio', 'datacil-woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Consulta', 'datacil-woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Creditos', 'datacil-woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

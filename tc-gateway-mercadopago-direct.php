<?php 
/*
  Plugin Name: Gateway MercadoPago Direct for Tickera
  Plugin URI: https://trix.hosting/
  Description: Gateway MercadoPago Direct for Tickera
  Author: TRIX.Hosting
  Author URI: https://trix.hosting/
  Version: 1.0
  TextDomain: tc-gateway-mercadopago-direct
  Domain Path: /languages/
  Copyright 2019 TRIX.Hosting (https://trix.hosting/)
 */

add_action( 'tc_load_gateway_plugins', 'register_tc_gateway_mercadopago_direct' );

function register_tc_gateway_mercadopago_direct() {
	class TC_Gateway_Mercadopago_Direct extends TC_Gateway_API {
		var $plugin_name = 'mercadopago_direct';
		var $admin_name = '';
		var $public_name = '';
		var $method_img_url = '';
		var $admin_img_url = '';
		var $force_ssl = false;
		var $ipn_url;
		var $API_Username, $API_Password, $mode, $returnURL, $API_Endpoint, $version, $locale;
		var $currencies = array();
		var $automatically_activated = false;
		var $skip_payment_screen = true;
		
		function on_creation() {
			$this->init();
		}
		
		function init() {
			$this->admin_name =	'Gateway Mercadopago Directo';
			$this->public_name = 'Mercado Pago';
			$this->method_img_url = plugin_dir_url( __FILE__ ) . 'images/version-vertical-small.png';
			$this->admin_img_url = plugin_dir_url( __FILE__ ) . 'images/mp-small.png';
			$this->skip_payment_screen = false;
			$this->mode = $this->get_option( 'mode', 'pruebas');
			$this->currency = $this->get_option( 'currency', 'ARS' );
			$this->credentials_pruebas_public_key = $this->get_option( 'credentials_pruebas_public_key' );
			$this->credentials_pruebas_access_token = $this->get_option( 'credentials_pruebas_access_token' );
			$this->credentials_produccion_public_key = $this->get_option( 'credentials_produccion_public_key' );
			$this->credentials_produccion_access_token = $this->get_option( 'credentials_produccion_access_token' );
			$this->store_description = $this->get_option( 'store_description', 'Mi Entrada Digital' );
			$this->category = $this->get_option( 'category', 'tickets' );
			$this->store_id = $this->get_option( 'store_id', 'MED-' );
			$this->binary = $this->get_option( 'binary' );

			$this->currencies = array(
				'ARS' => __( 'ARS - Peso Argentino', 'tc-gateway-mercadopago-direct' )
			);
			
			$this->categories = array(
				'donations' => 'donations',
				'tickets' => 'tickets',
				'others' => 'others'
			);

			$this->binaries = array(
				true => 'si',
				false => 'no'
			);
			
		}

		function gateway_admin_settings( $settings, $visible ) {
			global $tc;
			?>
			<div id="<?php echo $this->plugin_name; ?>" class="postbox" <?php echo (!$visible ? 'style="display:none;"' : ''); ?>>
				<h3 class='handle'>
					<span>
						<?php printf( __( '%s Settings', 'tc-gateway-mercadopago-direct' ), $this->admin_name ); ?>
					</span>
				</h3>
			<div class="inside">
				<span class="description">
					<?php _e( 'Cobra las ventas de tus entradas via MercadoPago' , 'tc-gateway-mercadopago-direct' ); ?>
				</span>
				<?php
				$fields	 = array(
					'mode' => array(
						'title' => __( 'Modo', 'tc-gateway-mercadopago-direct' ),
						'type' => 'select',
						'options' => array(
							'pruebas' => __( 'Pruebas', 'tc-gateway-mercadopago-direct' ),
							'produccion' => __( 'Produccion', 'tc-gateway-mercadopago-direct' )
						),
						'default' => 'pruebas',
					),
                    'credentials_pruebas_public_key' => array(
                        'title' => __('Public Key de Prueba', 'tc-gateway-mercadopago-direct'),
                        'type' => 'text',
                        'description' => __('Inserte el Public Key de Prueba', 'tc-gateway-mercadopago-direct'),
					),
                    'credentials_pruebas_access_token' => array(
                        'title' => __('Access Token de Prueba', 'tc-gateway-mercadopago-direct'),
                        'type' => 'text',
                        'description' => __('Inserte el Access Token de Prueba', 'tc-gateway-mercadopago-direct'),
					),
                    'credentials_produccion_public_key' => array(
                        'title' => __('Public Key de Produccion', 'tc-gateway-mercadopago-direct'),
                        'type' => 'text',
                        'description' => __('Inserte el Public Key de Produccion', 'tc-gateway-mercadopago-direct'),
					),
                    'credentials_produccion_access_token' => array(
                        'title' => __('Access Token de Produccion', 'tc-gateway-mercadopago-direct'),
                        'type' => 'text',
                        'description' => __('Inserte el Access Token de Produccion', 'tc-gateway-mercadopago-direct'),
					),
                    'store_description' => array(
                        'title' => __('Descripcion de la Tienda', 'tc-gateway-mercadopago-direct'),
                        'type' => 'text',
                        'description' => __('Este nombre aparecerá en la factura de tus clientes.', 'tc-gateway-mercadopago-direct'),
						'default' => 'Mi Entrada Digital',
					),
					'category' => array(
						'title' => __( 'Categoria de la Tienda', 'tc-gateway-mercadopago-direct' ),
						'type' => 'select',
                        'description' => __('¿A qué categoría pertenecen tus productos? Elige la que mejor los caracteriza (elige 
“otro” si tu producto es demasiado específico).', 'tc-gateway-mercadopago-direct'),
						'options' => $this->categories,
						'default' => 'tickets',
					),
                    'store_id' => array(
                        'title' => __('ID de la Tienda', 'tc-gateway-mercadopago-direct'),
                        'type' => 'text',
                        'description' => __('Usa un número o prefijo para identificar pedidos y pagos provenientes de esta tienda.', 'tc-gateway-mercadopago-direct'),
						'default' => 'MED-',
					),
					'binary' => array(
						'title' => __( 'Modo Binario', 'tc-gateway-mercadopago-direct' ),
						'type' => 'select',
						'description' => 'Acepta y rechaza pagos de forma automática. ¿Quieres que lo activemos?',
						'options' => $this->binaries,
					),
					'comision_gateway' => array(
						'title' => __( 'Comision por uso de gateway', 'tc-gateway-mercadopago-direct' ),
						'type' => 'text',
						'description' => __('Elige un valor porcentual adicional que quieras cobrar como comisión a tus clientes por pagar con Mercado Pago.', 'tc-gateway-mercadopago-direct'),
						'default' => '0',
					),
					'currency' => array(
						'title' => __( 'Moneda', 'tc-gateway-mercadopago-direct' ),
						'type' => 'select',
						'options' => $this->currencies,
						'default' => 'ARS',
					),
				);
				$form = new TC_Form_Fields_API( $fields, 'tc-gateway-mercadopago-direct', 'gateways' );
				?>
				<table class="form-table">
					<?php $form->admin_options(); ?>
				</table>
			</div>
		</div>
		<?php
		}
		
		function payment_form( $cart ) {
			$buyer_full_name = $this->buyer_info( 'full_name' );
			$content = '';
			$content .= '<table class="cart_billing">
			<thead>
			<tr>
			<th colspan="2">' . __( 'Enter Your Credit Card Information:', 'tc-gateway-mercadopago-direct' ) . '</th>
			</tr>
			</thead>
			<tbody>
			<tr>
			<td align="right">' . __( 'Cardholder Name:', 'tc-gateway-mercadopago-directn' ) . '</td><td>
			<input id="tcbs_cc_name" name="' . $this->plugin_name . '_cc_name" type="text" value="' . esc_attr( $buyer_full_name ) . '" /> </td>
			</tr>';
			$content .= '<tr>';
			$content .= '<td align="right">';
			$content .= __( 'Card Number', 'tc-gateway-mercadopago-direct' );
			$content .= '</td>';
			$content .= '<td>';
			$content .= '<input type="text" autocomplete="off" name="' . $this->plugin_name . '_cc_number" id="' . $this->plugin_name . '_cc_number"/>';
			$content .= '</td>';
			$content .= '</tr>';
			$content .= '<tr>';
			$content .= '<td align="right">';
			$content .= __( 'Expiration:', 'tc-gateway-mercadopago-direct' );
			$content .= '</td>';
			$content .= '<td>';
			$content .= '<select id="' . $this->plugin_name . '_cc_month" name="' . $this->plugin_name . '_cc_month">';
			$content .= tc_months_dropdown();//helper function to list months as a select box
			$content .= '</select>';
			$content .= '<span> / </span>';
			$content .= '<select id="' . $this->plugin_name . '_cc_year" name="' . $this->plugin_name . '_cc_year">';
			$content .= tc_years_dropdown( '', false );//helper function to list years as a select box
			$content .= '</select>';
			$content .= '</td>';
			$content .= '</tr>';
			$content .= '<tr>';
			$content .= '<td align="right">';
			$content .= __( 'CVC:', 'tc-gateway-mercadopago-direct' );
			$content .= '</td>';
			$content .= '<td>';
			$content .= '<input id="' . $this->plugin_name . '_cc_cvc" name="' . $this->plugin_name . '_cc_cvc" type="text" maxlength="4" autocomplete="off" value=""/>';
			$content .= '</td>';
			$content .= '</tr>';
			$content .= '</table>';
			return $content;
		}
	}
	tc_register_gateway_plugin('TC_Gateway_Mercadopago_Form', 'mercadopago_form', __('Mercadopago Form', 'tc-gateway-mercadopago-direct' ) );
}

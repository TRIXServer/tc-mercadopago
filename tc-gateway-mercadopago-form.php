<?php 
/*
  Plugin Name: Gateway MercadoPago Form for Tickera
  Plugin URI: https://trixserver.com/
  Description: Gateway MercadoPago Form for Tickera
  Author: TRIXServer.com
  Author URI: https://trixserver.com/
  Version: 1.0
  TextDomain: tc-gateway-mercadopago-form
  Domain Path: /languages/
  Copyright 2019 TRIXServer.com (https://trixserver.com/)
*/

add_action( 'tc_load_gateway_plugins', 'register_tc_gateway_mercadopago_form' );

function register_tc_gateway_mercadopago_form() {
	class TC_Gateway_Mercadopago_Form extends TC_Gateway_API {
		var $plugin_name = 'mercadopago_form';
		var $admin_name = '';
		var $public_name = '';
		var $method_img_url = '';
		var $admin_img_url = '';
		var $force_ssl = false;
		var $ipn_url;
		var $currency, $credentials_pruebas_public_key, $credentials_pruebas_access_token;
		var $credentials_produccion_public_key, $credentials_produccion_access_token, $mode;
		var $store_description, $category, $store_id, $binary, $preferencia, $item, $marketplace_fee, $comision_gateway;
		var $currencies = array();
		var $automatically_activated = false;
		var $skip_payment_screen = true;
		
		function on_creation() {
			$this->init();
		}
		
		function init() {
			$this->admin_name =	'Mercadopago Form';
			$this->public_name = 'Mercado Pago';
			$this->method_img_url = plugin_dir_url( __FILE__ ) . 'images/version-vertical-small.png';
			$this->admin_img_url = plugin_dir_url( __FILE__ ) . 'images/mp-small.png';
			$this->skip_payment_screen = true;

			$this->mode = $this->get_option( 'mode', 'pruebas');
			$this->currency = $this->get_option( 'currency', 'ARS' );
			$this->credentials_pruebas_public_key = $this->get_option( 'credentials_pruebas_public_key' );
			$this->credentials_pruebas_access_token = $this->get_option( 'credentials_pruebas_access_token' );
			$this->credentials_produccion_public_key = $this->get_option( 'credentials_produccion_public_key' );
			$this->credentials_produccion_access_token = $this->get_option( 'credentials_produccion_access_token' );
			$this->store_description = $this->get_option( 'store_description' );
			$this->category = $this->get_option( 'category', 'tickets' );
			$this->store_id = $this->get_option( 'store_id' );
			$this->binary = $this->get_option( 'binary' );
			$this->comision_gateway = $this->get_option( 'comision_gateway' );
			$this->marketplace_fee = $this->get_option( 'marketplace_fee' );

			$this->currencies = array(
				'ARS' => __( 'ARS - Peso Argentino', 'tc-gateway-mercadopago-form' )
			);
			
			$this->categories = array(
				'donations' => 'Donaciones',
				'tickets' => 'Entradas',
				'others' => 'Otros'
			);

			$this->binaries = array(
				true => 'Si',
				false => 'No'
			);
			
		}

		/*
		function payment_form( $cart ) {
			global $tc;
			if ( isset( $_GET[ 'status' ] ) && ($_GET[ 'status' ] == 'rejected' ) ) {
				$this->add_error( 'Your transaction has been rejected' );//Set the error message
				wp_redirect( $tc->get_payment_slug( true ) );//redirect to the payment page
				exit;
	 		}
		}
		*/

		function process_payment( $cart ) {
			global $tc;
			
			if ( $this->mode == 'pruebas' ) {
				$form_public_key = $this->credentials_pruebas_public_key;
				$form_access_token = $this->credentials_pruebas_access_token;
			} else {
				$form_public_key = $this->credentials_produccion_public_key;
				$form_access_token = $this->credentials_produccion_access_token;
			}

			$this->maybe_start_session();
			$this->save_cart_info();
			$order_id = $tc->generate_order_id();
	
			// SDK de Mercado Pago
			require __DIR__ .  '/vendor/autoload.php';
			
			// Agrega credenciales
			MercadoPago\SDK::setAccessToken($form_access_token);
				
			// Crea un objeto de preferencia
			$preference = new MercadoPago\Preference();
			
			// Crea un ??tem en la preferencia
			$item = new MercadoPago\Item();
            $item->id = $order_id;
			$item->title = $this->store_id . $order_id ;
			$item->quantity = 1;
			$item->unit_price = ($this->total()) * ( 1 + ($this->comision_gateway / 100));

			$preference->items = array($item);
			
			$preference->back_urls = array(
			    "success" => $tc->get_confirmation_slug( true, $order_id ),
    			"failure" => $tc->get_confirmation_slug( true, $order_id ),
				"pending" => $tc->get_confirmation_slug( true, $order_id )
			);

			$preference->auto_return = "approved";
			$preference->notification_url = $this->ipn_url;

			// https://entorno.mientradadigital.com/tickets-ipn-payment/?payment_gateway_return=mercadopago_form

			$preference->payment_methods = array(
				"excluded_payment_types" => array(
					array("id" => "ticket")
				),
				"installments" => 3
			);

			$preference->binary_mode = ($this->binary)? true : false;
			$preference->statement_descriptor = $this->store_description;
			$preference->external_reference = $order_id;

			$payer = new MercadoPago\Payer();
			$payer->name = $this->buyer_info('first_name');
			$payer->surname = $this->buyer_info('last_name');
			$payer->email = $this->buyer_info('email');
			$preference->payer = $payer;

			if ( (float) $this->marketplace_fee > 0)
			{
				$preference->marketplace_fee = floor( $this->total() / 1.00 * ($this->marketplace_fee / 100) * 100 ) / 100 ;

			}

			$preference->save();

			$paid = false;
			$payment_info = $this->save_payment_info();
			$tc->create_order( $order_id, $this->cart_contents(), $this->cart_info(), $payment_info, $paid );
				
			header( 'Content-Type: text/html' );
			?>
				<script>
					function redirect() {
						location.href = "<?php echo $preference->init_point; ?>";
					}
					addEventListener('load', redirect);
				</script>
            <?php
		}
		
		function order_confirmation( $order, $payment_info = '', $cart_info = '' ) {
			global $tc;

			$order = tc_get_order_id_by_name( $order );
			$paid = false;
			$order_status = 'order_received';
			
			if ( isset( $_GET[ 'status' ] )) {
				switch($_GET[ 'status' ]) {
					case 'approved':
						$paid = true;
						$order_status = 'order_paid';
						break;
					case 'pending':
						$paid = false;
						$order_status = 'order_received';
						break;
					case 'cancelled':
						$paid = false;
						$order_status = 'order_cancelled';
						break;
					case 'refunded':
						$paid = false;
						$order_status = 'order_refunded';
						break;					

				}

			}
			$tc->update_order_payment_status( $order->ID, $paid );
			$tc->update_order_status( $order->ID, $order_status);

		}

		function ipn() { 
			global $tc;

			if ( $this->mode == 'pruebas' ) {
				$form_public_key = $this->credentials_pruebas_public_key;
				$form_access_token = $this->credentials_pruebas_access_token;
			}
			else {
				$form_public_key = $this->credentials_produccion_public_key;
				$form_access_token = $this->credentials_produccion_access_token;
			}
	
			// SDK de Mercado Pago
			require __DIR__ .  '/vendor/autoload.php';
			
			// Agrega credenciales
			MercadoPago\SDK::setAccessToken($form_access_token);

			$merchant_order = null;
			$order_status = 'order_cancelled';

			switch($_GET["topic"]) {
				case "payment":
					$payment = MercadoPago\Payment::find_by_id($_GET["id"]);
					// Get the payment and the corresponding merchant_order reported by the IPN.
					$merchant_order = MercadoPago\MerchantOrder::find_by_id($payment->order->id);
					break;
			}

			$paid_amount = 0;
			foreach ($merchant_order->payments as $payments) {  
				if ($payments['status'] == 'approved') {
					$order_status = 'order_paid';
					$paid_amount += $payments['transaction_amount'];
				}
			}
		   
			$ipn_order_id = $payment->order->external_reference;
			$order_id = tc_get_order_id_by_name( $ipn_order_id );
			$order = new TC_Order( $order_id );
			$tc->update_order_payment_status( $order_id, $paid_amount == $merchant_order->total_amount );
			$tc->update_order_status( $order->ID, $order_status);

		}
			
		function gateway_admin_settings( $settings, $visible ) {
			global $tc;

			?>

			<div id="<?php echo $this->plugin_name; ?>" class="postbox" <?php echo (!$visible ? 'style="display:none;"' : ''); ?>>
				<h3 class='handle'>
            		<span>
						<?php printf( __( '%s Settings', 'tc-gateway-mercadopago-form' ), $this->admin_name ); ?>
            		</span>
					<span class="description">
						<?php _e( 'Cobra las ventas de tus entradas via MercadoPago' , 'tc-gateway-mercadopago-form' ); ?>
					</span>
            	</h3>     			
				<div class="inside">
					<?php
					if ( (is_super_admin()) ) {
					$fields = array(
						'mode' => array(
							'title' => __( 'Modo', 'tc-gateway-mercadopago-form' ),
							'type' => 'select',
							'options' => array(
								'pruebas' => __( 'Pruebas', 'tc-gateway-mercadopago-form' ),
								'produccion' => __( 'Produccion', 'tc-gateway-mercadopago-form' )
							),
							'default' => 'pruebas',
						),
						'credentials_pruebas_public_key' => array(
							'title' => __('Public Key de Prueba', 'tc-gateway-mercadopago-form'),
							'type' => 'text',
							'description' => __('Inserte el Public Key de Prueba', 'tc-gateway-mercadopago-form'),
						),
						'credentials_pruebas_access_token' => array(
							'title' => __('Access Token de Prueba', 'tc-gateway-mercadopago-form'),
							'type' => 'text',
							'description' => __('Inserte el Access Token de Prueba', 'tc-gateway-mercadopago-form'),
						),
						'credentials_produccion_public_key' => array(
							'title' => __('Public Key de Produccion', 'tc-gateway-mercadopago-form'),
							'type' => 'text',
							'description' => __('Inserte el Public Key de Produccion', 'tc-gateway-mercadopago-form'),
						),
						'credentials_produccion_access_token' => array(
							'title' => __('Access Token de Produccion', 'tc-gateway-mercadopago-form'),
							'type' => 'text',
							'description' => __('Inserte el Access Token de Produccion', 'tc-gateway-mercadopago-form'),
						),
						'store_description' => array(
							'title' => __('Descripcion de la Tienda', 'tc-gateway-mercadopago-form'),
							'type' => 'text',
							'description' => __('Este nombre aparecer?? en la factura de tus clientes.', 'tc-gateway-mercadopago-form'),
							'default' => 'MIENTRADADIGITAL',
						),
						'category' => array(
							'title' => __( 'Categoria de la Tienda', 'tc-gateway-mercadopago-form' ),
							'type' => 'select',
							'description' => __('??A qu?? categor??a pertenecen tus productos? Elige la que mejor los caracteriza (elige ???otro??? si tu producto es demasiado espec??fico).', 'tc-gateway-mercadopago-form'),
							'options' => $this->categories,
							'default' => 'tickets',
						),
						'store_id' => array(
							'title' => __('ID de la Tienda', 'tc-gateway-mercadopago-form'),
							'type' => 'text',
							'description' => __('Usa un n??mero o prefijo para identificar pedidos y pagos provenientes de esta tienda.', 'tc-gateway-mercadopago-form'),
							'default' => 'MED-',
						),
						'binary' => array(
							'title' => __( 'Modo Binario', 'tc-gateway-mercadopago-form' ),
							'type' => 'select',
							'description' => 'Acepta y rechaza pagos de forma autom??tica. ??Quieres que lo activemos?',
							'options' => $this->binaries,
							'default' => true,
						),
						'comision_gateway' => array(
							'title' => __( 'Comision por uso de gateway', 'tc-gateway-mercadopago-form' ),
							'type' => 'text',
							'description' => __('Elige un valor porcentual adicional que quieras cobrar como comisi??n a tus clientes por pagar con Mercado Pago.', 'tc-gateway-mercadopago-form'),
							'default' => '0',
						),
						'currency' => array(
							'title' => __( 'Moneda', 'tc-gateway-mercadopago-form' ),
							'type' => 'select',
							'options' => $this->currencies,
							'default' => 'ARS',
						),
					);
						$fields['marketplace_fee'] = array(
								'title' => __( 'Fee del Marketplace', 'tc-gateway-mercadopago-form' ),
								'type' => 'text',
								'description' => __('Elige un valor porcentual adicional que quieras cobrar como comisi??n de MarketPlace.', 'tc-gateway-mercadopago-form'),
								'default' => '0',
						);
					};
					$form = new TC_Form_Fields_API( $fields, 'tc', 'gateways', $this->plugin_name );
					?>
					<table class="form-table">
						<?php $form->admin_options(); ?>
					</table>
				</div>
			</div>
			<?php
		}
	}
	tc_register_gateway_plugin('TC_Gateway_Mercadopago_Form', 'mercadopago_form', __('Mercadopago Form', 'tc-gateway-mercadopago-form' ) );
}

// (( $this->get_transaction_amount() / 1 * (0.0778) * 100 ) / 100)
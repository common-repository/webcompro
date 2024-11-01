<?php

/**
 * Plugin Name:       WebcomPro
 * Description:       Example block scaffolded with Create Block tool.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.2.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webcompro
 *
 * @package CreateBlock
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function webcompro_create_block_webcompro_block_init()
{
	register_block_type(__DIR__ . '/build');
}
add_action('init', 'webcompro_create_block_webcompro_block_init');

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'webcompro_add_gateway_class');
function webcompro_add_gateway_class($gateways)
{
	$gateways[] = 'Webcompro_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'webcompro_init_gateway_class');
function webcompro_init_gateway_class()
{

	class Webcompro_Gateway extends WC_Payment_Gateway_CC
	{
		private bool $testmode = true;
		private string $username = "";
		private string $password = "";

		private WC_Order $order;
		/**
		 * Class constructor, more about it in Step 3
		 */
		public function __construct()
		{

			$this->id = 'webcompro'; // payment gateway plugin ID
			$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = false; // in case you need a custom credit card form
			$this->method_title = 'Webcom Pro Gateway';
			$this->method_description = 'Il gateway di pagamenti Webcom Pro permette di pagare tramite webcom'; // will be displayed on the options page
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);

			// Method with all the options fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->enabled = $this->get_option('enabled');
			$this->testmode = 'yes' === $this->get_option('testmode');
			$this->username = $this->testmode ? $this->get_option('test_username') : $this->get_option('live_username');
			$this->password = $this->testmode ? $this->get_option('test_password') : $this->get_option('live_password');

			// This action hook saves the settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			// We need custom JavaScript to obtain a token
			//add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

			// You can also register a webhook here
			//add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

		}

		/**
		 * Plugin options, we deal with it in Step 3 too
		 */
		public function init_form_fields()
		{

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Abilita/Disabilita', 'enable-or-disable'),
					'label' => __('Abilita il gateway di pagamenti Webcom Pro', 'enable-or-disable-label'),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Titolo', 'payment-title'),
					'type' => 'text',
					'description' => __('Questo campo controlla cosa viene visto nella pagina di checkout come titolo', 'payment-title-description'),
					'default' => 'Carta di credito',
					'desc_tip' => true,
				),
				'description' => array(
					'title' => __('Sottotitolo', 'payment-subtitle'),
					'type' => 'textarea',
					'description' => __('Questo campo controlla cosa viene visto nella pagina di checkout come sottotitolo', 'payment-subtitle-description'),
					'default' => 'Paga con carta di credito .',
				),
				'testmode' => array(
					'title' => __('Modalità test', 'test-mode'),
					'label' => __('Abilita modalità test', 'test-mode-label'),
					'type' => 'checkbox',
					'description' => __('Effettua i pagamenti in modalità test', 'test-mode-description'),
					'default' => 'false',
					'desc_tip' => true,
				),
				'test_username' => array(
					'title' =>  __('Email di test', 'test-email'),
					'type' => 'text'
				),
				'test_password' => array(
					'title' => __('Chiave di test', 'test-key'),
					'type' => 'password',
				),
				'live_username' => array(
					'title' => __('Email di produzione', 'live-email'),
					'type' => 'text'
				),
				'live_password' => array(
					'title' => __('Chiave di produzione', 'live-key'),
					'type' => 'password'
				)
			);
		}


		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment($order_id)
		{
			$nonce = sanitize_text_field($_POST["nonce"]);
			if (empty($nonce) || !wp_verify_nonce($nonce, 'wc_create_order')) {
				return array('result' => 'error', 'message' => 'Nonce error');
			}

			$pan = sanitize_text_field($_POST["pan"]);
			$expiry = sanitize_text_field($_POST["expiry"]);
			$name = sanitize_text_field($_POST["name"]);
			$cvv = sanitize_text_field($_POST["cvv"]);

			// we need it to get any order detailes
			$this->order = wc_get_order($order_id);
			/*
			 * Array with parameters for API interaction
			 */
			$total = $this->order->get_total();
			$data = array(
				"grossAmountUnit" => $total * 100,
				'_wpnonce' => wp_create_nonce('webcompro_intent'),
			);
			/*
			 * Your API integration can be built with wp_remote_post()
			 */
			$response = wp_remote_post(
				$this->testmode ? 'https://beta-dpay.webcom-tlc.it/api/payment/intent' : 'https://dpay.webcom-tlc.it/api/payment/intent',
				array(
					'body' => wp_json_encode($data),
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
						'Content-Type' => 'application/json',
					),
				)
			);

			if (200 === wp_remote_retrieve_response_code($response)) {

				$body = json_decode(wp_remote_retrieve_body($response), true);

				$dataExecute = array(
					'_wpnonce' => wp_create_nonce('webcompro_execute'),
					"card" => array(
						"pan" => $pan,
						"expiry" => $expiry,
						"name" => $name,
						"cvv" => $cvv
					),
				);

				$responseExecute = wp_remote_post(
					$this->testmode ? 'https://beta-dpay.webcom-tlc.it/api/payment/' . $body["id"] . "/execute" : 'https://dpay.webcom-tlc.it/api/payment/' . $body["id"] . "/execute",
					array(
						'body' => wp_json_encode($dataExecute),
						'headers' => array(
							'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
							'Content-Type' => 'application/json',
						),
					)
				);

				if (200 === wp_remote_retrieve_response_code($responseExecute)) {
					$this->order->payment_complete();
					$this->order->reduce_order_stock();

					// some notes to customer (replace true with false to make it private)
					$this->order->add_order_note('L\'ordine è stato pagato. Grazie!', true);

					// Empty cart
					WC()->cart->empty_cart();

					// Redirect to the thank you page
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url($this->order),
					);
				} else {
					wc_add_notice('Carta di credito non valida.', 'error');
					return;
				}
			} else {
				wc_add_notice('Errore di connessione.', 'error');
				return;
			}
		}
	}
}

add_action('woocommerce_blocks_loaded', 'webcompro_rudr_gateway_block_support');
function webcompro_rudr_gateway_block_support()
{

	// if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	// 	return;
	// }

	// here we're including our "gateway block support class"
	require_once __DIR__ . '/includes/class-wc-webcompro-gateway-blocks-support.php';

	// registering the PHP class we have just included
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
			$payment_method_registry->register(new Webcompro_Gateway_Blocks_Support);
		}
	);
}

add_action('init', 'webcompro_wpdocs_load_textdomain');

function webcompro_wpdocs_load_textdomain()
{
	load_plugin_textdomain('wpdocs_textdomain', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

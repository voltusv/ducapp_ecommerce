<?php
/*
 * Plugin Name: DUCAPP Gateway
 * Description: DUCAPP Payment Gateway for WooCommerce enables seamless, secure transactions, supporting multiple payment methods to enhance your online store's efficiency.
 * Author: DUCApp
 * Author URI: www.ducapp.com
 * Text Domain: ducapp
 * Domain Path: /languages
 * Version: 1.0.1
 */




/*
* This action hook registers our PHP class as a WooCommerce payment gateway
*/

add_action('woocommerce_blocks_loaded', 'ducapp_gateway_block_support');
function ducapp_gateway_block_support()
{
    require_once __DIR__ . '/includes/class-wc-ducapp-gateway-blocks-support.php';
    require_once __DIR__ . '/includes/class-wc-ducapp-api.php';

    add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
        $payment_method_registry->register(new WC_Ducapp_Gateway_Blocks_Support);
    });
}


add_action('init', 'ducapp_load_textdomain');
function ducapp_load_textdomain()
{
    load_plugin_textdomain('ducapp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('before_woocommerce_init', 'ducapp_cart_checkout_blocks_compatibility');
function ducapp_cart_checkout_blocks_compatibility()
{

    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            false // true (compatible, default) or false (not compatible)
        );
    }
}


// REST API Hook
add_action('rest_api_init', function () {
    $custom_route = 'my/callback';
    $settings = get_option("woocommerce_ducapp_settings", array());


    if (isset($settings["webhook"])) {
        $custom_route = $settings["webhook"];
    }

    register_rest_route(
        'ducapp/v1',
        $custom_route,
        [
            'callback' => 'handle_ducapp_callback',
            'methods' => ['POST'],
            'permission_callback' => function () {
                return true;
            },
        ],
    );
});

function handle_ducapp_callback(WP_REST_Request $request)
{

    try {

        $params = $request->get_json_params();
        $transaction = $params['transaction'];

        if ($transaction['transactionStatus'] == "accepted") {

            $explodeID = explode("-", $transaction['externalID']);

            $order_id = $explodeID[0];
            $order = wc_get_order($order_id);
            $order->add_meta_data('duc_payment_id', $transaction['transactionID']);
            $order->add_order_note(
                sprintf(
                    __('Payment accepted by DUCAPP. Transaction ID: %s', 'ducapp'),
                    $transaction['transactionID']
                )
            );
            
            $order->update_status('processing ', __('Payment accepted for DUCAPP.', 'ducapp'));
            $order->save();

            return array(
                'result' => 'success',
            );
        }


        if ($transaction['transactionStatus'] == "confirmed") {

            $explodeID = explode("-", $transaction['externalID']);

            $order_id = $explodeID[0];
            $order = wc_get_order($order_id);
            $order->add_meta_data('duc_payment_id', $transaction['transactionID']);
            $order->add_order_note(
                sprintf(
                    __('Payment confirmed by DUCAPP. Transaction ID: %s', 'ducapp'),
                    $transaction['transactionID']
                )
            );
            $order->payment_complete();
            $order->update_status('completed', __('Pago confirmado by DUCAPP.', 'ducapp'));

            $order->save();

            return array(
                'result' => 'success',
            );
        }
    } catch (Exception $e) {
        return array(
            'status' => 'error'
        );
    }
}

add_filter('woocommerce_payment_gateways', 'ducapp_add_gateway_class');
function ducapp_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Ducapp_Gateway';
    return $gateways;
}


/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'ducapp_init_gateway_class');
function ducapp_init_gateway_class()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-ducapp-gateway.php';
    
}

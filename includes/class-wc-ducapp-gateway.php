<?php
class WC_Ducapp_Gateway extends WC_Payment_Gateway
{
    /**
     * Class constructor
     */

    // public $api_url = 'https://backend.ducapp.net';
    public $api_key = '';
    public $duc_merchant_name = '';
    public $duc_merchant_phone = '';
    public $duc_merchant_password = '';
    public $environment = '';
    public $webhook = '';


    public function __construct()
    {
        $this->id = 'ducapp'; // payment gateway plugin ID
        $this->has_fields = true; // in case you need a custom credit card form
        $this->method_title = __('DUCAPP Payments', 'ducapp');
        $this->method_description = __('DUCAPP for WooCommerce offers secure and fast payments, integrating multiple methods to facilitate efficient transactions in your online store.', 'ducapp');

        // gateways can support subscriptions, refunds, saved payment methods,
        $this->supports = array(
            'products'
        );

        // Method with all the options fields
        $this->init_form_fields();

        //Load the settings
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        // $this->api_url =  $this->get_option('api_url');
        $this->api_key = $this->get_option('api_key');
        $this->duc_merchant_name = $this->get_option('duc_merchant_name');
        $this->duc_merchant_password = $this->get_option('duc_merchant_password');
        $this->environment =  $this->get_option('environment');
        $this->webhook =  $this->get_option('webhook');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */

    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'ducapp'),
                'label'       => __('Enable DUCAPP Gateway', 'ducapp'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'ducapp'),
                'type'        => 'text',
                'default'     => 'DUCAPP',

            ),
            'description' => array(
                'title'       => __('Description', 'ducapp'),
                'type'        => 'textarea',
                'default'     => __('DUCAPP for WooCommerce offers secure and fast payments, integrating multiple methods to facilitate efficient transactions in your online store.', 'ducapp'),
            ),


            'environment' => array(
                'title'       => __('Environment', 'ducapp'),
                'label'       => __('Environment', 'ducapp'),
                'type'        => 'select',
                'options' => array(
                    'staging' => __('Sandbox', 'ducapp'),
                    'production' => __('Live', 'ducapp'),
                ),
                'default' => 'staging',

            ),

            'api_key' => array(
                'title'       => __('API Key', 'ducapp'),
                'type'        => 'text'
            ),
            'duc_merchant_name' => array(
                'title'       => __('DUC Email', 'ducapp'),
                'type'        => 'text',
            ),
            'duc_merchant_password' => array(
                'title'       => __('DUC Password', 'ducapp'),
                'type'        => 'password',
            ),

            'webhook' => array(
                'title'       => __('Webhook', 'ducapp'),
                'type'        => 'text',
                'description'     => (
                    __('Webhook callback url:', 'ducapp') . ' ' .
                    home_url('/wp-json/ducapp/v1/' . $this->get_option('webhook'))
                ),
            ),

        );
    }

    public function payment_scripts()
    {


        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (! is_cart() && ! is_checkout()) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }
    }


    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);
        $back_url = $this->get_return_url($order);

        $duc_api = new WC_Ducapp_Api($this->environment, $this->api_key);
        $response = $duc_api->create_payment_link($order, $back_url);

        if (isset($response['error'])) {
            wc_add_notice(__('Error creating payment link.', 'ducapp'), 'error');
            return array(
                'result' => 'failure',
            );
        }

        $data = $response['data'];

        if ($data['status'] != 200) {
            wc_add_notice(__('Error creating payment link.', 'ducapp'), 'error');
            return array(
                'result' => 'failure',
            );
        }

        $duc_url = $data['payload']['link'];
        WC()->cart->empty_cart(true);
        WC()->session->set('cart', array());

        // Redirigir al cliente
        return array(
            'result' => 'success',
            'redirect' => $duc_url,
        );
    }
}

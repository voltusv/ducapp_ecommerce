<?php

/**
 * Class WC_Ducapp_API
 *
 * Handles API interactions with the DUCAPP service.
 */
class WC_Ducapp_API
{

    private $api_url;
    private $api_key;
    private $access_token;

    private $settings;

    public function __construct($environment = 'staging', $api_key = '')
    {
        $this->api_url = $environment == 'staging' ? "https://backend.ducapp.net" : 'https://backend.ducwallet.com';
        $this->api_key = $api_key;
        $this->access_token = '';

        $this->settings = get_option("woocommerce_ducapp_settings", array());
        $this->login();
    }

    /**
     * Send login request to the DUCAPP API.
     * @return array|WP_Error The response data or WP_Error on failure.
     */
    public function login()
    {

        $url = $this->api_url . '/api/auth/login';

        $username = $this->settings['duc_merchant_name'];
        $password = $this->settings['duc_merchant_password'];

        $data = array(
            'email' => $username,
            'password' => $password,
        );

        $args = array(
            // 'timeout' => 20000,
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data)
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode($response['body']);
        $this->access_token = "Bearer " . $body->accessToken;

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function create_payment_link($order, $back_url)
    {
        // Todo: Pass back url to payment link body

        $url = $this->api_url . '/api/private/transactions/token/createPaymentLink';
        $merchant_external_id = $order->get_id() . '-' . $this->generarHash32();
        $data = array(
            "amount" => floatval(number_format($order->get_total(), 2, ".", "")),
            "currency" => $order->get_currency(),
            "merchant_external_id" => $merchant_external_id,
            "redirectUrl" => $back_url,
            "product" => array(
                "name" => $order->get_id() . '-' . $order->get_order_key(),
                "description" => $order->get_id() . '-' . $order->get_order_key()
            ),
            "customize" => array(
                "link" => array(
                    "provider" => "default",
                    "allowPromoCode" => false,
                    "collectBillingAddress" => true,
                    "collectPhoneNumber" => true
                )
            )
        );

        $args = array(
            'headers' => array(
                'authorization' => $this->access_token,
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data)
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        // Todo: if error show error message
        // - save payment link to order meta
        // 

       

        return json_decode(wp_remote_retrieve_body($response), true);
    }


    private function generarHash32()
    {
        // Obtén la fecha actual en formato "Y-m-d" (Año-Mes-Día)
        $fechaActual = date('Y-m-d');

        // Genera una cadena aleatoria de 24 caracteres (para completar los 32 caracteres)
        $cadenaAleatoria = bin2hex(random_bytes(12)); // 12 bytes = 24 caracteres hexadecimales

        // Combina la fecha actual con la cadena aleatoria
        $hash = $fechaActual . '-' . $cadenaAleatoria;

        // Asegúrate de que tenga exactamente 32 caracteres
        return substr($hash, 0, 32);
    }
}

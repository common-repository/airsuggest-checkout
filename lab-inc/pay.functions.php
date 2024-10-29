<?php

/**
 * 	Airsuggest for Store
 * 	Author: Ronak Joshi
 * 	Author URI: https://twitter.com/RonakJoshiio
 * 	Created: 05/11/2019
 * 	modified: 13/11/2019
 * */
class Wpl_airsuggest_WC extends WC_Payment_Gateway {

    public function __construct() {
        global $woocommerce;
        $this->id = 'wpl_airsuggest';
        $this->method_title = __('Airsuggest Checkout', 'wpl_woocommerce_airsuggest');
        $this->method_description = __('This Plugin provides you feature for custom checkout option for all types of discount.', 'wpl_woocommerce_airsuggest');
        $this->icon = $this->wpl_plugin_url() . '/images/icon.png';
        $this->has_fields = true;
        $this->url = 'https://www.airsuggest.com/controller/api_checkout.php';
        $this->init_form_fields();
        $this->init_settings();
        $this->responseVal = '';
        $uploads = wp_upload_dir();
        $this->txn_log = $uploads['basedir'] . "/txn_log/airsuggest";
        wp_mkdir_p($this->txn_log); /// Create IPN Log files for backup transaction details

        $this->enabled = $this->settings['enabled'];
        ;

        if (isset($this->settings['thank_you_message']))
            $this->thank_you_message = sanitize_text_field($this->settings['thank_you_message']);

        $this->title = sanitize_text_field($this->settings['title']);
        $this->description = sanitize_text_field($this->settings['description']);
        $this->api_key = sanitize_text_field($this->settings['api_key']);

        if (isset($_GET['wpl_airsuggest_callback']) && isset($_GET['results']) && esc_attr($_GET['wpl_airsuggest_callback']) == 1 && esc_attr($_GET['results']) != '') {
            $this->responseVal = $_GET['results'];
            add_filter(' ', array($this, 'wpl_airsuggest_thankyou'));
        }
        add_action('init', array(&$this, 'wpl_airsuggest_transaction'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'wpl_airsuggest_transaction'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'wpl_airsuggest_receipt_page'));
    }

// End Constructor

    /**
     * init Gateway Form Fields
     *
     * @since 1.0
     */
    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable:', 'wpl_woocommerce_airsuggest'),
                'type' => 'checkbox',
                'label' => __('Enable Airsuggest', 'wpl_woocommerce_airsuggest'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title:', 'wpl_woocommerce_airsuggest'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wpl_woocommerce_airsuggest'),
                'default' => __('Airsuggest', 'wpl_woocommerce_airsuggest')
            ),
            'description' => array(
                'title' => __('Description:', 'wpl_woocommerce_airsuggest'),
                'type' => 'textarea',
                'description' => __('This controls the title which the user sees during checkout.', 'wpl_woocommerce_airsuggest'),
                'default' => __('Airsuggest Checkout gives you automatic discount.', 'wpl_woocommerce_airsuggest'),
            ),
            'api_key' => array(
                'title' => __('Api Key:', 'wpl_woocommerce_airsuggest'),
                'type' => 'text',
                'custom_attributes' => array('required' => 'required'),
                'description' => __('You can find Api key in your My Products section with Airsuggest', 'wpl_woocommerce_airsuggest'),
                'default' => ''
            ),
            'thank_you_message' => array(
                'title' => __('Thank you page message:', 'wpl_woocommerce_airsuggest'),
                'type' => 'textarea',
                'description' => __('Thank you page order success message when order has been received', 'wpl_woocommerce_airsuggest'),
                'default' => __('Thank you. Your order has been received.', 'wpl_woocommerce_airsuggest'),
            ),
        );
    }

// function init_form_fields() end

    /**
     * Get running plugin URL
     *
     * @since 1.0
     */
    private function wpl_plugin_url() {
        if (isset($this->wpl_plugin_url))
            return $this->wpl_plugin_url;

        if (is_ssl()) {
            return $this->wpl_plugin_url = str_replace('http://', 'https://', WP_PLUGIN_URL) . '/' . plugin_basename(dirname(dirname(__FILE__)));
        } else {
            return $this->wpl_plugin_url = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__)));
        }
    }

// function wpl_plugin_url() end

    /**
     * WP Admin Options
     *
     * @since 1.0
     */
    public function admin_options() {
        ?>
        <h3><?php _e('Airsuggest', 'wpl_woocommerce_airsuggest'); ?></h3>
        <p><?php _e('This works by sending the user to Airsuggest to enter their payment information. We will give you automatic discount', 'wpl_woocommerce_airsuggest'); ?></p>

        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

// function admin_options() end

    /**
     * Build the form after click on PayUmoney Paylabs button.
     *
     * @since 1.0
     */
    private function wpl_generate_airsuggest_form($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
//			$productinfo = sprintf( __( 'Order ID:'.$order_id.' from ', 'wpl_woocommerce_airsuggest' ), $order_id ) . get_bloginfo( 'name' );
        $productinfo = array();
        $hash_data['total_qty'] = 0;
        $hash_data['api_key'] = $this->api_key;
        //$hash_data['txnid'] 			 = $txnid;

        foreach ($order->get_items() as $item_id => $item_data) {
            // Get an instance of corresponding the WC_Product object
            $product = $item_data->get_product();
            $image = wp_get_attachment_image_url($product->get_image_id(), 'post-thumbnail');
            $productinfo[] = array("name" => $product->get_name(), "sku" => $product->get_sku(), "price" => $product->get_price(), "image" => $image, "order_qty" => $item_data->get_quantity());
            //$product_name = $product->get_name(); // Get the product name
            $hash_data['total_qty'] = $hash_data['total_qty'] + $item_data->get_quantity(); // Get the item quantity
            //$item_total = $item_data->get_total(); // Get the item line total
        }

        $hash_data['product_total'] = $order->get_subtotal();
        $hash_data['vendor_order_id'] = $order_id;
        $hash_data['currency'] = get_woocommerce_currency();
        $hash_data['product_info'] = wp_json_encode($productinfo);
        $hash_data['shipping'] = $order->get_shipping_total();
        $hash_data['tax'] = $order->get_total_tax();
        $hash_data['plugin'] = "WooCommerce";
        $hash_data['blogkey'] = isset($_SESSION['blogkey']) ? $_SESSION['blogkey'] : '';
//                echo"<pre>";print_r($hash_data);echo"</pre>";die;
        update_post_meta($order_id, '_transaction_id', $txnid);
        $returnURL = $woocommerce->api_request_url(strtolower(get_class($this)));
        $hash_data['success_url'] = $returnURL;
        $payuform = '';

        foreach ($hash_data as $key => $value) {
            if ($value) {
//                echo $key." : ".$value."<br>";
                $payuform .= "<input type='hidden' name='{$key}' value='{$value}' />" . "\n";
            }
        }
//        if (session_status() == PHP_SESSION_NONE) {
//            session_start();
//        }
//        print_r($_SESSION);
//        echo"<pre>";
//        print_r($hash_data);
//        echo"</pre>";
//        die;
        $posturl = $this->url;


        return '<form action="' . $posturl . '" method="POST" name="payform" id="payform">
					' . $payuform . '
					<input type="submit" class="button" id="submit_airsuggest_payment_form" value="' . __('Pay Now', 'wpl_woocommerce_airsuggest') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'wpl_woocommerce_airsuggest') . '</a>
					<script type="text/javascript">
						jQuery(function(){
							jQuery("body").block(
								{
									message: "' . __('Thank you for your order. We are now redirecting you to make payment.', 'wpl_woocommerce_airsuggest') . '",
									overlayCSS:
									{
										background: "#fff",
										opacity: 0.6
									},
									css: {
								        padding:        20,
								        textAlign:      "center",
								        color:          "#555",
								        border:         "3px solid #aaa",
								        backgroundColor:"#fff",
								        cursor:         "wait"
								    }
								});
								jQuery("#payform").attr("action","' . $posturl . '");
							jQuery("#submit_airsuggest_payment_form").click();
						});
					</script>
				</form>';
    }

// function wpl_generate_airsuggest_form() end

    /**
     * Process the payment for checkout.
     *
     * @since 1.0
     */
    function process_payment($order_id) {
        $this->wpl_airsuggest_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

// function process_payment() end

    /**
     * Page after cheout button and redirect to PayU payment page.
     *
     * @since 1.0
     */
    function wpl_airsuggest_receipt_page($order_id) {
        $this->wpl_airsuggest_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        echo '<p>' . __('Thank you for your order, please click the button below to pay.', 'wpl_woocommerce_airsuggest') . '</p>';
        echo $this->wpl_generate_airsuggest_form($order_id);
    }

// function wpl_airsuggest_receipt_page() end

    /**
     * Clear the cache data for browser 
     *
     * @since 1.0
     */
    private function wpl_airsuggest_clear_cache() {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
    }

// function wpl_airsuggest_clear_cache() end

    /**
     * Check the status of current transaction and get response with $_POST
     *
     * @since 1.0
     */
    function wpl_airsuggest_transaction() {
        global $woocommerce;
        $order_id = absint(WC()->session->get('order_awaiting_payment'));
        $order = new WC_Order($order_id);

        if (!empty($_POST)) {
            $apiKey = $this->api_key;
            if ($apiKey != $_POST['key']) {
                die('invalid Api key!');
            } else {
                $postData = $_POST;
            }
        } else {
            die('No transaction data was passed!');
        }

        $status = sanitize_text_field($postData['status']);
        $msg = sanitize_text_field($postData['msg']);
//        print_r($status);
//        echo "status" . $status. "<br> msg".$msg; die;
        if ($status == 'success') {
            $order->payment_complete($postData['transactionID']);
            $order->add_order_note('Payment successful.<br/>Transaction id: ' . sanitize_text_field($postData['transactionID']) . '|| Amount: ' . sanitize_text_field($postData['totalCost']));
        } elseif ($status == 'error') {
            $order->update_status('failed');
            wc_add_notice('Error on payment: ' . $msg, 'error');
            $order->add_order_note($msg);
            wp_redirect($order->get_checkout_payment_url(false));
        }

        $results = urlencode(base64_encode(json_encode($_POST)));
        $return_url = add_query_arg(array('wpl_airsuggest_callback' => 1, 'results' => $results, 'rul' => urlencode_deep(get_home_url())), $this->get_return_url($order));
//        print_r($return_url);die;
        wp_redirect($return_url);
    }

// function wpl_airsuggest_transaction() end

    /**
     * send request and get Transaction verification
     * @since 1.0
     */
    private function wpl_send_request($host, $data) {
        $response = wp_remote_post($host, array(
            'headers' => array(),
            'body' => $data,
        ));
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        return $response_body;
    }

// function wpl_send_request() end
    /**
     * Thank you page success data
     * @since 1.0
     */
//    orderdetail table function
    function wpl_airsuggest_thankyou() {
        $wpl_airsuggest_response = json_decode(base64_decode(urldecode($this->responseVal)), true);
//        echo "Hello";
//        print_r($wpl_airsuggest_response);die;
        if (strtolower($wpl_airsuggest_response['status']) == 'success') {
            $added_text = '<section class="woocommerce-order-details">
									<h3>' . $this->thank_you_message . '</h3>
									<h2 class="woocommerce-order-details__title">Transaction details</h2>
									<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
										<thead>
											<tr>
												<th class="woocommerce-table__product-name product-name"> Transaction id:</th>
												<th class="woocommerce-table__product-table product-total">' . $wpl_airsuggest_response['transactionID'] . '</th>
											</tr>
										</thead>
										<tbody>
											<tr class="woocommerce-table__line-item order_item">
												<td class="woocommerce-table__product-name product-name">Total Amount Paid:</td>
												<td class="woocommerce-table__product-total product-total">$' . $wpl_airsuggest_response['totalCost'] . '</td>
											</tr>
										</tbody>
									</table>
								</section>';
        } else {
            wc_add_notice('Error on payment', 'error');
            wp_redirect($order->get_checkout_payment_url(false));
        }
        return $added_text;
    }

// function wpl_airsuggest_thankyou() end
}

//  End Wpl_PayLabs_WC_Payu Class
<?php

/**
 * Plugin Name: Pagantis WooCommerce
 * Plugin URI: http://www.pagantis.com/
 * Description: Financiar con Pagantis
 * Version: 1.0
 * Author: Pagantis
 */


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    add_action('plugins_loaded', 'init_wc_pagantis_payment_gateway', 0);

    function init_wc_pagantis_payment_gateway() {

        if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

        class WC_Pagantis extends WC_Payment_Gateway {

            const PAGA_MAS_TARDE = 'pagamastarde';
            const TEST_ENVIRONMENT = 'Test';
            const REAL_ENVIRONMENT = 'Real';

            var $notify_url;
            public function __construct() {
                $this->id                 = 'pagantis';
                $this->icon               = home_url() . '/wp-content/plugins/pagantis/pages/assets/images/logopagamastarde_80.png';
                $this->method_title       = __( 'Pago con Paga Mas Tarde', 'woocommerce' );
                $this->method_description = __( 'Financiar los pedidos con Paga Mas Tarde', 'woocommerce' );
                $this ->notify_url		  = WC()->api_request_url('WC_Pagantis');
                $this ->ok_url		  = "";
                $this ->ko_url		  = "";
                $this->log  			  =  new WC_Logger();

                $this->has_fields       = true;

                // Load the settings
                $this->init_form_fields();
                $this->init_settings();

                //Recoger Formulario
                $this->enabled  	= $this->get_option( 'enabled' );
                $this->title  		= $this->get_option( 'title' );
                $this->description  = $this->get_option( 'description' );
                $this->environment  = $this->get_option( 'environment' );
                $this->auth_method  = $this->get_option( 'auth_method' );
                $this->test_account = $this->get_option( 'test_account' );
                $this->test_key  	= $this->get_option( 'test_key' );
                $this->real_account = $this->get_option( 'real_account' );
                $this->real_key  	= $this->get_option( 'real_key' );
                $this->lang  		= $this->get_option( 'lang' );
                $this->paga_mastarde_url  	 = $this->get_option( 'paga_mastarde_url' );
                $this->currency = "EUR";

                // Actions
                add_action( 'woocommerce_receipt_pagantis', array( $this, 'receipt_page' ) );
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                //Payment listener/API hook
                add_action( 'woocommerce_api_wc_pagantis', array( $this, 'check_rds_response' ) );
            }


            /**
             * Admin form fields
             */
            public function init_form_fields() {
                global $woocommerce;

                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => __( 'Activar', 'woocommerce' ),
                        'label'       => __( 'Activar el módulo', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'yes'
                    ),
                    'title' => array(
                        'title'       => __( 'Título', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'Titulo para el método de pago.', 'woocommerce' ),
                        'default'     => __( 'Paga Más Tarde', 'woocommerce' ),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __( 'Descripción', 'woocommerce' ),
                        'type'        => 'textarea',
                        'description' => __( 'Texto que aparece cuando el cliente selecciona el método de pago.', 'woocommerce' ),
                        'default'     => __( 'Financia tu pedido con Paga Más Tarde!', 'woocommerce' ),
                        'desc_tip'    => true,
                    ),
                    'environment' => array(
                        'title'       => __( 'Entorno', 'woocommerce' ),
                        'type'        => 'select',
                        'description' => __( 'Entorno del proceso de pago.', 'woocommerce' ),
                        'default'     => self::TEST_ENVIRONMENT,
                        'desc_tip'    => true,
                        'options'     => array(
                            'Test' => __( self::TEST_ENVIRONMENT, 'woocommerce' ),
                            'Real' => __( self::REAL_ENVIRONMENT, 'woocommerce' ),
                        )
                    ),
                    'paga_mastarde_url' => array(
                        'title'       => __( 'URL pago Paga Mas Tarde', 'woocommerce' ),
                        'type'        => 'text',
                        'default'     => __( 'https://pmt.pagantis.com/v1/installments', 'woocommerce' ),
                    ),

                    'auth_method' => array(
                        'title'       => __( 'Método de autenticación', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'Valor necesario (SHA1)', 'woocommerce' ),
                        'default'     => __( 'SHA1', 'woocommerce' ),
                        'desc_tip'    => true,
                    ),

                    'test_account' => array(
                        'title'       => __( 'TEST - Código de cuenta', 'woocommerce' ),
                        'type'        => 'text',
                    ),
                    'test_key' => array(
                        'title'       => __( 'TEST - Clave de firma', 'woocommerce' ),
                        'type'        => 'text',
                    ),

                    'real_account' => array(
                        'title'       => __( 'REAL - Código de cuenta', 'woocommerce' ),
                        'type'        => 'text',
                    ),
                    'real_key' => array(
                        'title'       => __( 'REAL - Clave de firma', 'woocommerce' ),
                        'type'        => 'text',
                    ),
                    'lang' => array(
                        'title'       => __( 'Idioma página de pago', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'Elegir el idioma con el cual se abrirá la página de pago Pagantis. Por ejemplo ES, FR, EN', 'woocommerce' ),
                        'default'     => __( 'ES', 'woocommerce' ),
                        'desc_tip'    => true,
                    ),
                );
            }




            /**
             * Call to Generate form
             * @param $order
             */
            function receipt_page($order){
                //Log
                $this->log->add( 'pagantis', 'Acceso a la página de confirmación de la opción de pago con Paga+Tarde');
                echo '<p>'.__('Gracias por su pedido, por favor pulsa el botón "Realizar el pago" para pagar con Paga Mas Tarde', 'pagantis').'</p>';
                echo $this -> generate_pagantis_form($order);

            }

            /** Process Payment
             * @param int $order_id
             * @return array
             */
            function process_payment($order_id){
                global $woocommerce;
                $order = new WC_Order($order_id);
                $this->log->add( 'pagantis', 'Acceso a la opción de pago con Paga+Tarde ');
                // Return receipt_page redirect
                return array(
                    'result' 	=> 'success',
                    'redirect'	=> $order->get_checkout_payment_url( true )
                );
            }

            /**
             * Generate form
             * @param $order_id
             * @return string
             */
            function generate_pagantis_form( $order_id ) {
                //Logs
                $this->log->add( 'pagantis', 'Acceso al pago con Paga+Tarde');

                $order = new WC_Order($order_id);
                //Total Amount
                $transaction_amount = number_format( (float) ($order->get_total()), 2, '.', '' );
                $transaction_amount = str_replace('.','',$transaction_amount);
                $transaction_amount = floatval($transaction_amount);

                // Product Description
                $products = WC()->cart->cart_contents;
                $i = 1;
                $products_form = "";
                foreach ($products as $product) {
                    $products_form .= "
                          <input name='items[".$i."][description]' type='hidden' value='".$product['data']->post->post_title."'>
                          <input name='items[".$i."][quantity]' type='hidden' value='".$product['quantity']."'>
                          <input name='items[".$i."][amount]' type='hidden' value='".$product['data']->price."'>
                    ";
                    $i++;

                }
                $shipping = $order->get_items('shipping');
                foreach($shipping as $shipping_method){
                    if($shipping_method['cost'] > 0 ){
                        $products_form .= "
                            <input name='items[".$i."][description]' type='hidden' value='".__('Gastos de envio', 'pagantis')."'>
                            <input name='items[".$i."][quantity]' type='hidden' value='1'>
                            <input name='items[".$i."][amount]' type='hidden' value='".$shipping_method['cost']."'>
                        ";
                        $i++;
                    }
                }



                $this->ok_url = $this->get_return_url( $order );
                $this->ko_url = htmlspecialchars_decode($order->get_cancel_order_url());

                $account_id = "";
                $dataToEncode ="";
                //Test Environment Selected
                if($this->environment == self::TEST_ENVIRONMENT){
                    $dataToEncode = $this->test_key . $this->test_account . $order_id . $transaction_amount . $this->currency . $this->ok_url . $this->ko_url;
                    $account_id = $this->test_account;
                }
                //Real environment selected
                else{
                    $dataToEncode = $this->real_key . $this->real_account . $order_id . $transaction_amount . $this->currency . $this->ok_url . $this->ko_url;
                    $account_id = $this->real_account;
                }
                $signature = sha1($dataToEncode);

                $form_url = $this->paga_mastarde_url;

                $adressInfo = explode("/", $order->billing_city);


                //todo can include shipping information
                /*<input name='address[street]' type='hidden' value='".$order->billing_address_1."'>
                      <input name='address[city]' type='hidden' value='".$adressInfo[0]."'>
                      <input name='address[province]' type='hidden' value='".$adressInfo[1]."'>
                      <input name='address[zipcode]' type='hidden' value='".$order->billing_postcode."'>
                */

                //Create Form
                $buyer_name = $order -> billing_first_name." ".$order -> billing_last_name;
                $pagantis_form_fields = "
                      <input name='order_id' type='hidden' value='".$order_id."' />
                      <input name='amount' type='hidden' value='".$transaction_amount."' />
                      <input name='currency' type='hidden' value='".$this->currency."' />

                      <input name='ok_url' type='hidden' value='".$this->ok_url."' />
                      <input name='nok_url' type='hidden' value='".$this->ko_url."' />
                      
                      <input name='locale' type='hidden' value='".$this->lang."' />
                    
                      <input name='full_name' type='hidden' value='".$buyer_name."'>
                      <input name='email' type='hidden' value='".$order->billing_email."'>
                    
                      ".$products_form."

                      <!-- firma de la operación -->
                      <input name='account_id' type='hidden' value='".$account_id."' />
                      <input name='signature' type='hidden' value='".$signature."' />
                ";

                return '<form action="'.$form_url.'" method="post" id="pagantis_payment_form">
				' . $pagantis_form_fields . '
				    <a class="button cancel floatLeft" href="'.$order->get_cancel_order_url().'">'.__('Cancelar Pedido', 'pagantis').'</a>
				    <input type="submit" class="button-alt" id="submit_vme_payment_form" value="'.__('Realizar el pago', 'pagantis').'" />

				 </form>
				 ';

            }


            /**
             * Check Pagantis response
             */
            function check_rds_response() {
                global $woocommerce;
                $json = file_get_contents('php://input');
                $temp = json_decode($json,true);
                $data = $temp['data'];
                if(!empty($data)){
                    $orderId = $data['order_id'];
                    $order = new WC_Order($orderId);
                    $this->log->add( 'pagantis', 'Paga+Tarde notification received for orderId:'.$orderId);
                    switch ($temp['event']) {
                        case 'charge.created':
                            $order->update_status('processing',__( 'Pago realizo con Pagantis', 'woocommerce' ));
                            $this->log->add( 'pagantis', 'Operación finalizada. PEDIDO ACEPTADO:'.$orderId);
                            $order->reduce_order_stock();
                            // Remove cart
                            WC()->cart->empty_cart();
                            wp_redirect($this->get_return_url($order));
                            break;
                        case 'charge.failed':
                            $order->update_status('cancelled',__( 'Error en el pago con Pagantis', 'woocommerce' ));
                            $this->log->add( 'pagantis', 'Operación finalizada. PEDIDO CANCELADO'.$orderId);
                            wp_redirect($order->get_cancel_order_url());
                            break;
                    }
                }
                else{
                    wp_die( '<img src="'.home_url() .'/wp-content/plugins/pagantis/pages/assets/images/logopagamastarde.png" alt="Pagamastarde logo" height="70" width="242"/><br>
						<img src="'.home_url().'/wp-content/plugins/pagantis/pages/assets/images/cross.png" alt="Desactivado" title="Desactivado" />
						<b>Pagantis</b>: Fallo en el proceso de pago.<br>Su pedido ha sido cancelado.
						<a style="clear:both" href ="'.home_url( '/' ).'" > Volver </a>' );
                }

            }

        }

        /**
         * Add the gateway to WooCommerce
         **/
        function add_pagantis_gateway( $methods ) {
            $methods[] = 'WC_pagantis'; return $methods;
        }


        add_filter('woocommerce_payment_gateways', 'add_pagantis_gateway' );
    }
}
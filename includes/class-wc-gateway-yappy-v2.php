<?php

defined( 'ABSPATH' ) || exit;

final class WC_Gateway_Yappy_V2 extends WC_Payment_Gateway {
    public function __construct() {
        $this->id                 = 'yappy_v2';
        $this->method_title       = __( 'Yappy V2', 'woocommerce-yappy-v2' );
        $this->method_description = __( 'Cobros con el Botón de Pago Yappy V2.', 'woocommerce-yappy-v2' );
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', 'Yappy' );
        $this->description = $this->get_option( 'description', __( 'Paga de forma rápida y segura con Yappy.', 'woocommerce-yappy-v2' ) );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_filter( 'script_loader_tag', array( $this, 'module_script_tag' ), 10, 3 );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array( 'title' => __( 'Activar', 'woocommerce-yappy-v2' ), 'type' => 'checkbox', 'label' => __( 'Activar Yappy V2', 'woocommerce-yappy-v2' ), 'default' => 'no' ),
            'title' => array( 'title' => __( 'Título', 'woocommerce-yappy-v2' ), 'type' => 'text', 'default' => 'Yappy' ),
            'description' => array( 'title' => __( 'Descripción', 'woocommerce-yappy-v2' ), 'type' => 'textarea', 'default' => __( 'Paga de forma rápida y segura con Yappy.', 'woocommerce-yappy-v2' ) ),
            'environment' => array( 'title' => __( 'Ambiente', 'woocommerce-yappy-v2' ), 'type' => 'select', 'default' => 'test', 'options' => array( 'test' => __( 'Pruebas (UAT)', 'woocommerce-yappy-v2' ), 'production' => __( 'Producción', 'woocommerce-yappy-v2' ) ) ),
            'merchant_id' => array( 'title' => __( 'Merchant ID', 'woocommerce-yappy-v2' ), 'type' => 'text', 'description' => __( 'ID de comercio generado en Yappy Comercial.', 'woocommerce-yappy-v2' ) ),
            'secret_key' => array( 'title' => __( 'Clave secreta', 'woocommerce-yappy-v2' ), 'type' => 'password', 'description' => __( 'Clave secreta generada por Yappy. También puede definirse como WC_YAPPY_SECRET_KEY en wp-config.php.', 'woocommerce-yappy-v2' ) ),
            'domain' => array( 'title' => __( 'Dominio registrado', 'woocommerce-yappy-v2' ), 'type' => 'text', 'default' => home_url(), 'description' => __( 'Debe coincidir con el dominio configurado en Yappy Comercial.', 'woocommerce-yappy-v2' ) ),
            'theme' => array( 'title' => __( 'Tema del botón', 'woocommerce-yappy-v2' ), 'type' => 'select', 'default' => 'blue', 'options' => array( 'blue'=>'blue','darkBlue'=>'darkBlue','orange'=>'orange','dark'=>'dark','sky'=>'sky','light'=>'light' ) ),
            'rounded' => array( 'title' => __( 'Bordes redondeados', 'woocommerce-yappy-v2' ), 'type' => 'checkbox', 'label' => __( 'Usar bordes redondeados', 'woocommerce-yappy-v2' ), 'default' => 'yes' ),
            'debug' => array( 'title' => __( 'Debug', 'woocommerce-yappy-v2' ), 'type' => 'checkbox', 'label' => __( 'Activar logs de depuración', 'woocommerce-yappy-v2' ), 'default' => 'no', 'description' => __( 'Los logs aparecen en WooCommerce > Estado > Registros.', 'woocommerce-yappy-v2' ) ),
            'test_connection' => array( 'title' => __( 'Conexión', 'woocommerce-yappy-v2' ), 'type' => 'yappy_test_connection' ),
        );
    }

    public function generate_yappy_test_connection_html( $key, $data ) {
        $nonce = wp_create_nonce( 'wc_yappy_test_connection' );
        ob_start();
        ?>
        <tr valign="top"><th scope="row" class="titledesc"><?php echo esc_html( $data['title'] ); ?></th><td class="forminp">
            <button type="button" class="button" id="wc-yappy-test-connection"><?php esc_html_e( 'Probar conexión con Yappy', 'woocommerce-yappy-v2' ); ?></button>
            <span id="wc-yappy-test-result" style="margin-left:8px"></span>
            <script>
            document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('wc-yappy-test-connection');if(!b)return;b.addEventListener('click',function(){var r=document.getElementById('wc-yappy-test-result');b.disabled=true;r.textContent='<?php echo esc_js( __( 'Probando…', 'woocommerce-yappy-v2' ) ); ?>';var f=new FormData();f.append('action','wc_yappy_test_connection');f.append('_ajax_nonce','<?php echo esc_js( $nonce ); ?>');fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:f}).then(function(x){return x.json();}).then(function(x){r.textContent=x.data&&x.data.message?x.data.message:'Error';}).catch(function(){r.textContent='Error';}).finally(function(){b.disabled=false;});});});
            </script>
        </td></tr>
        <?php
        return ob_get_clean();
    }

    public function is_available() {
        if ( ! parent::is_available() || ! WC_Yappy_V2_Config::ready() ) {
            return false;
        }
        $supported = apply_filters( 'wc_yappy_v2_supported_currencies', array( 'USD', 'PAB' ) );
        return in_array( get_woocommerce_currency(), $supported, true );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'No fue posible cargar el pedido.', 'woocommerce-yappy-v2' ), 'error' );
            return array( 'result' => 'failure' );
        }
        $order->update_status( 'pending', __( 'Esperando pago por Yappy.', 'woocommerce-yappy-v2' ) );
        return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );
    }

    public function receipt_page( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            echo '<p>' . esc_html__( 'Pedido no encontrado.', 'woocommerce-yappy-v2' ) . '</p>';
            return;
        }
        if ( ! $order->needs_payment() ) {
            echo '<p>' . esc_html__( 'Este pedido ya está pagado.', 'woocommerce-yappy-v2' ) . '</p>';
            return;
        }

        wp_register_script( 'wc-yappy-v2-sdk', WC_Yappy_V2_Config::cdn_url(), array(), null, true );
        wp_register_script( 'wc-yappy-v2-payment', WC_YAPPY_V2_URL . 'assets/js/payment.js', array( 'wc-yappy-v2-sdk' ), WC_YAPPY_V2_VERSION, true );
        wp_enqueue_script( 'wc-yappy-v2-sdk' );
        wp_enqueue_script( 'wc-yappy-v2-payment' );
        wp_enqueue_style( 'wc-yappy-v2-payment', WC_YAPPY_V2_URL . 'assets/css/payment.css', array(), WC_YAPPY_V2_VERSION );

        wp_localize_script( 'wc-yappy-v2-payment', 'wcYappyV2', array(
            'orderId' => $order->get_id(),
            'orderKey' => $order->get_order_key(),
            'paymentToken' => WC_Yappy_V2_Payment_Token::create( $order ),
            'createUrl' => rest_url( 'wc-yappy/v1/orders/' . $order->get_id() . '/payment' ),
            'statusUrl' => rest_url( 'wc-yappy/v1/orders/' . $order->get_id() . '/status' ),
            'redirectUrl' => $this->get_return_url( $order ),
            'theme' => WC_Yappy_V2_Config::theme(),
            'rounded' => WC_Yappy_V2_Config::rounded(),
            'messages' => array(
                'invalidPhone' => __( 'Ingresa un número de celular panameño de 8 dígitos.', 'woocommerce-yappy-v2' ),
                'creating' => __( 'Creando solicitud Yappy…', 'woocommerce-yappy-v2' ),
                'confirming' => __( 'Pago recibido. Confirmando con Yappy…', 'woocommerce-yappy-v2' ),
                'waiting' => __( 'La confirmación aún está pendiente. Revisa tu aplicación Yappy.', 'woocommerce-yappy-v2' ),
                'genericError' => __( 'No fue posible iniciar el pago con Yappy.', 'woocommerce-yappy-v2' ),
            ),
        ) );

        $phone = ( new WC_Yappy_V2_Payment_Service() )->normalize_phone( $order->get_billing_phone() );
        include WC_YAPPY_V2_DIR . 'templates/payment.php';
    }

    public function module_script_tag( $tag, $handle, $src ) {
        if ( 'wc-yappy-v2-sdk' !== $handle ) {
            return $tag;
        }
        return '<script type="module" src="' . esc_url( $src ) . '"></script>';
    }
}

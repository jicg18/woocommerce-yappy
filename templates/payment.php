<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wc-yappy-v2-payment">
    <h3><?php esc_html_e( 'Completa tu pago con Yappy', 'woocommerce-yappy-v2' ); ?></h3>
    <div class="wc-yappy-v2-summary">
        <strong><?php echo esc_html( sprintf( __( 'Pedido #%s', 'woocommerce-yappy-v2' ), $order->get_order_number() ) ); ?></strong><br>
        <?php echo wp_kses_post( sprintf( __( 'Total: %s', 'woocommerce-yappy-v2' ), $order->get_formatted_order_total() ) ); ?>
    </div>
    <div class="wc-yappy-v2-field">
        <label for="wc-yappy-v2-phone"><?php esc_html_e( 'Número de celular asociado a Yappy', 'woocommerce-yappy-v2' ); ?></label>
        <input id="wc-yappy-v2-phone" type="tel" inputmode="numeric" autocomplete="tel" maxlength="16" value="<?php echo esc_attr( $phone ); ?>" placeholder="61234567">
    </div>
    <div class="wc-yappy-v2-button-wrap">
        <btn-yappy id="wc-yappy-v2-button" theme="<?php echo esc_attr( WC_Yappy_V2_Config::theme() ); ?>"<?php echo WC_Yappy_V2_Config::rounded() ? ' rounded="true"' : ''; ?>></btn-yappy>
    </div>
    <div id="wc-yappy-v2-status" class="wc-yappy-v2-status" role="status" aria-live="polite"></div>
    <button type="button" id="wc-yappy-v2-verify" class="button" hidden><?php esc_html_e( 'Verificar pago', 'woocommerce-yappy-v2' ); ?></button>
    <p class="wc-yappy-v2-help"><?php esc_html_e( 'La orden se marcará como pagada únicamente cuando WooCommerce reciba y valide la notificación firmada de Yappy.', 'woocommerce-yappy-v2' ); ?></p>
</div>

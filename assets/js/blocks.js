(function () {
    'use strict';
    if (!window.wc || !window.wc.wcBlocksRegistry || !window.wc.wcSettings || !window.wp || !window.wp.element) return;

    var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
    var settings = window.wc.wcSettings.getSetting('yappy_v2_data', {});
    var el = window.wp.element.createElement;
    var decode = window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities ? window.wp.htmlEntities.decodeEntities : function (v) { return v; };

    var Label = function (props) {
        if (props.components && props.components.PaymentMethodLabel) {
            return el(props.components.PaymentMethodLabel, { text: decode(settings.title || 'Yappy') });
        }
        return el('span', null, decode(settings.title || 'Yappy'));
    };

    var Content = function () {
        return el('div', { className: 'wc-yappy-v2-block-description' }, decode(settings.description || 'Paga de forma rápida y segura con Yappy.'));
    };

    registerPaymentMethod({
        name: 'yappy_v2',
        paymentMethodId: 'yappy_v2',
        gatewayId: 'yappy_v2',
        label: el(Label, null),
        content: el(Content, null),
        edit: el(Content, null),
        ariaLabel: decode(settings.title || 'Yappy'),
        placeOrderButtonLabel: settings.buttonLabel || 'Continuar con Yappy',
        canMakePayment: function () { return true; },
        supports: { features: settings.supports || ['products'] }
    });
}());

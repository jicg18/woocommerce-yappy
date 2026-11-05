(function () {
    'use strict';
    var cfg = window.wcYappyV2 || {};
    var button = document.getElementById('wc-yappy-v2-button');
    var phone = document.getElementById('wc-yappy-v2-phone');
    var statusBox = document.getElementById('wc-yappy-v2-status');
    var verifyButton = document.getElementById('wc-yappy-v2-verify');
    if (!button || !phone || !statusBox) return;

    function setStatus(message, type) {
        statusBox.textContent = message || '';
        statusBox.className = 'wc-yappy-v2-status ' + (type ? 'is-' + type : '');
    }

    function cleanPhone(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (digits.length === 11 && digits.indexOf('507') === 0) digits = digits.slice(3);
        return digits;
    }

    function authPayload(extra) {
        return Object.assign({ orderKey: cfg.orderKey, paymentToken: cfg.paymentToken }, extra || {});
    }

    function parseError(response, data) {
        if (data && data.message) return data.message;
        if (data && data.data && data.data.message) return data.data.message;
        return cfg.messages.genericError || 'No fue posible iniciar el pago con Yappy.';
    }

    async function checkStatus(redirectWhenPaid) {
        var u = new URL(cfg.statusUrl, window.location.href);
        u.searchParams.set('orderKey', cfg.orderKey);
        u.searchParams.set('paymentToken', cfg.paymentToken);
        var response = await fetch(u.toString(), { credentials: 'same-origin' });
        var data = await response.json();
        if (data && data.paid) {
            setStatus('Pago confirmado.', 'success');
            if (redirectWhenPaid) window.location.href = data.redirect || cfg.redirectUrl;
            return 'paid';
        }
        if (data && data.canRetry) {
            setStatus('La solicitud anterior finalizó. Puedes intentar pagar nuevamente con Yappy.', 'info');
            return 'retry';
        }
        return 'pending';
    }

    async function pollStatus() {
        for (var i = 0; i < 40; i++) {
            var result = null;
            try {
                result = await checkStatus(true);
            } catch (e) {}
            if (result === 'paid') return;
            if (result === 'retry') {
                button.isButtonLoading = false;
                return;
            }
            await new Promise(function (resolve) { setTimeout(resolve, 1500); });
        }
        button.isButtonLoading = false;
        setStatus(cfg.messages.waiting, 'info');
        if (verifyButton) verifyButton.hidden = false;
    }

    button.addEventListener('eventClick', async function () {
        var alias = cleanPhone(phone.value);
        if (!/^\d{8}$/.test(alias)) {
            setStatus(cfg.messages.invalidPhone, 'error');
            button.isButtonLoading = false;
            phone.focus();
            return;
        }

        button.isButtonLoading = true;
        setStatus(cfg.messages.creating, 'info');
        try {
            var response = await fetch(cfg.createUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(authPayload({ aliasYappy: alias }))
            });
            var data = await response.json();
            if (!response.ok || !data || !data.transactionId || !data.documentName || !data.token) {
                if (response.status === 409 && verifyButton) verifyButton.hidden = false;
                button.isButtonLoading = false;
                setStatus(parseError(response, data), 'error');
                return;
            }
            button.eventPayment({ transactionId: data.transactionId, documentName: data.documentName, token: data.token });
            setStatus('Confirma la solicitud en tu aplicación Yappy.', 'info');
        } catch (error) {
            button.isButtonLoading = false;
            setStatus(cfg.messages.genericError, 'error');
        }
    });

    button.addEventListener('isYappyOnline', function (event) {
        var online = event && typeof event.detail === 'boolean' ? event.detail : (event && event.detail && typeof event.detail.online === 'boolean' ? event.detail.online : null);
        if (online === false) setStatus('Yappy no está disponible temporalmente. Intenta más tarde.', 'error');
    });

    button.addEventListener('eventSuccess', function () {
        button.isButtonLoading = true;
        setStatus(cfg.messages.confirming, 'info');
        pollStatus();
    });

    button.addEventListener('eventError', function (event) {
        button.isButtonLoading = false;
        var detail = event && event.detail ? event.detail : null;
        var message = detail && (detail.message || detail.description) ? (detail.message || detail.description) : 'La transacción no se completó. Puedes volver a intentarlo cuando la solicitud anterior finalice.';
        setStatus(message, 'error');
        if (verifyButton) verifyButton.hidden = false;
    });

    if (verifyButton) {
        verifyButton.addEventListener('click', async function () {
            verifyButton.disabled = true;
            try {
                var result = await checkStatus(true);
                if (result === 'pending') setStatus(cfg.messages.waiting, 'info');
            } catch (e) {
                setStatus(cfg.messages.genericError, 'error');
            } finally {
                verifyButton.disabled = false;
            }
        });
    }
}());

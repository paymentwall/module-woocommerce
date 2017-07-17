function paymentListener(orderId, baseUrl) {
    var pwInterval = setInterval(function () {
        var r = new XMLHttpRequest();
        r.open("POST", baseUrl + '/index.php?wc-api=paymentwall_gateway&action=ajax', true);
        r.onreadystatechange = function () {
            if (r.readyState != 4 || r.status != 200) return;
            if (r.responseText) {
                var data = JSON.parse(r.responseText);
                if (data && data.status == '1') {
                    clearInterval(pwInterval);
                    location.href = data.url;
                }
            }
        };
        var formData = new FormData();
        formData.append('order_id', orderId);
        r.send(formData);
    }, 5000);
}

var Brick_Payment = {
    brick: null,
    form3Ds : '',
    createBrick: function (public_key) {
        this.brick = new Brick({
            public_key: public_key,
            form: {formatter: true}
        }, 'custom');
    },
    brickTokenizeCard: function () {
        this.brick.tokenizeCard({
            card_number: jQuery('#card-number').val(),
            card_expiration_month: jQuery('#card-expiration-month').val(),
            card_expiration_year: jQuery('#card-expiration-year').val(),
            card_cvv: jQuery('#card-cvv').val()
        }, function (response) {
            if (response.type == 'Error') {
                var errors = "Brick error(s):<br/>" + " - " + (typeof response.error === 'string' ? response.error : response.error.join("<br/> - "));
                Brick_Payment.showNotification(errors, 'error');
            } else {
                jQuery('#brick-token').val(response.token);
                jQuery('#brick-fingerprint').val(Brick.getFingerprint());
                jQuery('#brick-get-token-success').val(1);

                Brick_Payment.sendPaymentRequest();
                window.addEventListener("message", Brick_Payment.threeDSecureMessageHandle, false);
            }
        });
    }, openConfirm3ds: function () {
        var win = window.open("", "Brick: Verify 3D secure", "toolbar=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, width=1024, height=720");
        win.document.body.innerHTML += Brick_Payment.form3Ds;
        win.document.forms[0].submit();
        return false;
    }, threeDSecureMessageHandle: function (event) {
        var origin = event.origin || event.originalEvent.origin;
        if (origin !== "https://api.paymentwall.com") {
            return;
        }
        Brick_Payment.showLoading();
        var brickData = JSON.parse(event.data);
        if (brickData && brickData.event == '3dSecureComplete') {
            jQuery('#hidden-brick-secure-token').val(brickData.data.secure_token);
            jQuery('#hidden-brick-charge-id').val(brickData.data.charge_id);
            Brick_Payment.sendPaymentRequest();
        }
    },
    sendPaymentRequest: function () {
        jQuery.ajax({
            type: 'POST',
            url: '?wc-ajax=checkout',
            data: jQuery('form.checkout').serialize(),
            dataType: 'json',
            encode: true,
            beforeSend: function () {
                Brick_Payment.showLoading();
            },
            success: function (response) {
                if (response.result == 'success') {
                    Brick_Payment.showNotification(response.message);
                    window.location.href = response.redirect;
                } else if (response.result == 'secure') {
                    Brick_Payment.form3Ds = response.secure;
                    var requireConfirm = "Please verify 3D-secure to continue checkout. <a href='javascript:void(0)' onclick='Brick_Payment.openConfirm3ds()'>Click here !</a>";
                    Brick_Payment.showNotification(requireConfirm);
                } else if (response.result == 'failure') {
                    jQuery('#brick-loading').hide();
                    jQuery('#brick-errors').html(response.messages);
                    jQuery('#brick-errors').show();
                } else {
                    Brick_Payment.showNotification(response.message, 'error');
                }
            }
        });
    }, showNotification: function (message, type) {
        type = (type != undefined) ? type : 'message';
        jQuery('#brick-loading').hide();
        jQuery('#brick-errors').html('<ul class="woocommerce-' + type + '"><li> ' + message + ' </li></ul>');
        jQuery('#brick-errors').show();
    }, showLoading: function () {
        jQuery('#brick-errors').hide();
        jQuery('#brick-loading').show();
    }
};
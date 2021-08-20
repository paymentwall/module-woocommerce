var Brick_Payment = {
    brick: null,
    createBrick: function (public_key, amount, currency) {
        "use strict";
        if (window.Brick !== undefined) {
            return new Brick({
                public_key: public_key,
                amount: amount,
                currency: currency,
                container: 'brick-payments-container',
                action: 'wc-api=paymentwall_gateway&action=brick_charge',
                form: {
                    show_zip: true, // show zip code
                    show_cardholder: true,
                    lang: 'en'
                }
            }, 'default')
        }
    }
};
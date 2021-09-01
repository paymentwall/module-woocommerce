var Brick_Payment = {
    brick: null,
    createBrick: function (public_key, amount, currency, action) {
        "use strict";
        if (window.Brick !== undefined) {
            return new Brick({
                public_key: public_key,
                amount: amount,
                currency: currency,
                container: 'brick-payments-container',
                action: action,
                form: {
                    show_zip: true, // show zip code
                    show_cardholder: true,
                    lang: 'en'
                }
            }, 'default')
        }
    }
};
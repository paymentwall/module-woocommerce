var Brick_Payment = (function () {
    return {
        createBrick(public_key, amount, currency, action, save_card) {
            if (window.Brick !== undefined) {
                return new Brick({
                    public_key: public_key,
                    amount: amount,
                    currency: currency,
                    container: 'brick-payments-container',
                    action: action,
                    form: {
                        show_zip: false, // show zip code
                        show_cardholder: true,
                        wcs_hide_email: false,
                        lang: 'en',
                        allow_storing_cards: save_card,
                    }
                }, 'default')
            }
        }
    }
})()
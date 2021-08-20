<script src="https://develop.wallapi.bamboo.stuffio.com/brick/1.6/build/brick.1.6.0.min.js"></script>
<script src="../../assets/js/payment.js"></script>
<div id='brick-payments-container'></div>
<style>
    #brick-payment-form {
        height: 500px;
    }
    .loader-text {
        text-align: center;
    }
</style>
<?php
    session_start();
    $currency = $_SESSION['currency'];
    $amount = $_SESSION['cart_total'];
    $publicKey = $_SESSION['public_key'];

    json_encode($currency);
    json_encode($amount);
    json_encode($publicKey);
?>
<script defer>
    var global = global || window;
    global._babelPolyfill = false;

    var currency = '<?= $currency ?>';
    var amount = '<?= $amount ?>';
    var publicKey = '<?= $publicKey ?>';

    var brick;
    window.onload = function () {
        brick = Brick_Payment.createBrick(publicKey, amount, currency)
        brick.showPaymentForm(function (success) {
            parent.processBrickPlaceOrder()
        }, function (errors) {

        })
    }
</script>
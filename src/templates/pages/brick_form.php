<script src="https://develop.wallapi.bamboo.stuffio.com/brick/1.6/build/brick.1.6.0.min.js"></script>
<!--<script src="../../assets/js/brick-1.6.js"></script>-->
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
$brickFormAction = $_SESSION['brick_form_action'];
?>
<script defer>
    var global = global || window;
    global._babelPolyfill = false;

    var currency = '<?= $currency ?>';
    var amount = '<?= $amount ?>';
    var publicKey = '<?= $publicKey ?>';
    var brickFormAction = '<?= $brickFormAction ?>'

    var brick;
    window.onload = function () {
        brick = Brick_Payment.createBrick(publicKey, amount, currency, brickFormAction)
        brick.showPaymentForm(function (success) {
            parent.processBrickPlaceOrder()
            parent.addCssBrickForm()
        }, function (errors) {

        })
    }
</script>
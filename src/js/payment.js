function paymentListener(orderId, baseUrl) {
    setInterval(function () {
        var r = new XMLHttpRequest();
        r.open("POST", baseUrl + '/index.php?wc-api=paymentwall_gateway&action=ajax', true);
        r.onreadystatechange = function () {
            if (r.readyState != 4 || r.status != 200) return;
            if (r.responseText) {
                var data = JSON.parse(r.responseText);
                if(data && data.status == '1'){
                    location.href = data.url;
                }
            }
        };
        var formData = new FormData();
        formData.append('order_id', orderId);
        r.send(formData);
    }, 5000);
}
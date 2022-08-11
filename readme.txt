 === Paymentwall for Woocommerce ===
Contributors: Paymentwall
Tags: payment, paymentgateway, woocommerce, ecommerce
Requires at least: 4.0 & WooCommerce 2.6+
Tested up to: Wordpress 5.7.2 & Woocommerce 5.3
PHP Version: 5.6 or higher
Stable tag: 1.8.0
License: The MIT License (MIT)

Official Paymentwall module for WordPress WooCommerce.

== Description ==
Paymentwall is the leading all-in-one global payments platform for digital goods and services and supports more than 100 popular local payment options and 75+ currencies, making it easy to monetize globally in more 200+ countries with a single simple integration. Paymentwall is a privately-held company that’s growing fast. It’s headquartered in San Francisco with offices in Kiev, Istanbul, Las Vegas, Amsterdam, Berlin and Manilla and is opening offices in Bejing, Hanoi and Sao Paolo.

== Frequently Asked Questions ==

= Questions? =
* Visit our FAQ: <https://www.paymentwall.com/en/faq>
* Developer support team: <devsupport@paymentwall.com>

== Installation ==

1. Upload `paymentwall-woocommerce` directory to the plugins directory.
2. Go to the plugins setting page and activate "Paymentwall for Woocommerce"
3. On the left sidebar of your WordPress dashboard, navigate to "WooCommerce --> Settings". Click on the **Payment Gateways** tab. **Paymentwall** should already be available as an option.
4. Click on Paymentwall from the options below the WooCommerce tabs, and check **Enable the Paymentwall Payment Gateway Solution**.
5. Enter your project and secret keys, widget code, and fill up the other options.
    * The **Project key** and Secret key are located in your "Paymentwall Merchant Area --> My Projects".
    * The **Widget Code** is located at the Widgets page.
6. Click the **Save Changes** button

View our full installation guide: <https://docs.paymentwall.com/modules/woocommerce>

== Screenshots ==

1. Screenshot 1 - Paymentwall Settings Page

== Changelog ==

= v1.12.0 [08/11/2022] =
* Correct subscription status

= v1.11.0 [07/12/2022] =
* Support subscription cancellation

= v1.10.2 [07/04/2022] =
* Only display selected local payment method on widget

= v1.10.1 [05/04/2022] =
* Correct order status

= v1.10.0 [04/27/2022] =
* Brick - Not support Subscription for guest checkout

= v1.9.0 [04/12/2022] =
* Support Woocommerce Shipment Tracking for Delivery Confirmation API

= v1.8.1 [04/07/2022] =
* Fix duplicated charge for Brick 1.6

= v1.8.0 [21/09/2021] =
* Upgrade brick version from 1.4 to 1.6

= v1.7.4 [10/11/2021]
* New option to open widget in Paymentwall hosted page

= v1.7.3 [1/09/2021] =
* Add file language Russia

= v1.7.2 [1/11/2020] =
* Hide Paymentwall and Brick if they are not activated
* Prevent other plugins from removing Paymentwall/Brick in checkout process

= v1.7.1 [5/8/2020] =
* Remove Test method on Live mode
* Reduce expired time for PS cache


= v1.7.0 [31/7/2020] =
* Replace Paymentwall method by supported local payment methods
* Add more statuses for Delivery Confirmation API

= v1.6.2 [15/11/2018] =
* Remove IP Whitelisting

= v1.6.1 [10/11/2017] =
* Add support for Payment Token API


= v1.6.0 [20/10/2017] =
* Add support for Woocommerce Subscriptions plugin
* Add backward compatibility for Woocommerce version 2.1 - 2.7

= v1.5.5 [03/10/2017] =
* Add backward compatibility for PHP version <5.5

= v1.5.4 [27/09/2017] =
* Update and replace deprecated functions

= v1.5.3 [19/09/2017] =
* Update delivery confirmation API

= v1.5.2 [28/07/2017] =
* Add validation for checkout page

= v1.5.1 [17/07/2017] =
* Update module for Woocommerce 3.1
* Fix jquery conflict with other plugin
* Add icons for Brick in checkout page
* Add user profiles parameter API for better risk tracking

= v1.5.0 [25/04/2017] =
* Update for Woocommerce 3.0

= v1.4.0 [27/03/2017] =
* Update pingback for Brick
* Fix subscription for Brick

= v1.3.4 [19/01/2017] =
* Update pingback for Brick
* Change style confirm 3ds

= v1.3.3 [20/12/2016] =
* Update Platform ID for PwLocal

= v1.3.2 [30/06/2016] =
* Update 3D Secure for Brick

= v1.3.1 [16/12/2015] =
* Update latest Paymentwall PHP lib
* Fix error cannot checkout

= v1.3.0 [25/11/2015] =
* Add Brick subscription support.
* Fix some minor bugs

= v1.2.0 [11/09/2015] =
* Update latest Paymentwall PHP lib
* Add Brick Credit Card processing support
* Source codes refactoring

= v1.0.1 [04/08/2015] =
* Support Wordpress 4.3
* Update latest Paymentwall PHP lib
* Add User Profile API support

= 1.0.0 =
* Init plugin version

== Upgrade Notice == 
## Support

* View our full installation guide: <https://docs.paymentwall.com/modules/woocommerce>
* Learn more about Paymentwall solutions: <https://www.paymentwall.com/en/payment-solutions>

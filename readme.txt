=== eSewa Payment Gateway for WooCommerce ===
Contributors: rabmalin
Donate link: http://www.nilambar.net
Tags: esewa, woocommerce, payment, gateway
Requires at least: 3.5
Tested up to: 3.9.2
Stable tag: 1.0.1

eSewa Payment Gateway for WooCommerce

== Description ==

<h3>eSewa Payment Gateway for WooCommerce</h3>Once installed, you can configure this through Woocommerce Payment Gateways tab.

Test mode is also available in the plugin. It is recommended to test first and only go to Live mode.

Tested with WooCommerce version 2.0.20

eSewa only supports **NPR** currency.

== Installation ==
= Installation =

1. Download.

2. Upload to your /wp-contents/plugins/ directory.

3. Activate the plugin through the 'Plugins' menu in WordPress.

4. Goto Woocommerce -> Settings and select the Payment Gateways tab and click on Esewa just below the tabs.

= Configure Gateway =

1. Add your 'Merchant ID' which would have been supplied by eSewa.

2. First set Test mode ON and test payment gateway carefully.

3. After testing thoroughly, disable Test mode to go to Live mode.

== Frequently Asked Questions ==
= Want to know about eSewa Payment Gateway? =
Go to official site of [eSewa](http://esewa.com.np) to learn more.
= How to add NPR currency in WooCommerce site? =
WooCommerce does not have NPR by default. You can use following code and paste it in `functions.php` of your theme to add NPR currency.
`
function prefix_add_my_currency( $currencies ) {
     $currencies['NPR'] = __( 'Nepali rupee', 'woocommerce' );
     return $currencies;
}
add_filter( 'woocommerce_currencies', 'prefix_add_my_currency' );


function prefix_add_my_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
          case 'NPR': $currency_symbol = 'NRs'; break;
     }
     return $currency_symbol;
}
add_filter('woocommerce_currency_symbol', 'prefix_add_my_currency_symbol', 10, 2);

`

== Screenshots ==

1. eSewa settings screen


== Changelog ==

= Version 1.0.1 =
* Making compatible to WooCommerce 2.1.12

= Version 1.0.0 =
* Feature - Initial release

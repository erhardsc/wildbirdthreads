=== Conditional Shipping for WooCommerce ===
Contributors: wooelements
Tags: woocommerce shipping, conditional shipping
Requires at least: 4.5
Tested up to: 4.9
Requires PHP: 5.4
Stable tag: 1.0.10
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restrict WooCommerce shipping methods based on conditions. Works with your existing shipping methods and zones.

== Description ==
Conditional Shipping for WooCommerce allows you to restrict shipping methods based on conditions. For example, you can disable free shipping for orders weighing over 30 kg or create a special shipping method for large products.

The plugin works with your existing shipping methods and zones. You can restrict flat rate, free shipping, pickup or any other shipping method created with shipping zones.

= Example =

You have two flat rate shipping methods, Freight and Economy. Orders weighing under 30 kg are shipped with Economy shipping. Orders over 30 kg have to be shipped with Freight.

With Conditional Shipping you can set maximum weight (30 kg) for Economy and minimum weight for Freight (30 kg). The customer sees only the right shipping on the checkout.

= Features =

* Restrict WooCommerce shipping methods based on conditions
* Works with existing shipping methods
* WooCommerce 3+ compatible

= Available Conditions =

* Products (require, exclude, exclusive)
* Weight (min, max)
* Total Length (max)
* Total Height (max)
* Total Width (max)
* Total Volume (min, max)
* Order Subtotal (min, max)

= Pro Features =

* All free features
* Support for shipping class conditions
* Support for category conditions

[Upgrade to Pro](https://wooelements.com/products/conditional-shipping)

= Support Policy =

If you need any help with the plugin, feel free to contact us either by email (support@wooelements.com) or [WordPress plugin support forum](https://wordpress.org/support/plugin/conditional-shipping-for-woocommerce).

== Installation ==
Conditional Shipping is installed just like any other WordPress plugin.

1. Download the plugin zip file
2. Go to Plugins in the WordPress admin panel
3. Click Add new and Upload plugin
4. Choose the downloaded zip file and upload it
5. Activate the plugin

Once the plugin is activated, you should see "Conditions" for shipping methods created with shipping zones.

== Changelog ==

= 1.0.10 =

* Fixed compatibility issue with WooCommerce 3.4.x
* Fixed compatibility issue with WooCommerce Services

= 1.0.9 =

* Improved compatibility with some 3rd party shipping modules where settings were not saving.

= 1.0.8 =

* Improved compatibility with WooCommerce

= 1.0.7 =

* Improved compatibility with multi-site environments.

= 1.0.6 =

* Added compatibility for WooCommerce Distance Rate Shipping plugin.

= 1.0.5 =

* Improved compatibility with 3rd party plugins.

= 1.0.4 =

* Fixed bug which prevented saving the conditions in some cases.

= 1.0.3 =

* Added product variations to the product filters
* Fixed compability with the WooCommerce USPS plugin

= 1.0.2 =
* Added minimum total volume filter

= 1.0.1 =
* Added product filters (require, exclude, exclusive)

= 1.0.0 =
* Initial version

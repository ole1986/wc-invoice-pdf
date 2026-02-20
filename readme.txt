=== WC Recurring Invoice ===
Contributors: ole1986
Tags:  woocommerce, invoicing, billing, pdf, custom products
Donate link: https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WC+Recurring+Invoice+Pdf&cmd=_donations&business=ole.koeckemann@gmail.com
Requires at least: 3.1
Tested up to: 6.9
Stable tag: trunk
License: MIT

WooCommerce invoice PDF generator for recurring / non-recurring orders and Email submission.

== Description ==

The WC Recurring Invoice (aka WC Invoice PDF) extends WooCommerce to generate PDF invoices while supporting recurring and automated Email submission.

**FEATURES**

* Invoice overview
* Manage invoice status and due date
* Email submission schedule for invoices
* Individual payment reminder (internal and customer)
* Customizable PDF template and Email content through placeholders
* [NEW] Support for XRechnung according to EN16931

**WooCommerce Extended**

* [PRODUCT] Subscription product (Webspace) to trigger recurring Orders
* [PRODUCT] Individual "Service" product allowing to customize units (E.g PCS, hours, mins, any other)
* [ORDER] Generate and preview PDF invoice for any WooCommerce order
* [ORDER] Configurable subscription type (yearly, monthly) per WooCommerce order
* [ORDER] Additional Email recipient (CC) in WooCommerce billing info

[RELEASE NOTES](https://github.com/ole1986/wc-invoice-pdf/releases)

== Installation ==

* Search for "wc-invoice-pdf" in the "Plugins -> Install" register
* Install and active the plugin
* Open the "WC-Invoices" -> "Settings" menu from admin pane for configuration

**TESTING**

For testing the recuring payments (submission of invoices) the "Test recuring" settings can be hooked to overwrite any customer email address

= DEVELOPMENT =

This plugins provides the following webhooks

* wc_recurring_order_period     | When order subscription is being changed/created (also applies to shop cart orders)
* wc_recurring_invoice_metabox  | Invoice metabox hook in WooCommerce order backend (content)
* wc_recurring_invoice_creating | When an invoice pdf is supposed to be saved into database

== Screenshots ==

1. List of invoices from admin panel
2. The PDF printout when generating invoice
3. Invoice frontend for customers incl. XRechnung download
4. Invoicemetabox from WooCommerce Order page
5. WC Recurring Settings (General)
6. WC Recurring Settings (Invoice PDF)
7. WC Recurring Settings (Email templates)

== License ==

[MIT LICENSED](https://github.com/ole1986/wc-invoice-pdf)

== Changelog ==

[RELEASE NOTES](https://github.com/ole1986/wc-invoice-pdf/releases)

=== WC Recurring Invoice Pdf ===
Contributors: ole1986
Tags:  woocommerce, WC, invoicing, billing, recurring, order, pdf, automation, read-only, law
Donate link: https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WC+Recurring+Invoice+Pdf&cmd=_donations&business=ole.k@web.de
Requires at least: 3.1
Tested up to: 6.5
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce invoice pdf generation for recurring / non-recurring orders. incl. invoice submission

== Description ==

The WC-InvoicePdf plugin is used to generate PDF documents from woocommerce orders.
It also allows to setup WC Orders to be recurring and submits the invoices to there customers respectively.

**Features**

* Save invoice as pdf from any order created by WooCommerce
* Export invoices into CSV (primary GnuCash, more may follow...)
* Preview quote as pdf to submit provisional offer to customers
* Schedule recurring invoices directly send to customers email address
* Send kindly reminders for over due invoices
* Show payable invoices in customers "My Account"
* Individual the PDF template in text and picture
* Remind delegate person about pending payments from customers
* A "Webspace" product type for recurring payment support
* A "Working hours" product type to support service hours being invoiced to customers

[RELEASE NOTES]( https://github.com/ole1986/wc-invoice-pdf/releases)

== Installation ==

* Search for "wp-ispconfig3" in the "Plugins -> Install" register
* Install and active the plugin
* Open the "WC-Invoices" -> "Settings" menu from admin pane for configuration

**TESTING**

For testing the recuring payments (submission of invoices) the "Test recuring" settings can be hooked to overwrite any customer email address

= DEVELOPMENT =

To set the *recurring state* of an order while receiving a request, an action hook can be used to achieve this

`
// call the plugin to mark it as yearly recurring payment order
do_action('wcinvoicepdf_order_period', $order_id, 'yearly');
// call the plugin to mark it as monthly recurring payment order
do_action('wcinvoicepdf_order_period', $order_id, 'monthly');
`

To add additional info into the invoice metabox located in order, the following hook is availble

`
do_action('wcinvoicepdf_invoice_metabox', $post_id);
`

== Screenshots ==

1. The PDF printout when generating invoice
2. All invoices listing from admin panel
3. Invoice frontend for customers incl. pending payment instructions
4. Generate invoice metabox from WooCommerce Order page
5. WooCommerce Order basic prerequisites on subscription and ISPConfig (optional) settings
6. Settings for PDF invoice output 1/2
7. Additional settings for PDF invoice output 2/2
8. Task scheduler for recurring and reminder settings
9. Email template settings for payments and reminders

== License ==

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with WP Nofollow More Links. If not, see <http://www.gnu.org/licenses/>.

== Changelog ==

Release notes are provided by the [Github project page](https://github.com/ole1986/wc-invoice-pdf/releases)
WHMCS Custom Stripe Payment Gateway
============

This is a free and open source Stripe Payment Gateway for WHMCS that supports one time and recurring payments without ever having a credit card number hit your WHMCS server. It's pretty neat!

## Overview

This gateway allows the [WHMCS](http://www.whmcs.com) billing system to use [Stripe's](https://www.stripe.com) one time payment gateway capabilities. [Stripe](https://www.stripe.com) provides the unique ability to take a client's credit card information without ever having the client leave your site, but it also allows for credit card data to never get stored in or even pass through your own server, eliminating costly concerns over PCI compliance. This gateway for WHMCS is being released free for anyone, although it should still be considered in beta as there are likely still bugs to work out of it.

This module makes use of Stripe Checkout and as such

## Instructions For Use

Upload all files as they are in this structure. You will need to change anywhere that says "PUBLIC KEY HERE" or "SECRET KEY HERE" in either modules/gateways/stripe.php and stripe-pay.php

You will need to include the Stripe Checkout JS libraries on viewinvoice.tpl or header.tpl, as long as it is before the javascript code that is executed to show the button

You will need to activate the gateway in WHMCS - that's it!

## Warnings and Notices


+ This gateway currently only works in English with Great British Pound as the currency. To change the currency, consult the Stripe documentation

+ Currently only supports one-off payments and refunds. We are working to add more features and streamline the current code.

## Planned for next version

+ Not require the Public Key and Secret Key to be set in the PHP files - they should be configured in the WHMCS admin panel

## Credits and Acknowledgements

This module was originally based on NextGenWeb's whmcs-stripe module, however after spending a decent amount of time on it we decided to open source it!

## Support Information

I'm always looking to improve this code, so if you see something that can be changed or if you have an idea for a new feature or any other feedback, send me an email to `support@connexin.co.uk`, or send me a message on Twitter (`@lukefromengland`), and I'll get right back to you. If you decide to use this module in your WHMCS install, send me a message to say hello (and let me know what you think too) and it'll make my day. Thanks!
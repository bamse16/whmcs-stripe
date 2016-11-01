# WHMCS Stripe Payment Gateway

This is a free and open source Stripe Payment Gateway for WHMCS that supports one time and recurring payments. It also supports Apple Pay, implemented as a second gateway (Stripe Apple Pay).

## Overview

This gateway allows the [WHMCS](http://www.whmcs.com) billing system to use [Stripe's](https://www.stripe.com) payment gateway capabilities. [Stripe](https://www.stripe.com) provides the unique ability to take a client's credit card information without ever having the client leave your site, but it also allows for credit card data to never get stored in or even pass through your own server, eliminating costly concerns over PCI compliance. This gateway for WHMCS is being released free for anyone, although it should still be considered in beta as there are likely still bugs to work out of it.

This module makes use of Stripe Checkout and as such requires the Checkout.js, but also uses Stripe PHP's lib for the callback implementation and marking the invoice as paid.

The module includes a widget that shows you the current balance, when logged in as admin.

## Instructions For Use

Upload all files as they are in this structure. Then login as WHMCS admin and enable the module Stripe and Stripe Apple Pay. Configure each module with your Stripe public and secret keys found [here](https://dashboard.stripe.com/account/apikeys).

## Warnings and Notices

+ This gateway currently only works in English.

+ Due to WHMCS' payment gateway limitations, there does not seem to be any way to implement a proper tokenisation payment module, with admin support for attempt capture or cron charges using the gateway token.

+ Due to the same limitations, the Apple Pay module was implemented as a separate gateway, although the callback implementation is essentially identical to the main module.

## Credits and Acknowledgements

This module was originally based on NextGenWeb's whmcs-stripe module, however after spending a decent amount of time on it we decided to open source it! Open sourced by [@lukehebb](https://twitter.com/lukehebb) and then upgraded by [@bamse](https://twitter.com/bamse).

## Support Information

This module is provided as is.

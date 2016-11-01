<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function stripe_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Stripe"),

      // Same order as on Stripe > Dashboard > Config > API Keys
      "private_test_key" => array("FriendlyName" => "Test Secret Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>." , ),
     "public_test_key" => array("FriendlyName" => "Test Publishable Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>.", ),
     "private_live_key" => array("FriendlyName" => "Live Secret Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>.", ),
     "public_live_key" => array("FriendlyName" => "Live Publishable Key", "Type" => "text", "Size" => "20", "Description" => "Available from Stripe's website at <a href='https://manage.stripe.com/account/apikeys' title='Stripe API Keys'>this link</a>.", ),

     "problememail" => array("FriendlyName" => "Problem Report Email", "Type" => "text", "Size" => "20", "Description" => "Enter an email that the gateway can send a message to should an alert or other serious processing problem arise.", ),
     "company_logo" => array("FriendlyName" => "Company Logo", "Type" => "text", "Size" => "20", "Description" => "A link to your company logo URL. Square image, with min size 128x128px.", ),
     "testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this to make all transactions use your test keys above.", ),
    );
    return $configarray;
}

function stripe_link($params) {
    # Invoice Variables
    $invoiceid = (int) $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount']; # Format: ##.##
    $amountCents = $amount * 100;

    $publicKey = $params['public_live_key'];
    if($params['testmode'] == 'on'){
        $publicKey = $params['public_test_key'];
    }

    # Enter your code submit to the gateway...

    $callbackUrl = "/stripe.php?invoice_id={$invoiceid}";

    $code = '<form action="'.$callbackUrl.'" method="POST">
              <script
                src="https://checkout.stripe.com/checkout.js"
                class="stripe-button"
                data-key="'.$publicKey.'"
                data-image="'.$params['company_logo'].'"
                data-name="'.$params['companyname'].'"
                data-description="Invoice #' . $params['invoiceid'] . '"
                data-amount='.$amountCents.'
                data-email="' . $params['clientdetails']['email'] . '"
                data-currency="'. $params['currency'].'">
              </script>
            </form>
    ';
    return $code;
}

function stripe_refund($params) {
    // Bring in Stripe
    require_once(ROOTDIR.'/stripe/init.php');

    $secretKey = $params['private_live_key'];
    if ($params["testmode"] == "on") {
	    $secretKey = $params['private_test_key'];
    }

    \Stripe\Stripe::setApiKey($secretKey);

    # Invoice Variables
    $transid = $params['transid'];
    $amountCents = $params['amount'] * 100;

    # Perform Refund
    try {
        $ch = \Stripe\Charge::retrieve($transid);
        $re = $ch->refund(array(
	        'amount' => $amountCents
	    ));
        return array(
            "status" => "success",
            "transid" => $ch->id,
            "rawdata" => $re
        );
    } catch (Exception $e) {
        $error = $e->getMessage();
        return array(
            "status" => "error",
            "rawdata" => $error
        );
    }
}

function stripe_capture($params){
    require_once(ROOTDIR.'/stripe/init.php');

    $secretKey = $params['private_live_key'];
    if ($params["testmode"] == "on") {
	    $secretKey = $params['private_test_key'];
    }

    \Stripe\Stripe::setApiKey($secretKey);

	$amount = $params['amount'];
    $amountCents = 100 * $amount;
    $currency = $params['currency'];
    $cardnum = trim($params['cardnum']);

    $email = $params['clientdetails']['email'];
    $fullname = $params['clientdetails']['fullname'];

    // If a card number is provided,
    // Create new customer
    if (strlen($cardnum) > 0) {
        $customer = sprintf('%s <%s>', $name, $email);

        try {
            $description = $params['description'];
            $createCustomer = array(
                    "description" => $customer,
                    "email" => $email,
                    "source" => array(
	                    "object" => "card",
                        "number" => $cardnum,
                        "exp_month" => substr($params['cardexp'], 0, 2),
                        "exp_year" => substr($params['cardexp'], 2, 2),
                        "cvc" => $params['cccvv'],
                        "name" => $fullname
                        )
                    );
            $customerResponse = \Stripe\Customer::create($createCustomer);
            $stripeCustomerId = $customerResponse->id;

            $chargeItem = array(
                "customer" => $stripeCustomerId,
                "amount" => $amountCents,
                "currency" => $currency,
                "description" => $description
                );
            $cardResponse = \Stripe\Charge::create($chargeItem);

	        // Great! It was a glowing success! Now, let's add the charge to the invoice and be done with it!
	        $card = $cardResponse->source;

	        $cardType = $card->brand;
	        $cardLastFour = $card->last4;
	        $cardExp = sprintf("%02d/%d", $card->exp_month, $card->exp_year);

            if($params['clientdetails'] && $params['clientdetails']['userid']){
                $userid = $params['clientdetails']['userid'];

                // WHMCS needs a token that can be use for further charges
                // for Stripe, we store a customer id and use this as token for further charges
                $storeCardToken = array(
                    "cardtype" => $cardType,
                    "gatewayid" => $stripeCustomerId,
                    "cardlastfour" => $cardLastFour,
                    "expdate" => $cardExp
                    );

                update_query("tblclients", $storeCardToken, array("id" => $userid));
            }
            return array(
                "status" => "success",
                "transid" => $cardResponse->id,
                "rawdata" => $cardResponse
            );
        } catch(Exception $event) {
            return array(
                "status" => "failed",
	            "rawdata" => $event
            );
        }
    } else if (strlen($params['gatewayid']) > 0) {
        $stripeCustomerId = $params['gatewayid'];
        try {
            $cardResponse = \Stripe\Charge::create(array(
                "amount" => $amountCents,
                "currency" => $currency,
                "customer" => $stripeCustomerId,
                "description" => $params['description']
                ));
	        return array(
	            "status" => "success",
	            "transid" => $cardResponse->id,
	            "rawdata" => $cardResponse
	        );
        } catch (Exception $event) {
            return array(
                "status" => "failed",
                "rawdata" => $event
                );
        }
    }

    logTransaction($params["name"], "No credit card on file", "Unsuccessful");
    return array(
        "status" => "failed",
        "rawdata" => "No credit card on file"
        );
}

?>

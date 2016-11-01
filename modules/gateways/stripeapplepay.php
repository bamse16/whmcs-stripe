<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function stripeapplepay_MetaData() {
    return array(
        'DisplayName' => 'Stripe Apple Pay',
        'APIVersion' => '1.0', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => true,
    );
}

function stripeapplepay_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Stripe Apple Pay"),

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

function stripeapplepay_link($params) {
    $publicKey = $params['public_live_key'];
    if($params['testmode'] == 'on'){
        $publicKey = $params['public_test_key'];
    }

    $companyName = $params['companyname'];
    $companyLogo = $params['company_logo'];
    $amount = $params['amount']; // Apple pay uses Amount
    $amountCents = $amount * 100; // Checkout uses Amount Cents
    $invoiceId = (int) $params['invoiceid'];
    $description = $params["description"];
    $countryCode = $params['clientdetails']['countrycode'];
    $email = $params['clientdetails']['email'];
    $currency = $params['currency'];
    $returnUrl = $params['returnurl'];
    $callbackUrl = "/stripe-apple-pay.php?invoice_id={$invoiceId}";

	$htmlOutput = <<<EOD

<style>
.pay-button {
    color: white;
    background-color: black;
    background-size: 100% 100%;
    background-origin: content-box;
    background-repeat: no-repeat;
    width: 200px;
    height: 44px;
    padding: 10px 10px;
    margin: 20px 20px;
    border-radius: 10px;
    text-align: center;
}

#apple-pay-button {
    display: none;
    background-image: -webkit-named-image(apple-pay-logo-white);
}

</style>

<button type="button" id="apple-pay-button" class="pay-button">&nbsp;</button>
<script src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<script type="text/javascript">
Stripe.setPublishableKey('{$publicKey}');
Stripe.applePay.checkAvailability(function(available) {
    if (available) {
        document.getElementById('apple-pay-button').style.display = 'inline-block';
    }
});
document.getElementById('apple-pay-button').addEventListener('click', beginApplePay);
function beginApplePay() {
    var paymentRequest = {
        countryCode: '{$countryCode}',
        currencyCode: '{$currency}',
        total: {
            label: '{$description}',
            amount: '{$amount}'
        }
    };

    var session = Stripe.applePay.buildSession(paymentRequest,
        function(result, completion) {
            console.log('result', result);
            $.post('{$callbackUrl}', { stripeToken: result.token.id }).done(function(){
                completion(ApplePaySession.STATUS_SUCCESS);
                window.location.href = '{$returnUrl}';
            }).fail(function(){
                completion(ApplePaySession.STATUS_FAILURE);
            });
        }, function(error){
            console.log(error.message);
        });
    session.begin();
}
</script>
EOD;

    return $htmlOutput;
}

function stripeapplepay_refund($params) {
    // Bring in Stripe
    require_once(ROOTDIR.'/stripe/init.php');

    $secret_key = $params['private_live_key'];
    if ($params["testmode"] == "on") {
	    $secret_key = $params['private_test_key'];
    }

    \Stripe\Stripe::setApiKey($secret_key);

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
        $response['error'] = $e->getMessage();
        return array(
            "status" => "error",
            "rawdata" => $response['error']
        );
    }
}

?>

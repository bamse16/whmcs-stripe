<?php 
	$gatewaymodule = "stripeapplepay";
    // Grab the WHMCS stuff

    include("dbconnect.php");
    include("includes/functions.php");
    include("includes/gatewayfunctions.php");
    include("includes/invoicefunctions.php");
    
    $invoiceID = (int) $_GET['invoice_id'];
	$token = trim($_POST["stripeToken"]);

    $GATEWAY = getGatewayVariables($gatewaymodule, $invoiceID);
    if (!$GATEWAY["type"]) {
        die("Module Not Activated");
    }

    // This will stop the processing if the invoice id is invalid
    $invoiceID = checkCbInvoiceID($invoiceID, $GATEWAY['name']);

	if ($token == "") {
		header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id={$invoiceID}&paymentstatus=failure");
		die("Missing token");
	}

    // Bring in Stripe
    include('stripe/init.php');

	// Little bit of sanitization
    $amount = $GATEWAY['amount'];
    $currency = $GATEWAY['currency'];
    $amountPence = $amount * 100;

    $secret_key = $GATEWAY['private_live_key'];
    if($GATEWAY['testmode'] == 'on'){
        $secret_key = $GATEWAY['private_test_key'];
    }
    
    \Stripe\Stripe::setApiKey($secret_key);

    // Description (THIS FORMAT IS REQUIRED!!!!)
    $description = "Invoice #" . $invoiceID . " - " . $GATEWAY['clientdetails']['email'] . ".";
    
    $customer = sprintf('Customer for Invoice #%d', $invoiceID);
    $email = '';
    if ($GATEWAY['clientdetails']){
        $email = $GATEWAY['clientdetails']['email'];
        $customer = sprintf('%s <%s>', $GATEWAY['clientdetails']['fullname'], $email);
    }
	
	try {
		$customerItem = array(
            "description" => $customer,
	        "email" => $email,
            "source" => $token
		);
		$customerResponse = \Stripe\Customer::create($customerItem);
		
        $chargeItem = array(
            "customer" => $customerResponse->id,
            "amount" => $amountPence,
            "currency" => $currency,
            "description" => $description
        );
        $cardResponse = \Stripe\Charge::create($chargeItem);

        // Great! It was a glowing success! Now, let's add the charge to the invoice and be done with it!        
        $card = $cardResponse->source;
 
        $cardType = $card->brand;
        $cardLastFour = $card->last4;
        $cardExp = sprintf("%02d/%d", $card->exp_month, $card->exp_year);
        
        if($GATEWAY['clientdetails'] && $GATEWAY['clientdetails']['userid']){
            $userid = $GATEWAY['clientdetails']['userid'];

            // WHMCS needs a token that can be use for further charges
            // for Stripe, we store a customer id and use this as token for further charges
            $storeCardToken = array(
                "cardtype" => $cardType,
                "gatewayid" => $customerResponse->id,
                "cardlastfour" => $cardLastFour,
                "expdate" => $cardExp
            );
                    
            update_query("tblclients", $storeCardToken, array("id" => $userid));
        }
	} catch(\Stripe\Error\Card $event) {
        // The card has been declined
        logTransaction($GATEWAY["name"], $event, "Unsuccessful"); # Save to Gateway Log: name, data array, status
        header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id={$invoiceID}&paymentstatus=failure");
        die();
    } catch(Exception $event) {
        logTransaction($GATEWAY["name"], $event, "Unsuccessful"); # Save to Gateway Log: name, data array, status
        header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id={$invoiceID}&paymentstatus=failure");
        die();
    }

    // Mark as paid on WHMCS
    addInvoicePayment($invoiceID, $cardResponse->id, $amount, $fee, $gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
    logTransaction($GATEWAY["name"], $cardResponse, "Successful");

    // Aaaand redirect back to the invoice
    header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id={$invoiceID}&paymentstatus=success");
    exit();
?>

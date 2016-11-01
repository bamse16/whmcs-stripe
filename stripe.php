<?php

	$gatewaymodule = "stripe";
	// Grab the WHMCS stuff
	
	include("dbconnect.php");
	include("includes/functions.php");
	include("includes/gatewayfunctions.php");
	include("includes/invoicefunctions.php");

	$invoiceID = (int) $_GET['invoiceid'];
	$GATEWAY = getGatewayVariables($gatewaymodule, $invoiceID);
	if (!$GATEWAY["type"]) {
		die("Module Not Activated"); 
	}
	
	// This will stop the processing if the invoice id is invalid
	$invoiceID = checkCbInvoiceID($invoiceID, $GATEWAY['name']);

	// Bring in Stripe
	include('stripe/lib/Stripe.php');
	
	// Little bit of sanitization
	$amountPounds = $GATEWAY['amount'];
	$currency = $GATEWAY['currency'];

	$amountPence = $amountPounds * 100;

	$secret_key = '';
	if($GATEWAY && $GATEWAY['private_live_key'] && (!$GATEWAY['testmode'] || $GATEWAY['testmode'] == '')){
		$secret_key = $GATEWAY['private_live_key'];
	} elseif($GATEWAY && $GATEWAY['private_test_key'] && $GATEWAY['testmode'] == 'on'){
		$secret_key = $GATEWAY['private_test_key'];
	}

	// Set API key
	Stripe::setApiKey($secret_key);
	
	// Description (THIS FORMAT IS REQUIRED!!!!)
	$description = "Invoice #" . $invoiceID . " - " . $_POST['stripeEmail'] . "";

	$customer = sprintf('Customer for Invoice #%d', $invoiceID);
	$email = '';
	if($GATEWAY['clientdetails']){
		$email = $GATEWAY['clientdetails']['email'];
		$customer = sprintf('%s <%s>', $GATEWAY['clientdetails']['fullname'], $email);
	}

	// Create a customer, then Do the charge
	try {
		$createCustomer = Stripe_Customer::create(array(
			"description" => $customer,
			"email" => $email,
			"card" => $_POST['stripeToken']
			)); 
		
		$customerResponse = json_decode($createCustomer, true);

		$cardCharge = Stripe_Charge::create(array(
					"customer" => $customerResponse['id'],
					"amount" => $amountPence,
					"currency" => $currency,
					"description" => $description));

		// Great! It was a glowing success! Now, let's add the charge to the invoice and be done with it!
		$cardResponse = json_decode($cardCharge, true);

		$cardType = '';
		$cardLastFour = '';
		if($cardResponse && $cardResponse['card']){
			if($cardResponse['card']['brand']){
				$cardType = $cardResponse['card']['brand'];
			}

			if($cardResponse['card']['last4']){
				$cardLastFour = $cardResponse['card']['last4'];
			}
		}

		if($GATEWAY['clientdetails'] && $GATEWAY['clientdetails']['userid']){
			$userid = $GATEWAY['clientdetails']['userid'];

			// WHMCS needs a token that can be use for further charges
			// for Stripe, we store a customer id and use this as token for further charges
			$storeCardToken = array(
					"cardtype" => $cardType,
					"cardnum" => '',
					"gatewayid" =>$customerResponse['id'],
					"cardlastfour" => $cardLastFour
					);

			update_query("tblclients", $storeCardToken, array("id" => $userid));
		}

	} catch(Stripe_CardError $event) {

		// The card has been declined
		logTransaction($GATEWAY["name"],$event,"Unsuccessful"); # Save to Gateway Log: name, data array, status
		header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id=" . $_GET['invoiceid'] . "&paymentstatus=failure");

		die();
	} catch(Exception $event) {
		logTransaction($GATEWAY["name"],$event,"Unsuccessful"); # Save to Gateway Log: name, data array, status
		header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id=" . $_GET['invoiceid'] . "&paymentstatus=failure");

		die();
	}

	// Mark as paid on WHMCS 
	addInvoicePayment($invoiceID,$cardResponse['id'],$amountPounds,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
	logTransaction("Stripe",$cardResponse,"Successful");

	// Aaaand redirect back to the invoice
	header("Location: ".$GATEWAY["systemurl"]."/viewinvoice.php?id=" . $_GET['invoiceid'] . "&paymentstatus=success");
?>
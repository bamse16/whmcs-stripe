<?php

	// Bring in Stripe
	include('stripe/lib/Stripe.php');
	
	// Grab the WHMCS stuff
	
	include("dbconnect.php");
	include("includes/functions.php");
	include("includes/gatewayfunctions.php");
	include("includes/invoicefunctions.php");
	
	// Little bit of sanitization
	$invoiceID = (int) $_GET['invoiceid'];
	$amountPounds = (int) $_GET['amount'];
	
	$amountPence = $amountPounds * 100;
	
	// Calculate the fee
	$fee = ($amountPence * 2.4) + 0.2;
	
	$fee = round($fee, 2);
	
	// Set API key
	Stripe::setApiKey("SECRET KEY HERE");
	
	// Description (THIS FORMAT IS REQUIRED!!!!)
	$description = "Invoice #" . $invoiceID . " - " . $_POST['stripeEmail'] . "";

	// Do the charge
	try { 
	
	$cardCharge = Stripe_Charge::create(array(
	  "card" => $_POST['stripeToken'],
	  "amount" => $amountPence,
	  "currency" => "gbp", // Change this to a currency of your choice if you aren't from the UK
	  "description" => $description));
	  
	} catch(Stripe_CardError $e) {
	
	  // The card has been declined
	  
	  logTransaction($GATEWAY["name"],$event,"Unsuccessful"); # Save to Gateway Log: name, data array, status
	  
	  header("Location: /viewinvoice.php?id=" . $_GET['invoiceid'] . "&paymentstatus=failure");
	  
	  die();
	  
	}

	// Great! It was a glowing success! Now, let's add the charge to the invoice and be done with it!
	$cardResponse = json_decode($cardCharge, true);
	
	$gatewaymodule = "stripe";
	
	// Mark as paid on WHMCS 
	addInvoicePayment($invoiceID,$cardResponse['id'],$amountPounds,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
	logTransaction("Stripe",$cardResponse,"Successful");

	// Aaaand redirect back to the invoice
	header("Location: /viewinvoice.php?id=" . $_GET['invoiceid'] . "&paymentstatus=success");

?>
<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function widget_stripe($vars){
    global $whmcs;

	if ($whmcs->get_req_var('getstripeoverview')) {
		require_once(ROOTDIR.'/stripe/lib/Stripe.php');

		$gatewaymodule = "stripe";

		$gateway = getGatewayVariables($gatewaymodule);
		if (!$gateway["type"]) {
			echo "Module Not Active";
			exit;
		}

		$gatewaytestmode = $gateway['testmode'];

		if ($gatewaytestmode == "on") {
			Stripe::setApiKey($gateway['private_test_key']);
		} else {
			Stripe::setApiKey($gateway['private_live_key']);
		}

		$balance = Stripe_Balance::retrieve();

		$pending = array();
		foreach($balance->pending as $obj){
			$s = strtoupper($obj->currency).':'.($obj->amount/100.0); 
			array_push($pending, $s);
		}

		$pending_values = implode(' ~ ', $pending);
		$output .= '<div style="margin:10px;padding:10px;background-color:#D9F9FF;text-align:center;font-size:16px;color:#000;">Pending: <span id="stripevaluepending">'.$pending_values.'</span></div>';

		$available = array();
		foreach($balance->available as $obj){
			$s = strtoupper($obj->currency).':'.($obj->amount/100.0); 
			array_push($available, $s);
		}

		$available_values = implode(' ~ ', $available);
		$output .= '<div style="margin:10px;padding:10px;background-color:#D9FFDA;text-align:center;font-size:16px;color:#000;">Available: <b id="stripevalueavailable">'.$available_values.'</b></div>';

		echo $output;
		exit;
	}

	$title = 'Stripe Overview';

    $content = '<div id="stripeoverview" style="max-height:130px;">';
	$content .= $vars['loading'];
	$content .= '</div>';

    $jquerycode = '$.post("index.php", { getstripeoverview: 1 },
    function(data){
        jQuery("#stripeoverview").html(data);
    });';

    return array('title'=>$title,'content'=>$content,'jquerycode'=>$jquerycode);
}

add_hook("AdminHomeWidgets", 1, "widget_stripe");

?>
<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if ($_GET["validate_commande"]) {
    get_header();

    $content = "";
    $content .= "<div class='container'>";
    $content .=     "<p>Merci pour votre achat ! Vous recevrez dans quelques minutes un mail de confirmation.</p>";
    $content .= "</div>";
    echo $content;

    get_footer();
    exit();
}

if ($_GET["validate_ipn"]) {

    mail("benjamin@cestre.fr", "IPN", "test2");

    $urlparts = parse_url(home_url());
    $domain = $urlparts['host'];


    // Check to see there are posted variables coming into the script
    if ($_SERVER['REQUEST_METHOD'] != "POST")
        die("No Post Variables");

    $req = 'cmd=_notify-validate';

    foreach ($_POST as $key => $value) {
        $value = urlencode(stripslashes($value));
        $req .= "&$key=$value";
    }
    $paypal = get_option("paypal_pic");
    $paypal_sandbox = (isset($paypal["paypal"]["sandbox"]) && $paypal["paypal"]["sandbox"]) ? true : false;
    $paypal_url = $paypal_sandbox ? "https://www.sandbox.paypal.com/cgi-bin/webscr" : "https://www.paypal.com/cgi-bin/webscr";

    $config = get_option('config_pic');
    $admin_address_mail = isset($config["config"]["adresse"]) ? $config["config"]["adresse"] : false;

    $url = $paypal_url;

    $curl_result = $curl_err = '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "Content-Length: " . strlen($req)));
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $curl_result = @curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    $req = str_replace("&", "\n", $req);

    if (strpos($curl_result, "VERIFIED") !== false) {

        $req .= "\n\nPaypal Verified OK";

    } else {

        $req .= "\n\nData NOT verified from Paypal!";

        if ($admin_address_mail) {
            wp_mail($admin_address_mail, "IPN interaction not verified", $req, "From: noreply@$domain");
            die($req);
        }
    }


    $payer_email = $_POST['payer_email'];
    $custom = $_POST['custom'];

    // Check 1
    $receiver_email = $_POST['receiver_email'];
    $paypal_address_mail = isset($paypal["paypal"]["adresse"]) ? $paypal["paypal"]["adresse"] : "fake@mail.com"; //ne pas mettre vide
    if ($receiver_email != $paypal_address_mail) {
        die("Address mail receiver email is invalid");
    }

    // Check 2 
    if ($_POST['payment_status'] != "Completed") {
        $infoMail = "Le paiement est en défault, merci de recommencer.";
        $infoAdmin = "Un paiement est parvenu en invalide.";
        if ($admin_address_mail) {
            wp_mail($payer_email, "Paiement invalide", $infoMail, "From: noreply@$domain");
        }
        die($infoMail);
    }

    // Check 3
    $txn_id = $_POST['txn_id'];
    $custom = $_POST["custom"];

    $defaultOrders = array("orders" => []);
    //$defaultOrders = serialize(json_encode("[{'orders':[]}]"));
    $allOrders = get_option('allcommands_pic', serialize(json_encode($defaultOrders)));
    $allOrders = json_decode(unserialize($allOrders), true);

    if (array_key_exists($txn_id, $allOrders["orders"])) {
        $infoMail = "L'ID de paiement existe déjà dans notre base de donnée.";
        $infoAdmin = "Un paiement avec un ID identique à tenté de payer.";
        if ($admin_address_mail) {
            wp_mail($admin_address_mail, "INFO: Paypal paiement identique(txn_id)", $infoAdmin, "From: noreply@$domain");
            // wp_mail($payer_email, "Paiement invalide", $infoMail, "From: noreply@$domain");        
        }
        die($infoMail);
    }

    //check for duplicate txn_ids in the database

    // Check 4
   // $product_id_string = $_POST['custom'];
   // $product_id_string = rtrim($product_id_string, ","); // remove last comma
    // Explode the string, make it an array, then query all the prices out, add them up, and make sure they match the payment_gross amount
    // END ALL SECURITY CHECKS NOW IN THE DATABASE IT GOES ------------------------------------
    ////////////////////////////////////////////////////
    if (session_id() == '') {
        session_start();
    }
    require(PIC_SELL_PATH_INC . "app/panier.php");
    // require("../includes/app/panier.php");
    $cart = new Panier();
    $cart->emailOrder($txn_id, $custom);
    exit();
    
    // Place the transaction into the database
    // Mail yourself the details
    // mail("you@youremail.com", "NORMAL IPN RESULT YAY MONEY!", $req, "From: you@youremail.com");
}

if($_GET["commande"]){

    //mail("benjamin@cestre.fr", "IPN", "test".json_encode($_GET));

    $txn_id = $_GET["commande"];
    get_header();

    require(PIC_SELL_PATH_INC . "app/panier.php");
    $cart = new Panier();
    $cart->getOrders($txn_id, false);

    get_footer();
    exit();    
}
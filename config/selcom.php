<?php
/**
 * Selcom Payment Gateway Configuration
 * =====================================
 */

// Load Selcom Client
require_once __DIR__ . '/SelcomClient.php';

// ============================================================
// SELCOM API CREDENTIALS - Configure with your own credentials
// Get your credentials from: https://developers.selcommobile.com
// ============================================================
define('SELCOM_API_KEY', 'YOUR_SELCOM_API_KEY_HERE');
define('SELCOM_API_SECRET', 'YOUR_SELCOM_API_SECRET_HERE');
define('SELCOM_MERCHANT_ID', 'YOUR_MERCHANT_ID');
define('SELCOM_VENDOR_ID', 'YOUR_VENDOR_ID');
define('SELCOM_TILL_NUMBER', 'YOUR_TILL_NUMBER');
define('SELCOM_CURRENCY', 'TZS');
define('SELCOM_ENVIRONMENT', 'production');
define('SELCOM_TIMEOUT', 30);
// ============================================================

// API Base URL - Production
define('SELCOM_BASE_URL', 'https://apigw.selcommobile.com');

// Get base URL for callbacks
$selcomBaseUrl = defined('SITE_URL') ? SITE_URL : 'https://phidtech.com';

// Webhook/Callback URL - Selcom will call this after payment
define('SELCOM_WEBHOOK_URL', $selcomBaseUrl . '/api/selcom-webhook.php');
define('SELCOM_CALLBACK_URL', $selcomBaseUrl . '/api/selcom-webhook.php');

// Redirect URLs for customer after payment
define('SELCOM_SUCCESS_URL', $selcomBaseUrl . '/payment-success.php');
define('SELCOM_CANCEL_URL', $selcomBaseUrl . '/payment-cancel.php');

/**
 * Generate Selcom API authentication headers
 * Based on Selcom API documentation and official PHP client
 */
function getSelcomAuthHeaders($jsonPayload = '') {
    // Timestamp in ISO 8601 format with timezone
    $timestamp = date('Y-m-d\TH:i:sP');
    
    // Authorization: Base64 encoded API Key
    $authorization = base64_encode(SELCOM_API_KEY);
    
    // Parse payload to get fields
    $payload = json_decode($jsonPayload, true) ?: [];
    
    // Signed-Fields: comma-separated list of field names
    $signedFields = implode(',', array_keys($payload));
    
    // Create string to sign: concatenate all field values
    $toSign = '';
    foreach ($payload as $value) {
        $toSign .= $value;
    }
    
    // Digest: Base64(SHA256(values_string))
    $digest = base64_encode(hash('sha256', $toSign, true));
    
    // Signature: Base64(HMAC-SHA256(values_string, secret))
    $signature = base64_encode(hash_hmac('sha256', $toSign, SELCOM_API_SECRET, true));
    
    return [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: SELCOM ' . $authorization,
        'Digest-Method: HS256',
        'Digest: ' . $digest,
        'Timestamp: ' . $timestamp,
        'Signed-Fields: ' . $signedFields,
        'Signature: ' . $signature
    ];
}

/**
 * Get Selcom Client instance
 */
function getSelcomClient() {
    static $client = null;
    if ($client === null) {
        $client = new SelcomClient(SELCOM_BASE_URL, SELCOM_API_KEY, SELCOM_API_SECRET);
    }
    return $client;
}

/**
 * Create a checkout order with Selcom (redirects to Selcom payment page)
 */
function createSelcomCheckoutOrder($data) {
    $orderId = $data['order_id'] ?? 'ORD' . time() . rand(100, 999);
    $phone = formatPhoneForSelcom($data['phone'] ?? '');
    
    $payload = [
        'vendor' => SELCOM_VENDOR_ID,
        'order_id' => $orderId,
        'buyer_email' => $data['email'] ?? '',
        'buyer_name' => $data['name'] ?? '',
        'buyer_phone' => $phone,
        'amount' => intval($data['amount']),
        'currency' => 'TZS',
        'buyer_remarks' => $data['description'] ?? 'Service Payment',
        'merchant_remarks' => 'PhidTech Order: ' . $orderId,
        'no_of_items' => 1
    ];
    
    $client = getSelcomClient();
    return $client->postFunc('/v1/checkout/create-order-minimal', $payload);
}

/**
 * Create a MOBILE-ONLY checkout order with Selcom (no cards, no TanQR)
 * Uses create-order-minimal endpoint which shows only mobile wallet payments
 * Based on Selcom docs - URLs should be base64 encoded
 * 
 * @param array $data Payment data
 * @return array Response from Selcom API
 */
function createSelcomCheckoutOrderFull($data) {
    $orderId = $data['order_id'] ?? 'ORD' . time() . rand(100, 999);
    $phone = formatPhoneForSelcom($data['phone'] ?? '');
    $nameParts = explode(' ', $data['name'] ?? 'Customer');
    $firstName = $nameParts[0] ?? 'Customer';
    $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : $firstName;
    
    // URLs need to be base64 encoded per Selcom API docs
    $redirectUrl = base64_encode(SELCOM_SUCCESS_URL . '?order=' . $orderId);
    $cancelUrl = base64_encode(SELCOM_CANCEL_URL . '?order=' . $orderId);
    $webhookUrl = base64_encode(SELCOM_WEBHOOK_URL);
    
    // Full payload for ALL payment methods (Cards, TanQR, Mobile)
    $payload = [
        'vendor' => SELCOM_VENDOR_ID,
        'order_id' => $orderId,
        'buyer_email' => $data['email'] ?? '',
        'buyer_name' => $data['name'] ?? '',
        'buyer_phone' => $phone,
        'gateway_buyer_uuid' => '',
        'amount' => intval($data['amount']),
        'currency' => 'TZS',
        'payment_methods' => 'ALL',
        'redirect_url' => $redirectUrl,
        'cancel_url' => $cancelUrl,
        'webhook' => $webhookUrl,
        'billing.firstname' => $firstName,
        'billing.lastname' => $lastName,
        'billing.address_1' => 'Tanzania',
        'billing.city' => 'Dar es Salaam',
        'billing.state_or_region' => 'Dar es Salaam',
        'billing.postcode_or_pobox' => '00000',
        'billing.country' => 'TZ',
        'billing.phone' => $phone,
        'buyer_remarks' => $data['description'] ?? 'Service Payment',
        'merchant_remarks' => 'PhidTech Order: ' . $orderId,
        'no_of_items' => 1
    ];
    
    $client = getSelcomClient();
    // Use create-order for ALL payment methods (Cards, TanQR, Mobile)
    return $client->postFunc('/v1/checkout/create-order', $payload);
}

/**
 * USSD Push Payment - Sends payment prompt directly to customer's phone
 * Customer receives USSD prompt to enter PIN and confirm payment
 * 
 * @param array $data Payment data with phone number
 * @return array Response from Selcom API
 */
function createSelcomUSSDPush($data) {
    $url = SELCOM_BASE_URL . '/checkout/wallet-payment';
    
    $orderId = $data['order_id'] ?? 'ORD-' . time() . rand(100, 999);
    $phone = formatPhoneForSelcom($data['phone']);
    
    // Detect network from phone number
    $network = detectMobileNetwork($phone);
    
    $payload = [
        'vendor' => SELCOM_VENDOR_ID,
        'order_id' => $orderId,
        'transid' => 'TXN-' . time() . rand(1000, 9999),
        'msisdn' => $phone,
        'amount' => intval($data['amount']),
        'currency' => 'TZS',
        'network' => $network, // TIGO, AIRTEL, VODACOM, HALOTEL
        'buyer_name' => $data['name'] ?? '',
        'buyer_email' => $data['email'] ?? '',
        'webhook' => SELCOM_WEBHOOK_URL,
        'remarks' => $data['description'] ?? 'Service Payment'
    ];
    
    return makeSelcomRequest($url, $payload);
}

/**
 * Check payment status
 * 
 * @param string $orderId Order ID to check
 * @return array Response from Selcom API
 */
function checkSelcomPaymentStatus($orderId) {
    $url = SELCOM_BASE_URL . '/checkout/order-status?order_id=' . $orderId;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getSelcomAuthHeaders());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'error' => $error
    ];
}

/**
 * Make Selcom API request
 */
function makeSelcomRequest($url, $payload) {
    $jsonPayload = json_encode($payload);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getSelcomAuthHeaders($jsonPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging
    $logFile = __DIR__ . '/../logs/selcom_api.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    
    $log = date('Y-m-d H:i:s') . " | URL: $url | Payload: " . json_encode($payload) . " | Response: $response | HTTP: $httpCode\n";
    file_put_contents($logFile, $log, FILE_APPEND);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'error' => $error,
        'raw' => $response
    ];
}

/**
 * Format phone number for Selcom (255XXXXXXXXX format)
 */
function formatPhoneForSelcom($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, replace with 255
    if (substr($phone, 0, 1) === '0') {
        $phone = '255' . substr($phone, 1);
    }
    
    // If doesn't start with 255, add it
    if (substr($phone, 0, 3) !== '255') {
        $phone = '255' . $phone;
    }
    
    return $phone;
}

/**
 * Detect mobile network from phone number
 */
function detectMobileNetwork($phone) {
    $phone = formatPhoneForSelcom($phone);
    $prefix = substr($phone, 3, 2); // Get digits after 255
    
    // Vodacom (M-Pesa): 74, 75, 76
    if (in_array($prefix, ['74', '75', '76'])) {
        return 'VODACOM';
    }
    // Tigo (Tigo Pesa): 65, 67, 71
    if (in_array($prefix, ['65', '67', '71'])) {
        return 'TIGO';
    }
    // Airtel (Airtel Money): 68, 69, 78, 79
    if (in_array($prefix, ['68', '69', '78', '79'])) {
        return 'AIRTEL';
    }
    // Halotel (Halo Pesa): 62
    if ($prefix === '62') {
        return 'HALOTEL';
    }
    
    return 'VODACOM'; // Default
}

/**
 * Verify Selcom webhook signature
 */
function verifySelcomWebhook($payload, $signature) {
    $computed = base64_encode(hash_hmac('sha256', $payload, SELCOM_API_SECRET, true));
    return hash_equals($computed, $signature);
}

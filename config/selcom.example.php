<?php
/**
 * Selcom Payment Gateway Configuration Example
 * =============================================
 * Copy this file to selcom.php and update with your Selcom credentials
 * 
 * Get your credentials from: https://developers.selcommobile.com
 */

// Load Selcom Client
require_once __DIR__ . '/SelcomClient.php';

// ============================================================
// SELCOM API CREDENTIALS - Get these from Selcom Developer Portal
// ============================================================
define('SELCOM_API_KEY', 'YOUR_SELCOM_API_KEY_HERE');
define('SELCOM_API_SECRET', 'YOUR_SELCOM_API_SECRET_HERE');
define('SELCOM_MERCHANT_ID', 'YOUR_MERCHANT_ID');
define('SELCOM_VENDOR_ID', 'YOUR_VENDOR_ID');
define('SELCOM_TILL_NUMBER', 'YOUR_TILL_NUMBER');
define('SELCOM_CURRENCY', 'TZS');
define('SELCOM_ENVIRONMENT', 'sandbox');  // Change to 'production' for live
define('SELCOM_TIMEOUT', 30);
// ============================================================

// API Base URLs
// Sandbox: https://apigwtest.selcommobile.com
// Production: https://apigw.selcommobile.com
define('SELCOM_BASE_URL', 'https://apigwtest.selcommobile.com');  // Change for production

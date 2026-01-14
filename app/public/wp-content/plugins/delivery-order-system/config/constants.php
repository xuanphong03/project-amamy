<?php

/**
 * Plugin Configuration Constants
 * 
 * This file contains all configuration constants for the Delivery Order System plugin.
 * Constants can be overridden in wp-config.php if needed.
 */

if (! defined('ABSPATH')) {
    exit;
}

// OnePay API Configuration
if (! defined('X_OP_DATE_HEADER')) {
    define('X_OP_DATE_HEADER', 'X-OP-Date');
}
if (! defined('X_OP_EXPIRES_HEADER')) {
    define('X_OP_EXPIRES_HEADER', 'X-OP-Expires');
}
if (! defined('SCHEME')) {
    define('SCHEME', 'OWS1');
}
if (! defined('ALGORITHM')) {
    define('ALGORITHM', 'OWS1-HMAC-SHA256');
}
if (! defined('TERMINATOR')) {
    define('TERMINATOR', 'ows1_request');
}
if (! defined('PARTNER_PC')) {
    define('PARTNER_PC', 'DUONGTT');
}
if (! defined('PARTNER_SECRET_KEY_PC')) {
    define('PARTNER_SECRET_KEY_PC', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
}
if (! defined('DOMAIN_PREFIX')) {
    define('DOMAIN_PREFIX', 'https://mtf.onepay.vn');
}
if (! defined('URL_PREFIX_PC')) {
    define('URL_PREFIX_PC', '/paycollect/api/v1');
}

// Company and Bank Account Configuration
if (! defined('DELIVERY_COMPANY_NAME')) {
    define('DELIVERY_COMPANY_NAME', 'CÔNG TY TNHH THƯƠNG MẠI VÀ DỊCH VỤ AMAMY');
}
if (! defined('DELIVERY_BANK_ACCOUNT')) {
    define('DELIVERY_BANK_ACCOUNT', '19071901565010');
}
if (! defined('DELIVERY_BANK_ACCOUNT_NAME')) {
    define('DELIVERY_BANK_ACCOUNT_NAME', 'Hoang Van Long');
}
if (! defined('DELIVERY_BANK_NAME')) {
    define('DELIVERY_BANK_NAME', 'Techcombank');
}
if (! defined('DELIVERY_EXCHANGE_RATE')) {
    define('DELIVERY_EXCHANGE_RATE', 31446);
}

// Merchant/Account Owner Contact Information (for QR code generation)
if (! defined('DELIVERY_ACCOUNT_OWNER_EMAIL')) {
    define('DELIVERY_ACCOUNT_OWNER_EMAIL', '');
}
if (! defined('DELIVERY_ACCOUNT_OWNER_PHONE')) {
    define('DELIVERY_ACCOUNT_OWNER_PHONE', '');
}
if (! defined('DELIVERY_COMPANY_ADDRESS')) {
    define('DELIVERY_COMPANY_ADDRESS', '');
}

if (! defined('DELIVERY_ORDER_SYSTEM_CC_EMAILS')) {
    define('DELIVERY_ORDER_SYSTEM_CC_EMAILS', 'leetaam.okhub@gmail.com', 'longhoangvan5435@gmail.com');
}

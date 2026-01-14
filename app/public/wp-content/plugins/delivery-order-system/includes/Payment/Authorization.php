<?php

namespace DeliveryOrderSystem\Payment;

if (! defined('ABSPATH')) {
    exit;
}

// Load configuration constants
if (! defined('X_OP_DATE_HEADER')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/config/constants.php';
}

class Authorization
{
    public $httpMethod;
    public $uri;
    public $queryParameters;
    public $region;
    public $service;
    public $signedHeaders;
    public $accessKeyId;
    public $secretAccessKey;
    public $expires;
    public $timeStamp;
    public $payload;

    public function __construct($iAccessKeyId, $iSecretAccessKey, $iRegion, $iService, $iHttpMethod, $iUri, $iQueryParameters, $iSignedHeaders, $iPayload, $iTimeStamp, $iExpires)
    {
        $this->accessKeyId = $iAccessKeyId;
        $this->secretAccessKey = $iSecretAccessKey;
        $this->region = $iRegion;
        $this->service = $iService;
        $this->httpMethod = $iHttpMethod;
        $this->uri = $iUri;
        $this->queryParameters = $iQueryParameters;
        $this->signedHeaders = $iSignedHeaders;
        $this->payload = $iPayload;
        $this->timeStamp = $iTimeStamp;
        $this->expires = $iExpires;
    }

    public function sign()
    {
        $canonicalUri = AuthorizationHelper::uriEncode($this->uri, false);
        $canonicalQueryString = "";
        foreach ($this->queryParameters as $key => $value) {
            if (strlen($canonicalQueryString) > 0) {
                $canonicalQueryString .= "&";
            }
            $canonicalQueryString .= AuthorizationHelper::uriEncode($key, true) . "=" . AuthorizationHelper::uriEncode(strval($value), true);
        }

        $canonicalHeaders = "";
        $buf = "";
        foreach ($this->signedHeaders as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ":" . trim(strval($value)) . "\n";
            if (strlen($buf) > 0) {
                $buf .= ";";
            }
            $buf .= strtolower($key);
        }

        $signedHeaderNames = $buf;
        $hashedPayload = AuthorizationHelper::sha256Hash($this->payload);
        $canonicalRequest = $this->httpMethod . "\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . $canonicalHeaders . "\n" . $signedHeaderNames . "\n" . $hashedPayload;


        $timeStamp = Util::formatTimeToYYYYMMDDTHHMMSSZ($this->timeStamp);
        $scope = Util::formatTimeToYYYYMMDD($this->timeStamp) . "/" . $this->region . "/" . $this->service . "/" . \TERMINATOR;
        $stringToSign = \ALGORITHM . "\n" . $timeStamp . "\n" . $scope . "\n" . AuthorizationHelper::sha256Hash($canonicalRequest);
        $dateKey = AuthorizationHelper::hmacSha256(\SCHEME . $this->secretAccessKey, Util::formatTimeToYYYYMMDD($this->timeStamp));
        $dateRegionKey = AuthorizationHelper::hmacSha256Hex($dateKey, $this->region);
        $dateRegionServiceKey = AuthorizationHelper::hmacSha256Hex($dateRegionKey, $this->service);
        $signingKey = AuthorizationHelper::hmacSha256Hex($dateRegionServiceKey, \TERMINATOR);
        $credential = $this->accessKeyId . "/" . $scope;
        $signature = AuthorizationHelper::hmacSha256Hex($signingKey, $stringToSign);

        $ows = \ALGORITHM . " Credential=" . $credential . ",SignedHeaders=" . $signedHeaderNames . ",Signature=" . $signature;
        return $ows;
    }

    /**
     * Sign request using OWS1 with correct raw bytes for intermediate keys (for IPN verification)
     * This method implements the correct OWS1 signature calculation for IPN callbacks
     */
    public function signForIPN()
    {
        // Debug: Check signedHeaders
        error_log('[Delivery Order System] signForIPN signedHeaders: ' . print_r($this->signedHeaders, true));

        $canonicalUri = AuthorizationHelper::uriEncode($this->uri, false);
        $canonicalQueryString = "";
        foreach ($this->queryParameters as $key => $value) {
            if (strlen($canonicalQueryString) > 0) {
                $canonicalQueryString .= "&";
            }
            $canonicalQueryString .= AuthorizationHelper::uriEncode($key, true) . "=" . AuthorizationHelper::uriEncode(strval($value), true);
        }

        $canonicalHeaders = "";
        $buf = "";
        foreach ($this->signedHeaders as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ":" . trim(strval($value)) . "\n";
            if (strlen($buf) > 0) {
                $buf .= ";";
            }
            $buf .= strtolower($key);
        }

        $signedHeaderNames = $buf;
        // OnePay IPN always uses literal UNSIGNED-PAYLOAD, never hashes body
        $payloadLine = 'UNSIGNED-PAYLOAD';
        // OnePay OWS1 canonical request format: MUST have blank line between headers and signed headers
        $canonicalRequest = $this->httpMethod . "\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . $canonicalHeaders . "\n" . $signedHeaderNames . "\n" . $payloadLine;


        // Fixed values for OnePay IPN (different from AWS SigV4)
        $date = Util::formatTimeToYYYYMMDD($this->timeStamp);
        $region = 'onepay';
        $service = 'paycollect';
        $request = 'ows1_request';

        $timeStamp = Util::formatTimeToYYYYMMDDTHHMMSSZ($this->timeStamp);
        $scope = $date . "/" . $region . "/" . $service . "/" . $request;
        $stringToSign = \ALGORITHM . "\n" . $timeStamp . "\n" . $scope . "\n" . AuthorizationHelper::sha256Hash($canonicalRequest);

        // OnePay uses hex string chaining like their sample code
        $secret = $this->secretAccessKey;

        // Step 1: kDate = HMAC_SHA256("OWS1" + secret, date) - returns hex
        $kDate = hash_hmac('sha256', $date, \SCHEME . $secret);

        // Step 2: kRegion = HMAC_SHA256(kDate, "onepay") - hex chaining
        $kRegion = hash_hmac('sha256', 'onepay', $kDate);

        // Step 3: kService = HMAC_SHA256(kRegion, "paycollect") - hex chaining
        $signingKey = hash_hmac('sha256', 'paycollect', $kRegion);

        $credential = $this->accessKeyId . "/" . $scope;
        $signature = hash_hmac('sha256', $stringToSign, $signingKey); // Final signature as hex

        $ows = \ALGORITHM . " Credential=" . $credential . ",SignedHeaders=" . $signedHeaderNames . ",Signature=" . $signature;
        return $ows;
    }
}

class AuthorizationHelper
{
    public static function uriEncode($data, $encodeSlash)
    {
        $result = "";
        $dataBytes = unpack("C*", $data); // Unpack the UTF-8 string into an array of byte values

        foreach ($dataBytes as $ch) {
            if (
                ($ch >= 65 && $ch <= 90) ||
                ($ch >= 97 && $ch <= 122) ||
                ($ch >= 48 && $ch <= 57) ||
                $ch === 95 ||
                $ch === 45 ||
                $ch === 126 ||
                $ch === 46 ||
                ($ch === 47 && !$encodeSlash)
            ) {
                $result .= chr($ch);
            } else {
                $result .= "%" . strtoupper(dechex($ch));
            }
        }

        return $result;
    }

    public static function sha256Hash($data)
    {
        return hash('sha256', $data);
    }

    public static function hmacSha256($key, $data)
    {
        return hash_hmac('sha256', $data, $key);
    }

    public static function hmacSha256Hex($key, $data)
    {
        return hash_hmac('sha256', $data, pack('H*', $key));
    }
}

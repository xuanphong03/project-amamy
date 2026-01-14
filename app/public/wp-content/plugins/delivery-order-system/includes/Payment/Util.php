<?php

namespace DeliveryOrderSystem\Payment;

if (! defined('ABSPATH')) {
    exit;
}

class Util
{
    public static function formatTimeToYYYYMMDD($time)
    {
        $result = $time->format("Ymd");
        return $result;
    }

    public static function formatTimeToYYYYMMDDTHHMMSSZ($time)
    {
        $result = $time->format("Ymd\THis\Z");
        return $result;
    }

    public static function callPostRequest($url, $content, $headers)
    {
        $ch = curl_init($url);
        // Set cURL options
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[Delivery Order System] cURL POST error: ' . $error);
            return false;
        }

        // Close cURL session
        curl_close($ch);

        return $response;
    }

    public static function callPutRequest($url, $content, $headers)
{
    $ch = curl_init($url);
    // Set cURL options
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('[Delivery Order System] cURL PUT error: ' . $error);
        return false;
    }

    // Close cURL session
    curl_close($ch);

        return $response;
    }

    public static function callGetRequest($url, $headers)
{
    $ch = curl_init($url);
    // Set cURL options
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('[Delivery Order System] cURL GET error: ' . $error);
        return false;
    }

    // Close cURL session
    curl_close($ch);

        return $response;
    }

    public static function parseIsoStringToDateObject($dateString)
    {
        $dateTime = \DateTime::createFromFormat('Ymd\THis\Z', $dateString);

        if ($dateTime instanceof \DateTime) {
            return $dateTime;
        } else {
            return false; // Invalid date format
        }
    }
}
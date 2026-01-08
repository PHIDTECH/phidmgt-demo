<?php
/**
 * Selcom API Gateway Client
 * Standalone implementation based on official Selcom PHP client
 * https://github.com/selcompaytechltd/selcom-apigw-client-php
 */

class SelcomClient {
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    
    public function __construct($baseUrl, $apiKey, $apiSecret) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }
    
    /**
     * Compute authentication headers
     * Based on official Selcom Java client implementation
     */
    public function computeHeader($data) {
        // Timestamp in ISO 8601 format
        $timestamp = date("Y-m-d\TH:i:sP");
        
        // Build message string: timestamp=<ts>&key1=value1&key2=value2...
        $serializedParts = [];
        $serializedParts[] = "timestamp=" . $timestamp;
        
        $keys = [];
        foreach ($data as $key => $value) {
            $serializedParts[] = $key . "=" . $value;
            $keys[] = $key;
        }
        
        // Join with & separator
        $message = implode("&", $serializedParts);
        $signedFields = implode(",", $keys);
        
        // Digest: HMAC-SHA256 of the message string
        $digest = base64_encode(hash_hmac('sha256', $message, $this->apiSecret, true));
        
        // Authorization: Base64 encoded API Key
        $authorization = base64_encode($this->apiKey);
        
        return [
            'Authorization' => 'SELCOM ' . $authorization,
            'Digest-Method' => 'HS256',
            'Digest' => $digest,
            'Timestamp' => $timestamp,
            'Signed-Fields' => $signedFields
        ];
    }
    
    /**
     * POST request
     */
    public function postFunc($path, $data) {
        $url = $this->baseUrl . $path;
        $headers = $this->computeHeader($data);
        
        $curlHeaders = [
            'Content-Type: application/json',
            'Authorization: ' . $headers['Authorization'],
            'Digest-Method: ' . $headers['Digest-Method'],
            'Digest: ' . $headers['Digest'],
            'Timestamp: ' . $headers['Timestamp'],
            'Signed-Fields: ' . $headers['Signed-Fields']
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log request for debugging
        $this->logRequest($url, $data, $curlHeaders, $response, $httpCode);
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'error' => $error,
            'raw' => $response
        ];
    }
    
    /**
     * GET request
     */
    public function getFunc($path, $params = []) {
        $url = $this->baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = $this->computeHeader($params);
        
        $curlHeaders = [
            'Accept: application/json',
            'Authorization: ' . $headers['Authorization'],
            'Digest-Method: ' . $headers['Digest-Method'],
            'Digest: ' . $headers['Digest'],
            'Timestamp: ' . $headers['Timestamp'],
            'Signed-Fields: ' . $headers['Signed-Fields'],
            'Signature: ' . $headers['Signature']
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
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
     * Log requests for debugging
     */
    private function logRequest($url, $data, $headers, $response, $httpCode) {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $log = date('Y-m-d H:i:s') . "\n";
        $log .= "URL: $url\n";
        $log .= "Data: " . json_encode($data) . "\n";
        $log .= "Headers: " . json_encode($headers) . "\n";
        $log .= "Response ($httpCode): $response\n";
        $log .= "---\n";
        
        file_put_contents($logDir . '/selcom_client.log', $log, FILE_APPEND);
    }
}

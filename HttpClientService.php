<?php
// php/services/HttpClientService.php

/**
 * A generic HTTP client service to handle all cURL requests.
 */
class HttpClientService {

    /**
     * Sends an HTTP request using cURL.
     *
     * @param string $url The URL to send the request to.
     * @param string $method The HTTP method (e.g., 'POST', 'GET').
     * @param mixed $payload The data to send with the request.
     * @param array $headers An array of HTTP headers.
     * @param bool $isJsonPayload Determines if the payload should be JSON encoded.
     * @return array The decoded JSON response.
     * @throws Exception on cURL or API errors.
     */
    public function sendRequest(string $url, string $method = 'GET', $payload = null, array $headers = [], bool $isJsonPayload = true): array {
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("Failed to initialize cURL.");
        }

        $loggableHeaders = [];
        foreach ($headers as $header) {
            if (stripos($header, 'Authorization:') === 0) {
                $parts = explode(' ', $header);
                $loggableHeaders[] = $parts[0] . ' Bearer ' . substr($parts[2] ?? '', 0, 8) . '...';
            } else {
                $loggableHeaders[] = $header;
            }
        }
        debug_log([
            'URL' => $url,
            'Method' => $method,
            'Headers' => $loggableHeaders,
            'Payload' => $payload
        ], '--- Sending HTTP Request ---');

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($payload) {
                $options[CURLOPT_POSTFIELDS] = $isJsonPayload ? json_encode($payload) : $payload;
            }
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        debug_log([
            'URL' => $url,
            'HTTP Code' => $http_code,
            'cURL Error' => $error ?: 'None',
            'Raw Response' => $response
        ], '--- Received HTTP Response ---');

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        $responseData = json_decode($response, true);

        if ($http_code >= 400) {
            $errorMessage = $responseData['result']['resultMsg'] ?? $responseData['error_description'] ?? 'API request failed.';
            throw new Exception("API Error on '{$url}' (HTTP {$http_code}): " . $errorMessage);
        }
        
        // Handle cases where the response is not valid JSON, but the request was successful
        if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
             if($http_code < 300) {
                return ['success' => true, 'raw_response' => $response];
             } else {
                throw new Exception("Invalid JSON response from '{$url}' (HTTP {$http_code})");
             }
        }

        return $responseData;
    }
}

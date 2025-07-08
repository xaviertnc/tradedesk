<?php
// php/services/GeminiApiService.php

/**
 * Handles all communication with the Google Gemini API.
 */
class GeminiApiService {
    private HttpClientService $httpClient;
    private string $apiKey;
    private string $apiUrl;

    public function __construct(HttpClientService $httpClient) {
        $this->httpClient = $httpClient;
        // The API key is left as an empty string. The execution environment will provide it.
        $this->apiKey = ""; 
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$this->apiKey}";
    }

    /**
     * Gets a market analysis for the USD/ZAR pair.
     * @return string The market analysis text.
     * @throws Exception
     */
    public function getUsdZarAnalysis(): string {
        $today = date('F j, Y');
        $prompt = "Provide a brief, professional market analysis for the USD/ZAR currency pair for today, {$today}. The analysis is for a forex trader in South Africa. Focus on recent news, key drivers (like interest rates, commodity prices, and political events), and the short-term outlook. Keep it concise and under 150 words.";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ]
        ];

        $headers = ['Content-Type: application/json'];

        $response = $this->httpClient->sendRequest($this->apiUrl, 'POST', $payload, $headers);

        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }

        debug_log($response, 'Unexpected Gemini API Response');
        throw new Exception('Failed to get market analysis from Gemini API.');
    }
}

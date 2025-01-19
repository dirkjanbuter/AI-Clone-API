<?php

class OllamaAPI {
    private string $model;
    private string $baseUrl = 'http://localhost:11434'; 
    private float $temperature;
    private int $maxTokens;
    private array $conversationHistory;

    /**
     * Initialize Ollama API
     * 
     * @param string $model Model to use (default: gpt-4o)
     * @param float $temperature Randomness of responses (0-2)
     * @param int $maxTokens Maximum tokens in response
     */
    public function __construct(
        string $model = 'llama3.1:8b',
        float $temperature = 0.7,
        int $maxTokens = 1000
    ) {
        $this->model = $model;
        $this->temperature = $temperature;
        $this->maxTokens = $maxTokens;
        $this->conversationHistory = [];
    }

    /**
     * Send a message and get a response
     * 
     * @param string $message User message
     * @param array $options Additional options
     * @return array Response data
     * @throws Exception
     */
    public function sendMessage(string $message, array $options = []): array {
        // Add user message to conversation history
        $this->conversationHistory[] = [
            'role' => 'user',
            'content' => $message
        ];

        // Prepare request data
        $data = [
            'model' => $this->model,
            'messages' => $this->conversationHistory,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'stream' => false
            // Add other options if needed
        ];

        try {
            $response = $this->makeRequest('/api/chat', $data); // Example endpoint
            
            if (isset($response['message'])) {
                // Add assistant's response to conversation history
                $this->conversationHistory[] = $response['message'];
            }
        } catch (Exception $e) {
            throw new Exception('Failed to get response: ' . $e->getMessage());
        }
        
        return $response;
    }

    /**
     * Make HTTP request to Ollama API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws Exception
     */
    private function makeRequest(string $endpoint, array $data): array {
        $ch = curl_init($this->baseUrl . $endpoint);

        $headers = [
            'Content-Type: application/json',
            // Add any other necessary headers
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($ch);
        $responseData = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new Exception(
                'API Error: ' . ($responseData['error']['message'] ?? 'Unknown error')
            );
        }

        return $responseData;
    }

    /**
     * Clear conversation history
     */
    public function clearConversation(): void {
        $this->conversationHistory = [];
    }

    /**
     * Get conversation history
     * 
     * @return array Conversation history
     */
    public function getConversationHistory(): array {
        return $this->conversationHistory;
    }

    /**
     * Set system message for conversation context
     * 
     * @param string $message System message
     */
    public function setSystemMessage(string $message): void {
        $this->conversationHistory = [[
            'role' => 'system',
            'content' => $message
        ]];
    }

    public function storeConversationHistory(string $filename): void {
        file_put_contents($filename, json_encode($this->conversationHistory));
    }

    public function loadConversationHistory(string $filename): void {
        $this->conversationHistory = json_decode(file_get_contents($filename), true);
    }
}

<?php

class ChatGPTAPI {
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';
    private float $temperature;
    private int $maxTokens;
    private array $conversationHistory;

    /**
     * Initialize ChatGPT API
     * 
     * @param string $apiKey OpenAI API key
     * @param string $model Model to use (default: gpt-3.5-turbo)
     * @param float $temperature Randomness of responses (0-2)
     * @param int $maxTokens Maximum tokens in response
     */
    public function __construct(
        string $apiKey,
        string $model = 'gpt-4o',
        float $temperature = 0.7,
        int $maxTokens = 1000
    ) {
        $this->apiKey = $apiKey;
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
            'n' => $options['n'] ?? 1,
            'stream' => $options['stream'] ?? false,
        ];

        // Add optional parameters if provided
        if (isset($options['presence_penalty'])) {
            $data['presence_penalty'] = $options['presence_penalty'];
        }
        if (isset($options['frequency_penalty'])) {
            $data['frequency_penalty'] = $options['frequency_penalty'];
        }
        if (isset($options['user'])) {
            $data['user'] = $options['user'];
        }

        try {
            $response = $this->makeRequest('/chat/completions', $data);
            
            if (isset($response['choices'][0]['message'])) {
                // Add assistant's response to conversation history
                $this->conversationHistory[] = $response['choices'][0]['message'];
            }

            return $response;
        } catch (Exception $e) {
            throw new Exception('Failed to get response: ' . $e->getMessage());
        }
    }

    /**
     * Stream the chat response
     * 
     * @param string $message User message
     * @param callable $callback Callback function for each chunk
     * @param array $options Additional options
     * @throws Exception
     */
    public function streamResponse(string $message, callable $callback, array $options = []): void {
        $options['stream'] = true;
        
        $this->conversationHistory[] = [
            'role' => 'user',
            'content' => $message
        ];

        $data = [
            'model' => $this->model,
            'messages' => $this->conversationHistory,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'stream' => true
        ];

        $this->makeStreamRequest('/chat/completions', $data, $callback);
    }

    /**
     * Make HTTP request to OpenAI API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws Exception
     */
    private function makeRequest(string $endpoint, array $data): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
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
     * Make streaming request to OpenAI API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param callable $callback Callback function for each chunk
     * @throws Exception
     */
    private function makeStreamRequest(string $endpoint, array $data, callable $callback): void {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use ($callback) {
                $lines = explode("\n", $chunk);
                foreach ($lines as $line) {
                    if (strpos($line, 'data: ') === 0) {
                        $jsonData = substr($line, 6);
                        if ($jsonData === '[DONE]') {
                            return strlen($chunk);
                        }
                        $responseData = json_decode($jsonData, true);
                        if ($responseData && isset($responseData['choices'][0]['delta']['content'])) {
                            $callback($responseData['choices'][0]['delta']['content']);
                        }
                    }
                }
                return strlen($chunk);
            },
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Stream Error: HTTP ' . $httpCode);
        }
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

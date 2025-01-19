<?php

class ElevenLabsSocketAPI {
    private string $apiKey;
    private string $host = 'api.elevenlabs.io';
    private int $port = 443;
    private string $outputDir;
    private int $timeout = 30;
    private bool $debug = true;  // Enable debug logging

    public function __construct(string $apiKey, string $outputDir = 'tts_output/') {
        $this->apiKey = $apiKey;
        $this->outputDir = rtrim($outputDir, '/') . '/';
        $this->createOutputDir();
    }

    public function streamTextToSpeech(
        string $text, 
        string $voiceId, 
        callable $callback,
        array $options = []
    ): string {
        $this->debug("Starting TTS request for voice: $voiceId");
        
        $defaultOptions = [
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75
            ]
        ];

        $options = array_merge($defaultOptions, $options);
        
        $data = json_encode([
            'text' => $text,
            'model_id' => $options['model_id'],
            'voice_settings' => $options['voice_settings']
        ]);

        $this->debug("Request payload: $data");

        $outputFile = $this->outputDir . uniqid() . '_output.mp3';
        $outputHandle = fopen($outputFile, 'wb');

        if (!$outputHandle) {
            throw new Exception("Failed to create output file: $outputFile");
        }

        try {
            $this->makeStreamingRequest(
                "POST",
                "/v1/text-to-speech/$voiceId",  // Removed /stream
                $data,
                function($chunk) use ($callback, $outputHandle) {
                    fwrite($outputHandle, $chunk);
                    $callback($chunk);
                }
            );
        } catch (Exception $e) {
            fclose($outputHandle);
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            throw $e;
        }

        fclose($outputHandle);
        return $outputFile;
    }

    private function makeStreamingRequest(
        string $method, 
        string $path, 
        string $data, 
        callable $callback
    ): void {
        $this->debug("Connecting to {$this->host}:{$this->port}");

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);

        $socket = @stream_socket_client(
            "ssl://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        $this->debug("Connected successfully");

        try {
            stream_set_timeout($socket, $this->timeout);

            $headers = [
                "$method $path HTTP/1.1",
                "Host: {$this->host}",
                "Connection: close",
                "xi-api-key: {$this->apiKey}",
                "Content-Type: application/json",
                "Accept: */*",
                "Content-Length: " . strlen($data),
                "",
                $data
            ];

            $request = implode("\r\n", $headers);
            $this->debug("Sending request:\n" . preg_replace('/xi-api-key: [^\n]+/', 'xi-api-key: [REDACTED]', $request));

            fwrite($socket, $request);
            
            $response = $this->processResponse($socket, $callback);
            
            print_r($response);

            if (!empty($response['error'])) {
                throw new Exception("API Error: " . $response['error']);
            }

        } finally {
            fclose($socket);
        }
    }

    private function processResponse($socket, callable $callback): array {
        $this->debug("Processing response");
        
        $headers = [];
        $headerComplete = false;
        $buffer = '';
        $responseBody = '';

        while (!feof($socket)) {
            $chunk = fgets($socket);
            if ($chunk === false) {
                $this->debug("Error reading from socket");
                break;
            }
            
            $buffer .= $chunk;

            if (!$headerComplete && strpos($buffer, "\r\n\r\n") !== false) {
                list($headerData, $buffer) = explode("\r\n\r\n", $buffer, 2);
                $headers = $this->parseHeaders($headerData);
                $this->debug("Response headers: " . print_r($headers, true));
                
                $headerComplete = true;

                if ($headers['status_code'] !== 200) {
                    // Collect error response
                    $responseBody = $buffer;
                    while (!feof($socket)) {
                        $responseBody .= fgets($socket);
                    }
                    
                    $this->debug("Error response body: $responseBody");
                    
                    try {
                        $errorData = json_decode($responseBody, true);
                        return [
                            'error' => $errorData['detail'] ?? $responseBody,
                            'status_code' => $headers['status_code']
                        ];
                    } catch (Exception $e) {
                        return [
                            'error' => "HTTP Error: {$headers['status_code']}",
                            'status_code' => $headers['status_code']
                        ];
                    }
                }

                if (!empty($buffer)) {
                    $callback($buffer);
                }
                continue;
            }

            if ($headerComplete) {
                $callback($buffer);
                $buffer = '';
            }
        }

        return ['status_code' => $headers['status_code'] ?? 0];
    }

    private function parseHeaders(string $headerData): array {
        $headers = [];
        $lines = explode("\r\n", $headerData);
        
        $statusLine = array_shift($lines);
        if (preg_match('#HTTP/\d\.\d (\d+)#', $statusLine, $matches)) {
            $headers['status_code'] = (int)$matches[1];
        }

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    private function createOutputDir(): void {
        if (!file_exists($this->outputDir)) {
            if (!mkdir($this->outputDir, 0777, true)) {
                throw new Exception('Failed to create output directory');
            }
        }
    }

    private function debug(string $message): void {
        if ($this->debug) {
            error_log("[ElevenLabs API] $message");
        }
    }
}
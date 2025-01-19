<?php
class WhisperAPI {
    private $api_key;
    private $api_endpoint = 'http://localhost:8000/v1/audio/transcriptions'; //'https://api.openai.com/v1/audio/transcriptions';
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function transcribe($audio_path, $options = []) {
        if (!file_exists($audio_path)) {
            throw new Exception('Audio file not found');
        }

        // Default options
        $default_options = [
            'model' => 'whisper-1',
            'language' => 'en', // optional: specify language
            'response_format' => 'json', // json, text, srt, verbose_json, or vtt
            'temperature' => 0, // 0-1, lower is more focused/deterministic
        ];

        $options = array_merge($default_options, $options);

        // Create CURLFile object
        $audio_file = new CURLFile($audio_path);

        // Prepare the request data
        $post_data = [
            'file' => $audio_file,
            'model' => $options['model'],
            'response_format' => $options['response_format']
        ];

        // Add optional parameters if set
        if (isset($options['language'])) {
            $post_data['language'] = $options['language'];
        }
        if (isset($options['temperature'])) {
            $post_data['temperature'] = $options['temperature'];
        }
        if (isset($options['prompt'])) {
            $post_data['prompt'] = $options['prompt'];
        }

        // Initialize cURL
        $curl = curl_init();

        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->api_key,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Check for errors
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($curl);

        // Handle response
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            throw new Exception('API Error: ' . 
                ($error_data['error']['message'] ?? 'Unknown error occurred'));
        }

        return $response;
    }
}

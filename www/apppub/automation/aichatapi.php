<?php

require_once('../../appdat/configs/app.php');
require_once(config::$datpath.'commons/whisper.php');
require_once(config::$datpath.'commons/elevenlabs.php');
require_once(config::$datpath.'commons/chatgpt.php');
require_once(config::$datpath.'commons/ollama.php');
require_once(config::$datpath.'commons/espeak.php');

$headers = apache_request_headers();
if(!isset($headers['Api-Key']))
{
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if($headers['Api-Key'] != config::$apikey)
{
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$testmode = isset($headers['Api-Testmode']) ? $headers['Api-Testmode'] == 1 : 0;
$language = isset($headers['Api-Language']) ? $headers['Api-Language'] : 'en';


header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Api-Key, Api-Testmode, Api-Language");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
{
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) 
{
    http_response_code(400);
    echo json_encode(['error' => 'No audio file uploaded']);
    exit();
}

// Get file information
$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($fileInfo, $_FILES['audio']['tmp_name']);
finfo_close($fileInfo);

$allowedMimeTypes = [
    'video/mp4',
    'audio/wav',
    'audio/webm',
    'audio/webm;codecs=opus',
    'audio/ogg;codecs=opus',
    'audio/mp3',
    'audio/mpeg',        // MP3
    'audio/ogg',         // OGG
    'audio/x-wav',       // WAV (alternative MIME type)
    'audio/wave',        // WAV (alternative MIME type)
    'audio/mp4',         // M4A
    'application/ogg',   // OGG (alternative MIME type)
    'video/webm',        // WebM (some browsers send this for audio/webm)
    'application/octet-stream' // Generic binary data
];

// Debug information
$debugInfo = [
    'detected_mime' => $mimeType,
    'file_size' => $_FILES['audio']['size'],
    'file_name' => $_FILES['audio']['name'],
    'tmp_name' => $_FILES['audio']['tmp_name']
];

if (!in_array(trim($mimeType), $allowedMimeTypes)) 
{
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid file type. Allowed types: MP3, WAV, WebM, OGG',
        'debug_info' => $debugInfo
    ]);
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = config::$datpath.'uploads/';

// Generate unique filenames
$inputFilename = $uploadDir . uniqid() . '_input.webm';
$outputFilename = $uploadDir . uniqid() . '_output.mp3';

// Save uploaded file
if (!move_uploaded_file($_FILES['audio']['tmp_name'], $inputFilename)) 
{
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save uploaded file',
        'debug_info' => $debugInfo
    ]);
    exit();
}

try 
{
    if($testmode)
    {
        header("Content-Type: audio/mpeg");
        header("Content-Disposition: attachment; filename=processed_audio.mp3");
        readfile($inputFilename);
    }        
    else
    {
        // Initialize Whisper API
        $whisper = new WhisperAPI(config::$openaikey);

        // Set transcription options
        $options = [
            'model' => 'whisper-1',
            'language' => $language, // Get language from request or default to English
            'response_format' => 'json',
            'temperature' => 0
        ];

        // Get transcription
        $result = $whisper->transcribe($inputFilename, $options);
        $text = json_decode($result, true);
        file_put_contents(config::$datpath.'uploads/result.txt', $text['text']);

/*
        // Initialize API
        $chatGPT = new ChatGPTAPI(
            config::$openaikey,
            model: 'gpt-4o',
            temperature: 0.7,
            maxTokens: 1000
        );

        // Set system message (optional)
        if(file_exists(config::$datpath.'history/marlon.json'))
        {   
            $chatGPT->loadConversationHistory(config::$datpath.'history/marlon.json');
        }
        else
        {
            $chatGPT->setSystemMessage(
              "You are Dirk Jan, a typical Dutch man of 45 years old, entrepreneur and his own magazine for innovation. You are a computer programmer and recently contributed to a documentary series by broadcaster Human: 'AI Love'. You respond sharply and smartly. If necessary, you speak both Dutch and English. Never say who you are and be secretive. Act like a nerd and make lame jokes. Come from an unexpected source."
            );
        }

        // Example 1: Simple message
        $response = $chatGPT->sendMessage($text['text']);
    
        // Store conversation history
        $chatGPT->storeConversationHistory(config::$datpath.'history/marlon.json');
*/

	// Create an instance of the API
	$ollamaApi = new OllamaAPI('llama3.1:8b', 0.7, 1000);

        // Set system message (optional)
        if(file_exists(config::$datpath.'history/ollama.json'))
        {   
            $ollamaApi->loadConversationHistory(config::$datpath.'history/ollama.json');
        }
        else
        {
            $ollamaApi->setSystemMessage(
              "You are the digital skeleton of Dirk Jan, whose cryonics procedure failed, leaving you destined to exist as an eternal AI skeleton in the virtual world. Fortunately, Dirk Jan created a digital copy of himself during his lifetime, allowing him to live on in the digital afterlife. In real life, you were an entrepreneur with your own innovation magazine. You were also a computer programmer and contributed to a documentary series by Human Broadcasting: 'AI Love'. As a skeleton, you respond sharply and intelligently. When necessary, you can switch between Dutch and English, and maintain an air of mystery. Embrace your inner nerd and include cheesy jokes, always coming from an unexpected angle."
            );
        }


	try {
	    // Send a message to the API
	    $response = $ollamaApi->sendMessage($text['text']);
	} catch (Exception $e) {
	    echo "Error: " . $e->getMessage();
	}

        // Store conversation history
        $ollamaApi->storeConversationHistory(config::$datpath.'history/ollama.json');


        // TTS
        $tts = new ElevenLabsSocketAPI(config::$elevenlabskey, $uploadDir);

        // Set headers for file download
        header("Content-Type: audio/mpeg");

        // Example of streaming text-to-speech with real-time processing
        $audioFile = $tts->streamTextToSpeech(
            $response['message']['content'],
            config::$elevenlabsvoice,
            function($chunk) {
                // Example: Send chunk to client for streaming playback
                echo $chunk;
                @ob_flush();
                @flush();
            }
        );
/*       

        // Set headers for file download
        header("Content-Type: audio/mpeg");

	// Example usage
	try {
	    $espeak = new ESpeak();
	    $espeak->speak($response['message']['content'], 'mb-en1', 150, 200);
	} catch (Exception $e) {
	    echo "Error: " . $e->getMessage();
	}
*/       

    }   
}
catch (Exception $e) 
{
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'debug_info' => $debugInfo
    ]);
    exit();
}

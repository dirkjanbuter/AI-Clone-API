# AI-Clone-API

A speech-to-speech webapi for use with my AI clone or avatar written in PHP. The API has an audio file as input and output. And in between are Whisper, ChatGPT and Eleven Labs.

* Whisper: for speech to text;
* ChatGPT - for pre-processing the text;
* Eleven Labs: for text-to-speech; (TTS)

Compatible with Ollama and a local Whisper. I am implementing a local TTS at the moment.

# Example

A usage example with cURL:

curl https://{{domain}}/apppub/automation/aichatapi.php -H 'Api-Key: {{key}}' -H 'Api-Testmode: 0' -H 'Api-Language: en' -F "audio=@in.mp3" -o out.mp3

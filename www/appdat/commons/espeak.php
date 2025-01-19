<?php

if(!defined('STDIN'))  define('STDIN',  fopen('php://stdin',  'rb'));
if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'wb'));
if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));

class ESpeak
{
    private $espeakPath;

    public function __construct($espeakPath = '/usr/bin/espeak')
    {
        if (!file_exists($espeakPath)) {
            throw new Exception("Espeak executable not found at: " . $espeakPath);
        }
        $this->espeakPath = $espeakPath;
    }

    /**
     * Converts text to speech and streams it to STDOUT.
     *
     * @param string $text The text to convert to speech.
     * @param string|null $voice The voice to use (optional).
     * @param int $speed The speed of the speech (optional).
     * @param int $volume The volume of the speech (0-200, optional).
     *
     * @return void
     */
    public function speak($text, $voice = null, $speed = 175, $volume = 100)
    {
        $command = escapeshellcmd($this->espeakPath) . ' ';

        if ($voice) {
            $command .= '--stdout -v ' . escapeshellarg($voice) . ' ';
        }

        $command .= '-s ' . intval($speed) . ' ';
        $command .= '-a ' . intval($volume) . ' ';
        $command .= escapeshellarg($text);

        // Set up the descriptors for proc_open
        $descriptorspec = [
            0 => ["pipe", "r"],   // stdin
            1 => ["pipe", "w"],   // stdout
            2 => ["pipe", "w"]    // stderr
        ];

        // Open the process
        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Close the stdin pipe since we are not writing to it
            fclose($pipes[0]);

            // Read the stdout and stderr
            while ($output = fgets($pipes[1])) {
                echo $output; // Stream to STDOUT
            }
            
            while ($errorOutput = fgets($pipes[2])) {
                // Handle errors if needed
                fprintf(STDERR, $errorOutput);
            }

            // Close the pipes
            fclose($pipes[1]);
            fclose($pipes[2]);

            // Close the process
            proc_close($process);
        }
    }
}



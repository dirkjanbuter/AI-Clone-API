<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Clone API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f0f2f5;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1, h2, h3 {
            text-align: center;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        button {
            padding: 12px 24px;
            font-size: 1rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #3498db;
            color: white;
            min-width: 200px;
        }

        button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        button:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }

        button.recording {
            background-color: #e74c3c;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .status {
            text-align: center;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 5px;
            font-weight: bold;
        }

        .status.recording {
            background-color: #fde8e8;
            color: #e74c3c;
        }

        .status.processing {
            background-color: #e8f4fd;
            color: #3498db;
        }

        .status.success {
            background-color: #e8fdf0;
            color: #27ae60;
        }

        .status.error {
            background-color: #fde8e8;
            color: #e74c3c;
        }

        .audio-players {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            margin: 2rem 0;
        }

        .audio-player {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .audio-player h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        audio {
            width: 100%;
            margin-top: 0.5rem;
        }

        .visualizer {
            width: 100%;
            height: 100px;
            margin: 1rem 0;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 1rem 0;
            color: #2c3e50;
        }

        .loading-spinner {
            display: none;
            width: 40px;
            height: 40px;
            margin: 1rem auto;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AI Chat</h1>
        
        <div class="controls">
            <input type="text" id="apikey" placeholder="Enter your API key">
            <input type="checkbox" id="testmode" checked> Testmode
            <select id="language">
                <option value="en">English</option>
                <option value="nl">Dutch</option>
            </select>
            <canvas id="visualizer" class="visualizer"></canvas>
            <div id="timer" class="timer">00:00</div>
            <button id="recordButton">Start Recording</button>
            <div id="loading" class="loading-spinner"></div>
        </div>

        <div id="status" class="status">Ready to record</div>

        <div class="audio-players">
            <div class="audio-player">
                <h3>Original Recording</h3>
                <audio id="originalAudio" controls></audio>
            </div>
            <div class="audio-player">
                <h3>Processed Audio</h3>
                <audio id="processedAudio" controls></audio>
            </div>
        </div>
    </div>

    <script>
        class AudioRecorder {
            constructor() {
                this.mediaRecorder = null;
                this.audioChunks = [];
                this.isRecording = false;
                this.stream = null;
                this.analyser = null;
                this.startTime = null;
                this.timerInterval = null;

                // DOM elements
                this.recordButton = document.getElementById('recordButton');
                this.statusDiv = document.getElementById('status');
                this.originalAudio = document.getElementById('originalAudio');
                this.processedAudio = document.getElementById('processedAudio');
                this.visualizer = document.getElementById('visualizer');
                this.timerElement = document.getElementById('timer');
                this.loadingSpinner = document.getElementById('loading');
                this.apikey = document.getElementById('apikey');
                this.testmode = document.getElementById('testmode');
                this.language = document.getElementById('language');

                // Bind methods
                this.toggleRecording = this.toggleRecording.bind(this);
                this.updateTimer = this.updateTimer.bind(this);
                this.drawVisualizer = this.drawVisualizer.bind(this);

                // Setup
                this.setupEventListeners();
                this.setupRecorder().catch(console.error);
            }

            setupEventListeners() {
                this.recordButton.addEventListener('click', this.toggleRecording);
            }

            async setupRecorder() {
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    
                    // Setup audio context and analyser
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const source = audioContext.createMediaStreamSource(this.stream);
                    this.analyser = audioContext.createAnalyser();
                    this.analyser.fftSize = 2048;
                    source.connect(this.analyser);

                    // Setup media recorder with preferred MIME type
                    const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                        ? 'audio/webm;codecs=opus'
                        : 'audio/webm';

                    this.mediaRecorder = new MediaRecorder(this.stream, { mimeType });

                    this.mediaRecorder.ondataavailable = (event) => {
                        this.audioChunks.push(event.data);
                    };

                    this.mediaRecorder.onstop = async () => {
                        const audioBlob = new Blob(this.audioChunks, { type: mimeType });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        this.originalAudio.src = audioUrl;
                        
                        this.updateStatus('Processing audio...', 'processing');
                        this.loadingSpinner.style.display = 'block';
                        await this.sendToAPI(audioBlob);
                    };

                    // Start visualizer
                    this.drawVisualizer();

                } catch (err) {
                    console.error('Error accessing microphone:', err);
                    this.updateStatus('Error accessing microphone. Please ensure you have given permission.', 'error');
                    this.recordButton.disabled = true;
                }
            }

            toggleRecording() {
                if (!this.isRecording) {
                    this.startRecording();
                } else {
                    this.stopRecording();
                }
            }

            startRecording() {
                this.audioChunks = [];
                this.mediaRecorder.start(10); // Collect data every 10ms
                this.isRecording = true;
                this.startTime = Date.now();
                this.timerInterval = setInterval(this.updateTimer, 1000);
                
                this.recordButton.textContent = 'Stop Recording';
                this.recordButton.classList.add('recording');
                this.updateStatus('Recording...', 'recording');
            }

            stopRecording() {
                this.mediaRecorder.stop();
                this.isRecording = false;
                clearInterval(this.timerInterval);
                
                this.recordButton.textContent = 'Start Recording';
                this.recordButton.classList.remove('recording');
            }

            updateTimer() {
                const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
                const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
                const seconds = (elapsed % 60).toString().padStart(2, '0');
                this.timerElement.textContent = `${minutes}:${seconds}`;
            }

            drawVisualizer() {
                const canvas = this.visualizer;
                const canvasCtx = canvas.getContext('2d');
                const bufferLength = this.analyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);

                const draw = () => {
                    const WIDTH = canvas.width;
                    const HEIGHT = canvas.height;

                    requestAnimationFrame(draw);

                    this.analyser.getByteTimeDomainData(dataArray);

                    canvasCtx.fillStyle = '#f8f9fa';
                    canvasCtx.fillRect(0, 0, WIDTH, HEIGHT);
                    canvasCtx.lineWidth = 2;
                    canvasCtx.strokeStyle = this.isRecording ? '#e74c3c' : '#3498db';
                    canvasCtx.beginPath();

                    const sliceWidth = WIDTH * 1.0 / bufferLength;
                    let x = 0;

                    for (let i = 0; i < bufferLength; i++) {
                        const v = dataArray[i] / 128.0;
                        const y = v * HEIGHT / 2;

                        if (i === 0) {
                            canvasCtx.moveTo(x, y);
                        } else {
                            canvasCtx.lineTo(x, y);
                        }

                        x += sliceWidth;
                    }

                    canvasCtx.lineTo(canvas.width, canvas.height / 2);
                    canvasCtx.stroke();
                };

                draw();
            }

            async sendToAPI(audioBlob) {
                try {
                    const formData = new FormData();
                    formData.append('audio', audioBlob, 'recording.webm');

                    const response = await fetch('apppub/automation/aichatapi.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Api-Key': this.apikey.value,
                            'Api-Testmode': this.testmode.checked ? 1 : 0,
                            'Api-Language': this.language.value,
                        }
                    });

                    this.loadingSpinner.style.display = 'none';

                    if (response.ok) {
                        const processedBlob = await response.blob();
                        const processedUrl = URL.createObjectURL(processedBlob);
                        this.processedAudio.src = processedUrl;
                        this.processedAudio.play();
                        this.updateStatus('Audio processed successfully!', 'success');
                    } else {
                        const error = await response.json();
                        throw new Error(error.error || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Error sending to API:', error);
                    this.updateStatus(`Error processing audio: ${error.message}`, 'error');
                }
            }

            updateStatus(message, type) {
                this.statusDiv.textContent = message;
                this.statusDiv.className = `status ${type}`;
            }
        }

        // Initialize the recorder when the page loads
        window.addEventListener('load', () => {
            new AudioRecorder();
        });
    </script>
</body>
</html>

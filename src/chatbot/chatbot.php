<?php
session_start();

// Initialize chat history if not exists
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Handle incoming messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message'])) {
        $userMessage = $_POST['message'];
        $botResponse = processMessage($userMessage);
        
        $_SESSION['chat_history'][] = [
            'user' => $userMessage,
            'bot' => $botResponse,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode(['response' => $botResponse]);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'get_history') {
        echo json_encode(['history' => $_SESSION['chat_history']]);
        exit;
    }
}

function processMessage($message) {
    $message = strtolower(trim($message));
    
    // Enhanced responses with more context
    $responses = [
        'greeting' => [
            'patterns' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'],
            'response' => "Hello! 👋 I'm your virtual assistant. How can I help you today? I can provide information about our services, working hours, location, or help you schedule an appointment."
        ],
        'location' => [
            'patterns' => ['address', 'location', 'where', 'find', 'directions'],
            'response' => "📍 We are located at 123 Main Street, City, State. You can find us easily using Google Maps. Would you like me to provide you with directions?"
        ],
        'hours' => [
            'patterns' => ['hours', 'working hours', 'open', 'close', 'schedule'],
            'response' => "🕒 Our working hours are:\n• Monday - Friday: 9:00 AM - 6:00 PM\n• Saturday: 10:00 AM - 4:00 PM\n• Sunday: Closed\n\nWould you like to schedule an appointment?"
        ],
        'services' => [
            'patterns' => ['services', 'offer', 'provide', 'do', 'check'],
            'response' => "🔧 We offer the following services:\n\n1. Smog Check\n2. Clean Truck Check\n3. Vehicle Maintenance\n4. Diagnostic Services\n5. Emissions Testing\n\nWhich service are you interested in?"
        ],
        'appointment' => [
            'patterns' => ['appointment', 'schedule', 'book', 'reserve'],
            'response' => "📅 To schedule an appointment, you can:\n\n1. Call us directly at (555) 123-4567\n2. Use our online booking system\n3. Send us an email at appointments@example.com\n\nWhat's your preferred method?"
        ],
        'pricing' => [
            'patterns' => ['price', 'cost', 'fee', 'charge', 'how much'],
            'response' => "💰 Our pricing varies based on the service and vehicle type. Here's a general overview:\n\n• Smog Check: $50-$100\n• Clean Truck Check: $75-$150\n• Vehicle Maintenance: Starting at $100\n\nWould you like a detailed quote for a specific service?"
        ],
        'contact' => [
            'patterns' => ['contact', 'phone', 'email', 'call', 'reach'],
            'response' => "📞 You can reach us through:\n\n• Phone: (555) 123-4567\n• Email: info@example.com\n• WhatsApp: (555) 123-4567\n\nWhat's the best way to contact you?"
        ],
        'thanks' => [
            'patterns' => ['thank', 'thanks', 'appreciate'],
            'response' => "You're welcome! 😊 Is there anything else I can help you with?"
        ]
    ];
    
    foreach ($responses as $category => $data) {
        foreach ($data['patterns'] as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return $data['response'];
            }
        }
    }
    
    return "I'm not sure I understand. 🤔 Could you please rephrase your question? I can help you with:\n\n• Services information\n• Working hours\n• Location\n• Pricing\n• Appointment scheduling\n• Contact information";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 380px;
            height: 600px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .chat-header {
            background: #17a2b8;
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-header h3 {
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header .status {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .chat-body {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: none;
            background: #f8f9fa;
        }

        .chat-message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            max-width: 85%;
        }

        .message-content {
            padding: 12px 16px;
            border-radius: 15px;
            position: relative;
            word-wrap: break-word;
        }

        .message-timestamp {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 5px;
            margin-left: 10px;
        }

        .user-message {
            align-self: flex-end;
        }

        .user-message .message-content {
            background: #17a2b8;
            color: white;
            border-radius: 15px 15px 0 15px;
        }

        .bot-message {
            align-self: flex-start;
        }

        .bot-message .message-content {
            background: white;
            color: #2c3e50;
            border-radius: 15px 15px 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .chat-input {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: none;
            background: white;
        }

        .input-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .chat-input input {
            flex-grow: 1;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 25px;
            outline: none;
            transition: all 0.3s ease;
        }

        .chat-input input:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23,162,184,0.25);
        }

        .chat-input button {
            background: #17a2b8;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-input button:hover {
            background: #138496;
            transform: scale(1.05);
        }

        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border-radius: 15px;
            margin-bottom: 15px;
            align-self: flex-start;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .typing-indicator span {
            height: 8px;
            width: 8px;
            background: #17a2b8;
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: typing 1s infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .quick-replies {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .quick-reply {
            background: #e9ecef;
            color: #2c3e50;
            padding: 8px 16px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .quick-reply:hover {
            background: #17a2b8;
            color: white;
        }

        .minimize-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .attachment-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .attachment-btn:hover {
            color: #17a2b8;
        }

        .file-preview {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .file-preview.active {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-preview .file-info {
            flex-grow: 1;
        }

        .file-preview .remove-file {
            color: #dc3545;
            cursor: pointer;
        }

        /* Animation classes */
        .slide-up {
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header" onclick="toggleChat()">
            <h3>
                <i class="fas fa-robot"></i>
                Chat Assistant
                <span class="status">Online</span>
            </h3>
            <button class="minimize-btn">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="typing-indicator" id="typingIndicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="chat-input">
            <div class="input-container">
                <button class="attachment-btn" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-paperclip"></i>
                </button>
                <input type="text" id="userInput" placeholder="Type your message...">
                <button onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <input type="file" id="fileInput" style="display: none" onchange="handleFileSelect(event)">
            <div class="file-preview" id="filePreview"></div>
        </div>
    </div>

    <script>
        let selectedFile = null;
        const quickReplies = [
            "What are your services?",
            "Where are you located?",
            "What are your working hours?",
            "How can I schedule an appointment?",
            "What are your prices?"
        ];

        function toggleChat() {
            const chatBody = document.querySelector('.chat-body');
            const chatInput = document.querySelector('.chat-input');
            const minimizeBtn = document.querySelector('.minimize-btn i');
            
            if (chatBody.style.display === 'none' || !chatBody.style.display) {
                chatBody.style.display = 'block';
                chatInput.style.display = 'block';
                minimizeBtn.classList.remove('fa-minus');
                minimizeBtn.classList.add('fa-chevron-down');
                if (document.querySelectorAll('.chat-message').length === 0) {
                    loadChatHistory();
                }
            } else {
                chatBody.style.display = 'none';
                chatInput.style.display = 'none';
                minimizeBtn.classList.remove('fa-chevron-down');
                minimizeBtn.classList.add('fa-minus');
            }
        }

        function loadChatHistory() {
            fetch('chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_history'
            })
            .then(response => response.json())
            .then(data => {
                if (data.history && data.history.length > 0) {
                    data.history.forEach(msg => {
                        addMessage(msg.user, 'user', msg.timestamp);
                        addMessage(msg.bot, 'bot', msg.timestamp);
                    });
                } else {
                    addMessage("Hello! 👋 How can I help you today?", 'bot');
                    addQuickReplies();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                addMessage("Hello! 👋 How can I help you today?", 'bot');
                addQuickReplies();
            });
        }

        function addMessage(message, sender, timestamp = null) {
            const chatBody = document.getElementById('chatBody');
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${sender}-message slide-up`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = message;
            
            const timeDiv = document.createElement('div');
            timeDiv.className = 'message-timestamp';
            timeDiv.textContent = timestamp || new Date().toLocaleTimeString();
            
            messageDiv.appendChild(contentDiv);
            messageDiv.appendChild(timeDiv);
            chatBody.appendChild(messageDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function addQuickReplies() {
            const chatBody = document.getElementById('chatBody');
            const quickRepliesDiv = document.createElement('div');
            quickRepliesDiv.className = 'quick-replies fade-in';
            
            quickReplies.forEach(reply => {
                const button = document.createElement('div');
                button.className = 'quick-reply';
                button.textContent = reply;
                button.onclick = () => {
                    document.getElementById('userInput').value = reply;
                    sendMessage();
                };
                quickRepliesDiv.appendChild(button);
            });
            
            chatBody.appendChild(quickRepliesDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function showTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = 'block';
            document.querySelector('.chat-body').scrollTop = document.querySelector('.chat-body').scrollHeight;
        }

        function hideTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = 'none';
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                selectedFile = file;
                const preview = document.getElementById('filePreview');
                preview.className = 'file-preview active';
                preview.innerHTML = `
                    <div class="file-info">
                        <strong>${file.name}</strong>
                        <br>
                        <small>${(file.size / 1024).toFixed(1)} KB</small>
                    </div>
                    <i class="fas fa-times remove-file" onclick="removeFile()"></i>
                `;
            }
        }

        function removeFile() {
            selectedFile = null;
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreview').className = 'file-preview';
        }

        function sendMessage() {
            const input = document.getElementById('userInput');
            const message = input.value.trim();
            
            if (message || selectedFile) {
                addMessage(message, 'user');
                input.value = '';
                
                // Remove quick replies if they exist
                const quickReplies = document.querySelector('.quick-replies');
                if (quickReplies) {
                    quickReplies.remove();
                }

                showTypingIndicator();

                // Send message to server
                const formData = new FormData();
                formData.append('message', message);
                if (selectedFile) {
                    formData.append('file', selectedFile);
                }

                fetch('chatbot.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideTypingIndicator();
                    setTimeout(() => {
                        addMessage(data.response, 'bot');
                        addQuickReplies();
                    }, 500);
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideTypingIndicator();
                    addMessage('Sorry, there was an error processing your message.', 'bot');
                });

                removeFile();
            }
        }

        // Allow sending message with Enter key
        document.getElementById('userInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Initialize chat
        document.addEventListener('DOMContentLoaded', function() {
            const chatBody = document.querySelector('.chat-body');
            const chatInput = document.querySelector('.chat-input');
            chatBody.style.display = 'none';
            chatInput.style.display = 'none';
        });
    </script>
</body>
</html> 
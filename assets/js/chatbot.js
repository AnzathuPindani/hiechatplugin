jQuery(document).ready(function($) {
    const $launcher = $('#custom-chatbot-launcher');
    const $container = $('#custom-chatbot-container');
    const $messages = $('.chatbot-messages');
    const $input = $('.chatbot-user-input');
    const $sendBtn = $('.chatbot-send-btn');
    const $closeBtn = $('.chatbot-close');
    // Add these variables at the top with your other declarations
const $attachmentBtn = $('<button class="chatbot-attachment-btn">📎</button>');
const $attachmentInput = $('<input type="file" id="chatbot-attachment-input" style="display: none;">');
const $attachmentPreview = $('<div class="attachment-preview"></div>');
let currentAttachment = null;

    let userData = {
        name: '',
        phone: '',
        issue: ''
    };
    let currentStep = 0;

    // Helper function to format time
    function formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function getUniqueUserId() {
        if (typeof wpApiSettings !== 'undefined' && wpApiSettings.user.id && wpApiSettings.user.id > 0) {
            return wpApiSettings.user.id;
        }
        let sessionId = sessionStorage.getItem('chatbot_session_id');
        if (!sessionId) {
            sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('chatbot_session_id', sessionId);
        }
        return sessionId;
    }

    const userId = getUniqueUserId();

    // Display message with timestamp
    function displayMessage(content, className, customTimestamp = null) {
        const timestamp = customTimestamp || formatTime(new Date());
        
        $messages.append(`
            <div class="message ${className}">
                <div class="message-content">${content}</div>
                <div class="message-timestamp">${timestamp}</div>
            </div>
        `);
        scrollToBottom();
    }

    function scrollToBottom() {
        $messages.scrollTop($messages[0].scrollHeight);
    }

    // Pusher initialization
    try {
        const pusher = new Pusher("f2d82e15fe0645138773", {
            cluster: "ap2",
            forceTLS: true,
        });

        const channel = pusher.subscribe(`chat.${userId}`);
    
        channel.bind("new-message", function(data) {
            console.info(data);
            displayMessage(data.message, 'bot-message', data.timestamp || formatTime(new Date()));
             if (data.attachment) {
                const fileName = data.attachment.split('/').pop();
                displayAttachment(data.attachment, fileName);
            }
        });
    } catch (err) {
        console.error("Pusher init failed:", err);
        displayMessage("⚠️ Couldn't connect to live updates", 'bot-message');
    }

    // Toggle UI
    $launcher.on('click', function() {
        $container.toggleClass('active');
        if ($container.hasClass('active')) startChat();
    });

    $closeBtn.on('click', function(e) {
        e.stopPropagation();
        $container.removeClass('active');
    });

    function startChat() {
        if (currentStep === 0) {
            userData = { name: '', phone: '', issue: '' };
            $messages.empty();
            displayMessage('👋 Hello! What is your name?', 'bot-message');
        }
    }

 // Update handleUserInput to accept attachment URL
function handleUserInput(message, attachmentUrl = '') {
    if (currentStep === 0) {
        userData.name = message;
        displayMessage(`Nice to meet you, ${message}! What is your phone number?`, 'bot-message');
        currentStep++;
    } else if (currentStep === 1) {
        userData.phone = message;
        displayMessage("How can we help you today?", 'bot-message');
        currentStep++;
    } else if (currentStep === 2) {
        userData.issue = message;
        displayMessage("Thank you! We will get back to you soon.", 'bot-message');
        sendMessageToServer(message, attachmentUrl);
        currentStep++;
    }
}
    // Add this after your input area creation
$('.chatbot-input-area').prepend($attachmentBtn);
$('.chatbot-input-area').append($attachmentInput);
$('.chatbot-messages').after($attachmentPreview);
$attachmentBtn.on('click', function() {
    $attachmentInput.click();
});

$attachmentInput.on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validate file size (e.g., 5MB max)
    if (file.size > 5 * 1024 * 1024) {
        displayMessage('File is too large (max 5MB)', 'bot-message');
        return;
    }
    
    // Show preview
    currentAttachment = file;
    $attachmentPreview.html(`
        <div class="attachment-item">
            <span>${file.name}</span>
            <button class="remove-attachment">×</button>
        </div>
    `).show();
});

$attachmentPreview.on('click', '.remove-attachment', function() {
    currentAttachment = null;
    $attachmentInput.val('');
    $attachmentPreview.hide().empty();
});

function sendMessageToServer(message, attachmentUrl = '') {
    $.ajax({
        url: "http://127.0.0.1:8000/api/send-webmessage",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
            name: userData.name,
            phone: userData.phone,
            issue: message,
            attachment: attachmentUrl,
            user_id: userId,
            timestamp: formatTime(new Date())
        }),
        success: function(response) {
            if (response.timestamp) {
            
                // Display any received attachments
                if (response.attachment) {
                    displayAttachment(response.attachment, response.attachment.split('/').pop());
                }
            } else {
              
            }
        },
        error: function(xhr) {
            displayMessage("❌ Error: " + xhr.responseText, "bot-message");
        }
    });
}
    function sendFollowupMessage(message,attachmentUrl = '') {
        $.ajax({
            url: "http://127.0.0.1:8000/api/send-webmessage",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                name: userData.name,
                phone: userData.phone,
                issue: message,
                attachment: attachmentUrl,
                user_id: userId,
                timestamp: formatTime(new Date())
            }),
            success: function(response) {
                console.log("✅ Follow-up sent");
                // Display any received attachments
                // if (response.attachment) {
                //     displayAttachment(response.attachment, response.attachment.split('/').pop());
                // }
            },
            error: function(xhr) {
                displayMessage("❌ Error: " + xhr.responseText, "bot-message");
            }
        });
    }

    function sendMessage() {
        const message = $input.val().trim();
        
        // Don't send if no message and no attachment
        if (!message && !currentAttachment) return;
        
        // Display the message immediately in the chat UI
        if (message) {
            displayMessage(message, 'user-message');
        }
        
        // Clear input right after displaying
        $input.val('');
    
        // Prepare the data based on whether we have an attachment
        if (currentAttachment) {
            // Use FormData for attachments
            const formData = new FormData();
            formData.append('name', userData.name);
            formData.append('phone', userData.phone);
            formData.append('issue', message || "Sent an attachment");
            formData.append('user_id', userId);
            formData.append('timestamp', formatTime(new Date()));
            formData.append('attachment', currentAttachment);
    
            $.ajax({
                url: "http://127.0.0.1:8000/api/send-webmessage",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.timestamp) {
                        displayMessage("✅ Your request has been sent successfully!", "bot-message", response.timestamp);
                        if (response.attachment) {
                            displayAttachment(response.attachment, response.attachment.split('/').pop());
                        }
                    }
                    // Handle the conversation flow
                    if (currentStep < 3) {
                        handleUserInput(message || "Sent an attachment", response.attachment || '');
                    } else {
                        // For follow-up messages after initial conversation
                        sendFollowupMessage(message || "Sent an attachment", response.attachment || '');
                    }
                },
                error: function(xhr) {
                    displayMessage("❌ Error: " + xhr.responseText, "bot-message");
                }
            });
        } else {
            // No attachment, handle the conversation flow normally
            if (currentStep < 3) {
                handleUserInput(message);
            } else {
                // For follow-up messages after initial conversation
                sendFollowupMessage(message);
            }
        }
    
        // Clear attachment after sending
        currentAttachment = null;
        $attachmentPreview.hide().empty();
    }
    
 function displayAttachment(url, name) {
   
    const isPDF = url.toLowerCase().endsWith('.pdf');

    // Optional: Normalize URL for local dev and production
    const fileUrl = url.startsWith('http') ? url : `http://127.0.0.1:8000/${url}`;
    const previewHtml = isPDF
        ? `<div class="pdf-attachment">
             <a href="${fileUrl}" target="_blank" class="attachment-link">
               <span class="attachment-icon">📄</span>
               <span class="attachment-name">${name}</span>
             </a>
           </div>`
        : `<div class="image-attachment">
             <a href="${fileUrl}" target="_blank">
               <img src="${fileUrl}" class="attachment-image" alt="${name}">
             </a>
           </div>`;

    // Ensure $messages is defined (assumes jQuery is used)
    if (typeof $messages === 'undefined') {
        console.error('Error: $messages is not defined.');
        return;
    }

    $messages.append(`
        <div class="message attachment-message">
            <div class="message-content">${previewHtml}</div>
            <div class="message-timestamp">${formatTime(new Date())}</div>
        </div>
    `);

    scrollToBottom();
}

    
    function getFileType(url) {
        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        const extension = url.split('.').pop().toLowerCase();
        return imageExtensions.includes(extension) ? 'image' : 'file';
    }
    

    $sendBtn.on('click', sendMessage);
    $input.on('keypress', function(e) {
        if (e.which === 13) sendMessage();
    });
});
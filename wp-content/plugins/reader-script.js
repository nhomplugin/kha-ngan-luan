document.addEventListener('DOMContentLoaded', function () {
    const contentText = document.getElementById('ai-post-content');
    if (!contentText) return;
    const textToRead = contentText.innerText;

    // Các phần tử giao diện
    const fabBtn = document.getElementById('ai-float-btn');
    const panel = document.getElementById('ai-float-panel');
    const btnClosePanel = document.getElementById('btn-close-panel');

    const btnPlay = document.getElementById('btn-play');
    const btnPause = document.getElementById('btn-pause');
    const btnResume = document.getElementById('btn-resume');
    const btnStop = document.getElementById('btn-stop');

    const statusLine = document.getElementById('ai-status-line');
    const statusText = document.getElementById('ai-status-text');

    function setStatus(text) {
        statusText.textContent = text;
    }

    // Đồng bộ hiệu ứng "đang đọc" (equalizer + nhịp trên icon nổi)
    function setSpeaking(isSpeaking) {
        statusLine.classList.toggle('is-speaking', isSpeaking);
        fabBtn.classList.toggle('is-speaking', isSpeaking);
    }

    // --- MỞ / ĐÓNG BẢNG ĐIỀU KHIỂN ---
    function openPanel() {
        panel.classList.add('show');
        fabBtn.classList.add('is-open');
        fabBtn.setAttribute('aria-expanded', 'true');
    }
    function closePanel() {
        panel.classList.remove('show');
        fabBtn.classList.remove('is-open');
        fabBtn.setAttribute('aria-expanded', 'false');
    }
    fabBtn.addEventListener('click', function () {
        if (panel.classList.contains('show')) closePanel(); else openPanel();
    });
    btnClosePanel.addEventListener('click', closePanel);

    // --- ĐỌC BÀI BẰNG AI (ResponsiveVoice) ---
    function startAIReading() {
        if (typeof responsiveVoice === 'undefined') {
            setStatus('Không tải được giọng đọc AI. Vui lòng thử lại sau.');
            return;
        }
        responsiveVoice.cancel();
        setStatus('Đang đọc bài viết cho bạn…');
        setSpeaking(true);
        responsiveVoice.speak(textToRead, 'Vietnamese Female', {
            onend: function () {
                setSpeaking(false);
                setStatus('Đã đọc xong bài viết.');
            },
            onerror: function () {
                setSpeaking(false);
                setStatus('Có lỗi khi đọc bài. Vui lòng thử lại.');
            }
        });
        openPanel();
    }

    btnPlay.addEventListener('click', startAIReading);

    btnPause.addEventListener('click', function () {
        if (typeof responsiveVoice === 'undefined') return;
        responsiveVoice.pause();
        setSpeaking(false);
        setStatus('Đã tạm dừng.');
    });

    btnResume.addEventListener('click', function () {
        if (typeof responsiveVoice === 'undefined') return;
        responsiveVoice.resume();
        setSpeaking(true);
        setStatus('Đang đọc tiếp…');
    });

    btnStop.addEventListener('click', function () {
        if (typeof responsiveVoice === 'undefined') return;
        responsiveVoice.cancel();
        setSpeaking(false);
        setStatus('Đã hủy đọc bài.');
    });
});

jQuery(document).ready(function ($) {
    const selectAiBtn = $('#aivr-select-ai-btn');
    const chatbox = $('#aivr-chatbox');
    const chatboxBody = $('#aivr-chatbox-body');
    const chatboxInput = $('#aivr-chatbox-input');
    const chatboxSend = $('#aivr-chatbox-send');
    const chatboxClose = $('#aivr-chatbox-close');

    let currentSelectedText = '';

    // 1. LẮNG NGHE SỰ KIỆN BÔI ĐEN VĂN BẢN TRÊN BÀI VIẾT
    $(document).on('mouseup touchend', function (e) {
        // Nếu nhấp chuột bên trong Chatbox hoặc nút AI thì không tắt nút
        if ($(e.target).closest('#aivr-chatbox, #aivr-select-ai-btn').length > 0) return;

        setTimeout(() => {
            const selection = window.getSelection();
            const text = selection.toString().trim();

            if (text.length > 1) {
                currentSelectedText = text;
                const range = selection.getRangeAt(0);
                const rect = range.getBoundingClientRect();

                // Hiển thị nút AI ngay phía trên vị trí bôi đen
                selectAiBtn.css({
                    top: (rect.top + window.scrollY - 38) + 'px',
                    left: (rect.left + window.scrollX) + 'px',
                    display: 'flex'
                });
            } else {
                selectAiBtn.hide();
            }
        }, 10);
    });

    // 2. KHI BẤM VÀO NÚT "✨ HỎI AI TỪ NÀY"
    selectAiBtn.on('click', function () {
        selectAiBtn.hide();
        chatbox.addClass('open');
        
        const initialQuestion = `Giải thích giúp tôi ý nghĩa của cụm từ: "${currentSelectedText}"`;
        appendMessage(initialQuestion, 'user');
        sendToGemini(initialQuestion, currentSelectedText);
    });

    // 3. ĐÓNG CHATBOX
    chatboxClose.on('click', function () {
        chatbox.removeClass('open');
    });

    // 4. NÚT GỬI CÂU HỎI BẰNG TAY TRONG CHATBOX
    function handleUserSend() {
        const msg = chatboxInput.val().trim();
        if (!msg) return;

        appendMessage(msg, 'user');
        chatboxInput.val('');
        sendToGemini(msg, '');
    }

    chatboxSend.on('click', handleUserSend);
    chatboxInput.on('keypress', function (e) {
        if (e.which === 13) handleUserSend();
    });

    // Hàm chèn tin nhắn vào khung chat
    function appendMessage(text, sender) {
        const msgClass = sender === 'user' ? 'aivr-msg-user' : 'aivr-msg-ai';
        // Format sơ bộ dấu xuống dòng và in đậm
        let formattedText = text.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        const msgHtml = `<div class="aivr-msg ${msgClass}">${formattedText}</div>`;
        chatboxBody.append(msgHtml);
        chatboxBody.scrollTop(chatboxBody[0].scrollHeight);
    }

    // 5. GỬI YÊU CẦU LÊN SERVER QUA WP AJAX
    function sendToGemini(message, selectedText) {
        const loadingId = 'loading-' + Date.now();
        chatboxBody.append(`<div id="${loadingId}" class="aivr-msg aivr-msg-ai"><i>AI đang suy nghĩ và tìm kiếm thông tin...</i></div>`);
        chatboxBody.scrollTop(chatboxBody[0].scrollHeight);

        $.ajax({
            url: aivr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aivr_gemini_chat',
                security: aivr_ajax.nonce,
                message: message,
                selected_text: selectedText
            },
            success: function (response) {
                $('#' + loadingId).remove();
                if (response.success) {
                    appendMessage(response.data, 'ai');
                } else {
                    appendMessage('⚠️ ' + response.data, 'ai');
                }
            },
            error: function () {
                $('#' + loadingId).remove();
                appendMessage('⚠️ Có lỗi kết nối máy chủ. Vui lòng thử lại.', 'ai');
            }
        });
    }
});
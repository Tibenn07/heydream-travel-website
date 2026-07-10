// floating_chatbot.js - Floating AI Companion Chatbot for HeyDream
(function () {
    // Load FontAwesome if not already present
    if (!document.querySelector('link[href*="font-awesome"]') && !document.querySelector('link[href*="all.min.css"]')) {
        const fa = document.createElement('link');
        fa.rel = 'stylesheet';
        fa.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(fa);
    }

    // 1. Inject Styles
    const style = document.createElement('style');
    style.innerHTML = `
        #hd-chat-bubble {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: white;
            border: 3px solid #003580;
            cursor: pointer;
            box-shadow: 0 6px 24px rgba(0,53,128,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            z-index: 9999;
            padding: 0;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
            animation: hdPulse 2.5s infinite, hdAttention 10s infinite ease-in-out;
            overflow: visible;
            outline: none;
        }
        #hd-bubble-logo {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }
        #hd-bubble-close {
            display: none;
            color: #003580;
            font-size: 1.4rem;
        }
        #hd-chat-bubble:hover {
            transform: scale(1.1) translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,53,128,0.55);
            animation-play-state: paused;
        }
        @keyframes hdPulse {
            0%,100% { box-shadow: 0 6px 24px rgba(0,53,128,0.45), 0 0 0 0 rgba(0,53,128,0.3); }
            50%      { box-shadow: 0 6px 24px rgba(0,53,128,0.45), 0 0 0 10px rgba(0,53,128,0); }
        }
        @keyframes hdAttention {
            0%, 80%, 100% { transform: translateY(0); }
            83% { transform: translateY(-10px); }
            86% { transform: translateY(0) scale(0.95); }
            89% { transform: translateY(-5px); }
        }
        #hd-chat-badge {
            position: absolute;
            top: -8px; right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px; height: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid white;
            animation: hdBounce 1s ease infinite;
        }
        @keyframes hdBounce { 0%,100%{transform:scale(1)} 50%{transform:scale(1.2)} }

        /* Chat Window */
        #hd-chat-window {
            position: fixed;
            bottom: 100px;
            right: 28px;
            width: 360px;
            max-height: 540px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: column;
            z-index: 9998;
            overflow: hidden;
            opacity: 0;
            transform: translateY(40px) scale(0.8);
            pointer-events: none;
            transition: opacity 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: 'Poppins', sans-serif;
        }
        #hd-chat-window.open {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        /* Header */
        .hd-chat-header {
            background: linear-gradient(135deg, #003580, #0057d9);
            padding: 16px 18px;
            color: white;
            display: flex; align-items: center; gap: 12px;
            flex-shrink: 0;
        }
        .hd-chat-avatar {
            width: 42px; height: 42px;
            background: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.5);
        }
        .hd-chat-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .hd-chat-header-info { flex: 1; }
        .hd-chat-header-info strong { display: block; font-size: 0.95rem; font-weight: 800; }
        .hd-chat-header-info span { font-size: 0.72rem; opacity: 0.85; }
        .hd-online-dot { display: inline-block; width: 8px; height: 8px; background: #4ade80; border-radius: 50%; margin-right: 4px; }
        .hd-close-btn {
            background: rgba(255,255,255,0.15); border: none; color: white;
            width: 30px; height: 30px; border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .hd-close-btn:hover { background: rgba(255,255,255,0.3); }

        .hd-menu-btn {
            background: rgba(255,255,255,0.15); border: none; color: white;
            width: 30px; height: 30px; border-radius: 50%; cursor: pointer;
            font-size: 0.85rem; display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, transform 0.3s ease;
        }
        .hd-menu-btn:hover { background: rgba(255,255,255,0.3); }
        .hd-menu-btn.active { transform: rotate(90deg); background: rgba(255,255,255,0.3); }

        /* Side Menu Panel */
        .hd-side-menu {
            position: absolute; top: 74px; left: -260px;
            width: 250px; height: calc(100% - 74px);
            background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px);
            box-shadow: 10px 0 30px rgba(0,0,0,0.15); z-index: 9999;
            transition: left 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex; flex-direction: column; border-right: 1px solid rgba(0, 53, 128, 0.15);
        }
        .hd-side-menu.open { left: 0; }
        .hd-side-menu-header {
            padding: 14px 18px; border-bottom: 1px solid #edf2f7;
            display: flex; align-items: center; justify-content: space-between;
            background: #f8fafc; flex-shrink: 0;
        }
        .hd-side-menu-header strong { font-size: 0.88rem; color: #0f172a; font-weight: 700; }
        .hd-side-menu-header button { background: none; border: none; color: #64748b; cursor: pointer; font-size: 0.85rem; padding: 4px; transition: color 0.2s; }
        .hd-side-menu-header button:hover { color: #0f172a; }
        .hd-side-menu-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; flex: 1; overflow-y: auto; }
        .hd-menu-item {
            background: white; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 12px 16px; display: flex; align-items: center; gap: 12px; cursor: pointer;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); text-align: left; width: 100%;
        }
        .hd-menu-item i { font-size: 1.15rem; color: #003580; width: 20px; text-align: center; }
        .hd-menu-item span { font-size: 0.85rem; font-weight: 700; color: #1e293b; }
        .hd-menu-item:hover { border-color: #003580; background: #f0f7ff; transform: translateX(4px); }

        /* Messages Area */
        #hd-messages {
            flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; background: #f8fafc;
        }
        #hd-messages::-webkit-scrollbar { width: 4px; }
        #hd-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        .hd-msg {
            display: flex; align-items: flex-end; gap: 8px; max-width: 88%;
            animation: hdPopIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; transform-origin: bottom left;
        }
        .hd-msg.bot { align-self: flex-start; }
        .hd-msg.user { align-self: flex-end; flex-direction: row-reverse; transform-origin: bottom right; }
        @keyframes hdPopIn { from { opacity: 0; transform: scale(0.8) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        .hd-msg-avatar {
            width: 28px; height: 28px; border-radius: 50%; background: white; border: 1.5px solid #003580;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;
        }
        .hd-msg-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .hd-msg-bubble {
            padding: 10px 14px; border-radius: 16px; font-size: 0.84rem; line-height: 1.55; max-width: 100%; word-wrap: break-word;
        }
        .hd-msg.bot .hd-msg-bubble { background: white; color: #1e293b; border-bottom-left-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .hd-msg.user .hd-msg-bubble { background: linear-gradient(135deg,#003580,#0057d9); color: white; border-bottom-right-radius: 4px; }
        .hd-msg.admin .hd-msg-bubble { background: linear-gradient(135deg, #e8f0fe, #d0e2ff); color: #003580; border-bottom-left-radius: 4px; box-shadow: 0 2px 8px rgba(0,53,128,0.10); border: 1px solid rgba(0,53,128,0.12); }
        .hd-agent-notice { display: flex; align-items: center; gap: 8px; justify-content: center; margin: 8px 0; font-size: 0.75rem; }
        .hd-agent-notice-line { flex: 1; height: 1px; background: linear-gradient(to right, transparent, rgba(0,53,128,0.18), transparent); }
        .hd-agent-notice-text { background: linear-gradient(135deg, #e8f0fe, #d0e2ff); color: #003580; border: 1px solid rgba(0,53,128,0.18); border-radius: 20px; padding: 4px 14px; font-weight: 600; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
        .hd-typing .hd-msg-bubble { padding: 12px 16px; }
        .hd-typing-dots { display: flex; gap: 4px; }
        .hd-typing-dots span { width: 7px; height: 7px; background: #94a3b8; border-radius: 50%; animation: hdDot 1.2s infinite; }
        .hd-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .hd-typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes hdDot { 0%,80%,100%{transform:scale(0.7);opacity:0.5} 40%{transform:scale(1);opacity:1} }

        /* Quick Replies */
        #hd-quick-replies {
            padding: 0 16px 12px; display: flex; flex-wrap: nowrap; gap: 8px; background: #f8fafc; flex-shrink: 0; overflow-x: auto; -webkit-overflow-scrolling: touch;
        }
        #hd-quick-replies::-webkit-scrollbar { height: 4px; }
        #hd-quick-replies::-webkit-scrollbar-track { background: transparent; }
        #hd-quick-replies::-webkit-scrollbar-thumb { background: rgba(0, 53, 128, 0.15); border-radius: 4px; }
        .hd-quick-btn {
            background: white; border: 1.5px solid #003580; color: #003580; border-radius: 50px; padding: 5px 12px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap; animation: hdReplyIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; opacity: 0;
        }
        .hd-quick-btn:hover { background: #003580; color: white; transform: scale(1.05); }
        @keyframes hdReplyIn { from { opacity: 0; transform: scale(0.8) translateY(12px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        /* Input Area */
        .hd-chat-input-area {
            padding: 12px 14px; border-top: 1px solid #e2e8f0; display: flex; gap: 8px; background: white; flex-shrink: 0;
        }
        #hd-chat-input {
            flex: 1; border: 1.5px solid #e2e8f0; border-radius: 50px; padding: 9px 16px; font-size: 0.84rem; outline: none; font-family: inherit; transition: border-color 0.2s;
        }
        #hd-chat-input:focus { border-color: #003580; }
        #hd-send-btn {
            width: 38px; height: 38px; background: linear-gradient(135deg,#003580,#0057d9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: transform 0.2s; flex-shrink: 0;
        }
        #hd-send-btn:hover { transform: scale(1.1); }
        #hd-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        @media (max-width: 768px) {
            #hd-chat-bubble { bottom: 15px; right: 15px; width: 56px; height: 56px; }
            #hd-chat-window { position: fixed; top: 15px; bottom: 85px; left: 10px; right: 10px; width: auto; height: auto; max-height: none; }
        }
        @media (max-width: 480px) {
            #hd-chat-bubble { bottom: 12px; right: 12px; width: 52px; height: 52px; }
            #hd-chat-window { position: fixed; top: 12px; bottom: 78px; left: 6px; right: 6px; width: auto; height: auto; max-height: none; }
            #hd-messages { flex: 1; min-height: 0; overflow-y: auto; padding: 12px; }
            .hd-chat-header { padding: 10px 14px; }
            .hd-chat-header-info strong { font-size: 0.85rem; }
            .hd-chat-header-info span { font-size: 0.65rem; }
            .hd-chat-input-area { padding: 8px 10px; }
            #hd-chat-input { font-size: 0.8rem; padding: 8px 14px; }
            #hd-send-btn { width: 34px; height: 34px; }
        }
        @keyframes hdEmojiIn { from { opacity: 0; transform: translateY(8px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
        #hd-emoji-btn:hover { color: #003580 !important; transform: scale(1.15); }
        
        .hd-confirm-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(4px); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; animation: hdFadeIn 0.25s ease-out; }
        .hd-confirm-card { background: #ffffff; border-radius: 20px; width: 100%; max-width: 290px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); border: 1px solid #e2e8f0; text-align: center; transform: scale(0.9); animation: hdScaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        .hd-confirm-icon { width: 52px; height: 52px; background: #eff6ff; color: #2563eb; font-size: 1.35rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; animation: hdPulse 2s infinite; }
        .hd-confirm-title { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 8px 0; line-height: 1.3; }
        .hd-confirm-text { font-size: 0.8rem; color: #64748b; line-height: 1.45; margin: 0 0 20px 0; }
        .hd-confirm-actions { display: flex; gap: 10px; }
        .hd-confirm-btn { flex: 1; padding: 10px 14px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; border: none; outline: none; }
        .hd-confirm-btn.confirm { background: #003580; color: #ffffff; }
        .hd-confirm-btn.confirm:hover { background: #00255c; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 53, 128, 0.2); }
        .hd-confirm-btn.cancel { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .hd-confirm-btn.cancel:hover { background: #e2e8f0; color: #334155; }
        @keyframes hdFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes hdScaleIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes hdPulse { 0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); } 100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); } }
    `;
    document.head.appendChild(style);

    // 2. HTML Structure
    const widget = document.createElement('div');
    widget.innerHTML = `
        <button id="hd-chat-bubble" title="Chat with HeyDream AI">
            <img src="images/Heydream Logo.png" id="hd-bubble-logo" alt="HeyDream AI">
            <i class="fas fa-times" id="hd-bubble-close"></i>
            <div id="hd-chat-badge">1</div>
        </button>

        <div id="hd-chat-window">
            <div id="hd-side-menu" class="hd-side-menu">
                <div class="hd-side-menu-header">
                    <strong>Menu Options</strong>
                    <button id="hd-menu-close-btn" title="Close Menu"><i class="fas fa-times"></i></button>
                </div>
                <div class="hd-side-menu-body">
                    <button class="hd-menu-item" id="hd-menu-live-agent">
                        <i class="fas fa-user-tie"></i><span>Live Agents</span>
                    </button>
                    <button class="hd-menu-item" id="hd-menu-visit-website">
                        <i class="fas fa-globe"></i><span>Visit Our Website</span>
                    </button>
                    <button class="hd-menu-item" id="hd-menu-report-issue">
                        <i class="fas fa-exclamation-triangle"></i><span>Report Issue</span>
                    </button>
                </div>
            </div>

            <div class="hd-chat-header">
                <div class="hd-chat-avatar"><img src="images/Heydream Logo.png" alt="HeyDream"></div>
                <div class="hd-chat-header-info">
                    <strong>Customer Service</strong>
                    <span><span class="hd-online-dot"></span>Online</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="hd-new-chat-btn" id="hd-new-chat-btn" title="Start New Chat" style="background: rgba(255,255,255,0.18); border: none; color: white; padding: 4px 8px; border-radius: 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 4px; outline: none; margin-right: 4px;"><i class="fas fa-plus"></i> New Chat</button>
                    <button class="hd-menu-btn" id="hd-menu-trigger" title="Menu"><i class="fas fa-bars"></i></button>
                    <button class="hd-close-btn" id="hd-chat-close-btn"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div id="hd-messages"></div>
            <div id="hd-quick-replies"></div>
            <div class="hd-chat-input-area" style="position: relative; display: flex; align-items: center; gap: 8px;">
                <button id="hd-emoji-btn" title="Add Emoji" style="background: none; border: none; color: #64748b; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0 2px; transition: all 0.2s; outline: none; flex-shrink: 0;"><i class="far fa-smile"></i></button>
                <input type="text" id="hd-chat-input" placeholder="Ask me anything about travel..." maxlength="300" style="flex: 1; min-width: 0;">
                <button id="hd-send-btn" style="flex-shrink: 0;"><i class="fas fa-paper-plane"></i></button>
                
                <div id="hd-emoji-picker" style="display: none; position: absolute; bottom: 55px; left: 10px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); border: 1.5px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 30px -5px rgba(0, 53, 128, 0.15); width: 220px; padding: 12px; z-index: 1000; animation: hdEmojiIn 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);">
                    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; font-size: 1.15rem; text-align: center; user-select: none;">
                        <span class="hd-emj">😊</span><span class="hd-emj">🛫</span><span class="hd-emj">🌴</span><span class="hd-emj">🗺️</span><span class="hd-emj">🏨</span><span class="hd-emj">☀️</span>
                        <span class="hd-emj">👍</span><span class="hd-emj">🏝️</span><span class="hd-emj">✈️</span><span class="hd-emj">👜</span><span class="hd-emj">📸</span><span class="hd-emj">🌊</span>
                        <span class="hd-emj">😍</span><span class="hd-emj">🤩</span><span class="hd-emj">✨</span><span class="hd-emj">🙏</span><span class="hd-emj">💖</span><span class="hd-emj">🎉</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(widget);

    // Add emoji interaction styles dynamically
    document.querySelectorAll('.hd-emj').forEach(emj => {
        emj.style.cursor = 'pointer';
        emj.style.transition = 'transform 0.1s';
        emj.style.display = 'inline-block';
        emj.onmouseover = () => emj.style.transform = 'scale(1.25)';
        emj.onmouseout = () => emj.style.transform = 'scale(1)';
        emj.onclick = () => hdInsertEmoji(emj.textContent);
    });

    // 3. Javascript Logic
    const hdQuickReplies = [
        "How can I pay for the package?",
        "What are your best travel packages?",
        "What are your payment options?",
        "Can I customize the destinations?",
        "Do I need passport details now?",
        "Is visa assistance included?"
    ];

    let hdChatOpen = false;
    let hdHistory = [];
    let hdBadgeSeen = false;
    let hdLastMsgId = 0;
    const hdRenderedMsgIds = new Set();
    let hdAdminJoined = false;

    // Session ID Logic for index.php
    let hdSessionId = localStorage.getItem('hd_floating_chat_session_id');
    if (!hdSessionId) {
        hdSessionId = 'hd_float_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('hd_floating_chat_session_id', hdSessionId);
    }

    function hdToggleChat() {
        hdChatOpen = !hdChatOpen;
        document.getElementById('hd-chat-window').classList.toggle('open', hdChatOpen);
        document.getElementById('hd-bubble-logo').style.display = hdChatOpen ? 'none' : 'block';
        document.getElementById('hd-bubble-close').style.display = hdChatOpen ? 'block' : 'none';
        if (hdChatOpen && !hdBadgeSeen) {
            document.getElementById('hd-chat-badge').style.display = 'none';
            hdBadgeSeen = true;
            if (hdHistory.length === 0) hdGreet();
        }
        if (!hdChatOpen) hdToggleMenu(false);
    }

    let hdMenuOpen = false;
    function hdToggleMenu(forceState) {
        hdMenuOpen = (forceState !== undefined) ? forceState : !hdMenuOpen;
        document.getElementById('hd-side-menu').classList.toggle('open', hdMenuOpen);
        document.getElementById('hd-menu-trigger').classList.toggle('active', hdMenuOpen);
    }

    // Emoji Logic
    document.getElementById('hd-emoji-btn').onclick = function (e) {
        e.stopPropagation();
        const p = document.getElementById('hd-emoji-picker');
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    };
    function hdInsertEmoji(emoji) {
        const input = document.getElementById('hd-chat-input');
        input.value += emoji;
        input.focus();
        document.getElementById('hd-emoji-picker').style.display = 'none';
    }
    document.addEventListener('click', function (e) {
        const picker = document.getElementById('hd-emoji-picker');
        const btn = document.getElementById('hd-emoji-btn');
        if (picker && picker.style.display === 'block') {
            if (!picker.contains(e.target) && !btn.contains(e.target)) picker.style.display = 'none';
        }
    });

    // Chat UI Functions
    let hdGreetingShown = false;
    function hdGreet() {
        if (hdGreetingShown) return;
        hdGreetingShown = true;
        const greeting = "👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I'm <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏";
        hdAddMsg('bot', greeting);
        hdShowQuickReplies(hdQuickReplies);

        if (hdSessionId) {
            fetch('inquiry/ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: '[GREETING]', session_id: hdSessionId,
                    customer_name: window.currentFullName || 'Guest',
                    customer_email: window.currentUserEmail || '',
                    source: 'index'
                })
            }).then(res => res.json()).then(data => {
                if (data.success && data.last_msg_id) {
                    const msgId = parseInt(data.last_msg_id);
                    if (msgId > hdLastMsgId) hdLastMsgId = msgId;
                    hdRenderedMsgIds.add(msgId);
                }
            }).catch(e => console.error("Greeting log error:", e));
        }
    }

    function hdAddMsg(role, html, isPolling = false, msgId = null) {
        if (msgId) {
            msgId = parseInt(msgId);
            if (hdRenderedMsgIds.has(msgId)) return;
            hdRenderedMsgIds.add(msgId);
        }
        const container = document.getElementById('hd-messages');
        const wrap = document.createElement('div');
        wrap.className = 'hd-msg ' + role;

        const avatarImg = 'images/Heydream Logo.png';
        const label = (role === 'admin') ? 'Agent' : 'HD';

        if (role === 'bot' || role === 'admin') {
            wrap.innerHTML = `<div class="hd-msg-avatar"><img src="${avatarImg}" alt="${label}"></div><div class="hd-msg-bubble">${html}</div>`;
        } else {
            wrap.innerHTML = `<div class="hd-msg-bubble">${html}</div>`;
        }

        container.appendChild(wrap);
        container.scrollTop = container.scrollHeight;

        if (!isPolling) hdHistory.push({ role: role === 'bot' ? 'model' : 'user', parts: [{ text: html.replace(/<[^>]*>/g, '') }] });
    }

    function hdShowTyping() {
        const container = document.getElementById('hd-messages');
        const t = document.createElement('div');
        t.className = 'hd-msg bot hd-typing';
        t.id = 'hd-typing';
        t.innerHTML = `<div class="hd-msg-avatar"><img src="images/Heydream Logo.png" alt="HD"></div><div class="hd-msg-bubble"><div class="hd-typing-dots"><span></span><span></span><span></span></div></div>`;
        container.appendChild(t);
        container.scrollTop = container.scrollHeight;
    }

    function hdRemoveTyping() {
        const t = document.getElementById('hd-typing');
        if (t) t.remove();
    }

    function hdShowAgentTyping() {
        if (document.getElementById('hd-agent-typing')) return;
        const container = document.getElementById('hd-messages');
        const t = document.createElement('div');
        t.className = 'hd-msg admin hd-typing';
        t.id = 'hd-agent-typing';
        t.innerHTML = `<div class="hd-msg-avatar"><img src="images/Heydream Logo.png" alt="Agent"></div><div class="hd-msg-bubble"><div class="hd-typing-dots"><span></span><span></span><span></span></div></div>`;
        container.appendChild(t);
        container.scrollTop = container.scrollHeight;
    }

    function hdRemoveAgentTyping() {
        const t = document.getElementById('hd-agent-typing');
        if (t) t.remove();
    }

    function hdShowQuickReplies(replies) {
        const area = document.getElementById('hd-quick-replies');
        area.innerHTML = '';
        replies.forEach((r, idx) => {
            const btn = document.createElement('button');
            btn.className = 'hd-quick-btn';
            btn.textContent = r;
            btn.style.animationDelay = (idx * 0.08) + 's';
            btn.onclick = () => { area.innerHTML = ''; hdSendMessage(r, true); };
            area.appendChild(btn);
        });
    }

    function hdGetClientBackupReply(msg, isForm) {
        const m = msg.toLowerCase();
        const isTagalog = /\b(kumusta|kamusta|magandang|anong|magkano|saan|gusto|meron|paano|salamat|po|ho|ba|nga|yung|ang|mga|ako|ikaw|kayo|kami|namin|natin|sino|oo|hindi)\b/i.test(msg);
        const inquiryLink = isForm ? 'inquiry form below 👇' : '<a href="inquiry/inquire.php" style="color:#003580; font-weight:bold; text-decoration:underline;">Inquiry Page</a>';
        const inquiryLinkTagalog = isForm ? 'inquiry form sa ibaba 👇' : '<a href="inquiry/inquire.php" style="color:#003580; font-weight:bold; text-decoration:underline;">Inquiry Page</a>';

        if (/\b(pay|payment|gcash|maya|card|bank|bpi|bdo|bayad)\b/i.test(m)) {
            if (isTagalog) {
                return {
                    reply: "Maaari po kayong magbayad sa HeyDream gamit ang mga secure na payment options:<br><br>" +
                        "📱 **Payment Apps:**<br>" +
                        "• **GCash / PayMaya:** Send/transfer to <strong>0945 776 4140</strong> (Account Name: HeyDream Travel & Tours)<br><br>" +
                        "💳 **Credit / Debit Cards:** Tanggap ang Visa/Mastercard/JCB credit/debit cards diretso sa checkout.<br><br>" +
                        "🏦 **Bank Transfer:** BPI (Account No: 1234 5678 90), BDO (Account No: 5678 1234 56), o Metrobank (Account No: 9012 3456 78). 😊",
                    suggestions: ["Paano mag-book?", "Anong destinations ang meron?", "Makipag-ugnayan"]
                };
            }
            return {
                reply: "You can easily pay for your HeyDream travel packages using any of our secure payment options:<br><br>" +
                    "📱 **Payment Apps:**<br>" +
                    "• **GCash / PayMaya:** Send/transfer to <strong>0945 776 4140</strong> (Account Name: HeyDream Travel & Tours)<br><br>" +
                    "💳 **Credit / Debit Cards:** We accept Visa, Mastercard, and JCB directly at checkout.<br><br>" +
                    "🏦 **Bank Transfer:** BPI, BDO, or Metrobank transfer options available. 😊",
                suggestions: ["How do I book a tour?", "What destinations do you offer?", "Contact our team"]
            };
        }

        if (/\b(book|booking|reserve|reservation)\b/i.test(m)) {
            if (isTagalog) {
                return {
                    reply: "Madali lang pong mag-book! Sundin ang mga simpleng hakbang na ito sa aming website:<br><br>" +
                        "1️⃣ Piliin ang inyong gustong package sa aming homepage.<br>" +
                        "2️⃣ I-click ang **Book Now**, piliin ang inyong dates at travelers.<br>" +
                        "3️⃣ Magbayad gamit ang GCash/Bank Transfer/Credit Card at i-upload ang resibo para sa mabilis na verification! 📩😊",
                    suggestions: ["Ano ang payment options?", "Anong destinations ang meron?", "Custom trip options"]
                };
            }
            return {
                reply: "Booking your dream vacation on the HeyDream website is simple!<br><br>" +
                    "1️⃣ Select your package on our homepage.<br>" +
                    "2️⃣ Click **Book Now**, select dates and number of guests.<br>" +
                    "3️⃣ Pay via GCash/Bank Transfer/Credit Card and upload your receipt. 📩😊",
                suggestions: ["What are your payment options?", "What destinations do you offer?", "Custom trip option"]
            };
        }

        if (/\b(destin|package|place|offer|tour|travel|lugar|pupuntahan)\b/i.test(m)) {
            if (isTagalog) {
                return {
                    reply: "Nag-aalok po kami ng napakagandang local at international destinations! 🌴✈️<br><br>" +
                        "🌸 **International:** Japan (Osaka/Tokyo/Kyoto), Singapore, Hong Kong, Bangkok, Bali, at iba pa.<br>" +
                        "🌊 **Domestic:** Boracay, El Nido, Siargao, Cebu, Bohol, at Palawan.<br><br>" +
                        "Piliin ang inyong preferred package sa aming homepage o mag-fill up ng " + inquiryLinkTagalog + " para sa custom quote! ☀️",
                    suggestions: ["Tell me about Boracay", "Japan visa requirements", "How do I book a tour?"]
                };
            }
            return {
                reply: "We offer premium local and international destinations! 🌴✈️<br><br>" +
                    "🌸 **International:** Japan, Singapore, Hong Kong, Bangkok, Bali, and more.<br>" +
                    "🌊 **Domestic:** Boracay, El Nido, Siargao, Cebu, Bohol, and Palawan.<br><br>" +
                    "Choose your package on our homepage or fill out our " + inquiryLink + " for a custom quote! ☀️",
                suggestions: ["Tell me about Boracay", "Japan visa requirements", "How do I book a tour?"]
            };
        }

        if (/\b(custom|customi|personali|sariling itinerary|tailor)\b/i.test(m)) {
            if (isTagalog) {
                return {
                    reply: "Yes po! Gumagawa kami ng **Customized at Personalized Travel Packages** para sa pamilya, barkada, o team building.<br>" +
                        "Paki-fill up lamang ang " + inquiryLinkTagalog + " at ilagay ang inyong specific budget, hotel class, at activities sa **Special Requests**! ✈️💖",
                    suggestions: ["Ano ang payment options?", "Anong destinations ang meron?", "Makipag-ugnayan"]
                };
            }
            return {
                reply: "Yes, absolutely! We specialize in **Custom & Tailored Travel Packages** to match your budget and travel style.<br>" +
                    "Please fill out our " + inquiryLink + " and specify your hotel class, target budget, and activities in the **Special Requests** field! ✈️💖",
                suggestions: ["What are your payment options?", "What destinations do you offer?", "Contact our team"]
            };
        }

        if (/\b(contact|call|email|reach|phone|number|facebook|fb|ig|social|media|talk|agent|us)\b/i.test(m)) {
            if (isTagalog) {
                return {
                    reply: "Maaari po kayong makipag-ugnayan sa amin sa pamamagitan ng:<br><br>" +
                        "📞 **Phone/Viber/WhatsApp:** 0945 776 4140<br>" +
                        "✉️ **Email:** heydreamtravelandtours@gmail.com<br>" +
                        "📱 **Facebook:** HeyDream Travel Page<br>" +
                        "📱 **Instagram:** @haedreamconsultancy<br><br>" +
                        "O mag-submit ng inquiry sa " + inquiryLinkTagalog + " at tutulungan kayo ng aming live agents! 📞😊",
                    suggestions: ["Anong destinations ang meron?", "Paano mag-book?", "Ano ang payment options?"]
                };
            }
            return {
                reply: "You can get in touch with us through any of our channels:<br><br>" +
                    "📞 **Phone/Viber/WhatsApp:** 0945 776 4140<br>" +
                    "✉️ **Email:** heydreamtravelandtours@gmail.com<br>" +
                    "📱 **Facebook:** HeyDream Travel Page<br>" +
                    "📱 **Instagram:** @haedreamconsultancy<br><br>" +
                    "Or submit a request on our " + inquiryLink + " to get in touch with our live agents! 📞😊",
                suggestions: ["What destinations do you offer?", "How do I book a tour?", "What are your payment options?"]
            };
        }

        if (isTagalog) {
            return {
                reply: "Nagkaroon po ng bahagyang problema sa koneksyon, ngunit narito po ang mabilis na sagot:<br><br>" +
                    "Kami po sa HeyDream ay nag-aalok ng domestic at international packages, custom itineraries, visa assistance, at travel insurance! ✈️<br><br>" +
                    "Paki-fill up ang detalye sa " + inquiryLinkTagalog + " o makipag-ugnayan sa amin sa <strong>0945 776 4140</strong> para masagot ng aming live team ang inyong mga tanong! 🙏😊",
                suggestions: ["Anong destinations ang meron?", "Paano mag-book?", "Ano ang payment options?"]
            };
        }
        return {
            reply: "I am having a minor connection issue, but here is some helpful information:<br><br>" +
                "HeyDream offers premium domestic and international travel packages, customized itineraries, visa assistance, and travel insurance! ✈️<br><br>" +
                "Please visit our " + inquiryLink + " to request slot availability, or call us at <strong>0945 776 4140</strong> for direct assistance! 🙏😊",
            suggestions: ["What destinations do you offer?", "How do I book a tour?", "What are your payment options?"]
        };
    }

    async function hdCallGeminiDirectly(msg, data, chatPath, isForm) {
        try {
            const response = await fetch(data.api_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data.payload)
            });
            const result = await response.json();
            let rawText = result.candidates[0].content.parts[0].text || '';

            // Clean up text
            rawText = rawText.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();

            try {
                const parsed = JSON.parse(rawText);
                if (parsed.reply) {
                    rawText = parsed.reply;
                }
            } catch (e) { }

            // Re-format bold tags and double asterisks
            rawText = rawText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            rawText = rawText.replace(/\*\*/g, '');

            hdRemoveTyping();
            hdAddMsg('bot', rawText);

            // Send background logging request
            const customerName = document.getElementById('full-name')?.value || window.currentFullName || 'Guest';
            const customerEmail = document.getElementById('email')?.value || window.currentUserEmail || '';

            fetch(chatPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    log_only: true,
                    message: msg,
                    ai_reply: rawText,
                    session_id: hdSessionId,
                    customer_name: customerName,
                    customer_email: customerEmail
                })
            })
                .then(logRes => logRes.json())
                .then(logData => {
                    if (logData.last_msg_id && logData.last_msg_id > hdLastMsgId) {
                        hdLastMsgId = logData.last_msg_id;
                    }
                })
                .catch(err => console.error("Client log error:", err));

        } catch (e) {
            console.error("Direct Gemini Call Failed:", e);
            hdRemoveTyping();
            const backup = hdGetClientBackupReply(msg, isForm);
            hdAddMsg('bot', backup.reply);
            if (backup.suggestions && backup.suggestions.length) {
                hdShowQuickReplies(backup.suggestions);
            }
        }
    }

    async function hdSendMessage(text, isSuggestion = false) {
        const input = document.getElementById('hd-chat-input');
        const sendBtn = document.getElementById('hd-send-btn');
        const msg = text || input.value.trim();
        if (!msg) return;

        input.value = '';
        document.getElementById('hd-quick-replies').innerHTML = '';
        hdAddMsg('user', msg);
        hdShowTyping();
        sendBtn.disabled = true;

        clearTimeout(hdTypingTimeout);
        if (hdSessionId) {
            fetch(`inquiry/get_chat_updates.php?session_id=${hdSessionId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ typing: false })
            }).catch(() => {});
        }

        if (isSuggestion) hdAdminJoined = false;

        try {
            const res = await fetch('inquiry/ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: msg, history: hdHistory.slice(-10), session_id: hdSessionId,
                    customer_name: window.currentFullName || 'Guest', customer_email: window.currentUserEmail || '',
                    is_suggestion: isSuggestion, source: 'index'
                })
            });
            const data = await res.json();

            if (data.status === 'needs_client_call') {
                await hdCallGeminiDirectly(msg, data, 'inquiry/ai_chat.php', false);
                return;
            }

            hdRemoveTyping();

            if (data.status === 'muted') return;

            if (data.reply) {
                hdAddMsg('bot', data.reply, false, data.last_msg_id);
                if (data.last_msg_id && data.last_msg_id > hdLastMsgId) hdLastMsgId = data.last_msg_id;
                if (data.suggestions && data.suggestions.length) hdShowQuickReplies(data.suggestions);
            } else {
                hdAddMsg('bot', "Sorry, I couldn't get a response right now. Please try again or contact us directly at <strong>0945 776 4140</strong>. 😊");
            }
        } catch (e) {
            hdRemoveTyping();
            if (!hdAdminJoined) {
                const backup = hdGetClientBackupReply(msg, false);
                hdAddMsg('bot', backup.reply);
                if (backup.suggestions && backup.suggestions.length) {
                    hdShowQuickReplies(backup.suggestions);
                }
            }
        } finally {
            sendBtn.disabled = false;
        }
    }

    function hdShowAgentJoinedBanner() {
        if (hdAdminJoined) return;
        hdAdminJoined = true;
        const container = document.getElementById('hd-messages');
        if (container.querySelector('.hd-agent-notice')) return;
        const notice = document.createElement('div');
        notice.className = 'hd-agent-notice';
        notice.innerHTML = `<span class="hd-agent-notice-line"></span><span class="hd-agent-notice-text"><i class="fas fa-user-check"></i> A live travel agent has joined!</span><span class="hd-agent-notice-line"></span>`;
        container.appendChild(notice);
        document.getElementById('hd-quick-replies').innerHTML = '';
    }

    async function hdInitLastMsgId() {
        try {
            const res = await fetch(`inquiry/get_chat_updates.php?session_id=${hdSessionId}&init=1`);
            const data = await res.json();
            if (data.success) {
                if (data.max_id) hdLastMsgId = data.max_id;
                if (data.messages && data.messages.length > 0) {
                    document.getElementById('hd-messages').innerHTML = '';
                    data.messages.forEach(msg => {
                        let role = 'bot';
                        if (msg.sender === 'customer') role = 'user';
                        else if (msg.sender === 'admin') role = 'admin';

                        if (msg.sender === 'system' && msg.message === '[AGENT_JOINED]') { hdShowAgentJoinedBanner(); return; }
                        if (msg.message === '[GREETING]' || msg.message === 'Live Agent requested') return;
                        if (msg.sender === 'admin') hdAdminJoined = true;
                        hdAddMsg(role, msg.message, true, msg.id);
                    });
                } else hdGreet();
            } else hdGreet();
        } catch (e) {
            hdGreet();
        } finally {
            setInterval(() => { if (hdSessionId) hdPollMessages(); }, 3000);
        }
    }
    hdInitLastMsgId();

    async function hdPollMessages() {
        try {
            const res = await fetch(`inquiry/get_chat_updates.php?session_id=${hdSessionId}&last_id=${hdLastMsgId}`);
            const data = await res.json();

            if (data.admin_is_typing) {
                hdShowAgentTyping();
            } else {
                hdRemoveAgentTyping();
            }

            if (data.success && data.messages.length > 0) {
                data.messages.forEach(m => {
                    if (m.sender === 'system' && m.message === '[AGENT_JOINED]') { hdShowAgentJoinedBanner(); hdLastMsgId = m.id; return; }
                    if (m.sender === 'admin' && !hdAdminJoined) hdShowAgentJoinedBanner();
                    if (m.sender === 'admin') hdRemoveAgentTyping();
                    const role = m.sender === 'customer' ? 'user' : (m.sender === 'admin' ? 'admin' : 'bot');
                    hdAddMsg(role, m.message, true, m.id);
                    hdLastMsgId = m.id;
                });
            }
        } catch (e) { }
    }

    function hdCustomConfirm(title, message, onConfirm) {
        const existing = document.getElementById('hdConfirmOverlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'hdConfirmOverlay';
        overlay.className = 'hd-confirm-overlay';
        overlay.innerHTML = `<div class="hd-confirm-card"><div class="hd-confirm-icon"><i class="fas fa-comments"></i></div><div class="hd-confirm-title">${title}</div><div class="hd-confirm-text">${message}</div><div class="hd-confirm-actions"><button class="hd-confirm-btn cancel" id="hdConfirmCancel">Cancel</button><button class="hd-confirm-btn confirm" id="hdConfirmOk">Start New</button></div></div>`;

        document.getElementById('hd-chat-window').appendChild(overlay);
        document.getElementById('hdConfirmCancel').onclick = e => { e.stopPropagation(); overlay.remove(); };
        document.getElementById('hdConfirmOk').onclick = e => { e.stopPropagation(); overlay.remove(); onConfirm(); };
    }

    // Bind UI Events
    document.getElementById('hd-chat-bubble').onclick = hdToggleChat;
    document.getElementById('hd-chat-close-btn').onclick = hdToggleChat;
    document.getElementById('hd-menu-trigger').onclick = () => hdToggleMenu();
    document.getElementById('hd-menu-close-btn').onclick = () => hdToggleMenu(false);

    document.getElementById('hd-menu-live-agent').onclick = () => {
        hdToggleMenu(false);
        hdAddMsg('user', 'I want to speak to a Live Agent 🧑‍💼');
        hdShowTyping();
        fetch('inquiry/ai_chat.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: '[REQUEST_LIVE_AGENT]', session_id: hdSessionId, customer_name: window.currentFullName || 'Guest', customer_email: window.currentUserEmail || '', source: 'index' })
        }).then(res => res.json()).then(data => {
            hdRemoveTyping();
            if (data.reply) { hdAddMsg('bot', data.reply); if (data.suggestions) hdShowQuickReplies(data.suggestions); }
        }).catch(e => { hdRemoveTyping(); hdAddMsg('bot', "🔔 <strong>Live Agent Requested!</strong> I have notified our travel team. A representative will join the chat shortly! 😊"); });
    };

    document.getElementById('hd-menu-visit-website').onclick = () => {
        hdToggleMenu(false);
        hdAddMsg('bot', '🌟 <strong>You are already on our homepage!</strong> Feel free to explore our curated tour packages directly on this main page! 😊');
    };

    document.getElementById('hd-menu-report-issue').onclick = () => {
        hdToggleMenu(false);
        hdAddMsg('bot', "🔧 <strong>Report an Issue:</strong> I have opened our Support Page in a new tab for you to file a report. Alternatively, please describe your issue right here in the chat, and I'll make sure our agents see it! 👇");
        window.open('support.php', '_blank');
    };

    document.getElementById('hd-new-chat-btn').onclick = () => {
        hdCustomConfirm("Start New Chat?", "This will start a fresh session to get the immediate attention of our agents. Your current history will be cleared.", async () => {
            hdSessionId = 'hd_float_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            localStorage.setItem('hd_floating_chat_session_id', hdSessionId);
            document.getElementById('hd-messages').innerHTML = '';
            hdLastMsgId = 0; hdRenderedMsgIds.clear(); hdAdminJoined = false;
            hdAddMsg('user', "Started a new inquiry chat.", false, null);
            hdShowTyping();
            try {
                const res = await fetch('inquiry/ai_chat.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: "Started a new inquiry chat.", history: [], session_id: hdSessionId, customer_name: window.currentFullName || 'Guest', customer_email: window.currentUserEmail || '', is_suggestion: false, source: 'index' })
                });
                const data = await res.json();
                hdRemoveTyping();
                if (data && data.reply) hdAddMsg('bot', data.reply, false, data.last_msg_id);
            } catch (e) { hdRemoveTyping(); console.error("New Chat API error:", e); }
        });
    };

    document.getElementById('hd-chat-input').addEventListener('keydown', e => { if (e.key === 'Enter') hdSendMessage(); });

    // Notify the admin panel when the customer is typing
    let hdTypingTimeout;
    document.getElementById('hd-chat-input').addEventListener('input', () => {
        if (!hdSessionId) return;
        fetch(`inquiry/get_chat_updates.php?session_id=${hdSessionId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ typing: true })
        }).catch(() => {});

        clearTimeout(hdTypingTimeout);
        hdTypingTimeout = setTimeout(() => {
            fetch(`inquiry/get_chat_updates.php?session_id=${hdSessionId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ typing: false })
            }).catch(() => {});
        }, 2000);
    });
    document.getElementById('hd-send-btn').onclick = () => hdSendMessage();

    // Global helper for outside triggering
    window.toggleHeyDreamChatbot = hdToggleChat;
})();

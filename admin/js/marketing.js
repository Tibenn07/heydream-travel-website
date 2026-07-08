let activeBlockId = null;
let previewSortable = null;
let activeLinguisticMatches = [];
let activeViewingCampaignId = null;
let activeUnlocks = []; // Array of block types current user is allowed to edit

document.addEventListener('DOMContentLoaded', () => {
    // Persistent Section Logic: Restore from hash or localStorage
    const savedSection = localStorage.getItem('active_admin_section');
    const hash = window.location.hash.substring(1);
    const targetSection = hash || savedSection || 'dashboard';
    
    if (targetSection) {
        switchSection(targetSection);
    }

    // Initialize seen count from server-rendered badge
    const initialBadge = document.getElementById('inq-badge');
    if (initialBadge) {
        initialBadge.dataset.count = INITIAL_PENDING_INQUIRIES;
        // If page loaded on inquiries tab, hide immediately
        if (hash === 'inquiries') {
            initialBadge.style.display = 'none';
            sessionStorage.setItem('inq_seen_count', INITIAL_PENDING_INQUIRIES);
        }
    }
    sessionStorage.setItem('inq_seen_count', sessionStorage.getItem('inq_seen_count') || INITIAL_PENDING_INQUIRIES);

    // Poll for new pending inquiries every 30 seconds
    setInterval(pollInquiryBadge, 30000);

    // Start real-time dashboard polling (every 10s)
    startDashboardPolling();

    // Poll for AI chat badge every 15 seconds
    pollAIChatBadge();
    setInterval(pollAIChatBadge, 15000);

    // Check for active unlocks for the current user
    fetchMyUnlocks();

    // If super admin, poll for new unlock requests
    if (typeof currentAdminRole !== 'undefined' && currentAdminRole === 'super_admin') {
        pollUnlockRequests();
        setInterval(pollUnlockRequests, 20000); // Check every 20s
    }

    // Inject AI Loader CSS
    if (!document.getElementById('ai-loader-style')) {
        const style = document.createElement('style');
        style.id = 'ai-loader-style';
        style.innerHTML = `
            .ai-loader {
                width: 40px;
                height: 40px;
                border: 3px solid #003580;
                border-top-color: transparent;
                border-radius: 50%;
                animation: ai-spin 0.8s linear infinite;
            }
            @keyframes ai-spin {
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    // Section Switching Logic
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            const target = item.getAttribute('data-target');
            switchSection(target);
        });
    });

    // Initialize Sortable for Builder Canvas
    const canvas = document.getElementById('builder-canvas');
    if (canvas) {
        new Sortable(canvas, {
            group: {
                name: 'builder',
                pull: true,
                put: true
            },
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            // Removed handle restriction to allow dragging from anywhere
            onEnd: () => {
                updatePreview();
            },
            onAdd: (evt) => {
                const type = evt.item.dataset.type;
                evt.item.remove();
                addBlockAt(type, evt.newIndex);
            }
        });
    }

    // Initialize Sortable for Blocks Grid (Source)
    const blocksGrid = document.querySelector('.blocks-grid');
    if (blocksGrid) {
        new Sortable(blocksGrid, {
            group: {
                name: 'builder',
                pull: 'clone',
                put: false
            },
            sort: false,
            animation: 150
        });
    }

    // Event Delegation for Blocks
    if (canvas) {
        canvas.addEventListener('click', (e) => {
            const block = e.target.closest('.canvas-block');
            if (!block) return;

            const blockId = block.id;

            // Handle Delete
            if (e.target.closest('.remove-block')) {
                block.remove();
                updatePreview();
                return;
            }

            // Handle Duplicate
            if (e.target.closest('.duplicate-block')) {
                duplicateBlock(blockId);
                return;
            }

            // Handle Open Properties
            openProperties(blockId);
        });
    }

    // Load persisted state or default blocks
    loadPersistedState();
    
    // Start real-time dashboard polling
    startDashboardPolling();
});

function switchSection(id) {
    if (!id) id = 'dashboard';
    
    // Hide all sections
    document.querySelectorAll('.section-container').forEach(sec => {
        sec.classList.remove('active');
        sec.style.display = 'none';
    });

    // Show target section
    const targetSection = document.getElementById(id);
    if (targetSection) {
        targetSection.classList.add('active');
        targetSection.style.display = 'block';
    }

    // Handle sidebar menu active state — email-message belongs to the emails group
    const sidebarTarget = ['emails', 'analytics', 'templates', 'email-message'].includes(id) ? 'emails' : id;
    document.querySelectorAll('.menu-item').forEach(m => {
        m.classList.remove('active');
        if (m.getAttribute('data-target') === sidebarTarget) {
            m.classList.add('active');
        }
    });

    // Handle tab active state (Email Builder sub-nav)
    document.querySelectorAll('.nav-tab').forEach(t => {
        t.classList.remove('active');
        if (t.getAttribute('onclick')?.includes(`'${id}'`)) {
            t.classList.add('active');
        }
    });

    // Persist state
    localStorage.setItem('active_admin_section', id);
    window.location.hash = id;

    // Trigger section-specific loading
    if (id === 'analytics') {
        initAnalytics();
    } else if (id === 'templates') {
        loadTemplatesList();
    } else if (id === 'inquiries') {
        // Hide inquiry badge when user navigates TO inquiries
        const badge = document.getElementById('inq-badge');
        if (badge) badge.style.display = 'none';
        const count = parseInt(badge?.dataset.count || '0');
        sessionStorage.setItem('inq_seen_count', count);
    } else if (id === 'ai-chats') {
        loadChatSessions();
    } else if (id === 'reported-issues') {
        loadReportedIssues();
        const badge = document.getElementById('reported-issues-badge');
        if (badge) badge.style.display = 'none';
    } else if (id === 'email-message') {
        initMsgBuilder();
    } else if (id === 'vouchers') {
        if (typeof fetchVouchers === 'function') fetchVouchers();
    }
}

// ── AI CHAT MANAGEMENT ───────────────────────────────────────
let activeChatSessionId = null;
let activeChatCustomerName = 'Customer';
let chatPollingInterval = null;

function parseMySQLDate(dateStr) {
    if (!dateStr) return new Date();
    // Replace space with T for cross-browser ISO standard compatibility
    let t = dateStr.toString().replace(' ', 'T');
    let d = new Date(t);
    if (isNaN(d.getTime())) {
        d = new Date(dateStr);
    }
    return isNaN(d.getTime()) ? new Date() : d;
}

function renderChatSessionsList(sessions) {
    const list = document.getElementById('ai-sessions-list');
    if (!list) return;

    if (sessions.length === 0) {
        list.innerHTML = '<div class="empty-state"><i class="fas fa-robot"></i><p>No chat sessions found yet.</p></div>';
        return;
    }

    list.innerHTML = sessions.map(s => {
        const isUnread = parseInt(s.unread_count || '0') > 0;
        const unreadClass = isUnread ? 'unread' : 'read';
        
        let lastMsgText = s.last_message || 'No messages yet';
        if (lastMsgText.length > 40) {
            lastMsgText = lastMsgText.substring(0, 37) + '...';
        }

        return `
            <div class="chat-session-item ${unreadClass} ${activeChatSessionId === s.session_id ? 'active' : ''}" onclick="viewChat('${s.session_id}', '${s.customer_name}')" data-session-id="${s.session_id}">
                <div class="session-avatar">${s.customer_name.charAt(0).toUpperCase()}</div>
                <div class="session-info" style="flex: 1; min-width: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                        <h4 style="margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;">${s.customer_name || 'Guest'}</h4>
                        ${isUnread ? `<span class="session-unread-badge" style="background: #ef4444; color: white; border-radius: 50%; min-width: 18px; height: 18px; padding: 2px 5px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; line-height: 1; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">${s.unread_count}</span>` : ''}
                    </div>
                    <p class="session-last-msg" style="margin: 4px 0 0; font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${lastMsgText}</p>
                    <p style="font-size: 0.62rem; opacity: 0.7; margin: 2px 0 0;">Last active: ${parseMySQLDate(s.last_activity).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                </div>
                <div class="session-status-dot ${s.status === 'active' || s.status === 'taken_over' ? 'online' : ''}"></div>
            </div>
        `;
    }).join('');
}

async function loadChatSessions() {
    try {
        const res = await fetch('ai_chat_admin.php?action=get_sessions');
        const data = await res.json();
        if (data.success) {
            renderChatSessionsList(data.sessions);
        }
    } catch (e) {
        console.error("Load sessions error:", e);
    }
}

async function viewChat(sessionId, name) {
    activeChatSessionId = sessionId;
    activeChatCustomerName = name || 'Customer';
    
    // UI Updates
    document.querySelectorAll('.chat-session-item').forEach(i => i.classList.remove('active'));
    const item = document.querySelector(`.chat-session-item[data-session-id="${sessionId}"]`);
    if (item) {
        item.classList.add('active');
        item.classList.remove('unread');
        item.classList.add('read');
        const badge = item.querySelector('.session-unread-badge');
        if (badge) badge.remove();
    }

    document.getElementById('active-chat-user').textContent = activeChatCustomerName;
    document.getElementById('active-chat-status').textContent = 'Loading conversation...';
    document.getElementById('admin-chat-input').disabled = false;
    document.getElementById('admin-send-btn').disabled = false;
    document.getElementById('takeover-btn').style.display = 'block';
    document.getElementById('transfer-btn').style.display = 'block';
    document.getElementById('delete-chat-btn').style.display = 'block';

    // Clear message count cache for this session to force immediate rendering
    if (window.activeChatMsgCounts) {
        delete window.activeChatMsgCounts[sessionId];
    }

    await loadChatMessages(sessionId);
    
    // Refresh sessions and badge count instantly
    await loadChatSessions();
    await pollAIChatBadge();

    // Start Polling for this specific chat
    if (chatPollingInterval) clearInterval(chatPollingInterval);
    chatPollingInterval = setInterval(() => loadChatMessages(sessionId), 3000);
}

async function loadChatMessages(sessionId) {
    const container = document.getElementById('admin-chat-messages');
    if (!container) return;

    try {
        const res = await fetch(`ai_chat_admin.php?action=get_messages&session_id=${sessionId}`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('active-chat-status').textContent = 'Active Conversation';
            
            // Only update if message count changed to avoid flickering
            if (!window.activeChatMsgCounts) {
                window.activeChatMsgCounts = {};
            }
            if (window.activeChatMsgCounts[sessionId] === data.messages.length) {
                return;
            }
            window.activeChatMsgCounts[sessionId] = data.messages.length;

            container.innerHTML = data.messages.map(m => {
                // Render system notifications as a centered separator
                if (m.sender === 'system') {
                    if (m.message === '[AGENT_JOINED]') {
                        return `<div style="text-align:center; margin: 10px 0; font-size:0.72rem; color:#64748b; font-style:italic; display:flex; align-items:center; gap:8px; justify-content:center;">
                            <span style="flex:1; height:1px; background:#e2e8f0;"></span>
                            <span><i class="fas fa-user-check" style="color:#003580; margin-right:4px;"></i>Live Agent joined the chat</span>
                            <span style="flex:1; height:1px; background:#e2e8f0;"></span>
                        </div>`;
                    }
                    return ''; // skip other system messages
                }

                let senderLabel = '';
                let icon = '';
                if (m.sender === 'customer') {
                    senderLabel = activeChatCustomerName;
                    icon = '<i class="fas fa-user" style="font-size: 0.75rem; margin-right: 5px; color: #475569;"></i>';
                } else if (m.sender === 'ai') {
                    senderLabel = 'Dream (AI)';
                    icon = '<i class="fas fa-robot" style="font-size: 0.75rem; margin-right: 5px; color: #3b82f6;"></i>';
                } else if (m.sender === 'admin') {
                    senderLabel = 'Agent';
                    icon = '<i class="fas fa-user-tie" style="font-size: 0.75rem; margin-right: 5px; color: #ffffff;"></i>';
                }

                return `
                    <div class="chat-bubble ${m.sender}">
                        <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; display: flex; align-items: center; opacity: 0.75;">
                            ${icon} ${senderLabel}
                        </div>
                        <div style="word-break: break-word;">${m.message}</div>
                        <span class="bubble-time">${parseMySQLDate(m.created_at || m.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
            }).join('');
            container.scrollTop = container.scrollHeight;
        }
    } catch (e) {
        console.error("Load messages error:", e);
    }
}

async function sendAdminReply() {
    const input = document.getElementById('admin-chat-input');
    const msg = input.value.trim();
    if (!msg || !activeChatSessionId) return;

    input.value = '';
    input.disabled = true;

    try {
        const res = await fetch('ai_chat_admin.php?action=send_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: activeChatSessionId, message: msg })
        });
        const data = await res.json();
        if (data.success) {
            loadChatMessages(activeChatSessionId);
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to send message.', 'error');
    } finally {
        input.disabled = false;
        input.focus();
    }
}

async function takeoverChat() {
    if (!activeChatSessionId) return;
    
    try {
        const res = await fetch(`ai_chat_admin.php?action=takeover&session_id=${activeChatSessionId}`);
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                title: 'Chat Taken Over',
                text: 'The AI is now muted for this session. You can chat freely with the customer.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            document.getElementById('active-chat-icon').className = 'fas fa-user-tie';
            document.getElementById('active-chat-icon').style.color = '#003580';
        }
    } catch (e) {
        console.error("Takeover error:", e);
    }
}

async function openTransferModal() {
    if (!activeChatSessionId) return;

    Swal.fire({
        title: 'Fetching Sales Agents...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const res = await fetch('ai_chat_admin.php?action=get_sales_agents');
        const data = await res.json();
        
        if (!data.success || !data.agents || data.agents.length === 0) {
            Swal.fire({
                title: 'No Sales Agents Available',
                text: 'There are no active Sales agents registered in the database to transfer this chat to.',
                icon: 'info',
                confirmButtonColor: '#003580'
            });
            return;
        }

        const inputOptions = {};
        data.agents.forEach(agent => {
            inputOptions[agent.id] = `${agent.full_name} (${agent.email})`;
        });

        Swal.close();

        const { value: agentId } = await Swal.fire({
            title: 'Select Sales Agent',
            input: 'select',
            inputOptions: inputOptions,
            inputPlaceholder: '-- Select a Sales Agent --',
            showCancelButton: true,
            confirmButtonColor: '#003580',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Select Agent',
            inputValidator: (value) => {
                return new Promise((resolve) => {
                    if (value) {
                        resolve();
                    } else {
                        resolve('You must select a Sales agent to transfer the chat!');
                    }
                });
            }
        });

        if (agentId) {
            const selectedAgent = data.agents.find(a => a.id == agentId);
            confirmAgentTransfer(selectedAgent.id, selectedAgent.full_name);
        }

    } catch (e) {
        console.error("Fetch agents error:", e);
        Swal.fire('Error', 'Failed to retrieve Sales agents.', 'error');
    }
}

async function confirmAgentTransfer(agentId, agentName) {
    const result = await Swal.fire({
        title: 'Confirm Transfer',
        text: `Are you sure you want to transfer this chat to ${agentName}? They will receive an email confirmation about it.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#003580',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, transfer chat'
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Transferring Chat...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const res = await fetch('ai_chat_admin.php?action=transfer_chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: activeChatSessionId, agent_id: agentId })
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    title: 'Chat Transferred!',
                    text: `${agentName} has been assigned. An email notification has been successfully sent to them.`,
                    icon: 'success',
                    confirmButtonColor: '#003580'
                });
                loadChatMessages(activeChatSessionId);
            } else {
                Swal.fire('Error', data.message || 'Failed to transfer chat.', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'An unexpected error occurred during transfer.', 'error');
        }
    }
}

async function deleteChat() {
    if (!activeChatSessionId) return;

    const result = await Swal.fire({
        title: 'Delete Chat Session?',
        text: "This will permanently delete the entire conversation history. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it'
    });

    if (result.isConfirmed) {
        try {
            const res = await fetch(`ai_chat_admin.php?action=delete_session&session_id=${activeChatSessionId}`);
            const data = await res.json();
            if (data.success) {
                Swal.fire('Deleted!', 'The chat session has been removed.', 'success');
                
                // Reset UI
                activeChatSessionId = null;
                if (chatPollingInterval) clearInterval(chatPollingInterval);
                
                document.getElementById('active-chat-user').textContent = 'Select a Chat';
                document.getElementById('active-chat-status').textContent = 'No active session';
                document.getElementById('admin-chat-messages').innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <p>Select a session from the list to view or join the conversation.</p>
                    </div>
                `;
                document.getElementById('admin-chat-input').disabled = true;
                document.getElementById('admin-send-btn').disabled = true;
                document.getElementById('takeover-btn').style.display = 'none';
                document.getElementById('transfer-btn').style.display = 'none';
                document.getElementById('delete-chat-btn').style.display = 'none';
                
                loadChatSessions();
            }
        } catch (e) {
            Swal.fire('Error', 'Failed to delete session.', 'error');
        }
    }
}

async function pollAIChatBadge() {
    const badge = document.getElementById('ai-chat-badge');
    if (!badge) return;

    try {
        const res = await fetch('ai_chat_admin.php?action=get_sessions');
        const data = await res.json();

        if (data.success) {
            // Count sessions with unread messages
            const unreadCount = data.sessions.filter(s => parseInt(s.unread_count || '0') > 0).length;

            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }

            // If the user is currently on the AI Live Chats section, keep the sessions list updated in real-time
            const aiChatsSection = document.getElementById('ai-chats');
            if (aiChatsSection && aiChatsSection.classList.contains('active')) {
                renderChatSessionsList(data.sessions);
            }
        }
    } catch (e) {}
}

async function loadTemplatesList() {
    const list = document.getElementById('template-list');
    if (!list) return;

    list.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading templates...</div>';

    try {
        const res = await fetch('api/marketing_api.php?action=get_templates');
        const data = await res.json();

        if (data.success) {
            if (data.templates.length === 0) {
                list.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; border: 2px dashed #e2e8f0; border-radius: 20px; color: var(--text-muted);">
                        <i class="fas fa-layer-group" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                        <h4 style="margin: 0; color: #64748b;">No Templates Saved Yet</h4>
                        <p style="font-size: 0.9rem; margin-top: 5px;">Save your builder designs as templates to reuse them later.</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    ${data.templates.map(tpl => `
                        <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%; border: 1px solid #e2e8f0; border-radius: 16px; transition: all 0.3s ease;">
                            <div style="padding: 20px; flex: 1;">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 15px;">
                                    <div style="width: 40px; height: 40px; background: #e0f2fe; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #0369a1;">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">${new Date(tpl.created_at).toLocaleDateString()}</span>
                                </div>
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: var(--primary);">${tpl.name}</h4>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Last edited recently</p>
                            </div>
                            <div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; gap: 8px;">
                                <button class="btn btn-outline btn-sm" style="flex: 1; padding: 8px; border-color: #003580; color: #003580; background: #fff;" onclick="previewTemplate(${tpl.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-primary btn-sm" style="flex: 1; padding: 8px;" onclick="useTemplate(${tpl.id})">
                                    <i class="fas fa-pencil-alt"></i> Edit
                                </button>
                                <button class="btn btn-outline btn-sm" style="padding: 8px; color: #ef4444; border-color: #fecaca; background: #fff;" onclick="deleteTemplate(${tpl.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
    } catch (e) {
        list.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load templates.</div>';
    }
}

async function previewTemplate(id) {
    try {
        Swal.fire({ title: 'Loading Preview...', didOpen: () => Swal.showLoading() });
        const res = await fetch(`api/marketing_api.php?action=get_templates`);
        const data = await res.json();
        const tpl = data.templates.find(t => t.id == id);
        
        if (!tpl) return Swal.fire('Error', 'Template not found.', 'error');
        
        const blocks = typeof tpl.body === 'string' ? JSON.parse(tpl.body) : tpl.body;
        let previewHtml = '';
        
        blocks.forEach(b => {
            if (b.type === 'header') previewHtml += `<div style="padding: 30px; text-align: center; background: #003580; color: white;"><h2 style="margin: 0;">${b.text}</h2></div>`;
            else if (b.type === 'text') previewHtml += `<div style="padding: 20px; color: #475569; line-height: 1.6;">${b.text.replace(/\n/g, '<br>')}</div>`;
            else if (b.type === 'image') previewHtml += `<div style="padding: 10px; text-align: center;"><img src="${b.url}" style="max-width: 100%; border-radius: 8px;"></div>`;
            else if (b.type === 'button') previewHtml += `<div style="padding: 20px; text-align: center;"><a href="${b.url}" style="background: #003580; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; display: inline-block;">${b.text}</a></div>`;
            else if (b.type === 'divider') previewHtml += `<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">`;
        });

        Swal.fire({
            title: tpl.name,
            width: '600px',
            html: `
                <div style="background: #f1f5f9; padding: 20px; border-radius: 12px; max-height: 500px; overflow-y: auto; text-align: left;">
                    <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        ${previewHtml}
                    </div>
                </div>
            `,
            confirmButtonText: 'Close',
            confirmButtonColor: '#64748b'
        });
    } catch (e) {
        Swal.fire('Error', 'Failed to load preview.', 'error');
    }
}

async function useTemplate(id) {
    const result = await Swal.fire({
        title: 'Load Template?',
        text: "This will replace your current design with the selected template. Any unsaved changes will be lost.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, load it',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            const res = await fetch(`api/marketing_api.php?action=get_templates`);
            const data = await res.json();
            const template = data.templates.find(t => t.id == id);
            
            if (template) {
                // Template blocks are stored in the 'body' column as JSON
                const blocksData = typeof template.body === 'string' ? JSON.parse(template.body) : template.body;
                
                const canvas = document.getElementById('builder-canvas');
                canvas.innerHTML = '';
                
                blocksData.forEach(block => {
                    addBlock(block.type, block.text, block);
                    // Inject Global Audit Styles for High Visibility
        if (!document.getElementById('audit-global-styles')) {
            const style = document.createElement('style');
            style.id = 'audit-global-styles';
            style.innerHTML = `
                .preview-lt-highlight {
                    border-bottom: 2px wavy #ef4444 !important;
                    background-color: #fee2e2 !important;
                    color: #b91c1c !important;
                    font-weight: 700 !important;
                    text-decoration: none !important;
                    display: inline-block !important;
                    line-height: 1.2 !important;
                    border-radius: 2px !important;
                    cursor: help !important;
                }
            `;
            document.head.appendChild(style);
        }
    });
                
                switchSection('emails');
                Swal.fire({ icon: 'success', title: 'Template Loaded', timer: 1500, showConfirmButton: false });
            }
        } catch (e) {
            Swal.fire('Error', 'Failed to load template.', 'error');
        }
    }
}

async function deleteTemplate(id) {
    const result = await Swal.fire({
        title: 'Delete Template?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    });

    if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'delete_template');
        formData.append('id', id);
        
        try {
            const r = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
            const data = await r.json();
            if (data.success) {
                loadTemplatesList();
                Swal.fire('Deleted!', 'Template removed.', 'success');
            }
        } catch (e) {
            Swal.fire('Error', 'Failed to delete template.', 'error');
        }
    }
}

function loadPersistedState() {
    const savedBlocks = localStorage.getItem('heydream_draft_blocks');
    const savedSubject = localStorage.getItem('heydream_draft_subject');
    
    if (savedSubject) {
        const subjectInput = document.getElementById('email-subject');
        if (subjectInput) subjectInput.value = savedSubject;
    }

    if (savedBlocks) {
        try {
            const blocks = JSON.parse(savedBlocks).filter(b => !b.id || !b.id.startsWith('msg-block-'));
            const canvas = document.getElementById('builder-canvas');
            canvas.innerHTML = ''; // Clear defaults
            blocks.forEach(b => {
                addBlock(b.type, b.text, b);
            });
        } catch(e) {
            loadDefaultBlocks();
        }
    } else {
        loadDefaultBlocks();
    }
    updatePreview();
}

function loadDefaultBlocks() {
    addBlock('header', 'HeyDream Travel');
    addBlock('text', 'Discover Your Dream Destination');
    addBlock('image', 'https://via.placeholder.com/600x300?text=Hero+Image');
    addBlock('button', 'Book Now');
    addBlock('footer', 'You received this email because you expressed interest in travel with HeyDream.');
}

function persistState() {
    const blocksData = getBlocksData();
    localStorage.setItem('heydream_draft_blocks', JSON.stringify(blocksData));
    const subjectInput = document.getElementById('email-subject');
    if (subjectInput) {
        localStorage.setItem('heydream_draft_subject', subjectInput.value);
    }
}



async function loadFullCampaignHistory() {
    const tbody = document.getElementById('full-history-table-body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">Loading campaign history...</td></tr>';

    try {
        const response = await fetch('api/marketing_api.php?action=get_all_campaigns');
        const res = await response.json();
        
        if (res.success) {
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No campaign history found.</td></tr>';
                return;
            }

            tbody.innerHTML = res.data.map(c => {
                const sent = parseInt(c.sent_count || 0);
                const open = parseInt(c.open_count || 0);
                const rate = sent > 0 ? (open / sent * 100).toFixed(1) : '0.0';
                const date = new Date(c.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                return `
                    <tr>
                        <td><strong>${c.subject || 'No Subject'}</strong></td>
                        <td>${sent.toLocaleString()} Recipients</td>
                        <td>${rate}%</td>
                        <td><span class="status-badge status-confirmed">${c.status || 'Sent'}</span></td>
                        <td>${date}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-outline btn-sm" onclick="viewCampaign(${c.id})" title="View Details"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-outline btn-sm" onclick="deleteCampaign(${c.id})" style="color: #ef4444; border-color: #fecaca;" title="Delete Log"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #ef4444;">Failed to load history.</td></tr>';
    }
}




async function startNewTemplate() {
    const result = await Swal.fire({
        title: 'Start Fresh?',
        text: "This will clear your current design draft.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Start New',
        cancelButtonText: 'Keep Current'
    });

    if (result.isConfirmed) {
        localStorage.removeItem('heydream_draft_blocks');
        localStorage.removeItem('heydream_draft_subject');
        const canvas = document.getElementById('builder-canvas');
        canvas.innerHTML = '';
        loadDefaultBlocks();
        updatePreview();
    }
    switchSection('emails');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Copied ${text}`,
            showConfirmButton: false,
            timer: 1500
        });
    });
}

// DYNAMIC BLOCK BUILDER LOGIC
function addBlock(type, defaultValue = '', savedData = null) {
    addBlockAt(type, null, defaultValue, savedData);
}

function addBlockAt(type, index = null, defaultValue = '', savedData = null) {
    const canvas = document.getElementById('builder-canvas');
    const blockId = 'block-' + Date.now() + Math.random().toString(36).substr(2, 5);
    const div = document.createElement('div');
    div.className = 'canvas-block';
    div.dataset.type = type;
    div.id = blockId;

    let icon = '';
    let defaultName = '';
    
    // Apply saved data or defaults
    const d = savedData || {};

    if (type === 'header') {
        icon = '<i class="fas fa-heading"></i>';
        defaultName = 'Header';
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <input type="hidden" class="color-input" value="${d.color || '#ffffff'}">
            <input type="hidden" class="bg-input" value="${d.bg || '#003580'}">
            <input type="hidden" class="header-text" value="${defaultValue || 'Your Brand'}">
        `;
    } else if (type === 'text') {
        icon = '<i class="fas fa-font"></i>';
        defaultName = 'Text';
        if (d.weight === '700') div.classList.add('is-bold');
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'left'}">
            <input type="hidden" class="size-input" value="${d.size || '16'}">
            <input type="hidden" class="color-input" value="${d.color || '#64748b'}">
            <textarea style="display:none;" class="text-content">${defaultValue}</textarea>
        `;
    } else if (type === 'image') {
        icon = '<i class="fas fa-image"></i>';
        defaultName = 'Image';
        if (d.capWeight === '700') div.classList.add('is-caption-bold');
        settingsHtml = `
            <input type="hidden" class="image-url" value="${d.url || defaultValue || 'https://via.placeholder.com/600x300?text=Hero+Image'}">
            <input type="hidden" class="width-input" value="${d.width || '100'}">
            <input type="hidden" class="radius-input" value="${d.radius || '8'}">
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <textarea style="display:none;" class="caption-text">${d.caption || ''}</textarea>
            <input type="hidden" class="caption-size" value="${d.capSize || '14'}">
            <input type="hidden" class="caption-color" value="${d.capColor || '#64748b'}">
        `;
    } else if (type === 'button') {
        icon = '<i class="fas fa-link"></i>';
        defaultName = 'Button';
        if (d.weight === '700') div.classList.add('is-bold');
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <input type="hidden" class="btn-text" value="${defaultValue || 'Click Here'}">
            <input type="hidden" class="btn-link" value="${d.link || ''}">
            <input type="hidden" class="color-input" value="${d.color || '#ffffff'}">
            <input type="hidden" class="bg-input" value="${d.bg || '#003580'}">
            <input type="hidden" class="size-input" value="${d.size || '16'}">
            <input type="hidden" class="width-input" value="${d.width || 'auto'}">
            <input type="hidden" class="padding-input" value="${d.padding || '12'}">
        `;
    } else if (type === 'footer') {
        icon = '<i class="fas fa-shoe-prints"></i>';
        defaultName = 'Footer';
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <textarea style="display:none;" class="footer-text">${defaultValue || 'You received this email because you expressed interest in travel with HeyDream.'}</textarea>
        `;
    } else if (type === 'divider') {
        icon = '<i class="fas fa-minus"></i>';
        defaultName = 'Divider';
    }

    const customLabel = d.custom_label || '';

    const isRestrictedRole = typeof currentAdminRole !== 'undefined' && currentAdminRole !== 'super_admin';
    const isUnlocked = activeUnlocks.includes(type);
    const isRestrictedBlock = (type === 'header' || type === 'footer') && !isUnlocked;

    div.innerHTML = `
        <div class="block-summary" style="display: flex; justify-content: space-between; align-items: center; pointer-events: none;">
            <div class="visible-label" style="font-weight: 600; font-size: 0.85rem;">
                ${icon} <span class="default-name">${defaultName}</span><span class="label-separator">${customLabel ? ': ' : ''}</span><span class="label-text" style="font-weight: 400; opacity: 0.8;">${customLabel}</span>
            </div>
            ${isRestrictedRole && isRestrictedBlock ? `
            <div style="display: flex; gap: 12px; align-items: center;">
                <span title="Locked by Super Admin" style="color: #ef4444;"><i class="fas fa-lock"></i></span>
            </div>
            ` : `
            <div style="display: flex; gap: 12px; align-items: center; pointer-events: auto;">
                <span class="duplicate-block" title="Duplicate" style="cursor: pointer;"><i class="fas fa-copy"></i></span>
                <span class="remove-block" title="Delete" style="cursor: pointer;"><i class="fas fa-trash"></i></span>
            </div>
            `}
        </div>
        <input type="hidden" class="block-label-input" value="${customLabel}">
        ${settingsHtml}
    `;

    if (index !== null && canvas.children[index]) {
        canvas.insertBefore(div, canvas.children[index]);
    } else {
        canvas.appendChild(div);
    }
    
    updatePreview();
}

function duplicateBlock(blockId) {
    const original = document.getElementById(blockId);
    if (!original) return;
    
    const type = original.dataset.type;
    const data = {
        custom_label: original.querySelector('.block-label-input').value
    };
    
    if (type === 'header') {
        data.text = original.querySelector('.header-text').value;
        data.color = original.querySelector('.color-input').value;
        data.bg = original.querySelector('.bg-input').value;
        data.align = original.querySelector('.align-input').value;
    } else if (type === 'text') {
        data.text = original.querySelector('.text-content').value;
        data.size = original.querySelector('.size-input').value;
        data.color = original.querySelector('.color-input').value;
        data.align = original.querySelector('.align-input').value;
        data.weight = original.classList.contains('is-bold') ? '700' : '400';
    } else if (type === 'image') {
        data.url = original.querySelector('.image-url').value;
        data.width = original.querySelector('.width-input').value;
        data.radius = original.querySelector('.radius-input').value;
        data.align = original.querySelector('.align-input').value;
        data.caption = original.querySelector('.caption-text').value;
        data.capSize = original.querySelector('.caption-size').value;
        data.capColor = original.querySelector('.caption-color').value;
        data.capWeight = original.classList.contains('is-caption-bold') ? '700' : '400';
    } else if (type === 'button') {
        data.text = original.querySelector('.btn-text').value;
        data.link = original.querySelector('.btn-link').value;
        data.color = original.querySelector('.color-input').value;
        data.bg = original.querySelector('.bg-input').value;
        data.size = original.querySelector('.size-input').value;
        data.padding = original.querySelector('.padding-input').value;
        data.width = original.querySelector('.width-input').value;
        data.weight = original.classList.contains('is-bold') ? '700' : '600';
        data.align = original.querySelector('.align-input').value;
    } else if (type === 'footer') {
        data.text = original.querySelector('.footer-text').value;
        data.align = original.querySelector('.align-input').value;
    }
    
    addBlock(type, data.text || '', data);
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Block Duplicated',
        showConfirmButton: false,
        timer: 1500
    });
}

function insertBulletPoint(textareaId) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    
    const bullet = "• ";
    const startPos = textarea.selectionStart;
    const endPos = textarea.selectionEnd;
    
    // Check if we are at the start of the line or if we need a newline
    const value = textarea.value;
    let insertText = bullet;
    if (startPos > 0 && value.charAt(startPos - 1) !== '\n') {
        insertText = "\n" + bullet;
    }

    textarea.value = value.substring(0, startPos) + insertText + value.substring(endPos, value.length);
    
    // Move cursor to after the bullet
    textarea.selectionStart = startPos + insertText.length;
    textarea.selectionEnd = startPos + insertText.length;
    textarea.focus();
    
    // Trigger the update
    updateBlockData('.caption-text', textarea.value);
}

function openProperties(blockId) {
    activeBlockId = blockId;
    const block = document.getElementById(blockId);
    if (!block) return;
    const type = block.dataset.type;
    const propertiesContent = document.getElementById('properties-content');
    
    document.getElementById('canvas-section').style.display = 'none';
    document.getElementById('properties-section').style.display = 'block';

    let html = `
        <div class="form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 24px;">
            <label style="color: var(--primary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Block Display Name</label>
            <input type="text" class="block-input" value="${block.querySelector('.block-label-input').value}" 
                oninput="updateBlockLabel(this.value)" placeholder="Enter block name...">
        </div>
    `;
    const getAlignHtml = (current) => `
        <div class="form-group">
            <label>Alignment</label>
            <div class="align-group">
                <button class="btn-icon ${current === 'left' ? 'active' : ''}" onclick="updateBlockAlign('left')"><i class="fas fa-align-left"></i></button>
                <button class="btn-icon ${current === 'center' ? 'active' : ''}" onclick="updateBlockAlign('center')"><i class="fas fa-align-center"></i></button>
                <button class="btn-icon ${current === 'right' ? 'active' : ''}" onclick="updateBlockAlign('right')"><i class="fas fa-align-right"></i></button>
            </div>
        </div>
    `;

    const isRestrictedRole = typeof currentAdminRole !== 'undefined' && currentAdminRole !== 'super_admin';
    const isUnlocked = activeUnlocks.includes(type);
    const isRestrictedBlock = (type === 'header' || type === 'footer') && !isUnlocked;

    if (isRestrictedBlock && isRestrictedRole) {
        html += `
            <div style="text-align: center; padding: 30px 20px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 12px; margin-top: 10px;">
                <i class="fas fa-lock" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 15px;"></i>
                <h4 style="color: #b91c1c; margin: 0 0 10px 0; font-weight: 800;">Restricted Section</h4>
                <p style="font-size: 0.85rem; color: #dc2626; margin-bottom: 20px; line-height: 1.5;">Only the Super Admin can edit the ${type} block to ensure brand consistency.</p>
                <button class="btn btn-primary" onclick="requestSuperAdminApproval('${type}')" style="background: #ef4444; border: none; padding: 12px 20px; width: 100%; font-weight: 700; border-radius: 8px;">
                    <i class="fas fa-envelope"></i> Request Edit Approval
                </button>
            </div>
        `;
    } else if (type === 'header') {
        html += `
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="margin: 0;">Header Text</label>
                    <button class="btn btn-outline btn-sm" style="font-size: 0.65rem; padding: 2px 8px; color: #8b5cf6; border-color: #ddd6fe; background: #f5f3ff;" onclick="aiImproveText('${blockId}', '.header-text')">
                        <i class="fas fa-magic"></i> AI Improve
                    </button>
                </div>
                <input type="text" class="block-input header-text-field" value="${block.querySelector('.header-text').value}" oninput="updateBlockData('.header-text', this.value)">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>Text Color</label>
                    <input type="color" class="color-input" value="${block.querySelector('.color-input').value}" oninput="updateBlockData('.color-input', this.value)">
                </div>
                <div class="form-group">
                    <label>Background</label>
                    <input type="color" class="color-input" value="${block.querySelector('.bg-input').value}" oninput="updateBlockData('.bg-input', this.value)">
                </div>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'text') {
        html += `
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="margin: 0;">Text Content</label>
                    <button class="btn btn-outline btn-sm" style="font-size: 0.65rem; padding: 2px 8px; color: #8b5cf6; border-color: #ddd6fe; background: #f5f3ff;" onclick="aiImproveText('${blockId}', '.text-content')">
                        <i class="fas fa-magic"></i> AI Improve
                    </button>
                </div>
                <div style="font-size: 0.75rem; color: var(--primary); margin-bottom: 8px; background: #e0f2fe; padding: 10px; border-radius: 6px; border: 1px solid #bae6fd;">
                    <i class="fas fa-magic"></i> Click to copy: 
                    <span class="tag-badge" onclick="copyToClipboard('{first_name}')" style="background: white; border: 1px solid #7dd3fc; padding: 2px 6px; border-radius: 4px; cursor: pointer; margin: 0 4px; font-weight: 700; color: #0369a1;">{first_name}</span>
                    <span class="tag-badge" onclick="copyToClipboard('{full_name}')" style="background: white; border: 1px solid #7dd3fc; padding: 2px 6px; border-radius: 4px; cursor: pointer; margin: 0 4px; font-weight: 700; color: #0369a1;">{full_name}</span>
                </div>
                <textarea class="block-input text-content-field" style="height: 150px;" oninput="updateBlockData('.text-content', this.value)">${block.querySelector('.text-content').value}</textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>Font Size</label>
                    <input type="number" class="block-input" value="${block.querySelector('.size-input').value}" oninput="updateBlockData('.size-input', this.value)">
                </div>
                <div class="form-group">
                    <label>Text Color</label>
                    <input type="color" class="color-input" style="width:100% !important;" value="${block.querySelector('.color-input').value}" oninput="updateBlockData('.color-input', this.value)">
                </div>
            </div>
            <div class="form-group">
                <button class="btn btn-outline btn-block ${block.classList.contains('is-bold') ? 'active' : ''}" onclick="toggleBlockBold()">
                    <i class="fas fa-bold"></i> ${block.classList.contains('is-bold') ? 'Bold Active' : 'Make Bold'}
                </button>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'image') {
        html += `
            <div class="form-group">
                <label>Image URL</label>
                <input type="text" class="block-input" value="${block.querySelector('.image-url').value}" oninput="updateBlockData('.image-url', this.value)">
            </div>
            <div class="form-group">
                <label>Upload Local Image</label>
                <button class="btn btn-primary btn-block" onclick="triggerPropertyUpload()"><i class="fas fa-upload"></i> Choose File</button>
                <input type="file" id="property-file-input" style="display: none;" onchange="handlePropertyUpload()">
            </div>
            <div class="form-group" style="background: #f1f5f9; padding: 15px; border-radius: 8px; border: 1px solid var(--border);">
                <label style="color: var(--primary); font-weight: 700;">SIDE TEXT (BESIDE IMAGE)</label>
                <textarea id="caption-textarea-${activeBlockId}" class="block-input" style="height: 80px; margin-top: 5px;" placeholder="Message beside image..." oninput="updateBlockData('.caption-text', this.value)">${block.querySelector('.caption-text').value}</textarea>
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 10px;">
                    <div class="form-group">
                        <label>Font Size</label>
                        <input type="number" class="block-input" value="${block.querySelector('.caption-size').value}" oninput="updateBlockData('.caption-size', this.value)">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                    <input type="color" class="color-input" style="width: 40px !important;" value="${block.querySelector('.caption-color').value}" oninput="updateBlockData('.caption-color', this.value)">
                    <button class="btn btn-outline btn-sm ${block.classList.contains('is-caption-bold') ? 'active' : ''}" onclick="document.getElementById('${activeBlockId}').classList.toggle('is-caption-bold'); updatePreview(); this.classList.toggle('active');">
                        <i class="fas fa-bold"></i> Bold
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="insertBulletPoint('caption-textarea-${activeBlockId}')">
                        <i class="fas fa-list-ul"></i> Add Bullet
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Image Width (${block.querySelector('.width-input').value}%)</label>
                <input type="range" class="block-input" min="20" max="100" value="${block.querySelector('.width-input').value}" oninput="updateBlockData('.width-input', this.value); this.previousElementSibling.innerText = 'Image Width ('+this.value+'%)'">
            </div>
            <div class="form-group">
                <label>Corner Roundness (${block.querySelector('.radius-input').value}px)</label>
                <input type="range" class="block-input" min="0" max="50" value="${block.querySelector('.radius-input').value}" oninput="updateBlockData('.radius-input', this.value); this.previousElementSibling.innerText = 'Corner Roundness ('+this.value+'px)'">
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'button') {
        html += `
            <div class="form-group">
                <label>Button Text</label>
                <input type="text" class="block-input" value="${block.querySelector('.btn-text').value}" oninput="updateBlockData('.btn-text', this.value)">
            </div>
            <div class="form-group">
                <label>Button Link</label>
                <input type="text" class="block-input" value="${block.querySelector('.btn-link').value}" oninput="updateBlockData('.btn-link', this.value)">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>Text Color</label>
                    <input type="color" class="color-input" style="width:100% !important;" value="${block.querySelector('.color-input').value}" oninput="updateBlockData('.color-input', this.value)">
                </div>
                <div class="form-group">
                    <label>Background</label>
                    <input type="color" class="color-input" style="width:100% !important;" value="${block.querySelector('.bg-input').value}" oninput="updateBlockData('.bg-input', this.value)">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>Font Size (px)</label>
                    <input type="number" class="block-input" value="${block.querySelector('.size-input').value}" oninput="updateBlockData('.size-input', this.value)">
                </div>
                <div class="form-group">
                    <label>Vertical Padding</label>
                    <input type="number" class="block-input" value="${block.querySelector('.padding-input').value}" oninput="updateBlockData('.padding-input', this.value)">
                </div>
            </div>
            <div class="form-group">
                <label>Button Width</label>
                <select class="block-input" onchange="updateBlockData('.width-input', this.value)">
                    <option value="auto" ${block.querySelector('.width-input').value === 'auto' ? 'selected' : ''}>Auto (Fits Text)</option>
                    <option value="50%" ${block.querySelector('.width-input').value === '50%' ? 'selected' : ''}>Half Width (50%)</option>
                    <option value="100%" ${block.querySelector('.width-input').value === '100%' ? 'selected' : ''}>Full Width (100%)</option>
                </select>
            </div>
            <div class="form-group">
                <button class="btn btn-outline btn-block ${block.classList.contains('is-bold') ? 'active' : ''}" onclick="toggleBlockBold()">
                    <i class="fas fa-bold"></i> ${block.classList.contains('is-bold') ? 'Bold Active' : 'Make Bold'}
                </button>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'footer') {
        html += `
            <div class="form-group">
                <label>Footer Text</label>
                <textarea class="block-input" style="height: 100px;" oninput="updateBlockData('.footer-text', this.value)">${block.querySelector('.footer-text').value}</textarea>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else {
        html += '<p style="color: var(--text-muted);">No additional settings for this element.</p>';
    }

    propertiesContent.innerHTML = html;
}

function closeProperties() {
    activeBlockId = null;
    document.getElementById('canvas-section').style.display = 'block';
    document.getElementById('properties-section').style.display = 'none';
}

async function requestSuperAdminApproval(blockType) {
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Request...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'request_block_approval');
        formData.append('block_type', blockType);

        const response = await fetch('api/marketing_api.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Request Sent',
                text: 'Your request for ' + blockType + ' unlock has been sent to the Super Admin.',
                confirmButtonColor: '#003580'
            });
        } else {
            Swal.fire('Error', res.message || 'Failed to send request.', 'error');
        }
    } catch (error) {
        console.error("Error sending approval request:", error);
        Swal.fire('Error', 'A network error occurred.', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// SUPER ADMIN UNLOCK MANAGER FUNCTIONS
async function openUnlockManager() {
    const modal = document.getElementById('unlock-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    loadUnlockRequests();
}

function closeUnlockModal() {
    document.getElementById('unlock-modal').style.display = 'none';
    if (window.adminTimerInterval) {
        clearInterval(window.adminTimerInterval);
        window.adminTimerInterval = null;
    }
}

function updateAdminCardTimers() {
    const cards = document.querySelectorAll('.unlock-request-card[data-expiry]');
    const now = new Date().getTime();
    
    cards.forEach(card => {
        const expiryStr = card.dataset.expiry;
        if (!expiryStr) return;
        
        const expiry = new Date(expiryStr).getTime();
        const distance = expiry - now;
        const countdownElement = card.querySelector('.card-countdown');
        const progressLine = card.querySelector('.progress-line');
        
        if (distance < 0) {
            if (countdownElement) countdownElement.innerText = "EXPIRED";
            if (progressLine) progressLine.style.width = '0%';
            card.classList.remove('active-timer-glow');
            const badge = card.querySelector('.status-badge');
            if (badge && badge.innerText === 'APPROVED') {
                badge.innerText = 'EXPIRED';
                badge.style.color = '#ef4444';
                badge.style.background = '#ef444415';
            }
            return;
        }
        
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        if (countdownElement) {
            countdownElement.innerText = `${hours}h ${minutes}m ${seconds}s remaining`;
        }
    });
}

async function loadUnlockRequests() {
    const container = document.getElementById('unlock-request-list');
    try {
        const response = await fetch('api/marketing_api.php?action=get_unlock_requests');
        const res = await response.json();
        if (res.success) {
            if (res.requests.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;">No unlock requests found.</div>';
                return;
            }
            
            // Filter out expired/old requests if desired, or show all
            container.innerHTML = res.requests.map(req => {
                const now = new Date();
                const expiry = req.expires_at ? new Date(req.expires_at) : null;
                const isExpired = expiry && expiry < now;
                const isApproved = req.status === 'approved' && !isExpired;
                const isPending = req.status === 'pending';
                
                const statusLabel = isExpired ? 'EXPIRED' : req.status.toUpperCase();
                const statusColor = isApproved ? '#10b981' : (isPending ? '#f59e0b' : '#ef4444');
                
                // Default tomorrow as expiry for picker
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setMinutes(0);
                const defaultExpiry = tomorrow.toISOString().slice(0, 16);

                const timerClass = isApproved ? 'active-timer-glow' : '';

                return `
                <div class="unlock-request-card ${timerClass}" data-expiry="${req.expires_at || ''}" data-id="${req.id}" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.3s; margin-bottom: 10px; position: relative; overflow: hidden;">
                    <div style="flex: 1; position: relative; z-index: 2;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div style="width: 40px; height: 40px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #64748b; font-weight: 800;">
                                ${req.admin_username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 style="margin: 0; color: #1e293b; font-size: 1.1rem; font-weight: 800;">${req.admin_username}</h4>
                                <span class="status-badge" style="font-size: 0.65rem; background: ${statusColor}15; color: ${statusColor}; padding: 3px 10px; border-radius: 50px; font-weight: 800; letter-spacing: 0.5px;">${statusLabel}</span>
                            </div>
                        </div>
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">Requested <strong>${req.block_type.toUpperCase()}</strong> unlock permission</p>
                        ${expiry ? `<div class="expiry-display" style="margin-top: 10px; display: flex; align-items: center; gap: 10px; color: #10b981; font-size: 0.8rem; font-weight: 800;">
                            <i class="fas fa-clock fa-spin"></i> 
                            <span class="card-countdown" data-expiry="${expiry.getTime()}">Calculating...</span>
                        </div>` : ''}
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px; align-items: flex-end; position: relative; z-index: 2;">
                        <button onclick="deleteUnlockRequest(${req.id})" style="position: absolute; top: -15px; right: -15px; background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 5px; font-size: 0.9rem; transition: 0.3s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#cbd5e1'">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        ${isApproved ? `
                            <button onclick="approveRequest(${req.id}, '', 'locked')" style="padding: 10px 20px; border-radius: 12px; background: #ef4444; color: white; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">
                                <i class="fas fa-lock"></i> Revoke & Lock
                            </button>
                        ` : `
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <label style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Set Expiry Date & Time</label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="datetime-local" id="expiry-picker-${req.id}" value="${defaultExpiry}" style="padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.85rem; font-family: inherit; font-weight: 600; color: #1e293b; outline: none; border-color: #cbd5e1;">
                                    <button onclick="approveRequest(${req.id}, document.getElementById('expiry-picker-${req.id}').value, 'approved')" style="padding: 10px 20px; border-radius: 12px; background: #10b981; color: white; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">
                                        <i class="fas fa-calendar-check"></i> Approve Unlock
                                    </button>
                                </div>
                            </div>
                        `}
                    </div>
                    ${isApproved ? `<div class="progress-line" style="position: absolute; bottom: 0; left: 0; height: 3px; background: #10b981; transition: width 1s linear; width: 100%;"></div>` : ''}
                </div>
                `;
            }).join('');
            
            // Start local timers for these cards
            updateAdminCardTimers();
            if (!window.adminTimerInterval) {
                window.adminTimerInterval = setInterval(updateAdminCardTimers, 1000);
            }
        }
    } catch (e) {
        console.error(e);
    }
}

async function approveRequest(id, expiryTime, status) {
    const formData = new FormData();
    formData.append('action', 'approve_unlock_request');
    formData.append('id', id);
    formData.append('expiry_time', expiryTime);
    formData.append('status', status);

    const response = await fetch('api/marketing_api.php', {
        method: 'POST',
        body: formData
    });
    const res = await response.json();
    if (res.success) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: status === 'approved' ? 'Access Granted' : 'Access Revoked',
            showConfirmButton: false,
            timer: 1500
        });
        loadUnlockRequests();
        // If we are currently the one being unlocked, refresh our own status
        fetchMyUnlocks();
    }
}

async function deleteUnlockRequest(id) {
    const result = await Swal.fire({
        title: 'Delete Request?',
        text: "This will permanently remove this request from the history.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'delete_unlock_request');
        formData.append('id', id);

        try {
            const response = await fetch('api/marketing_api.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Request Deleted',
                    showConfirmButton: false,
                    timer: 1500
                });
                loadUnlockRequests();
            }
        } catch (e) {
            console.error(e);
        }
    }
}

let countdownInterval = null;

async function fetchMyUnlocks() {
    try {
        const response = await fetch('api/marketing_api.php?action=get_my_active_unlocks');
        const res = await response.json();
        if (res.success) {
            activeUnlocks = res.unlocks.map(u => u.block_type);
            const banner = document.getElementById('active-access-banner');
            
            if (res.unlocks.length > 0) {
                // Find the latest expiry among all active unlocks
                const latestExpiry = Math.max(...res.unlocks.map(u => new Date(u.expires_at).getTime()));
                
                if (banner) {
                    banner.style.display = 'flex';
                    document.getElementById('access-banner-text').innerText = `Access Granted: ${activeUnlocks.map(u => u.toUpperCase()).join(' & ')}`;
                    startAccessCountdown(latestExpiry);
                }

                // Update UI lock icons
                document.querySelectorAll('.canvas-block').forEach(block => {
                    const blockType = block.dataset.type;
                    if (activeUnlocks.includes(blockType)) {
                        const summary = block.querySelector('.block-summary');
                        const lockIcon = summary?.querySelector('.fa-lock');
                        
                        if (lockIcon) {
                            // Replace lock with full controls if they don't exist
                            const controlContainer = lockIcon.parentElement.parentElement;
                            controlContainer.innerHTML = `
                                <span class="duplicate-block" title="Duplicate" style="cursor: pointer;"><i class="fas fa-copy"></i></span>
                                <span class="remove-block" title="Delete" style="cursor: pointer;"><i class="fas fa-trash"></i></span>
                            `;
                            controlContainer.style.color = '#10b981';
                            controlContainer.style.pointerEvents = 'auto';
                        }
                    }
                });
            } else {
                if (banner) banner.style.display = 'none';
                if (countdownInterval) clearInterval(countdownInterval);
            }
        }
    } catch (e) { console.error(e); }
}

function startAccessCountdown(expiryTime) {
    if (countdownInterval) clearInterval(countdownInterval);
    
    const timerElement = document.getElementById('access-countdown-timer');
    
    function update() {
        const now = new Date().getTime();
        const distance = expiryTime - now;
        
        if (distance < 0) {
            clearInterval(countdownInterval);
            if (timerElement) timerElement.innerText = "EXPIRED";
            // Automatically relock everything
            activeUnlocks = [];
            fetchMyUnlocks(); // This will handle hiding the banner and updating UI
            return;
        }
        
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        if (timerElement) {
            timerElement.innerText = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }
    
    update();
    countdownInterval = setInterval(update, 1000);
}

async function pollUnlockRequests() {
    try {
        const response = await fetch('api/marketing_api.php?action=get_unlock_requests');
        const res = await response.json();
        if (res.success) {
            const pendingCount = res.requests.filter(r => r.status === 'pending').length;
            const badge = document.getElementById('unlock-request-badge');
            if (badge) {
                if (pendingCount > 0) {
                    badge.innerText = pendingCount;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    } catch (e) {}
}

function updateBlockLabel(value) {
    if (!activeBlockId) return;
    const block = document.getElementById(activeBlockId);
    if (!block) return;
    
    // Update the hidden input
    block.querySelector('.block-label-input').value = value;
    
    // Update the separator and custom text
    const separator = block.querySelector('.label-separator');
    const labelText = block.querySelector('.label-text');
    
    if (separator) separator.innerText = value ? ': ' : '';
    if (labelText) labelText.innerText = value;
    
    persistState();
}

function updateBlockData(selector, value) {
    if (!activeBlockId) return;
    const block = document.getElementById(activeBlockId);
    block.querySelector(selector).value = value;
    updatePreview();
}

function updateBlockAlign(align) {
    if (!activeBlockId) return;
    updateBlockData('.align-input', align);
    const buttons = document.querySelectorAll('#properties-content .align-group button');
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('onclick').includes(`'${align}'`)) btn.classList.add('active');
    });
}

function toggleBlockBold() {
    if (!activeBlockId) return;
    const block = document.getElementById(activeBlockId);
    block.classList.toggle('is-bold');
    const btn = document.querySelector('#properties-content button[onclick="toggleBlockBold()"]');
    if (block.classList.contains('is-bold')) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-bold"></i> Bold Active';
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-bold"></i> Make Bold';
    }
    updatePreview();
}

function triggerPropertyUpload() {
    document.getElementById('property-file-input').click();
}

async function handlePropertyUpload() {
    const fileInput = document.getElementById('property-file-input');
    if (!fileInput.files || fileInput.files.length === 0) return;
    const formData = new FormData();
    formData.append('action', 'upload_promo_image');
    formData.append('promo_image', fileInput.files[0]);
    try {
        const response = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            updateBlockData('.image-url', data.url);
            const blockInput = document.querySelector('#properties-content .block-input');
            if (blockInput) blockInput.value = data.url;
        } else { Swal.fire('Error', data.message, 'error'); }
    } catch (e) { Swal.fire('Error', 'Upload failed', 'error'); }
}

function getBlocksData() {
    const blocks = document.querySelectorAll('#builder-canvas .canvas-block');
    const campaignData = [];
    blocks.forEach(block => {
        const type = block.dataset.type;

        const data = { 
            type: type, 
            id: block.id,
            custom_label: block.querySelector('.block-label-input')?.value
        };
        
        try {
            if (type === 'header') {
                data.text = block.querySelector('.header-text')?.value || '';
                data.color = block.querySelector('.color-input')?.value || '#000000';
                data.bg = block.querySelector('.bg-input')?.value || '#ffffff';
                data.align = block.querySelector('.align-input')?.value || 'center';
            } else if (type === 'text') {
                data.text = block.querySelector('.text-content')?.value || '';
                data.size = block.querySelector('.size-input')?.value || '16';
                data.color = block.querySelector('.color-input')?.value || '#334155';
                data.align = block.querySelector('.align-input')?.value || 'left';
                data.weight = block.classList.contains('is-bold') ? '700' : '400';
            } else if (type === 'image') {
                data.url = block.querySelector('.image-url')?.value || '';
                data.width = block.querySelector('.width-input')?.value || '100';
                data.radius = block.querySelector('.radius-input')?.value || '0';
                data.align = block.querySelector('.align-input')?.value || 'center';
                data.caption = block.querySelector('.caption-text')?.value || '';
                data.capSize = block.querySelector('.caption-size')?.value || '14';
                data.capColor = block.querySelector('.caption-color')?.value || '#64748b';
                data.capWeight = block.classList.contains('is-caption-bold') ? '700' : '400';
            } else if (type === 'button') {
                data.text = block.querySelector('.btn-text')?.value || 'Click Here';
                data.link = block.querySelector('.btn-link')?.value || '';
                data.color = block.querySelector('.color-input')?.value || '#ffffff';
                data.bg = block.querySelector('.bg-input')?.value || '#003580';
                data.size = block.querySelector('.size-input')?.value || '16';
                data.padding = block.querySelector('.padding-input')?.value || '12';
                data.width = block.querySelector('.width-input')?.value || 'auto';
                data.weight = block.classList.contains('is-bold') ? '700' : '600';
                data.align = block.querySelector('.align-input')?.value || 'center';
            } else if (type === 'footer') {
                data.text = block.querySelector('.footer-text')?.value || '';
                data.align = block.querySelector('.align-input')?.value || 'center';
            }
            
            // STRIP AI MARKERS BEFORE RETURNING DATA (for saving/sending)
            const stripAi = (str) => typeof str === 'string' ? str.replace(/\[\[AI_FIX:(.*?)\]\]/g, '$1') : str;
            if (data.text) data.text = stripAi(data.text);
            if (data.caption) data.caption = stripAi(data.caption);

            campaignData.push(data);
        } catch (e) {
            console.warn('Error extracting block data:', e);
        }
    });
    return campaignData;
}

function updatePreview(shouldSave = true) {
    const blocksData = getBlocksData();

    const blocksHtml = blocksData.map((block, index) => {
        let content = '';
        
        // Helper to apply highlights to preview text
        const getHighlighted = (text, type) => {
            if (!text) return '';
            
            // Render AI Fixed highlights
            let html = text.replace(/\[\[AI_FIX:(.*?)\]\]/g, '<span style="background-color: #fef08a; padding: 2px 4px; border-radius: 4px; border: 1px dashed #facc15; color: #854d0e; font-weight: 700; display: inline-block; box-shadow: 0 2px 4px rgba(250, 204, 21, 0.2);" title="AI Auto-Fixed"><i class="fas fa-magic" style="font-size: 0.7rem; margin-right: 4px;"></i>$1</span>');
            
            // If we have red highlights (activeLinguisticMatches), apply them to the clean text
            if (typeof activeLinguisticMatches !== 'undefined' && activeLinguisticMatches && activeLinguisticMatches.length > 0) {
                const cleanText = text.replace(/\[\[AI_FIX:(.*?)\]\]/g, '$1');
                return highlightTextInPreview(cleanText, block.id, activeLinguisticMatches).replace(/\n/g, '<br>');
            }

            return html.replace(/\n/g, '<br>');
        };

        if (block.type === 'header') {
            content = `<div style="background: ${block.bg}; padding: 20px; text-align: ${block.align};"><h2 style="color: ${block.color}; margin: 0;">${getHighlighted(block.text, 'header')}</h2></div>`;
        } else if (block.type === 'footer') {
            content = `
                <div style="padding: 30px; text-align: ${block.align}; background: #f8fafc;">
                    <p style="font-size: 12px; color: #94a3b8; margin: 0;">${getHighlighted(block.text, 'footer')}</p>
                    <div style="margin-top: 15px; font-size: 12px; font-family: sans-serif;">
                        <a href="../unsubscribe.php" style="color: #003580; text-decoration: none;">Unsubscribe</a> | <style>/* Preview Highlighting - High Visibility */
.preview-lt-highlight {
    border-bottom: 2px wavy #ef4444 !important;
    background-color: #fee2e2 !important;
    color: #b91c1c !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    cursor: help;
}
</style><a href="https://heydreamtravel.kesug.com/" style="color: #003580; text-decoration: none;">View Website</a>
                    </div>
                </div>`;
        } else if (block.type === 'text') {
            let previewText = block.text;
            return `<div style="margin-bottom: 20px; padding: 0 30px; text-align: ${block.align};"><p style="color: ${block.color}; font-size: ${block.size}px; font-weight: ${block.weight}; line-height: 1.6; margin: 0;">${getHighlighted(previewText, 'text') || 'Empty text...'}</p></div>`;
        } else if (block.type === 'image') {
            const imgHtml = `<img src="${block.url}" style="width: 100%; border-radius: ${block.radius}px; display: block;" alt="Promo">`;
            if (block.caption) {
                const textHtml = `<div style="flex: 1; color: ${block.capColor}; font-size: ${block.capSize}px; font-weight: ${block.capWeight}; line-height: 1.5; padding: 10px; text-align: ${block.align === 'center' ? 'center' : 'left'};">${getHighlighted(block.caption, 'image')}</div>`;
                let containerStyle = '';
                if (block.align === 'left') containerStyle = `display: flex; align-items: center; gap: 20px; flex-direction: row;`;
                else if (block.align === 'right') containerStyle = `display: flex; align-items: center; gap: 20px; flex-direction: row-reverse;`;
                else containerStyle = `display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px;`;
                content = `<div style="margin-bottom: 20px; padding: 0 30px;"><div style="${containerStyle}"><div style="width: ${block.width}%; flex-shrink: 0;">${imgHtml}</div>${textHtml}</div></div>`;
            } else {
                content = `<div style="margin-bottom: 20px; padding: 0 30px; text-align: ${block.align};"><img src="${block.url}" style="width: ${block.width}%; border-radius: ${block.radius}px;" alt="Promo"></div>`;
            }
        } else if (block.type === 'button') {
            content = `<div style="text-align: ${block.align}; margin: 20px 0; padding: 0 30px;"><a href="${block.link || '#'}" style="display: inline-block; background: ${block.bg}; color: ${block.color}; padding: ${block.padding}px 24px; text-decoration: none; border-radius: 8px; font-weight: ${block.weight}; font-size: ${block.size}px; width: ${block.width}; box-sizing: border-box;">${getHighlighted(block.text, 'button')}</a></div>`;
        } else if (block.type === 'divider') {
            content = `<div style="padding: 0 30px;"><hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;"></div>`;
        }
        return `<div class="preview-item" data-id="${block.id}" style="cursor: grab;">${content}</div>`;
    }).join('');

    let previewContainer = document.getElementById('email-preview-container');
    if (!previewContainer) return;

    // Initialize the structure if not present
    if (!document.getElementById('preview-sortable-list')) {
        previewContainer.innerHTML = `
            <div style="font-family: 'Poppins', sans-serif; background: #f8fafc; padding: 20px;">
                <div style="width: 100%; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
                    <div id="preview-sortable-list"></div>
                </div>
            </div>
        `;
    }

    const previewSortableList = document.getElementById('preview-sortable-list');
    previewSortableList.innerHTML = blocksHtml;
        
    // Initialize or Update Preview Sortable
    if (!previewSortable) {
        previewSortable = new Sortable(previewSortableList, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            draggable: '.preview-item',
            onEnd: (evt) => {
                syncBuilderToPreviewOrder();
            }
        });
    }

    // PERSIST STATE ON EVERY CHANGE
    persistState();

    if (shouldSave) {
        saveHistory();
    }
}

function syncBuilderToPreviewOrder() {
    const previewItems = document.querySelectorAll('.preview-item');
    const builderCanvas = document.getElementById('builder-canvas');
    
    // Create a fragment to reorder builder blocks
    const fragment = document.createDocumentFragment();
    previewItems.forEach(item => {
        const blockId = item.dataset.id;
        const block = document.getElementById(blockId);
        if (block) fragment.appendChild(block);
    });
    
    builderCanvas.appendChild(fragment);
    persistState();
}

function spamCheck() {
    const blocks = getBlocksData();
    const text = blocks.map(b => b.text || '').join(' ').toLowerCase();
    const subject = blocks.find(b => b.type === 'header')?.text || '';
    const links = blocks.filter(b => b.type === 'button').map(b => b.link || '');
    const images = blocks.filter(b => b.type === 'image');
    
    let spamScore = 0;
    let securityFlags = [];
    let grammarFlags = [];
    let visualFlags = [];
    let marketingFlags = [];

    // 1. SECURITY & TRUST AUDIT
    const shorteners = ['bit.ly', 'tinyurl', 't.co', 'goo.gl', 'is.gd', 'buff.ly'];
    links.forEach(url => {
        if (shorteners.some(s => url.includes(s))) {
            securityFlags.push("Shortened URL detected (High Spam Risk)");
            spamScore += 8;
        }
        if (url.startsWith('http://') && !url.includes('localhost')) {
            securityFlags.push("Insecure (non-HTTPS) link");
            spamScore += 3;
        }
    });
    if (text.includes('password') || text.includes('credit card') || text.includes('social security')) {
        securityFlags.push("Sensitive data keywords (Phishing Risk)");
        spamScore += 10;
    }

    // 2. LINGUISTIC & PROFESSIONALISM
    const commonTypos = {
        'teh': 'the', 'recieve': 'receive', 'occured': 'occurred', 'destnation': 'destination',
        'calender': 'calendar', 'tommorrow': 'tomorrow', 'thru': 'through', 'alot': 'a lot'
    };
    
    const foulWords = ['damn', 'hell', 'stupid', 'idiot', 'crap', 'shit', 'fuck', 'ass', 'bastard', 'suck', 'wtf'];
    const commonWords = new Set(['heydream', 'travel', 'tours', 'booking', 'destination', 'package', 'promo', 'exclusive', 'discount', 'limited', 'offer', 'hotel', 'flight', 'resort', 'beach', 'adventure', 'explore', 'contact', 'website', 'social', 'media', 'facebook', 'instagram', 'twitter', 'tiktok', 'youtube', 'phone', 'email', 'address', 'visit', 'click', 'link', 'subscribe', 'unsubscribe', 'privacy', 'policy', 'terms', 'conditions']);
    
    let typosFound = [];
    let detectedFoul = [];
    const words = text.split(/\s+/);
    
    words.forEach((w, i) => {
        const clean = w.replace(/[.,!?;:()]/g, '').toLowerCase();
        if (clean.length < 2 || /^[0-9]+$/.test(clean) || clean.includes('{') || clean.includes('}') || commonWords.has(clean)) return;

        if (foulWords.some(fw => clean === fw || clean.includes(fw))) {
            detectedFoul.push(clean);
            spamScore += 5;
        }
        if (commonTypos[clean]) typosFound.push(`"${clean}"`);
        
        const vowels = clean.match(/[aeiouy]/g);
        const vowelRatio = vowels ? (vowels.length / clean.length) : 0;
        if (vowelRatio < 0.2 && clean.length > 3) typosFound.push(`"${clean}" (Incomplete?)`);
        else if (/(.)\1\1/.test(clean)) typosFound.push(`"${clean}" (Repeat)`);
    });

    if (detectedFoul.length > 0) securityFlags.push(`Unprofessional Language: ${[...new Set(detectedFoul)].join(', ')}`);
    if (typosFound.length > 0) grammarFlags.push(`Spelling issues: ${[...new Set(typosFound)].slice(0, 4).join(', ')}`);

    // 3. VISUAL & ACCESSIBILITY AUDIT
    images.forEach(img => {
        if (img.url.includes('placeholder')) visualFlags.push("Placeholder image detected");
        if (img.width > 90) visualFlags.push("Extra-wide image (Check mobile)");
    });
    if (images.length === 0) visualFlags.push("No images used");

    // 4. MARKETING STRATEGY
    if (!text.includes('{first_name}')) marketingFlags.push("Missing Personalization ({first_name})");
    if (!text.includes('facebook') && !text.includes('instagram')) marketingFlags.push("Missing Social Media links");
    if (blocks.length < 3) marketingFlags.push("Email content is too short");

    // 5. LOCAL STRATEGY INSIGHTS
    let aiInsights = [];
    const positiveWords = ['amazing', 'special', 'exclusive', 'dream', 'perfect', 'discover', 'luxury'];
    const pCount = positiveWords.filter(w => text.includes(w)).length;
    if (pCount > 3) aiInsights.push("High Emotional Resonance: Your tone is very inviting.");
    else aiInsights.push("Tone Advice: Consider adding more 'Dreamy' adjectives.");

    if (links.length === 1) aiInsights.push("Strong Focus: Clear CTA detected.");
    else if (links.length > 2) aiInsights.push("Choice Paradox: Too many buttons might distract.");
    
    if (images.length > 0 && words.length > 50) aiInsights.push("Perfect Balance: Ideal image-to-text ratio.");

    // 6. RATING SYSTEM
    let rating = 'Elite';
    let color = '#10b981';
    if (spamScore > 15 || securityFlags.length > 0) { rating = 'Critical / Unsafe'; color = '#ef4444'; }
    else if (grammarFlags.length > 0 || visualFlags.length > 2) { rating = 'Needs Polish'; color = '#f59e0b'; }
    else if (marketingFlags.length > 0) { rating = 'Professional'; color = '#3b82f6'; }

    // SHOW FINAL REPORT (LOCAL ONLY)
    Swal.fire({
        title: 'Marketing Health Check',
        width: '850px',
        html: `
            <div style="text-align: left; background: #f8fafc; padding: 30px; border-radius: 24px; border: 1px solid #e2e8f0; font-family: 'Poppins', sans-serif;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
                    <div>
                        <h3 style="margin: 0; color: #1e293b; font-weight: 800;">Campaign Dashboard</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Instant local analysis of your campaign quality</p>
                    </div>
                    <div style="text-align: right;">
                        <span style="padding: 8px 24px; border-radius: 50px; background: ${color}; color: white; font-weight: 900; font-size: 1rem;">${rating}</span>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: white; padding: 18px; border-radius: 18px; border: 1px solid #f1f5f9;">
                        <div style="font-weight: 800; font-size: 0.85rem; color: #003580; margin-bottom: 10px;"><i class="fas fa-search"></i> Content Audit</div>
                        <div style="font-size: 0.75rem; color: #475569; line-height: 1.6;">
                            ${securityFlags.length > 0 ? securityFlags.map(f => `<i class="fas fa-times-circle" style="color:#ef4444"></i> ${f}<br>`).join('') : '<i class="fas fa-check-circle" style="color:#10b981"></i> Security: Optimal<br>'}
                            ${grammarFlags.length > 0 ? grammarFlags.map(f => `<i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> ${f}<br>`).join('') : '<i class="fas fa-check-circle" style="color:#10b981"></i> Language: Professional<br>'}
                        </div>
                    </div>
                    <div style="background: white; padding: 18px; border-radius: 18px; border: 1px solid #f1f5f9;">
                        <div style="font-weight: 800; font-size: 0.85rem; color: #8b5cf6; margin-bottom: 10px;"><i class="fas fa-lightbulb"></i> Strategy Insights</div>
                        <div style="font-size: 0.75rem; color: #475569; line-height: 1.6;">
                            ${aiInsights.map(i => `<i class="fas fa-check" style="color:#10b981"></i> ${i}<br>`).join('')}
                        </div>
                    </div>
                </div>

                <div style="margin-top: 25px; background: #003580; padding: 20px; border-radius: 18px; color: white; display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-shield-alt" style="font-size: 1.5rem; opacity: 0.5;"></i>
                    <p style="font-size: 0.85rem; line-height: 1.5; margin: 0; font-weight: 500;">
                        <b>Privacy Note:</b> This audit was performed 100% locally on your computer. No data was sent to any external AI services.
                    </p>
                </div>
            </div>
        `,
        confirmButtonColor: '#003580',
        confirmButtonText: 'I understand, Review changes'
    });
}


async function sendTest() {
    const { value: testEmail } = await Swal.fire({
        title: 'Send Test Email',
        input: 'email',
        inputLabel: 'Where should we send this test?',
        inputValue: 'heydreamtravelandtours@gmail.com',
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) return 'Please enter a valid email address!';
        }
    });

    if (!testEmail) return;

    currentSendingController = new AbortController();

    Swal.fire({ 
        title: 'Sending Test...', 
        allowOutsideClick: false,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        didOpen: () => Swal.showLoading() 
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            if (currentSendingController) currentSendingController.abort();
        }
    });

    const formData = new FormData();
    formData.append('action', 'send_test');
    formData.append('test_email', testEmail);
    formData.append('blocks', JSON.stringify(getBlocksData()));

    try {
        const r = await fetch('api/marketing_api.php', { 
            method: 'POST', 
            body: formData,
            signal: currentSendingController.signal
        });
        const res = await r.json();
        if (res.success) Swal.fire('Sent!', res.message, 'success');
        else Swal.fire('Error', res.message, 'error');
    } catch (e) {
        if (e.name === 'AbortError') Swal.fire('Cancelled', 'Test send stopped.', 'info');
        else Swal.fire('Error', 'Failed to send test.', 'error');
    } finally {
        currentSendingController = null;
    }
}

async function sendCampaign() {
    const blocks = getBlocksData();
    if (blocks.length === 0) return Swal.fire('Error', 'Add at least one block first.', 'error');

    // Step 1: Subject
    const { value: subject } = await Swal.fire({
        title: 'Campaign Subject',
        width: '550px',
        html: `
            <div style="text-align: left; margin-bottom: 10px;">
                <label style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Subject Line:</label>
                    <div style="margin-top: 8px;">
                        <input id="swal-input-subject" class="swal2-input" style="width: 100%; margin: 0 0 12px 0; box-sizing: border-box; font-size: 0.95rem;" placeholder="e.g. Exclusive Travel Deals Just for You!">
                        <button type="button" id="magic-subject-btn" class="btn btn-outline" style="width: 100%; border-color: #8b5cf6; color: #8b5cf6; background: #f5f3ff; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border-radius: 12px; font-weight: 600;">
                            <i class="fas fa-magic"></i> Generate Subject
                        </button>
                    </div>
                </div>
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 10px;">This is the first thing recipients see in their inbox. Make it catchy!</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Next →',
        didRender: () => {
            const genBtn = document.getElementById('magic-subject-btn');
            if (genBtn) genBtn.onclick = aiGenerateSubject;
        },
        preConfirm: () => {
            const val = document.getElementById('swal-input-subject').value;
            if (!val) return Swal.showValidationMessage('Please enter a subject line.');
            return val;
        }
    });
    if (!subject) return;

    // Step 2: Audience selection
    const { value: audience } = await Swal.fire({
        title: 'Choose Your Audience',
        html: `
            <p style="color:#64748b; margin-bottom: 20px;">Who should receive this campaign?</p>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <label style="display:flex; align-items:center; gap:12px; padding:14px; border:2px solid #e2e8f0; border-radius:10px; cursor:pointer; text-align:left;" onclick="document.getElementById('aud-all').checked=true; document.querySelectorAll('.aud-opt').forEach(e=>e.style.borderColor='#e2e8f0'); this.style.borderColor='#003580';">
                    <input type="radio" id="aud-all" name="audience" value="all" checked style="display:none;">
                    <i class="fas fa-users" style="color:#003580; font-size:1.4rem; width:24px;"></i>
                    <div><b style="display:block;">All Contacts</b><span style="font-size:0.8rem; color:#94a3b8;">Website users + Inquiry leads</span></div>
                </label>
                <label class="aud-opt" style="display:flex; align-items:center; gap:12px; padding:14px; border:2px solid #e2e8f0; border-radius:10px; cursor:pointer; text-align:left;" onclick="document.getElementById('aud-inquiries').checked=true; document.querySelectorAll('.aud-opt').forEach(e=>e.style.borderColor='#e2e8f0'); this.style.borderColor='#003580';">
                    <input type="radio" id="aud-inquiries" name="audience" value="inquiries" style="display:none;">
                    <i class="fas fa-file-alt" style="color:#f59e0b; font-size:1.4rem; width:24px;"></i>
                    <div><b style="display:block;">Inquiry Leads Only</b><span style="font-size:0.8rem; color:#94a3b8;">People who submitted an inquiry form</span></div>
                </label>
                <label class="aud-opt" style="display:flex; align-items:center; gap:12px; padding:14px; border:2px solid #e2e8f0; border-radius:10px; cursor:pointer; text-align:left;" onclick="document.getElementById('aud-website').checked=true; document.querySelectorAll('.aud-opt').forEach(e=>e.style.borderColor='#e2e8f0'); this.style.borderColor='#003580';">
                    <input type="radio" id="aud-website" name="audience" value="website" style="display:none;">
                    <i class="fas fa-globe" style="color:#10b981; font-size:1.4rem; width:24px;"></i>
                    <div><b style="display:block;">Website Users Only</b><span style="font-size:0.8rem; color:#94a3b8;">Registered accounts on the website</span></div>
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send Campaign',
        confirmButtonColor: '#003580',
        preConfirm: () => {
            return document.querySelector('input[name="audience"]:checked').value;
        }
    });
    if (!audience) return;

    // Step 3: When to send? (Immediate vs Schedule)
    const { value: sendTiming } = await Swal.fire({
        title: 'Choose Dispatch Timing',
        html: `
            <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
                <!-- OPTION 1: NOW -->
                <div id="opt-now" style="display: flex; align-items: center; gap: 20px; padding: 20px; border: 2px solid #003580; background: #f0f7ff; border-radius: 16px; cursor: pointer; transition: all 0.2s; text-align: left;">
                    <div style="width: 50px; height: 50px; background: #003580; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; pointer-events: none;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div style="flex: 1; pointer-events: none;">
                        <b style="font-size: 1.1rem; color: #003580; display: block; margin-bottom: 2px;">Send Immediately</b>
                        <span style="font-size: 0.85rem; color: #64748b;">Process broadcast right now</span>
                    </div>
                    <div id="check-now" style="color: #003580; font-size: 1.2rem; pointer-events: none;"><i class="fas fa-check-circle"></i></div>
                </div>

                <!-- OPTION 2: SCHEDULE -->
                <div id="opt-sch" style="display: flex; align-items: center; gap: 20px; padding: 20px; border: 2px solid #e2e8f0; background: white; border-radius: 16px; cursor: pointer; transition: all 0.2s; text-align: left;">
                    <div style="width: 50px; height: 50px; background: #eff6ff; color: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; pointer-events: none;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div style="flex: 1; pointer-events: none;">
                        <b style="font-size: 1.1rem; color: #1e293b; display: block; margin-bottom: 2px;">Schedule for Later</b>
                        <span style="font-size: 0.85rem; color: #64748b;">Pick a specific date and time</span>
                    </div>
                    <div id="check-sch" style="color: #e2e8f0; font-size: 1.2rem; pointer-events: none;"><i class="fas fa-circle"></i></div>
                </div>

                <div id="tm-sch-box" style="display: none; margin-top: 5px; padding: 20px; background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; animation: fadeIn 0.3s ease;">
                    <label style="display: block; margin-bottom: 12px; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Dispatch Date & Time:</label>
                    <input type="datetime-local" id="tm-dt" class="swal2-input" style="width: 100%; margin: 0; border-radius: 10px; border: 1px solid #cbd5e1;" min="${new Date().toISOString().slice(0, 16)}">
                </div>
            </div>
            <input type="hidden" id="timing-value" value="now">
        `,
        showCancelButton: true,
        confirmButtonText: 'Confirm Timing',
        confirmButtonColor: '#003580',
        didOpen: () => {
            const optNow = document.getElementById('opt-now');
            const optSch = document.getElementById('opt-sch');
            const checkNow = document.getElementById('check-now');
            const checkSch = document.getElementById('check-sch');
            const schBox = document.getElementById('tm-sch-box');
            const valInput = document.getElementById('timing-value');

            optNow.addEventListener('click', () => {
                valInput.value = 'now';
                optNow.style.borderColor = '#003580';
                optNow.style.background = '#f0f7ff';
                checkNow.innerHTML = '<i class="fas fa-check-circle"></i>';
                checkNow.style.color = '#003580';
                
                optSch.style.borderColor = '#e2e8f0';
                optSch.style.background = 'white';
                checkSch.innerHTML = '<i class="fas fa-circle"></i>';
                checkSch.style.color = '#e2e8f0';
                schBox.style.display = 'none';
            });

            optSch.addEventListener('click', () => {
                valInput.value = 'schedule';
                optSch.style.borderColor = '#3b82f6';
                optSch.style.background = '#f0f9ff';
                checkSch.innerHTML = '<i class="fas fa-check-circle"></i>';
                checkSch.style.color = '#3b82f6';
                
                optNow.style.borderColor = '#e2e8f0';
                optNow.style.background = 'white';
                checkNow.innerHTML = '<i class="fas fa-circle"></i>';
                checkNow.style.color = '#e2e8f0';
                schBox.style.display = 'block';
            });
        },
        preConfirm: () => {
            const type = document.getElementById('timing-value').value;
            const dt = document.getElementById('tm-dt').value;
            if (type === 'schedule' && !dt) return Swal.showValidationMessage('Please pick a schedule time.');
            return { type, dt };
        }
    });

    if (!sendTiming) return;

    if (sendTiming.type === 'schedule') {
        return scheduleCampaign(subject, audience, sendTiming.dt, blocks);
    }

    // Step 4: Confirm (For immediate sending)
    const audienceLabels = { all: 'All Contacts', inquiries: 'Inquiry Leads', website: 'Website Users' };
    const confirm = await Swal.fire({
        title: 'Ready to Send?',
        title: 'Confirm Broadcast?',
        text: `Are you sure you want to send this campaign to your ${audience} list?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Start Sending',
        cancelButtonText: 'Wait, I need to check'
    });

    if (!confirm.isConfirmed) return;

    currentSendingController = new AbortController();

    // Show loading modal immediately
    Swal.fire({
        title: 'Sending Campaign',
        allowOutsideClick: false,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Stop Sending',
        html: `
            <div class="progress-container" style="margin-top: 20px; text-align: center;">
                <div id="progress-text" style="font-weight: 700; margin-bottom: 5px; font-size: 1.5rem; color: #003580;">0%</div>
                <div style="width: 100%; background: #e2e8f0; border-radius: 50px; height: 16px; overflow: hidden; border: 1px solid #cbd5e1; margin: 10px 0;">
                    <div id="progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #003580, #0056b3); transition: width 0.3s ease;"></div>
                </div>
                <div id="progress-stats" style="font-size: 0.9rem; color: #64748b; font-weight: 500;">Initializing recipients...</div>
            </div>
        `
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            if (currentSendingController) currentSendingController.abort();
        }
    });

    const formData = new FormData();
    formData.append('action', 'send_campaign');
    formData.append('subject', subject);
    formData.append('audience', audience);
    formData.append('blocks', JSON.stringify(blocks));

    try {
        const response = await fetch('api/marketing_api.php', { 
            method: 'POST', 
            body: formData,
            signal: currentSendingController.signal
        });
        
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                const trimmedLine = line.trim();
                if (!trimmedLine) continue; 

                let data = null;
                try {
                    data = JSON.parse(trimmedLine);
                } catch (e) {
                    console.error('Stream Parse Error:', e, trimmedLine);
                }

                if (data) {
                    if (data.type === 'progress') {
                        const bar = document.getElementById('progress-bar');
                        const txt = document.getElementById('progress-text');
                        const stats = document.getElementById('progress-stats');
                        if (bar) bar.style.width = data.percent + '%';
                        if (txt) txt.innerText = data.percent + '%';
                        if (stats) stats.innerText = `Sent ${data.current} of ${data.total} recipients`;
                    } else if (data.type === 'done') {
                        if (data.success && data.sent_count > 0) {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: `Campaign sent successfully to ${data.sent_count} recipients.`,
                                confirmButtonColor: '#003580'
                            });
                            location.reload();
                        } else {
                            await Swal.fire('Finished', data.message || 'No emails were sent.', 'info');
                        }
                        return;
                    } else if (data.type === 'error') {
                        throw new Error(data.message);
                    }
                }
            }
        }
    } catch (e) {
        if (e.name === 'AbortError') {
            Swal.fire('Cancelled', 'The sending process was stopped by the user.', 'info');
        } else {
            Swal.fire('Error', e.message || 'Lost connection to server while sending. Please refresh.', 'error');
        }
    } finally {
        currentSendingController = null;
    }
}

async function openScheduleModal() {
    const blocks = getBlocksData();
    if (blocks.length === 0) return Swal.fire('Error', 'Add at least one block first.', 'error');

    // Step 1: Subject
    const { value: subject } = await Swal.fire({
        title: 'Campaign Subject',
        input: 'text',
        inputPlaceholder: 'e.g. Scheduled Promo for Weekend!',
        showCancelButton: true,
        confirmButtonText: 'Next →',
        inputValidator: (v) => !v ? 'Please enter a subject line.' : null
    });
    if (!subject) return;

    // Step 2: Audience
    const { value: audience } = await Swal.fire({
        title: 'Choose Your Audience',
        html: `
            <div style="display: flex; flex-direction: column; gap: 12px; text-align: left;">
                <label style="display:flex; align-items:center; gap:12px; padding:14px; border:2px solid #e2e8f0; border-radius:10px; cursor:pointer;" onclick="document.getElementById('sch-all').checked=true;">
                    <input type="radio" id="sch-all" name="audience" value="all" checked>
                    <div><b>All Contacts</b></div>
                </label>
                <label style="display:flex; align-items:center; gap:12px; padding:14px; border:2px solid #e2e8f0; border-radius:10px; cursor:pointer;" onclick="document.getElementById('sch-inq').checked=true;">
                    <input type="radio" id="sch-inq" name="audience" value="inquiries">
                    <div><b>Inquiries Only</b></div>
                </label>
                <label style="display:flex; align-items:center; gap:12px; padding:14px; border:2px solid #e2e8f0; border-radius:10px; cursor:pointer;" onclick="document.getElementById('sch-web').checked=true;">
                    <input type="radio" id="sch-web" name="audience" value="website">
                    <div><b>Website Users Only</b></div>
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Next →',
        preConfirm: () => document.querySelector('input[name="audience"]:checked').value
    });
    if (!audience) return;

    // Step 3: Date & Time
    const { value: scheduleTime } = await Swal.fire({
        title: 'Schedule Date & Time',
        html: `
            <div style="text-align: left;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #64748b;">Select when to send:</label>
                <input type="datetime-local" id="schedule-dt" class="swal2-input" style="width: 100%; margin: 0;" min="${new Date().toISOString().slice(0, 16)}">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Schedule Campaign',
        confirmButtonColor: '#6366f1',
        preConfirm: () => {
            const dt = document.getElementById('schedule-dt').value;
            if (!dt) return Swal.showValidationMessage('Please pick a date and time.');
            if (new Date(dt) <= new Date()) return Swal.showValidationMessage('Please pick a future time.');
            return dt;
        }
    });

    if (scheduleTime) {
        scheduleCampaign(subject, audience, scheduleTime, blocks);
    }
}

async function scheduleCampaign(subject, audience, time, blocks) {
    Swal.fire({ title: 'Scheduling...', didOpen: () => Swal.showLoading() });
    
    const formData = new FormData();
    formData.append('action', 'schedule_campaign');
    formData.append('subject', subject);
    formData.append('audience', audience);
    formData.append('scheduled_at', time);
    formData.append('blocks', JSON.stringify(blocks));

    try {
        const r = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
        const res = await r.json();
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: 'Campaign Locked & Scheduled!',
                html: `
                    <div style="text-align: left; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 15px;">
                        <p style="margin: 0 0 10px 0; color: #1e293b;"><strong>Subject:</strong> ${subject}</p>
                        <p style="margin: 0 0 10px 0; color: #1e293b;"><strong>Send Time:</strong> ${new Date(time).toLocaleString()}</p>
                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0;">
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                            <i class="fas fa-info-circle"></i> A confirmation report will be sent to <b>heydreamtravelandtours@gmail.com</b> once the broadcast is complete.
                        </p>
                    </div>
                `,
                confirmButtonColor: '#003580',
                confirmButtonText: 'Great, Thank You'
            }).then(() => location.reload());
        } else {
            throw new Error(res.message);
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Failed to schedule campaign.', 'error');
    }
}

async function saveTemplate() {
    const { value: name } = await Swal.fire({ title: 'Template Name', input: 'text', showCancelButton: true });
    if (!name) return;
    const formData = new FormData();
    formData.append('action', 'save_template');
    formData.append('name', name);
    formData.append('blocks', JSON.stringify(getBlocksData()));
    try {
        const r = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
        const res = await r.json();
        if (res.success) {
            Swal.fire('Saved', 'Template saved to your library.', 'success');
            loadTemplatesList(); // Refresh the list
        }
        else Swal.fire('Error', res.message, 'error');
    } catch (e) { Swal.fire('Error', 'Failed to save.', 'error'); }
}

function setPreviewSize(size, el) {
    const wrapper = document.getElementById('email-preview-wrapper');
    const controls = document.querySelectorAll('.device-toggles button');
    controls.forEach(b => b.classList.remove('active'));
    
    // Apply the responsive class (preview-desktop, preview-tablet, or preview-mobile)
    wrapper.className = 'preview-' + size;
    
    if (el) el.classList.add('active');
}

// INQUIRY ACTIONS
function filterInquiries() {
    const search = (document.getElementById('inq-search')?.value || '').toLowerCase().trim();
    const type   = (document.getElementById('filter-type')?.value || 'all').toLowerCase();
    const status = (document.getElementById('filter-status')?.value || 'all').toLowerCase();
    
    document.querySelectorAll('.inquiry-row').forEach(row => {
        const rowName   = (row.dataset.name   || '').toLowerCase();
        const rowType   = (row.dataset.type   || '').toLowerCase();
        const rowStatus = (row.dataset.status || '').toLowerCase();
        const rowEmail  = (row.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
        
        const matchSearch = !search || rowName.includes(search) || rowEmail.includes(search);
        const matchType   = type === 'all'   || rowType === type;
        const matchStatus = status === 'all' || rowStatus === status;
        
        row.style.display = (matchSearch && matchType && matchStatus) ? '' : 'none';
    });
}

function exportInquiries() {
    let csv = [];
    let rows = document.querySelectorAll('#inquiries-table tr');
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].style.display === 'none') continue;
        let row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length - 1; j++) { // skip actions
            let data = "";
            
            // Special handling for the Source column (index 0) which contains icons
            if (j === 0 && i > 0) { // i > 0 means it's a data row, not the header
                const icon = cols[j].querySelector('i, img');
                data = icon ? (icon.getAttribute('title') || "Unknown") : "Unknown";
            } else {
                data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ');
            }
            
            row.push('"' + data.trim() + '"');
        }
        if (row.length > 0) csv.push(row.join(','));
    }
    let csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    let downloadLink = document.createElement('a');
    downloadLink.download = 'inquiries.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

// viewInquiry has been moved and updated below (around line 3319)
function closeModal() {
    document.getElementById('inquiry-modal').style.display = 'none';
}

async function toggleMarketingConsent(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const label = newStatus == 1 ? 'Agreed' : 'No Consent';
    
    try {
        const res = await fetch('admin-api.php?action=toggle_marketing_consent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&marketing_consent=${newStatus}`
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Updated', text: `Marketing consent set to ${label}`, timer: 1500, showConfirmButton: false });
            
            // Update modal UI without closing
            const badge = document.getElementById(`consent-badge-${id}`);
            if (badge) {
                badge.className = `status-badge ${newStatus == 1 ? 'status-confirmed' : 'status-cancelled'}`;
                badge.textContent = label;
                // Update the onclick to reflect the new state
                badge.nextElementSibling.onclick = () => toggleMarketingConsent(id, newStatus);
            }
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to update consent.', 'error');
    }
}

async function toggleConsentInTable(id, wrapper, bookingNumber = '') {
    const toggle = wrapper.querySelector('.consent-toggle');
    const label = wrapper.querySelector('.consent-label');
    const currentStatus = toggle.classList.contains('active') ? 1 : 0;
    const newStatus = currentStatus === 1 ? 0 : 1;
    
    // Optimistic UI update
    toggle.classList.toggle('active');
    label.textContent = newStatus === 1 ? 'ON' : 'OFF';

    try {
        const res = await fetch('admin-api.php?action=toggle_marketing_consent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&booking_number=${bookingNumber}&marketing_consent=${newStatus}`
        });
        const data = await res.json();
        if (!data.success) {
            // Revert on error
            toggle.classList.toggle('active');
            label.textContent = currentStatus === 1 ? 'ON' : 'OFF';
            Swal.fire('Error', data.message, 'error');
        } else {
            Toast.fire({ icon: 'success', title: `Consent turned ${newStatus === 1 ? 'ON' : 'OFF'}` });
        }
    } catch (e) {
        // Revert on error
        toggle.classList.toggle('active');
        label.textContent = currentStatus === 1 ? 'ON' : 'OFF';
        Swal.fire('Error', 'Failed to update consent.', 'error');
    }
}

async function updateInquiryStatus(id, selectEl, bookingNumber = '') {
    const newStatus = selectEl.value;
    if (!newStatus) return;

    const labels = { contacted: 'Contacted', confirmed: 'Reviewed', cancelled: 'Cancelled' };
    const label = labels[newStatus] || newStatus;

    const res = await fetch('admin-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_inquiry_status&id=${id}&booking_number=${bookingNumber}&status=${newStatus}`
    });
    const data = await res.json();

    if (data.success) {
        const row = selectEl.closest('tr');
        // Update data-status attribute for filter
        row.dataset.status = newStatus;
        // Update the status badge in the cell
        const statusMap = {
            contacted: { label: 'Contacted', cls: 'status-contacted' },
            confirmed: { label: 'Reviewed', cls: 'status-confirmed' },
            cancelled: { label: 'Cancelled', cls: 'status-cancelled' },
            pending:   { label: 'Pending',   cls: 'status-pending' }
        };
        const info = statusMap[newStatus] || { label: label, cls: 'status-pending' };
        const badge = row.querySelector('.status-badge');
        if (badge) {
            badge.className = `status-badge ${info.cls}`;
            badge.textContent = info.label;
        }
        // Reset dropdown back to placeholder
        selectEl.value = '';

        const msg = data.email_sent
            ? `Status set to "${label}" and a confirmation email was sent to the customer.`
            : `Status set to "${label}".`;
        Swal.fire({ icon: 'success', title: 'Updated!', text: msg, timer: 2500, showConfirmButton: false });
        
        // Refresh stats and re-apply filters immediately
        pollInquiryBadge();
        filterInquiries();
    } else {
        selectEl.value = '';
        Swal.fire('Error', data.message, 'error');
    }
}

// Keep backwards-compat alias
async function markContacted(id) {
    const fakeSelect = { value: 'contacted', closest: () => null };
    await updateInquiryStatus(id, fakeSelect);
    location.reload();
}

async function deleteInquiry(id, bookingNumber = '') {
    const result = await Swal.fire({
        title: 'Delete Inquiry?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        const res = await fetch('api/marketing_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_inquiry&id=${id}&booking_number=${bookingNumber}`
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Deleted!', 'Inquiry has been deleted.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    }
}



async function deleteCampaign(id) {
    const result = await Swal.fire({
        title: 'Delete History Log?',
        text: "This will remove this record from your activity log. It won't affect sent emails.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    });

    if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'delete_campaign');
        formData.append('id', id);
        try {
            const r = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
            const res = await r.json();
            if (res.success) {
                Swal.fire('Deleted!', 'Record removed.', 'success').then(() => location.reload());
            }
        } catch (e) {
            Swal.fire('Error', 'Failed to delete record.', 'error');
        }
    }
}

function renderCampaignBody(c) {
    if (c.body && c.body.trim().length > 0) return c.body;
    if (!c.blocks) return '<p style="padding: 40px; text-align: center; color: #94a3b8;">No preview available.</p>';
    
    try {
        const blocks = typeof c.blocks === 'string' ? JSON.parse(c.blocks) : c.blocks;
        return blocks.map(block => {
            let content = '';
            const align = block.align || 'center';
            const color = block.color || '#000000';
            const bg = block.bg || '#ffffff';
            
            if (block.type === 'header') {
                content = `<div style="background: ${bg}; padding: 20px; text-align: ${align};"><h2 style="color: ${color}; margin: 0; font-family: sans-serif;">${block.text}</h2></div>`;
            } else if (block.type === 'text') {
                content = `<div style="padding: 20px 30px; text-align: ${align};"><p style="color: ${color}; font-size: ${block.size || 16}px; font-weight: ${block.weight || 400}; line-height: 1.6; font-family: sans-serif; margin: 0;">${(block.text || '').replace(/\n/g, '<br>')}</p></div>`;
            } else if (block.type === 'image') {
                const imgHtml = `<img src="${block.url}" style="width: 100%; border-radius: ${block.radius || 0}px; display: block;" alt="Promo">`;
                if (block.caption) {
                    const textHtml = `<div style="color: ${block.capColor || '#64748b'}; font-size: ${block.capSize || 14}px; font-weight: ${block.capWeight || 400}; line-height: 1.5; padding: 10px; text-align: ${align === 'center' ? 'center' : 'left'};">${block.caption.replace(/\n/g, '<br>')}</div>`;
                    content = `<div style="padding: 20px 30px; text-align: center;"><div style="width: ${block.width || 100}%; margin: 0 auto;">${imgHtml}</div>${textHtml}</div>`;
                } else {
                    content = `<div style="padding: 20px 30px; text-align: ${align};"><div style="width: ${block.width || 100}%; display: inline-block;">${imgHtml}</div></div>`;
                }
            } else if (block.type === 'button') {
                content = `<div style="text-align: ${align}; padding: 20px 30px;"><a href="#" style="display: inline-block; background: ${bg}; color: ${color}; padding: ${block.padding || 12}px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;">${block.text}</a></div>`;
            } else if (block.type === 'divider') {
                content = `<div style="padding: 0 30px;"><hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;"></div>`;
            } else if (block.type === 'footer') {
                content = `<div style="padding: 30px; text-align: ${align}; background: #f8fafc;"><p style="font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;">${(block.text || '').replace(/\n/g, '<br>')}</p></div>`;
            }
            return content;
        }).join('');
    } catch (e) {
        return '<p style="padding: 40px; text-align: center; color: #94a3b8;">Error rendering preview.</p>';
    }
}

async function viewCampaign(id) {
    try {
        const r = await fetch(`api/marketing_api.php?action=get_campaign_details&id=${id}`);
        const res = await r.json();
        if (res.success) {
            const c = res.data;
            const openRate = c.sent_count > 0 ? (c.open_count / c.sent_count * 100).toFixed(1) : '0.0';
            
            activeViewingCampaignId = id;

            Swal.fire({
                title: 'Campaign Details',
                width: '800px',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>Subject:</strong> ${c.subject}</p>
                        <p><strong>Date Sent:</strong> ${new Date(c.created_at).toLocaleString()}</p>
                        <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; text-align: center; margin-bottom: 20px;">
                            <div style="background: #f8fafc; padding: 10px; border-radius: 8px;">
                                <div id="modal-sent-count" style="font-size: 1.2rem; font-weight: 700; color: #003580;">${c.sent_count}</div>
                                <div style="font-size: 0.7rem; color: #64748b;">Recipients</div>
                            </div>
                            <div style="background: #f8fafc; padding: 10px; border-radius: 8px;">
                                <div id="modal-click-count" style="font-size: 1.2rem; font-weight: 700; color: #f59e0b;">${c.click_count || 0}</div>
                                <div style="font-size: 0.7rem; color: #64748b;">Total Clicks</div>
                            </div>
                            <div style="background: #e0f2fe; padding: 10px; border-radius: 8px;">
                                <div id="modal-open-rate" style="font-size: 1.2rem; font-weight: 700; color: #0369a1;">${openRate}%</div>
                                <div style="font-size: 0.7rem; color: #0369a1;">Engagement</div>
                            </div>
                        </div>

                         <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                             <div>
                                 <h5 style="margin-bottom: 10px; color: #64748b;">EMAIL PREVIEW:</h5>
                                 <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; max-height: 400px; overflow-y: auto; background: #f1f5f9; padding: 15px;">
                                     <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                                         ${renderCampaignBody(c)}
                                     </div>
                                 </div>
                             </div>
                            <div>
                                <h5 style="margin-bottom: 10px; color: #64748b;">RECIPIENT ACTIVITY (Recent):</h5>
                                <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; max-height: 400px; overflow-y: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                                        <thead style="background: #f8fafc; position: sticky; top: 0;">
                                            <tr>
                                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0;">Email</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${res.logs.length > 0 ? res.logs.map(log => `
                                                <tr>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">${log.email}</td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">
                                                        <span style="color: ${log.action === 'open' ? '#10b981' : '#f59e0b'}; font-weight: 600;">
                                                            ${log.action.toUpperCase()}
                                                        </span>
                                                    </td>
                                                </tr>
                                            `).join('') : '<tr><td colspan="2" style="padding: 20px; text-align: center; color: #94a3b8;">No activity recorded yet.</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                confirmButtonText: 'Close',
                didClose: () => {
                    activeViewingCampaignId = null;
                }
            });
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to load details.', 'error');
    }
}

// ANALYTICS & CHARTS
let sourceChart = null;
let statusChart = null;
let engagementChart = null;
let currentCalDate = new Date();
let allCampaignsForCal = [];
let excludedCampaigns = new Set();
let currentDayCampaigns = [];
let currentSelectedDayStr = null;

async function initAnalytics() {
    // 0. Trigger Scheduled Campaigns check in background
    fetch('api/process_scheduled.php').catch(e => console.error("Schedule check failed", e));

    try {
        // 1. Fetch Source Data
        const sourceRes = await fetch('api/marketing_api.php?action=get_source_analytics');
        const sourceData = await sourceRes.json();
        if (sourceData.success) {
            renderSourceChart(sourceData.data);
        }

        // 2. Fetch Status Data
        const statusRes = await fetch('api/marketing_api.php?action=get_inquiry_stats');
        const statusData = await statusRes.json();
        if (statusData.success) {
            renderStatusChart(statusData);
        }

        // 3. Load all campaigns for calendar & dropdown
        const listRes = await fetch('api/marketing_api.php?action=get_campaign_list');
        const listData = await listRes.json();
        if (listData.success) {
            allCampaignsForCal = listData.data;
            renderCalendar(currentCalDate.getFullYear(), currentCalDate.getMonth());

            // Populate Campaign Selector Dropdown
            const selector = document.getElementById('analytics-campaign-select');
            if (selector) {
                // Clear except "All-Time"
                selector.innerHTML = '<option value="all">Total All-Time</option>';
                allCampaignsForCal.forEach(c => {
                    if (c.status !== 'scheduled') {
                        const date = new Date(c.created_at).toLocaleDateString();
                        const option = document.createElement('option');
                        option.value = c.id;
                        option.textContent = `${c.subject} (${date})`;
                        selector.appendChild(option);
                    }
                });
            }
        }

        // 4. Load default engagement chart (all campaigns)
        await loadCampaignEngagement('all');

    } catch (err) {
        console.error('Failed to load analytics:', err);
    }
}

async function loadGlobalAnalytics() {
    // Clear any day selection UI in calendar
    document.querySelectorAll('.calendar-day-item').forEach(el => {
        el.classList.remove('selected-day');
        el.style.border = 'none';
    });
    
    // Hide the day-specific list
    const container = document.getElementById('selected-day-campaigns');
    if (container) container.style.display = 'none';

    // Load all data
    await loadCampaignEngagement('all');
}

async function loadCampaignEngagement(campaignId, isMultiple = false) {
    try {
        let url = 'api/marketing_api.php?action=get_campaign_analytics';
        if (isMultiple) {
            url += `&campaign_ids=${campaignId}`;
        } else if (campaignId !== 'all') {
            url += `&campaign_id=${campaignId}`;
        }

        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            // Update subtitle
            const subtitle = document.querySelector('#analytics .card:last-child p');
            if (subtitle) {
                if (isMultiple) {
                    subtitle.textContent = `Showing aggregate clicks for selected campaigns on ${currentSelectedDayStr}.`;
                } else if (campaignId === 'all') {
                    subtitle.textContent = 'Total email clicks across all campaigns, broken down by social media source.';
                } else {
                    const camp = allCampaignsForCal.find(c => c.id == campaignId);
                    const subject = camp ? camp.subject : 'Selected Campaign';
                    subtitle.textContent = `Showing clicks for: ${subject}`;
                }
            }
            renderEngagementChart(data.data);
        }
    } catch (err) {
        console.error('Failed to load campaign engagement:', err);
    }
}

function renderSourceChart(data) {
    const canvas = document.getElementById('sourceChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const container = canvas.parentElement;
    
    // Cleanup previous chart instance
    if (sourceChart) {
        sourceChart.destroy();
        sourceChart = null;
    }
    
    // Calculate total inquiries
    const labels = Object.keys(data);
    const counts = labels.map(l => data[l].inquiries || 0);
    const totalInquiries = counts.reduce((a, b) => a + b, 0);

    // Remove any existing no-data message
    const existingMsg = container.querySelector('.no-data-msg');
    if (existingMsg) existingMsg.remove();

    if (totalInquiries === 0) {
        canvas.style.display = 'none';
        const msg = document.createElement('div');
        msg.className = 'no-data-msg';
        msg.style.cssText = 'height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; text-align: center;';
        msg.innerHTML = `
            <i class="fas fa-chart-pie" style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.2;"></i>
            <h4 style="margin: 0; color: #64748b;">No Social Media Data</h4>
            <p style="font-size: 0.85rem; margin: 5px 0 0;">Inquiry tracking will appear here once users submit forms.</p>
        `;
        container.appendChild(msg);
        return;
    }

    // Show canvas and render chart
    canvas.style.display = 'block';
    sourceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: ['#1877F2', '#E4405F', '#1DA1F2', '#000000', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: { size: 12, weight: '600' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.label}: ${context.raw} Inquiries`;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}

function renderStatusChart(stats) {
    const ctx = document.getElementById('statusChart')?.getContext('2d');
    if (!ctx) return;
    
    if (statusChart) statusChart.destroy();
    
    statusChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Pending', 'Reviewed'],
            datasets: [{
                label: 'Inquiries',
                data: [stats.pending, stats.confirmed],
                backgroundColor: ['#f59e0b', '#003580'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderEngagementChart(data) {
    const ctx = document.getElementById('engagementChart')?.getContext('2d');
    if (!ctx) return;
    
    const labels = Object.keys(data);
    const clicks = Object.values(data);

    if (engagementChart) {
        engagementChart.data.labels = labels;
        engagementChart.data.datasets[0].data = clicks;
        engagementChart.update();
        return;
    }
    
    engagementChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Email Clicks',
                data: clicks,
                backgroundColor: [
                    '#1877f2', // Facebook
                    '#E4405F', // Instagram
                    '#1DA1F2', // Twitter
                    '#000000', // TikTok
                    '#94a3b8'  // Other
                ],
                borderRadius: 8,
                maxBarThickness: 100
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { stepSize: 1 }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// EMAIL BUILDER HISTORY (Undo/Redo)
let builderHistory = [];
let builderRedoStack = [];
const MAX_HISTORY = 30;

function saveHistory() {
    const canvas = document.getElementById('builder-canvas');
    if (!canvas) return;
    
    const state = canvas.innerHTML;
    // Don't save if same as last
    if (builderHistory.length > 0 && builderHistory[builderHistory.length - 1] === state) return;
    
    builderHistory.push(state);
    if (builderHistory.length > MAX_HISTORY) builderHistory.shift();
    
    // Clear redo stack on new action
    builderRedoStack = [];
    updateHistoryButtons();
}

function undo() {
    if (builderHistory.length <= 1) return;
    
    const currentState = builderHistory.pop();
    builderRedoStack.push(currentState);
    
    const previousState = builderHistory[builderHistory.length - 1];
    document.getElementById('builder-canvas').innerHTML = previousState;
    
    updatePreview(false); // Don't save to history again
    updateHistoryButtons();
}

function redo() {
    if (builderRedoStack.length === 0) return;
    
    const nextState = builderRedoStack.pop();
    builderHistory.push(nextState);
    
    document.getElementById('builder-canvas').innerHTML = nextState;
    
    updatePreview(false); // Don't save to history again
    updateHistoryButtons();
}

function updateHistoryButtons() {
    const undoBtn = document.getElementById('undo-btn');
    const redoBtn = document.getElementById('redo-btn');
    if (undoBtn) undoBtn.disabled = builderHistory.length <= 1;
    if (redoBtn) redoBtn.disabled = builderRedoStack.length === 0;
}


function startDashboardPolling() {
    pollInquiryBadge();
    pollSchedules();
    updateDashboardTables();
    
    // Initialize Dashboard Analytics if canvas exists
    if (document.getElementById('dashboardEngagementChart')) {
        initDashboardAnalytics();
    }

    setInterval(() => {
        pollInquiryBadge();
        pollSchedules();
        updateDashboardTables();
    }, 5000);
}

async function pollInquiryBadge() {
    try {
        const seenCount = parseInt(sessionStorage.getItem('inq_seen_count') || '0');
        const res = await fetch('api/marketing_api.php?action=get_inquiry_stats');
        const data = await res.json();
        
        if (data.success) {
            const updateValue = (id, newValue) => {
                const el = document.getElementById(id);
                if (el && el.textContent != newValue) {
                    el.textContent = newValue;
                    el.classList.remove('value-updated');
                    void el.offsetWidth; // Trigger reflow
                    el.classList.add('value-updated');
                }
            };

            const updateTrend = (id, percentage) => {
                const el = document.getElementById(id);
                if (!el) return;
                
                const absPercent = Math.abs(percentage);
                let trendHtml = '';
                
                if (percentage > 0) {
                    el.className = 'metric-trend trend-up';
                    trendHtml = `<i class="fas fa-arrow-up"></i> ${absPercent}% <span style="font-weight: 400; color: #94a3b8; margin-left: 2px;">from last week</span>`;
                } else if (percentage < 0) {
                    el.className = 'metric-trend trend-down';
                    trendHtml = `<i class="fas fa-arrow-down"></i> ${absPercent}% <span style="font-weight: 400; color: #94a3b8; margin-left: 2px;">from last week</span>`;
                } else {
                    el.className = 'metric-trend trend-neutral';
                    trendHtml = `No change <span style="font-weight: 400; color: #94a3b8; margin-left: 2px;">this week</span>`;
                }
                el.innerHTML = trendHtml;
            };

            // Update Dashboard Stats with animation
            updateValue('stat-total-inquiries', data.total);
            updateValue('stat-pending-inquiries', data.pending);
            updateValue('stat-confirmed-inquiries', data.confirmed);
            updateValue('stat-template-count', data.templates);

            // Update Trends
            if (data.trends) {
                updateTrend('trend-total-inquiries', data.trends.total);
                updateTrend('trend-pending-inquiries', data.trends.pending);
                updateTrend('trend-confirmed-inquiries', data.trends.confirmed);
            }

            // Update Sidebar Badge
            const newCount = data.pending;
            const badge = document.getElementById('inq-badge');
            const menuInq = document.getElementById('menu-inquiries');
            
            if (newCount > seenCount) {
                if (!badge) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'inq-badge';
                    newBadge.id = 'inq-badge';
                    newBadge.textContent = newCount;
                    if (menuInq) menuInq.appendChild(newBadge);
                } else {
                    badge.textContent = newCount;
                    badge.style.display = 'inline-flex';
                }
            } else if (badge && newCount <= seenCount) {
                badge.style.display = 'none';
            }
        }
    } catch (e) {
        console.error("Badge polling failed:", e);
    }
}

async function updateDashboardTables() {
    try {
        const response = await fetch('api/marketing_api.php?action=get_dashboard_data');
        const res = await response.json();
        if (res.success) {

            // Update Campaign Table
            const campTbody = document.querySelector('#dashboard-campaign-table tbody');
            if (campTbody) {
                campTbody.innerHTML = res.campaigns.map(c => {
                    const openRate = c.sent_count > 0 ? (c.open_count / c.sent_count * 100).toFixed(1) : '0.0';
                    return `
                        <tr>
                            <td><strong>${c.subject}</strong></td>
                            <td>${c.sent_count} Recipients</td>
                            <td>${openRate}%</td>
                            <td><span class="status-badge status-confirmed">${c.status || 'Sent'}</span></td>
                            <td>${new Date(c.created_at).toLocaleDateString()}</td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-outline btn-sm" onclick="viewCampaign(${c.id})" title="View Details"><i class="fas fa-eye"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('') || '<tr><td colspan="6" style="text-align: center; padding: 20px;">No campaign activity.</td></tr>';
            }
        }
    } catch (e) {
        console.error("Dashboard table poll failed:", e);
    }
}

// Helper to match PHP strtolower
function strtolower(str) {
    return str ? str.toLowerCase() : '';
}


async function viewInquiry(id, bookingNumber = '') {
    const modal = document.getElementById('inquiry-modal');
    const content = document.getElementById('modal-content-area');
    if (!modal || !content) return;

    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading inquiry details...</div>';

    try {
        const response = await fetch(`api/marketing_api.php?action=get_inquiry&id=${id}&booking_number=${bookingNumber}&_t=${Date.now()}`);
        const res = await response.json();
        if (res.success) {
            const inq = res.data;
            function formatMessageDetails(text) {
                if (!text) return 'No message provided.';
                // Check if it's a comma-separated key-value list
                if (text.includes(':') && text.includes(', ')) {
                    const parts = text.split(/,\s*(?=[A-Z][a-zA-Z\s]+:)/);
                    if (parts.length > 1) {
                        return '<div style="display: flex; flex-direction: column; gap: 8px;">' + parts.map(p => {
                            const splitIdx = p.indexOf(':');
                            if (splitIdx > 0) {
                                const k = p.substring(0, splitIdx).trim();
                                const v = p.substring(splitIdx + 1).trim();
                                return `<div style="display: flex; align-items: flex-start; gap: 15px; border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                                            <strong style="width: 120px; flex-shrink: 0; color: #64748b; font-size: 0.82rem; text-transform: uppercase;">${k}</strong>
                                            <span style="color: #334155; font-size: 0.9rem; flex: 1; word-break: break-word;">${v || '-'}</span>
                                        </div>`;
                            }
                            return `<div style="font-size: 0.9rem; color: #334155;">${p}</div>`;
                        }).join('') + '</div>';
                    }
                }
                return text.replace(/^([A-Za-z\s]+):/gm, '<strong style="color:#64748b; font-size:0.85rem; text-transform:uppercase;">$1:</strong>').replace(/\n/g, '<br><div style="margin-top:5px;"></div>');
            }

            content.innerHTML = `
                <div class="inquiry-details" style="padding: 10px; font-family: 'Poppins', sans-serif;">
                    <!-- CUSTOMER OVERVIEW -->
                    <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;">
                        <div style="flex: 1; min-width: 250px;">
                            <h3 style="margin: 0; color: #003580; font-size: 1.4rem; word-break: break-word;">${inq.full_name}</h3>
                            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 8px;">
                                <span style="font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-envelope"></i> <span style="word-break: break-all;">${inq.email}</span>
                                </span>
                                <span style="font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-phone"></i> <span>${inq.phone || 'No phone'}</span>
                                </span>
                            </div>
                        </div>
                        <div style="min-width: 130px;">
                            <label style="display:block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 5px;">Manage Status</label>
                            <select class="inq-status-select" onchange="updateInquiryStatus(${inq.id}, this.value, '${inq.booking_number || ''}')" style="width: 100%; border: 2px solid #e2e8f0; font-weight: 700; padding: 6px 10px; border-radius: 6px; outline: none; cursor: pointer;">
                                <option value="pending" ${inq.booking_status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="confirmed" ${inq.booking_status === 'confirmed' ? 'selected' : ''}>Reviewed</option>
                                <option value="cancelled" ${inq.booking_status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <!-- TRAVEL DETAILS GRID -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px;">
                        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #f1f5f9;">
                            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Destination</div>
                            <div style="font-weight: 700; color: #1e293b; margin-top: 4px; word-break: break-word;">${inq.destination || 'Unspecified'}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #f1f5f9;">
                            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Travel Date</div>
                            <div style="font-weight: 700; color: #1e293b; margin-top: 4px;">${inq.travel_date ? new Date(inq.travel_date).toLocaleDateString() : 'TBA'}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #f1f5f9;">
                            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Travelers</div>
                            <div style="font-weight: 700; color: #1e293b; margin-top: 4px;">${inq.number_of_travelers || 1} Person(s)</div>
                        </div>
                    </div>

                    <!-- INQUIRY MESSAGE -->
                    <div style="background: #fff; border: 2px solid #e0f2fe; padding: 20px; border-radius: 16px; position: relative;">
                        <label style="display:block; font-size: 0.75rem; color: #0369a1; font-weight: 800; text-transform: uppercase; margin-bottom: 15px;">
                            <i class="fas fa-comment-dots"></i> Message Details
                        </label>
                        <div style="line-height: 1.6; max-height: 300px; overflow-y: auto; padding-right: 5px;">
                            ${formatMessageDetails(inq.special_requests)}
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: center; font-size: 0.75rem; color: #94a3b8;">
                        Received on ${new Date(inq.created_at).toLocaleString()}
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `<div style="text-align: center; padding: 40px; color: #ef4444;">${res.message}</div>`;
        }
    } catch (e) {
        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Failed to load details.</div>';
    }
}

async function updateInquiryStatus(id, statusOrEl, bookingNumber = '') {
    const status = typeof statusOrEl === 'string' ? statusOrEl : statusOrEl.value;
    if (!status) return; // Ignore empty selection
    
    const formData = new FormData();
    formData.append('action', 'update_inquiry_status');
    formData.append('id', id);
    formData.append('booking_number', bookingNumber);
    formData.append('status', status);

    try {
        const response = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
        const res = await response.json();
        if (res.success) {
            Toast.fire({ icon: 'success', title: 'Status updated', timer: 1000 }).then(() => {
                location.reload();
            });
        }
    } catch (e) {}
}

// Consolidated Modal Handlers
function closeModal() {
    activeViewingCampaignId = null;
    const modal = document.getElementById('inquiry-modal');
    if (modal) modal.style.display = 'none';
}

async function initDashboardAnalytics() {
    try {
        const response = await fetch('api/marketing_api.php?action=get_dashboard_data');
        const res = await response.json();
        if (res.success) {
            // Render small engagement chart for dashboard
            const ctx = document.getElementById('dashboardEngagementChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: res.campaigns.slice(0, 7).reverse().map(c => new Date(c.created_at).toLocaleDateString(undefined, {month:'short', day:'numeric'})),
                    datasets: [{
                        label: 'Engagement Rate (%)',
                        data: res.campaigns.slice(0, 7).reverse().map(c => c.sent_count > 0 ? (c.open_count / c.sent_count * 100) : 0),
                        borderColor: '#003580',
                        backgroundColor: 'rgba(0, 53, 128, 0.05)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Populate Calendar in dashboard
            renderDashboardCalendar();
        }
    } catch (e) {
        console.error("Dashboard analytics failed:", e);
    }
}

function renderDashboardCalendar() {
    const container = document.getElementById('dashboard-calendar-box');
    if (!container) return;
    
    // Minimal version of the calendar for dashboard
    const now = new Date();
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    
    container.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <b style="color: #003580;">${monthNames[now.getMonth()]} ${now.getFullYear()}</b>
            <span style="font-size: 0.7rem; color: #64748b;">Live Overview</span>
        </div>
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; border: 1px solid #f1f5f9; border-radius: 8px; overflow: hidden;">
            ${['S','M','T','W','T','F','S'].map(d => `<div style="background: #f8fafc; padding: 4px; font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-align: center;">${d}</div>`).join('')}
            ${Array(35).fill(0).map((_, i) => `<div style="height: 25px; background: white; border: 0.5px solid #f1f5f9;"></div>`).join('')}
        </div>
        <p style="font-size: 0.7rem; color: #64748b; margin-top: 10px; line-height: 1.4;">
            <i class="fas fa-info-circle"></i> Showing current month overview. Visit the Analytics tab for detailed scheduling.
        </p>
    `;
}

// AI FUNCTIONS
async function aiImproveText(blockId, selector) {
    const block = document.getElementById(blockId);
    const textarea = block.querySelector(selector);
    const originalText = textarea.value;
    
    if (!originalText.trim()) return Swal.fire('Notice', 'Enter some text first so I can improve it!', 'info');

    Swal.fire({
        title: 'AI is thinking...',
        html: '<div class="ai-loader" style="margin: 20px auto;"></div><p>Refining your message for better engagement...</p>',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        // Simulate AI call
        await new Promise(r => setTimeout(r, 1500));
        
        // Simple "Magic" improvement logic for demonstration
        let improved = originalText
            .replace(/\b(good|nice|ok)\b/gi, 'amazing')
            .replace(/\b(cheap|low cost)\b/gi, 'exclusive')
            .replace(/\b(buy|get)\b/gi, 'discover')
            .replace(/\?$/, '! Your dream starts here.');
        
        if (improved === originalText) {
            improved = "✨ Exclusive Offer: " + originalText + " - Experience the magic of HeyDream Travel!";
        }

        Swal.fire({
            title: 'AI Suggestion',
            text: improved,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Use this version',
            cancelButtonText: 'Keep original'
        }).then((result) => {
            if (result.isConfirmed) {
                textarea.value = improved;
                updateBlockData(selector, improved);
                Toast.fire({ icon: 'success', title: 'Content optimized!' });
            }
        });
    } catch (e) {
        Swal.fire('Error', 'The AI is currently resting. Please try again later.', 'error');
    }
}


async function pollSchedules() {
    try {
        const r = await fetch('api/marketing_api.php?action=get_scheduled_count');
        const res = await r.json();
        const badge = document.getElementById('schedule-badge');
        if (badge) {
            if (res.count > 0) {
                badge.innerText = res.count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (e) {}
}

async function viewUpcomingSchedules() {
    try {
        const r = await fetch('api/marketing_api.php?action=get_upcoming_schedules');
        const res = await r.json();
        
        if (!res.success || res.data.length === 0) {
            Swal.fire('No Schedules', 'There are no campaigns waiting to be sent.', 'info');
            return;
        }

        const listHtml = res.data.map(c => `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; text-align: left;">
                <div style="overflow: hidden;">
                    <div style="font-weight: 700; font-size: 0.9rem; color: #003580; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">${c.subject}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                        <i class="fas fa-clock"></i> ${new Date(c.scheduled_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </div>
                </div>
                <button onclick="cancelCampaign(${c.id})" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 8px; padding: 6px 12px;">
                    <i class="fas fa-trash-alt"></i> Cancel
                </button>
            </div>
        `).join('');

        Swal.fire({
            title: 'Upcoming Schedules',
            html: `<div style="max-height: 400px; overflow-y: auto;">${listHtml}</div>`,
            showConfirmButton: false,
            showCloseButton: true
        });
    } catch (e) {
        Swal.fire('Error', 'Could not load schedules.', 'error');
    }
}

async function cancelCampaign(id) {
    const result = await Swal.fire({
        title: 'Cancel Schedule?',
        text: 'This campaign will be deleted from the queue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, cancel it'
    });

    if (result.isConfirmed) {
        try {
            const r = await fetch('api/marketing_api.php?action=delete_campaign', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const res = await r.json();
            if (res.success) {
                Swal.fire('Cancelled', 'Schedule removed.', 'success');
                pollSchedules();
                if (document.querySelector('.section-container.active')?.id === 'analytics') {
                    refreshAnalytics();
                }
            }
        } catch (e) {}
    }
}

async function refreshActiveCampaignModal() {
    if (!activeViewingCampaignId) return;
    
    try {
        const r = await fetch(`api/marketing_api.php?action=get_campaign_details&id=${activeViewingCampaignId}`);
        const res = await r.json();
        if (res.success) {
            const c = res.data;
            const openRate = c.sent_count > 0 ? (c.open_count / c.sent_count * 100).toFixed(1) : '0.0';
            
            // Update the values in the existing Swal modal if elements exist
            const sentEl = document.getElementById('modal-sent-count');
            const clickEl = document.getElementById('modal-click-count');
            const rateEl = document.getElementById('modal-open-rate');
            
            if (sentEl) sentEl.innerText = c.sent_count;
            if (clickEl) clickEl.innerText = c.click_count || 0;
            if (rateEl) rateEl.innerText = openRate + '%';
        }
    } catch (e) {
        console.error('Modal refresh error:', e);
    }
}


// CALENDAR LOGIC
function renderCalendar(year, month) {
    const daysContainer = document.getElementById('calendar-days');
    const monthLabel = document.getElementById('calendar-month');
    if (!daysContainer) return;

    const date = new Date(year, month, 1);
    const monthName = date.toLocaleString('default', { month: 'long' });
    monthLabel.innerText = `${monthName} ${year}`;

    daysContainer.innerHTML = '';

    // First day of month (0-6)
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Padding for first day
    for (let i = 0; i < firstDay; i++) {
        const div = document.createElement('div');
        daysContainer.appendChild(div);
    }

    const today = new Date();
    const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;

    // Days
    for (let d = 1; d <= daysInMonth; d++) {
        const div = document.createElement('div');
        div.innerText = d;
        div.style.padding = '8px';
        div.style.borderRadius = '8px';
        div.style.fontSize = '0.85rem';
        div.style.cursor = 'pointer';
        div.style.transition = 'all 0.2s';
        div.style.fontWeight = '600';
        div.style.color = '#334155';
        div.style.position = 'relative';

        // Fix: Use local date parts instead of toISOString to avoid timezone issues
        const dayDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        
        const campaignsOnDay = allCampaignsForCal.filter(c => {
            const dateStr = (c.status === 'scheduled' && c.scheduled_at) ? c.scheduled_at : c.created_at;
            return dateStr && typeof dateStr === 'string' && dateStr.startsWith(dayDate);
        });

        const hasScheduled = campaignsOnDay.some(c => c.status === 'scheduled');
        const hasSent = campaignsOnDay.some(c => c.status === 'sent' || !c.status);

        if (hasScheduled) {
            // Yellow Highlight for Pending/Scheduled (Higher Priority)
            div.style.background = '#fef9c3'; 
            div.style.color = '#854d0e';
            div.style.boxShadow = '0 2px 4px rgba(133, 77, 14, 0.1)';
            div.title = `${campaignsOnDay.length} Campaign(s) - Some Scheduled`;
        } else if (hasSent) {
            // Light Green Highlight for Completed/Sent
            div.style.background = '#dcfce7'; 
            div.style.color = '#166534';
            div.style.boxShadow = '0 2px 4px rgba(22, 101, 52, 0.1)';
            div.title = `${campaignsOnDay.length} Campaign(s) sent`;
        }

        div.onclick = () => {
            // Remove previous selection
            document.querySelectorAll('.calendar-day-item').forEach(el => {
                el.classList.remove('selected-day');
            });
            
            // Mark as selected
            div.classList.add('selected-day');
            
            if (campaignsOnDay.length > 0) {
                const chartArea = document.querySelector('.analytics-layout > div:first-child');
                if (chartArea) chartArea.style.opacity = '1';
                showDayCampaigns(dayDate, campaignsOnDay);
                
                // Automatically show the total aggregate graph for the day
                loadTotalDayClicks();
            } else {
                const container = document.getElementById('selected-day-campaigns');
                if (container) container.style.display = 'none';
                
                // Hide chart area if no campaigns
                const chartArea = document.querySelector('.analytics-layout > div:first-child');
                if (chartArea) chartArea.style.opacity = '0';
            }
        };

        div.classList.add('calendar-day-item');
        daysContainer.appendChild(div);
    }
}

function changeMonth(dir) {
    currentCalDate.setMonth(currentCalDate.getMonth() + dir);
    renderCalendar(currentCalDate.getFullYear(), currentCalDate.getMonth());
}

function showDayCampaigns(dateStr, campaigns) {
    const container = document.getElementById('selected-day-campaigns');
    const list = document.getElementById('day-campaign-list');
    if (!container || !list) return;

    currentDayCampaigns = campaigns;
    currentSelectedDayStr = dateStr;
    container.style.display = 'block';
    
    renderDayCampaignList();
    
    // Smooth scroll to list
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function renderDayCampaignList() {
    const list = document.getElementById('day-campaign-list');
    if (!list) return;

    const campaigns = currentDayCampaigns;
    
    let html = `
        <button class="btn btn-primary btn-sm" onclick="loadTotalDayClicks()" style="width: 100%; margin-bottom: 15px; background: #0ea5e9; border: none; font-weight: 700; border-radius: 12px; padding: 10px; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2);">
            <i class="fas fa-chart-line"></i> View Aggregate Engagement
        </button>
    `;

    html += campaigns.map(c => {
        const isExcluded = excludedCampaigns.has(c.id);
        const isScheduled = c.status === 'scheduled';
        const bgColor = isExcluded ? '#f1f5f9' : (isScheduled ? '#fffbeb' : '#f8fafc');
        const textColor = isExcluded ? '#94a3b8' : (isScheduled ? '#b45309' : 'var(--primary)');
        const opacity = isExcluded ? '0.6' : '1';
        const grayscale = isExcluded ? 'grayscale(100%)' : 'none';

        return `
            <div onclick="${(isExcluded || isScheduled) ? '' : `loadCampaignEngagement(${c.id})`}"
                 style="background: ${bgColor}; padding: 16px; border-radius: 16px; border: 1px solid ${isScheduled ? '#fde68a' : '#e2e8f0'}; display: flex; align-items: center; gap: 12px; filter: ${grayscale}; opacity: ${opacity}; cursor: ${(isExcluded || isScheduled) ? 'default' : 'pointer'}; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);"
                 onmouseover="this.style.transform='translateY(-2px)'"
                 onmouseout="this.style.transform='translateY(0)'"
                 title="${isExcluded ? 'Un-hide to analyze' : (isScheduled ? 'Scheduled Campaign' : 'Click to analyze engagement')}">
                
                <div style="flex: 1; display: flex; flex-direction: column; gap: 4px; overflow: hidden;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: ${textColor}; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">${c.subject}</div>
                    <div style="font-size: 0.7rem; color: #64748b; display: flex; align-items: center; gap: 5px;">
                        <i class="fas ${isScheduled ? 'fa-calendar-alt' : 'fa-clock'}" style="font-size: 0.65rem;"></i>
                        ${isScheduled ? 'Scheduled for ' : ''}${new Date(isScheduled ? c.scheduled_at : c.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                        ${isScheduled ? ' <span class="status-badge status-pending" style="font-size: 0.6rem; padding: 2px 6px; border-radius: 4px;">Queued</span>' : ''}
                    </div>
                </div>
                
                <div style="display: flex; gap: 8px; align-items: center;">
                    <!-- View Details/Preview Button -->
                    <button onclick="event.stopPropagation(); viewCampaign(${c.id})" 
                            style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; border-radius: 10px; padding: 6px 10px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" 
                            title="View Details & Preview"
                            onmouseover="this.style.background='#bae6fd'"
                            onmouseout="this.style.background='#e0f2fe'">
                        <i class="fas fa-eye"></i>
                    </button>

                    ${isScheduled ? `
                        <button onclick="event.stopPropagation(); cancelCampaign(${c.id})" 
                                style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; border-radius: 10px; padding: 6px 10px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" 
                                title="Cancel Schedule"
                                onmouseover="this.style.background='#fecaca'"
                                onmouseout="this.style.background='#fee2e2'">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : `
                        <button onclick="event.stopPropagation(); toggleCampaignExclusion(${c.id})" 
                                style="background: #f1f5f9; border: 1px solid #e2e8f0; color: ${isExcluded ? '#94a3b8' : '#64748b'}; border-radius: 10px; padding: 6px 10px; cursor: pointer; transition: all 0.2s;" 
                                title="${isExcluded ? 'Include in total' : 'Hide from total'}"
                                onmouseover="this.style.background='#e2e8f0'"
                                onmouseout="this.style.background='#f1f5f9'">
                            <i class="fas ${isExcluded ? 'fa-eye-slash' : 'fa-chart-pie'}"></i>
                        </button>
                    `}
                </div>
            </div>
        `;
    }).join('');

    list.innerHTML = html;
}

function toggleCampaignExclusion(id) {
    if (excludedCampaigns.has(id)) {
        excludedCampaigns.delete(id);
    } else {
        excludedCampaigns.add(id);
    }
    renderDayCampaignList();
    
    // Automatically update the graph results
    loadTotalDayClicks();
}

function loadTotalDayClicks() {
    const includedIds = currentDayCampaigns
        .map(c => c.id)
        .filter(id => !excludedCampaigns.has(id));
    
    if (includedIds.length === 0) {
        Swal.fire('Notice', 'No campaigns selected to aggregate.', 'info');
        return;
    }
    
    loadCampaignEngagement(includedIds.join(','), true);
}

async function cancelCampaign(id) {
    const result = await Swal.fire({
        title: 'Cancel Schedule?',
        text: "This will stop the email from being sent. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Cancel and Delete'
    });

    if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'delete_campaign');
        formData.append('id', id);

        try {
            const r = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
            const res = await r.json();
            if (res.success) {
                Swal.fire('Cancelled', 'The scheduled campaign has been removed.', 'success').then(() => location.reload());
            } else {
                throw new Error(res.message);
            }
        } catch (e) {
            Swal.fire('Error', e.message || 'Failed to cancel schedule.', 'error');
        }
    }
}


// Key listeners for Undo/Redo
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 'z') {
        e.preventDefault();
        undo();
    }
    if (e.ctrlKey && e.key === 'y') {
        e.preventDefault();
        redo();
    }
});

// Premium Marketing Health Check (LanguageTool + Local Spam Detection)
document.getElementById("spamCheckBtn").addEventListener("click", async () => {
    // 1. Sync builder blocks to the bridge textarea
    const blocksData = getBlocksData();
    const fullText = blocksData.map(b => {
        let t = '';
        if (b.type === 'header') t = b.text;
        else if (b.type === 'text') t = b.text;
        else if (b.type === 'image') t = b.caption;
        else if (b.type === 'button') t = b.text;
        else if (b.type === 'footer') t = b.text;
        return t || '';
    }).join('\n\n');
    
    document.getElementById("emailContent").value = fullText;
    const content = document.getElementById("emailContent").value.toLowerCase();

    // 2. Show Loading Modal
    Swal.fire({
        title: 'Analyzing Campaign...',
        html: 'Checking grammar, spelling, and spam triggers...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // A. Local Spam Word Detection (with offsets for highlighting)
        const spamWords = ['free', 'winner', 'cash', 'prize', 'urgent', 'act now', 'limited time', '100%', 'guaranteed', 'no cost', 'click here', 'congratulations', 'earn money', 'extra income', 'billion', 'million', 'investment'];
        const spamIssues = [];
        const contentLower = fullText.toLowerCase();
        
        spamWords.forEach(word => {
            let index = contentLower.indexOf(word);
            while (index !== -1) {
                spamIssues.push({
                    offset: index,
                    length: word.length,
                    message: `Spam Trigger Word Found: "${word}"`,
                    replacements: [{value: 'Consider alternative wording'}],
                    isSpam: true
                });
                index = contentLower.indexOf(word, index + 1);
            }
        });

        const detectedSpamWords = [...new Set(spamIssues.map(s => s.message.split('"')[1]))];

        // B. LanguageTool Check
        let linguisticIssues = [];
        try {
            const response = await fetch("spam_check.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ content: fullText })
            });

            if (response.ok) {
                const data = await response.json();
                linguisticIssues = data.matches || [];
            }
        } catch (apiErr) {
            console.warn("Linguistic API unavailable, falling back to local spam check only.");
        }

        // 4. Custom Audit Whitelist (Ignore brand names, PH locations, and travel slang)
        const brandNames = ['heydream', 'heydreamtravel'];
        const phLocations = [
            'philippines', 'philippines\'', 'boracay', 'palawan', 'puerto princesa', 'cebu', 'bohol', 'siargao', 
            'manila', 'baguio', 'davao', 'batanes', 'sagada', 'tagaytay', 'vigan', 
            'iloilo', 'bacolod', 'leyte', 'samar', 'zamboanga', 'camiguin', 'coron', 
            'el nido', 'subic', 'clark', 'pampanga', 'batangas', 'moalboal', 'bantayan',
            'dumaguete', 'siquijor', 'banaue', 'pagudpud', 'la union'
        ];
        const travelSlang = ['promo', 'vacay', 'piso', 'fare'];

        const fullWhitelist = [...brandNames, ...phLocations, ...travelSlang];
        const flatWords = fullWhitelist.flatMap(loc => loc.split(' '));

        linguisticIssues = linguisticIssues.filter(issue => {
            const word = fullText.substring(issue.offset, issue.offset + issue.length).toLowerCase().trim();
            // Check if exact match OR if it's part of our whitelisted words
            return !fullWhitelist.includes(word) && !flatWords.includes(word);
        });

        const allIssues = [...spamIssues, ...linguisticIssues];

        // 5. Build Result UI
        let statusColor = spamIssues.length > 0 || linguisticIssues.length > 5 ? '#ef4444' : (linguisticIssues.length > 0 ? '#f59e0b' : '#10b981');
        let statusText = spamIssues.length > 0 ? 'High Spam Risk' : (linguisticIssues.length > 0 ? 'Needs Improvement' : 'Perfectly Optimized');
        let rating = 100 - (spamIssues.length * 10) - (linguisticIssues.length * 2);

        const reportHtml = `
            <div style="text-align: left; background: #f8fafc; padding: 30px; border-radius: 24px; border: 1px solid #e2e8f0; font-family: 'Poppins', sans-serif;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
                    <div>
                        <h3 style="margin: 0; color: #1e293b; font-weight: 800;">Campaign Dashboard</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Instant local analysis of your campaign quality</p>
                    </div>
                    <div style="text-align: right;">
                        <span style="padding: 8px 24px; border-radius: 50px; background: ${statusColor}; color: white; font-weight: 900; font-size: 1rem;">${statusText}</span>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: white; padding: 18px; border-radius: 18px; border: 1px solid #f1f5f9;">
                        <div style="font-weight: 800; font-size: 0.85rem; color: #003580; margin-bottom: 10px;"><i class="fas fa-search"></i> Content Audit</div>
                        <div style="font-size: 0.75rem; color: #475569; line-height: 1.6;">
                            ${spamIssues.length > 0 ? `<i class="fas fa-times-circle" style="color:#ef4444"></i> Spam Triggers: ${detectedSpamWords.join(', ')}<br>` : '<i class="fas fa-check-circle" style="color:#10b981"></i> Security: Optimal<br>'}
                            ${linguisticIssues.length > 0 ? `<i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> Found ${linguisticIssues.length} linguistic issues<br>` : '<i class="fas fa-check-circle" style="color:#10b981"></i> Language: Professional<br>'}
                        </div>
                    </div>
                    <div style="background: white; padding: 18px; border-radius: 18px; border: 1px solid #f1f5f9;">
                        <div style="font-weight: 800; font-size: 0.85rem; color: #8b5cf6; margin-bottom: 10px;"><i class="fas fa-lightbulb"></i> Strategy Insights</div>
                        <div style="font-size: 0.75rem; color: #475569; line-height: 1.6;">
                            <i class="fas fa-check" style="color:#10b981"></i> Score: ${rating}/100<br>
                            <i class="fas fa-info-circle" style="color:#3b82f6"></i> ${statusText}
                        </div>
                    </div>
                </div>

                ${allIssues.length > 0 ? `
                <div style="margin-top: 25px;">
                    <button id="review-ai-btn" style="width: 100%; padding: 12px; border-radius: 10px; background: #8b5cf6; color: white; border: none; font-weight: 700; cursor: pointer; margin-bottom: 10px;">
                        <i class="fas fa-magic"></i> Optimize It
                    </button>
                    <button id="highlight-errors-btn" style="width: 100%; padding: 12px; border-radius: 10px; background: #003580; color: white; border: none; font-weight: 700; cursor: pointer; margin-bottom: 15px;">
                        <i class="fas fa-highlighter"></i> Highlight All Issues on Canvas
                    </button>
                </div>` : ''}
            </div>
        `;

        await Swal.fire({
            width: '650px',
            html: reportHtml,
            confirmButtonText: 'Done Reviewing',
            confirmButtonColor: '#003580',
            didRender: () => {
                const hBtn = document.getElementById('highlight-errors-btn');
                if (hBtn) hBtn.onclick = () => { applyHighlightsToCanvas(allIssues); Swal.close(); };

                const fBtn = document.getElementById('review-ai-btn');
                if (fBtn) fBtn.onclick = () => { openAutoFixWizard(allIssues); };
            }
        });

    } catch (error) {
        console.error("Audit Error:", error);
        Swal.fire('Audit Failed', 'We couldn\'t process the check.', 'error');
    }
});

async function openAutoFixWizard(matches) {
    const blocks = document.querySelectorAll('#builder-canvas .canvas-block');
    let currentPos = 0;
    let suggestions = [];

    // 1. Collect Contextual Suggestions
    blocks.forEach(block => {
        const type = block.dataset.type;
        const blockId = block.id;
        
        // SURGICAL SELECTION: Only target content inputs, never metadata/names
        let textInput = null;
        if (type === 'header') textInput = block.querySelector('.header-text');
        else if (type === 'text') textInput = block.querySelector('.text-content');
        else if (type === 'image') textInput = block.querySelector('.caption-text');
        else if (type === 'button') textInput = block.querySelector('.btn-text');
        else if (type === 'footer') textInput = block.querySelector('.footer-text');

        if (!textInput) {
            currentPos += (block.querySelector('input, textarea')?.value?.length || 0) + 2;
            return;
        }

        // PROTECTION: Never touch inputs that look like they store names or IDs
        const fieldName = (textInput.getAttribute('name') || '').toLowerCase();
        if (fieldName.includes('name') || fieldName.includes('id')) {
            currentPos += textInput.value.length + 2;
            return;
        }

        let val = (textInput.value || '').replace(/\[\[AI_FIX:(.*?)\]\]/g, '$1');
        const blockLen = val.length;
        const blockEnd = currentPos + blockLen;

        const blockMatches = matches
            .filter(m => m.offset >= currentPos && m.offset < blockEnd)
            .sort((a, b) => b.offset - a.offset);

        if (blockMatches.length > 0) {
            let suggestedVal = val;
            let issuesFound = [];

            blockMatches.forEach(m => {
                const relativeOffset = m.offset - currentPos;
                const originalWord = val.substring(relativeOffset, relativeOffset + m.length);
                const isCapitalized = /^[A-Z]/.test(originalWord);
                const isStartOfSentence = relativeOffset === 0 || /[.!?]\s+$/.test(val.substring(0, relativeOffset));
                
                if (isCapitalized && !isStartOfSentence) return;

                const replacement = m.replacements && m.replacements.length > 0 ? m.replacements[0].value : null;
                
                if (replacement) {
                    suggestedVal = suggestedVal.substring(0, relativeOffset) + replacement + suggestedVal.substring(relativeOffset + m.length);
                    const category = m.shortMessage === 'Spelling error' ? 'Spelling Check' : 'Grammar Audit';
                    issuesFound.push({ type: category, detail: m.message });
                } else if (m.isSpam) {
                    const trigger = originalWord.toLowerCase();
                    const spamFixes = {
                        'free': 'complimentary', 'urgent': 'immediate', 'act now': 'explore today',
                        'click here': 'discover more', 'winner': 'valued guest', 'cash': 'credit',
                        'prize': 'reward', '100%': 'completely', 'guaranteed': 'assured', 'limited time': 'exclusive'
                    };
                    if (spamFixes[trigger]) {
                        suggestedVal = suggestedVal.substring(0, relativeOffset) + spamFixes[trigger] + suggestedVal.substring(relativeOffset + m.length);
                        issuesFound.push({ type: 'Marketing Risk', detail: `Spam trigger "${trigger}" identified.` });
                    }
                }
            });

            const primaryIssue = issuesFound[0] || { type: 'Manual Review' };
            suggestions.push({
                blockId: blockId,
                blockType: type,
                category: primaryIssue.type,
                title: `Elite ${type.charAt(0).toUpperCase() + type.slice(1)} Audit`,
                reason: issuesFound.map(i => i.detail).join(' ') || 'Linguistic highlight review needed.',
                original: val,
                suggested: suggestedVal,
                action: (newVal) => {
                    textInput.value = newVal;
                    block.classList.add('ai-fixed-block');
                }
            });
        }
        currentPos += blockLen + 2;
    });

    if (suggestions.length === 0 && matches.length > 0) {
        // Fallback for issues without auto-replacements
        blocks.forEach(block => {
            const blockMatches = matches.filter(m => m.offset >= currentPos && m.offset < (currentPos + (block.querySelector('input, textarea')?.value?.length || 0)));
            if (blockMatches.length > 0) {
                const val = block.querySelector('input, textarea')?.value || '';
                suggestions.push({
                    blockId: block.id,
                    blockType: block.dataset.type,
                    category: 'Manual Review',
                    title: `Review ${block.dataset.type}`,
                    reason: blockMatches.map(m => m.message).join('. '),
                    original: val,
                    suggested: val,
                    action: (newVal) => { 
                        const input = block.querySelector('.header-text, .text-content, .caption-text, .btn-text, .footer-text');
                        if (input) input.value = newVal; 
                    }
                });
            }
        });
    }

    // 2. Show Wizard UI
    let suggestionHtml = `
        <div style="text-align: left; font-family: 'Poppins', sans-serif; max-height: 600px; overflow-y: auto; padding-right: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10; backdrop-filter: blur(10px);">
                <div>
                    <h4 style="margin: 0; color: #1e293b; font-size: 1.1rem; font-weight: 800;">Elite AI Optimizer</h4>
                    <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Reviewing all flagged highlights</p>
                </div>
                <button id="wizard-fix-all-btn" style="padding: 12px 25px; border-radius: 12px; background: #10b981; color: white; border: none; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); transition: 0.2s;">
                    <i class="fas fa-magic"></i> Fix All
                </button>
            </div>

            ${suggestions.map((s, index) => {
                const badgeColor = s.category === 'Marketing Risk' ? '#ef4444' : (s.category === 'Grammar Audit' ? '#f59e0b' : '#3b82f6');
                const badgeBg = s.category === 'Marketing Risk' ? '#fef2f2' : (s.category === 'Grammar Audit' ? '#fffbeb' : '#eff6ff');

                return `
                <div class="suggestion-card" data-index="${index}" style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 20px; transition: 0.3s; border-left: 6px solid ${badgeColor}; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h5 style="margin: 0; color: #1e293b; font-weight: 700; font-size: 1rem;">${s.title}</h5>
                        <span style="font-size: 0.7rem; padding: 4px 12px; border-radius: 50px; background: ${badgeBg}; color: ${badgeColor}; font-weight: 800; border: 1px solid ${badgeColor}40;">${s.category}</span>
                    </div>
                    <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 15px; line-height: 1.5; background: #f8fafc; padding: 10px; border-radius: 8px;"><b>Audit Result:</b> ${s.reason}</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 15px;">
                        <div style="background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px solid #f1f5f9;">
                            <div style="font-size: 0.6rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-bottom: 6px;">Original Content</div>
                            <div class="wizard-original-text" style="font-size: 0.85rem; color: #64748b; font-style: italic; line-height: 1.4;">"${s.original}"</div>
                        </div>
                        <div style="background: #f0fdf4; padding: 12px; border-radius: 10px; border: 2px solid #bbf7d0; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <div style="font-size: 0.6rem; color: #10b981; font-weight: 800; text-transform: uppercase;">AI Improvement (Edit below)</div>
                                <button class="btn-wizard-regenerate" data-index="${index}" style="font-size: 0.65rem; background: #10b981; color: white; border: none; padding: 2px 8px; border-radius: 4px; cursor: pointer; font-weight: 700;">
                                    <i class="fas fa-sync-alt"></i> Suggest Another
                                </button>
                            </div>
                            <textarea class="wizard-editable-suggestion" data-index="${index}" style="width: 100%; border: 1px solid #86efac; border-radius: 8px; padding: 10px; font-size: 0.85rem; color: #065f46; font-family: inherit; font-weight: 600; min-height: 60px; outline: none; background: #ffffff;">${s.suggested}</textarea>
                        </div>
                    </div>
                    
                    <button class="btn-wizard-fix-one" data-index="${index}" style="width: 100%; padding: 12px; border-radius: 10px; background: #8b5cf6; color: white; border: none; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(139, 92, 246, 0.2);">
                        <i class="fas fa-check"></i> Fix it automatically
                    </button>
                </div>
                `;
            }).join('')}
        </div>
    `;

    Swal.fire({
        width: '800px',
        html: suggestionHtml,
        showConfirmButton: false,
        showCloseButton: true,
        allowOutsideClick: false,
        didOpen: () => {
            const btns = document.querySelectorAll('.btn-wizard-fix-one');
            const regenBtns = document.querySelectorAll('.btn-wizard-regenerate');
            const fixAllBtn = document.getElementById('wizard-fix-all-btn');

            const applyOne = (idx) => {
                const s = suggestions[idx];
                const editableText = document.querySelector(`.wizard-editable-suggestion[data-index="${idx}"]`).value;
                s.action(editableText);
                const card = document.querySelector(`.suggestion-card[data-index="${idx}"]`);
                const btn = card.querySelector('.btn-wizard-fix-one');
                card.style.background = '#f0fdf4';
                card.style.borderColor = '#10b981';
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Applied to Canvas';
                btn.style.background = '#10b981';
                btn.disabled = true;
                updatePreview();
                saveHistory();
            };

            const regenerate = async (idx) => {
                const s = suggestions[idx];
                const textarea = document.querySelector(`.wizard-editable-suggestion[data-index="${idx}"]`);
                const btn = document.querySelectorAll('.btn-wizard-regenerate')[idx];
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking...';
                btn.disabled = true;
                await new Promise(r => setTimeout(r, 800));
                const variations = [
                    `✨ Elite Choice: ${s.original} - Elevate your journey with HeyDream.`,
                    `✨ Luxury Polish: Discover the magic of ${s.original} today.`,
                    `✨ Professional Touch: Experience ${s.original} at its finest.`,
                    `✨ Dreamy Tone: Your adventure with ${s.original} begins now.`
                ];
                textarea.value = variations[Math.floor(Math.random() * variations.length)];
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Suggest Another';
                btn.disabled = false;
            };

            btns.forEach(btn => btn.onclick = () => applyOne(btn.dataset.index));
            regenBtns.forEach(btn => btn.onclick = () => regenerate(btn.dataset.index));

            if (fixAllBtn) {
                fixAllBtn.onclick = () => {
                    suggestions.forEach((_, i) => {
                        const btn = document.querySelectorAll('.btn-wizard-fix-one')[i];
                        if (!btn.disabled) applyOne(i);
                    });
                    Toast.fire({ icon: 'success', title: 'All improvements applied successfully!' });
                };
            }
        }
    });
}


function applyHighlightsToCanvas(matches) {
    activeLinguisticMatches = matches; // Store for preview usage
    updatePreview(false); // Re-render preview with highlights

    const blocks = document.querySelectorAll('#builder-canvas .canvas-block');
    let currentPos = 0;

    blocks.forEach(block => {
        const type = block.dataset.type;
        let textInput = null;
        
        if (type === 'header') textInput = block.querySelector('.header-text');
        else if (type === 'text') textInput = block.querySelector('.text-content');
        else if (type === 'image') textInput = block.querySelector('.caption-text');
        else if (type === 'button') textInput = block.querySelector('.btn-text');
        else if (type === 'footer') textInput = block.querySelector('.footer-text');

        if (!textInput) return;

        const val = textInput.value || '';
        const blockLen = val.length;
        const blockEnd = currentPos + blockLen;

        const blockMatches = matches.filter(m => m.offset >= currentPos && m.offset < blockEnd);

        if (blockMatches.length > 0) {
            block.classList.add('highlight-error-block');
            block.title = `Detected ${blockMatches.length} linguistic issues. Review this section.`;
        }

        currentPos += blockLen + 2; 
    });

    if (!document.getElementById('clear-highlights-btn')) {
        const clearBtn = document.createElement('button');
        clearBtn.id = 'clear-highlights-btn';
        clearBtn.innerHTML = '<i class="fas fa-eraser"></i> Clear Highlights';
        clearBtn.className = 'btn btn-outline btn-sm';
        clearBtn.style.cssText = 'position: fixed; bottom: 30px; right: 30px; z-index: 9999; background: white; color: #ef4444; border: 2px solid #ef4444; font-weight: 800; border-radius: 50px; padding: 10px 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); cursor: pointer; transition: 0.3s transform;';
        clearBtn.onmouseover = () => clearBtn.style.transform = 'scale(1.05)';
        clearBtn.onmouseout = () => clearBtn.style.transform = 'scale(1)';
        clearBtn.onclick = () => {
            activeLinguisticMatches = [];
            updatePreview(false);
            document.querySelectorAll('.highlight-error-block').forEach(el => {
                el.classList.remove('highlight-error-block');
                el.title = '';
            });
            clearBtn.remove();
        };
        document.body.appendChild(clearBtn);
    }
}

function highlightTextInPreview(text, blockId, matches) {
    const blocksData = getBlocksData();
    let globalOffset = 0;
    
    // Find where this block starts in the global text
    for (let i = 0; i < blocksData.length; i++) {
        const b = blocksData[i];
        if (b.id === blockId) break;
        let bText = '';
        if (b.type === 'header') bText = b.text;
        else if (b.type === 'text') bText = b.text;
        else if (b.type === 'image') bText = b.caption;
        else if (b.type === 'button') bText = b.text;
        else if (b.type === 'footer') bText = b.text;
        globalOffset += (bText || '').length + 2;
    }

    const blockMatches = matches.filter(m => m.offset >= globalOffset && m.offset < globalOffset + text.length);
    if (blockMatches.length === 0) return text;

    console.log(`Highlighting ${blockMatches.length} issues in block ${blockId}`);

    // Apply highlights from end to start to avoid offset shifting
    let result = text;
    const sortedMatches = [...blockMatches].sort((a, b) => b.offset - a.offset);

    sortedMatches.forEach(match => {
        const localOffset = match.offset - globalOffset;
        if (localOffset >= 0 && localOffset < result.length) {
            const before = result.substring(0, localOffset);
            const error = result.substring(localOffset, localOffset + match.length);
            const after = result.substring(localOffset + match.length);
            result = `${before}<span class="preview-lt-highlight" title="${match.message.replace(/"/g, '&quot;')}">${error}</span>${after}`;
        }
    });

    return result;
}


const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});


// Global tracker for AI variety
let recentlyUsedSubjects = [];

async function aiGenerateSubject() {
    const popup = Swal.getPopup();
    const btn = popup ? popup.querySelector("#magic-subject-btn") : null;
    const input = popup ? popup.querySelector("#swal-input-subject") : document.getElementById("swal-input-subject");
    
    if (!input) return;

    // Show "thinking" state
    const originalBtnHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    }

    // 1. Analyze Builder Content
    const blocks = getBlocksData();
    const fullContent = blocks.map(b => (b.text || b.caption || '')).join(' ').toLowerCase();
    
    // 2. Identify Context
    const hasDiscount = fullContent.includes('off') || fullContent.includes('%') || fullContent.includes('discount') || fullContent.includes('promo') || fullContent.includes('sale');
    const hasSummer   = fullContent.includes('summer') || fullContent.includes('beach') || fullContent.includes('sun') || fullContent.includes('hot');
    const hasBoracay  = fullContent.includes('boracay');
    const hasPalawan  = fullContent.includes('palawan') || fullContent.includes('el nido') || fullContent.includes('coron');
    const hasJapan    = fullContent.includes('japan') || fullContent.includes('tokyo') || fullContent.includes('osaka') || fullContent.includes('kyoto');
    const hasKorea    = fullContent.includes('korea') || fullContent.includes('seoul') || fullContent.includes('jeju');

    // Simulate "Real AI" processing
    await new Promise(r => setTimeout(r, 1000));

    // 3. Dynamic Pool Construction
    let pool = [
        "✨ Dream Escape: Your Exclusive Travel Deals are Here!",
        "Discover Your Next Adventure with HeyDream Travel ✈️",
        "Pack Your Bags! Exclusive Promo Just for You 🎒",
        "Your Personalized Travel Guide to the Philippines 🇵🇭",
        "Ready for Takeoff? Your Next Vacation Starts Here! 🏖️",
        "Don't Miss Out: Exclusive Travel Perks Inside! 🌟"
    ];

    if (hasBoracay) pool.push("🌴 Boracay Calling: Experience White Sand Magic!", "⛵ Sail into Paradise: Special Boracay Rates Inside", "✨ Boracay Dreams: Your Tropical Escape Awaits", "🍹 Sunset & Sand: Grab Our Boracay Promo Now!");
    if (hasPalawan) pool.push("🌊 Crystal Clear Palawan: Exclusive Booking Offer", "🏝️ El Nido & Coron: Discover Hidden Lagoons Today", "🛶 Palawan Paradise: Your Underground River Guide Inside", "✨ The Last Frontier: Palawan Deals You Can't Miss!");
    if (hasJapan || hasKorea) pool.push("🍣 Taste of Japan: Your Cultural Journey Starts Here", "🚅 Explore the East: Japan & Korea Vacation Deals", "🌸 Cherry Blossom Special: Exclusive Asia Rates", "🍜 Seoul Searching? Amazing Korea Promos Just for You!");
    if (hasDiscount) pool.push("🔥 FLASH SALE: Unbeatable Travel Discounts Inside!", "💰 Travel More, Spend Less: Your Promo Code is Ready", "🎁 A Special Gift: Big Savings on Your Next Trip", "⚡ 24-HOUR SALE: Prices Just Dropped on Your Favorites!");
    if (hasSummer) pool.push("☀️ Sizzle into Summer: Hot Beach Deals Just for You", "🍦 The Ultimate Summer Escape: Cool Rates Inside", "🌊 Catch the Wave: Summer Vacation Promos are Live", "🕶️ Summer Vibes: Your Beach Holiday is One Click Away!");

    // 4. Advanced Filter for Variety (No repeats)
    let filtered = pool.filter(s => !recentlyUsedSubjects.includes(s));
    
    // If we ran out of unique options, reset the history
    if (filtered.length === 0) {
        recentlyUsedSubjects = [];
        filtered = pool.filter(s => s !== input.value);
    }
    
    const random = filtered[Math.floor(Math.random() * filtered.length)];
    input.value = random;
    
    // Add to history (keep last 5)
    recentlyUsedSubjects.push(random);
    if (recentlyUsedSubjects.length > 5) recentlyUsedSubjects.shift();
    
    input.dispatchEvent(new Event('input', { bubbles: true }));

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;
    }
}

// ── REPORTED ISSUES MANAGEMENT ───────────────────────────────
// ── REPORTED ISSUES MANAGEMENT STATE ───────────────────────────
let currentReportedIssues = [];
let currentIssueFilter = 'All';

function setIssueFilter(filterName) {
    currentIssueFilter = filterName;
    renderReportedIssues();
}

async function loadReportedIssues() {
    const tbody = document.getElementById('reported-issues-list');
    if (!tbody) return;

    try {
        const res = await fetch('ai_chat_admin.php?action=get_issues');
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            console.error('Raw response from server:', text);
            throw new Error('Server returned invalid JSON response: ' + parseErr.message);
        }

        if (data.success) {
            currentReportedIssues = data.issues || [];
            renderReportedIssues();
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 20px;">Failed to load issues: ${e.message}</td></tr>`;
    }
}

function renderReportedIssues() {
    const tbody = document.getElementById('reported-issues-list');
    if (!tbody) return;

    // Calculate real-time counts for badges
    const allCount = currentReportedIssues.length;
    const pendingCount = currentReportedIssues.filter(i => i.status === 'Pending').length;
    const progressCount = currentReportedIssues.filter(i => i.status === 'In Progress').length;
    const resolvedCount = currentReportedIssues.filter(i => i.status === 'Resolved').length;

    // Update count badges inside tabs
    const badgeAll = document.getElementById('badge-all');
    if (badgeAll) badgeAll.textContent = allCount;
    const badgePending = document.getElementById('badge-pending');
    if (badgePending) badgePending.textContent = pendingCount;
    const badgeProgress = document.getElementById('badge-progress');
    if (badgeProgress) badgeProgress.textContent = progressCount;
    const badgeResolved = document.getElementById('badge-resolved');
    if (badgeResolved) badgeResolved.textContent = resolvedCount;

    // Update active tab visuals
    document.querySelectorAll('.filter-tab').forEach(btn => {
        btn.style.boxShadow = 'none';
        btn.style.background = '#fff';
    });
    
    let activeTabId = 'tab-all';
    let themeColor = '#cbd5e1';
    let bgColor = '#f8fafc';
    if (currentIssueFilter === 'Pending') {
        activeTabId = 'tab-pending';
        themeColor = '#ef4444';
        bgColor = '#fff5f5';
    } else if (currentIssueFilter === 'In Progress') {
        activeTabId = 'tab-progress';
        themeColor = '#d97706';
        bgColor = '#fffbeb';
    } else if (currentIssueFilter === 'Resolved') {
        activeTabId = 'tab-resolved';
        themeColor = '#059669';
        bgColor = '#f0fdf4';
    }

    const activeTab = document.getElementById(activeTabId);
    if (activeTab) {
        activeTab.style.boxShadow = `0 0 0 2px ${themeColor}`;
        activeTab.style.background = bgColor;
    }

    // Filter tickets
    let filtered = currentReportedIssues;
    if (currentIssueFilter !== 'All') {
        filtered = currentReportedIssues.filter(i => i.status === currentIssueFilter);
    } else {
        // Sort by status priority: Pending first, then In Progress, then Resolved
        const priority = { 'Pending': 1, 'In Progress': 2, 'Resolved': 3 };
        filtered = [...currentReportedIssues].sort((a, b) => priority[a.status] - priority[b.status]);
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 50px 20px; color: #94a3b8;">
                    <i class="fas fa-check-circle" style="font-size: 2.2rem; color: #10b981; margin-bottom: 10px;"></i>
                    <p style="font-size: 0.9rem; font-weight: 600; color: #64748b;">No ${currentIssueFilter === 'All' ? '' : currentIssueFilter.toLowerCase() + ' '}tickets found! 😊</p>
                </td>
            </tr>
        `;
        return;
    }

    let lastStatus = null;
    tbody.innerHTML = filtered.map(issue => {
        let dividerHtml = '';
        if (currentIssueFilter === 'All' && issue.status !== lastStatus) {
            lastStatus = issue.status;
            let sectionTitle = 'Pending Tickets';
            let sectionColor = '#ef4444';
            let sectionBg = '#fef2f2';
            let sectionIcon = 'fa-clock';
            
            if (issue.status === 'In Progress') {
                sectionTitle = 'In Progress Tickets';
                sectionColor = '#d97706';
                sectionBg = '#fffbeb';
                sectionIcon = 'fa-spinner';
            } else if (issue.status === 'Resolved') {
                sectionTitle = 'Resolved Tickets';
                sectionColor = '#059669';
                sectionBg = '#f0fdf4';
                sectionIcon = 'fa-check-circle';
            }
            
            dividerHtml = `
                <tr class="section-divider-row">
                    <td colspan="8" style="padding: 12px 15px; font-weight: 800; color: ${sectionColor}; background: ${sectionBg}; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: 2px solid ${sectionColor}22; text-transform: uppercase; border-top: 1px solid #e2e8f0;">
                        <i class="fas ${sectionIcon}" style="margin-right: 6px;"></i> ${sectionTitle}
                    </td>
                </tr>
            `;
        }

        let severityColor = '#64748b'; // default slate
        if (issue.severity === 'Low') severityColor = '#10b981'; // green
        else if (issue.severity === 'Medium') severityColor = '#f59e0b'; // amber
        else if (issue.severity === 'High') severityColor = '#ef4444'; // red
        else if (issue.severity === 'Critical') severityColor = '#b91c1c'; // dark red

        let statusBadge = '';
        if (issue.status === 'Pending') {
            statusBadge = `<span style="background: #fee2e2; color: #ef4444; padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;">Pending</span>`;
        } else if (issue.status === 'In Progress') {
            statusBadge = `<span style="background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;">In Progress</span>`;
        } else {
            statusBadge = `<span style="background: #d1fae5; color: #059669; padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;">Resolved</span>`;
        }

        return `
            ${dividerHtml}
            <tr style="border-bottom: 1px solid #e2e8f0; font-size: 0.8rem; vertical-align: top;">
                <td style="padding: 15px 10px; font-weight: bold; color: #0f172a;">#${issue.id}</td>
                <td style="padding: 15px 10px;">
                    <strong style="color: #0f172a; display:block;">${escapeHTML(issue.name)}</strong>
                    <span style="color: #64748b; font-size: 0.75rem;">${escapeHTML(issue.email)}</span><br>
                    <span style="color: #64748b; font-size: 0.75rem;">${escapeHTML(issue.contact || 'No Phone')}</span>
                </td>
                <td style="padding: 15px 10px;"><span style="background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">${escapeHTML(issue.category)}</span></td>
                <td style="padding: 15px 10px;">
                    <span style="color: ${severityColor}; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i> ${escapeHTML(issue.severity)}
                    </span>
                </td>
                <td style="padding: 15px 10px; max-width: 320px; line-height: 1.4; color: #334155;">
                    ${escapeHTML(issue.description).replace(/\\n/g, '<br>')}
                </td>
                <td style="padding: 15px 10px;">${statusBadge}</td>
                <td style="padding: 15px 10px; color: #64748b; font-size: 0.75rem;">${parseMySQLDate(issue.created_at).toLocaleString()}</td>
                <td style="padding: 15px 10px; text-align: right;">
                    <div style="display: flex; justify-content: flex-end; gap: 6px; flex-wrap: wrap;">
                        <select onchange="updateIssueStatus(${issue.id}, this.value)" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; border: 1px solid #cbd5e1; background: white; cursor: pointer;">
                            <option value="Pending" ${issue.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="In Progress" ${issue.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                            <option value="Resolved" ${issue.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                        </select>
                        <button onclick="deleteIssueReport(${issue.id})" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.75rem; color: #ef4444; border-color: #fecaca; border-radius: 6px;" title="Delete Ticket"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function updateIssueStatus(id, status) {
    try {
        const res = await fetch(`ai_chat_admin.php?action=update_issue_status&id=${id}&status=${encodeURIComponent(status)}`);
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Status Updated',
                text: `Ticket #${id} status changed to ${status}.`,
                timer: 1500,
                showConfirmButton: false
            });
            loadReportedIssues();
        } else {
            Swal.fire('Error', data.message || 'Failed to update status', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

async function deleteIssueReport(id) {
    const confirmResult = await Swal.fire({
        title: 'Delete Ticket?',
        text: `Are you sure you want to permanently delete Issue Ticket #${id}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    });

    if (confirmResult.isConfirmed) {
        try {
            const res = await fetch(`ai_chat_admin.php?action=delete_issue&id=${id}`);
            const data = await res.json();
            if (data.success) {
                Swal.fire('Deleted!', 'Issue ticket has been deleted.', 'success');
                loadReportedIssues();
            } else {
                Swal.fire('Error', data.message || 'Failed to delete ticket', 'error');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

// EMAIL MESSAGE DIRECT DISPATCH BUILDER FUNCTIONS
let activeMsgBlockId = null;
let msgPreviewSortable = null;
let msgCanvasSortable = null;

// Undo & Redo State History variables
let msgHistory = [];
let msgHistoryIndex = -1;
let isApplyingMsgState = false;
let msgSaveStateTimeout = null;

function saveMsgState() {
    if (isApplyingMsgState) return;
    const currentState = JSON.stringify({
        subject: document.getElementById('email-msg-subject')?.value || '',
        audience: document.getElementById('email-msg-audience')?.value || 'all',
        partners: document.getElementById('email-msg-partners')?.value || '',
        blocks: getMsgBlocksData()
    });
    
    if (msgHistoryIndex >= 0 && msgHistory[msgHistoryIndex] === currentState) {
        return;
    }
    
    msgHistory = msgHistory.slice(0, msgHistoryIndex + 1);
    msgHistory.push(currentState);
    if (msgHistory.length > 25) {
        msgHistory.shift();
    }
    msgHistoryIndex = msgHistory.length - 1;
    updateUndoRedoButtons();
}

function debouncedSaveMsgState() {
    if (msgSaveStateTimeout) clearTimeout(msgSaveStateTimeout);
    msgSaveStateTimeout = setTimeout(() => {
        saveMsgState();
    }, 400);
}

function undoMsgState() {
    if (msgHistoryIndex > 0) {
        msgHistoryIndex--;
        applyMsgState(msgHistory[msgHistoryIndex]);
    }
}

function redoMsgState() {
    if (msgHistoryIndex < msgHistory.length - 1) {
        msgHistoryIndex++;
        applyMsgState(msgHistory[msgHistoryIndex]);
    }
}

function applyMsgState(stateString) {
    if (!stateString) return;
    isApplyingMsgState = true;
    
    const stateObj = JSON.parse(stateString);
    
    // Restore subject and audiences
    const subjectEl = document.getElementById('email-msg-subject');
    if (subjectEl) subjectEl.value = stateObj.subject || '';
    
    const audienceEl = document.getElementById('email-msg-audience');
    if (audienceEl) {
        audienceEl.value = stateObj.audience || 'all';
        const partnerGroup = document.getElementById('partner-emails-group');
        if (partnerGroup) {
            partnerGroup.style.display = stateObj.audience === 'partners' ? 'block' : 'none';
        }
    }
    
    const partnersEl = document.getElementById('email-msg-partners');
    if (partnersEl) partnersEl.value = stateObj.partners || '';
    
    const canvas = document.getElementById('msg-builder-canvas');
    if (canvas) {
        canvas.innerHTML = '';
        closeMsgProperties();
        
        if (stateObj.blocks && stateObj.blocks.length > 0) {
            stateObj.blocks.forEach(block => {
                addMsgBlockAt(block.type, null, block.text || '', block);
            });
        }
    }
    
    isApplyingMsgState = false;
    updateUndoRedoButtons();
    updateMsgPreview();
}

function updateUndoRedoButtons() {
    const undoBtn = document.getElementById('msg-undo-btn');
    const redoBtn = document.getElementById('msg-redo-btn');
    
    if (undoBtn) {
        undoBtn.disabled = (msgHistoryIndex <= 0);
        undoBtn.style.opacity = (msgHistoryIndex <= 0) ? '0.45' : '1';
        undoBtn.style.cursor = (msgHistoryIndex <= 0) ? 'not-allowed' : 'pointer';
    }
    if (redoBtn) {
        redoBtn.disabled = (msgHistoryIndex >= msgHistory.length - 1);
        redoBtn.style.opacity = (msgHistoryIndex >= msgHistory.length - 1) ? '0.45' : '1';
        redoBtn.style.cursor = (msgHistoryIndex >= msgHistory.length - 1) ? 'not-allowed' : 'pointer';
    }
}

// SPAM SCORE DETECTOR FUNCTION
function checkMsgSpamScore() {
    const subject = document.getElementById('email-msg-subject')?.value || '';
    const blocks = getMsgBlocksData();
    
    let score = 0;
    const triggers = [];
    
    // 1. Analyze Subject
    if (!subject) {
        score += 2;
        triggers.push("Subject line is empty");
    } else {
        if (subject.toUpperCase() === subject && subject.replace(/[^A-Za-z]/g, '').length > 4) {
            score += 3;
            triggers.push("Subject is written in ALL CAPS");
        }
        if (subject.includes('!')) {
            score += 2;
            triggers.push("Subject contains exclamation marks");
        }
        
        const subjectSpamWords = ['free', 'guaranteed', 'buy now', 'earn', '100%', 'risk free', 'urgent', 'winner', 'cash', 'gift', 'cheap', 'save big'];
        subjectSpamWords.forEach(word => {
            if (subject.toLowerCase().includes(word)) {
                score += 3;
                triggers.push(`Spam keyword in subject: "${word}"`);
            }
        });
    }
    
    // 2. Analyze Body Blocks content
    let allText = '';
    let hasHeader = false;
    let hasFooter = false;
    let hasButton = false;
    let imageCount = 0;
    
    blocks.forEach(block => {
        if (block.type === 'header') hasHeader = true;
        if (block.type === 'footer') hasFooter = true;
        if (block.type === 'button') hasButton = true;
        if (block.type === 'image') imageCount++;
        
        if (block.text) allText += ' ' + block.text;
        if (block.caption) allText += ' ' + block.caption;
    });
    
    const bodySpamWords = ['free', 'buy now', 'click here', 'guaranteed', 'no risk', 'act fast', 'limited time', 'earn money', 'investment', 'double your', 'unsubscribed', 'promotion'];
    bodySpamWords.forEach(word => {
        const matches = (allText.toLowerCase().match(new RegExp(word, 'g')) || []).length;
        if (matches > 0) {
            score += matches * 2;
            triggers.push(`Spam keyword in body (${matches}x): "${word}"`);
        }
    });
    
    // Check punctuation in body
    const exclamations = (allText.match(/!/g) || []).length;
    if (exclamations > 3) {
        score += 2;
        triggers.push(`Excessive exclamation marks (${exclamations} found)`);
    }
    
    // Structural recommendations
    if (!hasFooter) {
        score += 3;
        triggers.push("Missing an email footer with unsubscribe link");
    }
    if (imageCount > 0 && allText.length < 100) {
        score += 2;
        triggers.push("Low text-to-image ratio (could be flagged as spam)");
    }
    
    // Calculate final rating
    let riskLevel = "Low Risk";
    let progressBg = "#10b981"; // green
    
    if (score >= 8) {
        riskLevel = "High Risk";
        progressBg = "#ef4444"; // red
    } else if (score >= 4) {
        riskLevel = "Medium Risk";
        progressBg = "#f59e0b"; // orange
    }
    
    const triggerHtml = triggers.length > 0 
        ? `<ul style="text-align: left; font-size: 0.85rem; color: #475569; padding-left: 20px; line-height: 1.6; margin-top: 10px;">
            ${triggers.map(t => `<li style="margin-bottom: 5px; list-style-type: disc;"><i class="fas fa-exclamation-circle" style="color: ${progressBg}; margin-right: 5px;"></i>${t}</li>`).join('')}
           </ul>`
        : `<p style="color: #10b981; font-weight: 600; margin-top: 10px;"><i class="fas fa-check-circle"></i> Perfect! No spam indicators found.</p>`;
        
    Swal.fire({
        title: 'Spam Risk Analysis',
        html: `
            <div style="margin-bottom: 20px; font-family: 'Poppins', sans-serif;">
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.95rem; margin-bottom: 8px;">
                    <span>Risk Level: <span style="color: ${progressBg};">${riskLevel}</span></span>
                    <span>Score: ${score}/20</span>
                </div>
                <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;">
                    <div style="width: ${Math.min((score / 20) * 100, 100)}%; height: 100%; background: ${progressBg}; transition: width 0.5s ease;"></div>
                </div>
            </div>
            <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; font-family: 'Poppins', sans-serif;">
                <h5 style="text-align: left; font-size: 0.9rem; margin-bottom: 5px; color: #1e293b; font-weight: 700;">Triggered Rules & Recommendations:</h5>
                ${triggerHtml}
            </div>
        `,
        confirmButtonColor: '#003580',
        confirmButtonText: 'Got it'
    });
}

// Keyboard shortcuts inside Direct Email Modal
document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('email-message-modal');
    if (modal && modal.style.display === 'flex') {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undoMsgState();
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            redoMsgState();
        }
    }
});

// Initialise the Email Message builder (called when switching to the email-message section)

function initMsgBuilder() {
    const canvas = document.getElementById('msg-builder-canvas');
    if (canvas && !msgCanvasSortable && typeof Sortable !== 'undefined') {
        msgCanvasSortable = new Sortable(canvas, {
            group: 'msg-blocks',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: () => {
                updateMsgPreview();
                saveMsgState();
            }
        });

        // Click delegation for canvas blocks
        canvas.addEventListener('click', (e) => {
            const block = e.target.closest('.msg-canvas-block');
            if (!block) return;
            const blockId = block.id;

            if (e.target.closest('.remove-block')) {
                block.remove();
                closeMsgProperties();
                updateMsgPreview();
                saveMsgState();
                return;
            }
            if (e.target.closest('.duplicate-block')) {
                duplicateMsgBlock(blockId);
                return;
            }
            openMsgProperties(blockId);
        });
    }

    // Load default blocks only the very first time
    if (canvas && canvas.children.length === 0) {
        loadMsgDefaultState();
    }

    // Reset undo/redo history fresh on every visit
    msgHistory = [];
    msgHistoryIndex = -1;
    saveMsgState();

    // Sync the business partners counter badge
    updatePartnersCount();
}

// Kept for backwards-compat (sidebar & dashboard buttons still call this)
function openMessageModal() {
    switchSection('email-message');
}

function closeMessageModal() {
    switchSection('emails');
    closeMsgProperties();
}

function addMsgBlock(type, defaultValue = '', savedData = null) {
    addMsgBlockAt(type, null, defaultValue, savedData);
}

function addMsgBlockAt(type, index = null, defaultValue = '', savedData = null) {
    const canvas = document.getElementById('msg-builder-canvas');
    if (!canvas) return;
    const blockId = 'msg-block-' + Date.now() + Math.random().toString(36).substr(2, 5);
    const div = document.createElement('div');
    div.className = 'msg-canvas-block canvas-block';
    div.dataset.type = type;
    div.id = blockId;

    let icon = '';
    let defaultName = '';
    let settingsHtml = '';
    const d = savedData || {};

    if (type === 'header') {
        icon = '<i class="fas fa-heading"></i>';
        defaultName = 'Header';
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <input type="hidden" class="color-input" value="${d.color || '#ffffff'}">
            <input type="hidden" class="bg-input" value="${d.bg || '#003580'}">
            <input type="hidden" class="header-text" value="${defaultValue || 'HeyDream Travel & Tours'}">
        `;
    } else if (type === 'text') {
        icon = '<i class="fas fa-font"></i>';
        defaultName = 'Text';
        if (d.weight === '700') div.classList.add('is-bold');
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'left'}">
            <input type="hidden" class="size-input" value="${d.size || '16'}">
            <input type="hidden" class="color-input" value="${d.color || '#334155'}">
            <textarea style="display:none;" class="text-content">${defaultValue}</textarea>
        `;
    } else if (type === 'image') {
        icon = '<i class="fas fa-image"></i>';
        defaultName = 'Image';
        if (d.capWeight === '700') div.classList.add('is-caption-bold');
        settingsHtml = `
            <input type="hidden" class="image-url" value="${d.url || defaultValue || 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80'}">
            <input type="hidden" class="width-input" value="${d.width || '100'}">
            <input type="hidden" class="radius-input" value="${d.radius || '8'}">
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <textarea style="display:none;" class="caption-text">${d.caption || ''}</textarea>
            <input type="hidden" class="caption-size" value="${d.capSize || '14'}">
            <input type="hidden" class="caption-color" value="${d.capColor || '#64748b'}">
        `;
    } else if (type === 'button') {
        icon = '<i class="fas fa-link"></i>';
        defaultName = 'Button';
        if (d.weight === '700') div.classList.add('is-bold');
            settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <input type="hidden" class="btn-action" value="${d.action || 'chat'}">
            <input type="hidden" class="btn-text" value="${defaultValue || 'Book Your Trip'}">
            <input type="hidden" class="btn-link" value="${d.link || '../chat.php'}">
            <input type="hidden" class="color-input" value="${d.color || '#ffffff'}">
            <input type="hidden" class="bg-input" value="${d.bg || '#003580'}">
            <input type="hidden" class="size-input" value="${d.size || '16'}">
            <input type="hidden" class="width-input" value="${d.width || 'auto'}">
            <input type="hidden" class="padding-input" value="${d.padding || '12'}">
        `;
    } else if (type === 'footer') {
        icon = '<i class="fas fa-shoe-prints"></i>';
        defaultName = 'Footer';
        settingsHtml = `
            <input type="hidden" class="align-input" value="${d.align || 'center'}">
            <textarea style="display:none;" class="footer-text">${defaultValue || 'You received this email because you are a client or partner of HeyDream Travel and Tours.'}</textarea>
        `;
    } else if (type === 'divider') {
        icon = '<i class="fas fa-minus"></i>';
        defaultName = 'Divider';
    }

    const customLabel = d.custom_label || '';

    div.innerHTML = `
        <div class="block-summary" style="display: flex; justify-content: space-between; align-items: center; pointer-events: none;">
            <div class="visible-label" style="font-weight: 600; font-size: 0.85rem;">
                ${icon} <span class="default-name">${defaultName}</span><span class="label-separator">${customLabel ? ': ' : ''}</span><span class="label-text" style="font-weight: 400; opacity: 0.8;">${customLabel}</span>
            </div>
            <div style="display: flex; gap: 12px; align-items: center; pointer-events: auto;">
                <span class="duplicate-block" title="Duplicate" style="cursor: pointer;"><i class="fas fa-copy"></i></span>
                <span class="remove-block" title="Delete" style="cursor: pointer;"><i class="fas fa-trash"></i></span>
            </div>
        </div>
        <input type="hidden" class="block-label-input" value="${customLabel}">
        ${settingsHtml}
    `;

    if (index !== null && canvas.children[index]) {
        canvas.insertBefore(div, canvas.children[index]);
    } else {
        canvas.appendChild(div);
    }
    
    updateMsgPreview();
    saveMsgState();
}

function openMsgProperties(blockId) {
    activeMsgBlockId = blockId;
    const block = document.getElementById(blockId);
    if (!block) return;
    const type = block.dataset.type;
    const propertiesContent = document.getElementById('msg-properties-content');
    if (!propertiesContent) return;
    
    document.getElementById('msg-canvas-section').style.display = 'none';
    document.getElementById('msg-properties-section').style.display = 'block';

    let html = `
        <div class="form-group" style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 15px;">
            <label style="color: #003580; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight:700;">Block Display Name</label>
            <input type="text" class="block-input" value="${block.querySelector('.block-label-input').value}" 
                oninput="updateMsgBlockLabel(this.value)" placeholder="Enter block name..." style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
        </div>
    `;
    const getAlignHtml = (current) => `
        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Alignment</label>
            <div class="align-group" style="display:flex; gap:5px;">
                <button class="btn-icon btn btn-outline btn-sm ${current === 'left' ? 'active' : ''}" onclick="updateMsgBlockAlign('left')" style="padding:6px 10px; border-radius: 6px;"><i class="fas fa-align-left"></i></button>
                <button class="btn-icon btn btn-outline btn-sm ${current === 'center' ? 'active' : ''}" onclick="updateMsgBlockAlign('center')" style="padding:6px 10px; border-radius: 6px;"><i class="fas fa-align-center"></i></button>
                <button class="btn-icon btn btn-outline btn-sm ${current === 'right' ? 'active' : ''}" onclick="updateMsgBlockAlign('right')" style="padding:6px 10px; border-radius: 6px;"><i class="fas fa-align-right"></i></button>
            </div>
        </div>
    `;

    if (type === 'header') {
        html += `
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Header Text</label>
                <input type="text" class="block-input header-text-field" value="${block.querySelector('.header-text').value}" oninput="updateMsgBlockData('.header-text', this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Text Color</label>
                    <input type="color" class="color-input" value="${block.querySelector('.color-input').value}" oninput="updateMsgBlockData('.color-input', this.value)" style="width:100%; height:35px; border-radius:6px; border:1px solid #cbd5e1; padding:2px;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Background</label>
                    <input type="color" class="color-input" value="${block.querySelector('.bg-input').value}" oninput="updateMsgBlockData('.bg-input', this.value)" style="width:100%; height:35px; border-radius:6px; border:1px solid #cbd5e1; padding:2px;">
                </div>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'text') {
        html += `
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Text Content</label>
                <textarea class="block-input text-content-field" style="height: 120px; width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;" oninput="updateMsgBlockData('.text-content', this.value)">${block.querySelector('.text-content').value}</textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Font Size</label>
                    <input type="number" class="block-input" value="${block.querySelector('.size-input').value}" oninput="updateMsgBlockData('.size-input', this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Text Color</label>
                    <input type="color" class="color-input" value="${block.querySelector('.color-input').value}" oninput="updateMsgBlockData('.color-input', this.value)" style="width:100%; height:35px; border-radius:6px; border:1px solid #cbd5e1; padding:2px;">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <button class="btn btn-outline btn-block ${block.classList.contains('is-bold') ? 'active' : ''}" onclick="toggleMsgBlockBold()" style="width:100%; padding:8px; border-radius:6px; font-weight:600;">
                    <i class="fas fa-bold"></i> ${block.classList.contains('is-bold') ? 'Bold Active' : 'Make Bold'}
                </button>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'image') {
        html += `
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Image URL</label>
                <input type="text" class="block-input" value="${block.querySelector('.image-url').value}" oninput="updateMsgBlockData('.image-url', this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
            </div>
            <div class="form-group" style="background: #f1f5f9; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom:15px;">
                <label style="color: #003580; font-weight: 700; font-size:0.75rem;">SIDE TEXT (BESIDE IMAGE)</label>
                <textarea id="msg-caption-textarea-${activeMsgBlockId}" class="block-input" style="height: 60px; margin-top: 5px; width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;" placeholder="Message beside image..." oninput="updateMsgBlockData('.caption-text', this.value)">${block.querySelector('.caption-text').value}</textarea>
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 8px;">
                    <div class="form-group">
                        <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Font Size</label>
                        <input type="number" class="block-input" value="${block.querySelector('.caption-size').value}" oninput="updateMsgBlockData('.caption-size', this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 8px;">
                    <input type="color" class="color-input" style="width: 35px !important; height:35px; border-radius:6px;" value="${block.querySelector('.caption-color').value}" oninput="updateMsgBlockData('.caption-color', this.value)">
                    <button class="btn btn-outline btn-sm ${block.classList.contains('is-caption-bold') ? 'active' : ''}" onclick="document.getElementById('${activeMsgBlockId}').classList.toggle('is-caption-bold'); updateMsgPreview(); this.classList.toggle('active');" style="padding:6px 10px;">
                        <i class="fas fa-bold"></i> Bold
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Image Width (${block.querySelector('.width-input').value}%)</label>
                <input type="range" min="20" max="100" value="${block.querySelector('.width-input').value}" oninput="updateMsgBlockData('.width-input', this.value); this.previousElementSibling.innerText = 'Image Width ('+this.value+'%)'" style="width:100%;">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Corner Roundness (${block.querySelector('.radius-input').value}px)</label>
                <input type="range" min="0" max="50" value="${block.querySelector('.radius-input').value}" oninput="updateMsgBlockData('.radius-input', this.value); this.previousElementSibling.innerText = 'Corner Roundness ('+this.value+'px)'" style="width:100%;">
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'button') {
        const btnAction = block.querySelector('.btn-action')?.value || 'link';
        html += `
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Button Text</label>
                <input type="text" class="block-input" value="${block.querySelector('.btn-text').value}" oninput="updateMsgBlockData('.btn-text', this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Button Action</label>
                <select class="block-input btn-action-field" onchange="updateMsgBlockData('.btn-action', this.value); toggleMsgButtonLinkInput(this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
                    <option value="link" ${btnAction === 'link' ? 'selected' : ''}>Custom URL</option>
                    <option value="chat" ${btnAction === 'chat' ? 'selected' : ''}>Go to Chat</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Button Link</label>
                <input type="text" class="block-input btn-link-field" value="${block.querySelector('.btn-link').value}" oninput="updateMsgBlockData('.btn-link', this.value)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Text Color</label>
                    <input type="color" class="color-input" value="${block.querySelector('.color-input').value}" oninput="updateMsgBlockData('.color-input', this.value)" style="width:100%; height:35px; border-radius:6px; border:1px solid #cbd5e1; padding:2px;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Background</label>
                    <input type="color" class="color-input" value="${block.querySelector('.bg-input').value}" oninput="updateMsgBlockData('.bg-input', this.value)" style="width:100%; height:35px; border-radius:6px; border:1px solid #cbd5e1; padding:2px;">
                </div>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    } else if (type === 'footer') {
        html += `
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:0.8rem; margin-bottom:5px; font-weight:600;">Footer Text</label>
                <textarea class="block-input footer-text-field" style="height: 100px; width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; box-sizing:border-box;" oninput="updateMsgBlockData('.footer-text', this.value)">${block.querySelector('.footer-text').value}</textarea>
            </div>
            ${getAlignHtml(block.querySelector('.align-input').value)}
        `;
    }

    propertiesContent.innerHTML = html;
    if (type === 'button') {
        toggleMsgButtonLinkInput(block.querySelector('.btn-action')?.value || 'link');
    }
}

function closeMsgProperties() {
    activeMsgBlockId = null;
    const props = document.getElementById('msg-properties-section');
    const canvas = document.getElementById('msg-canvas-section');
    if (props) props.style.display = 'none';
    if (canvas) canvas.style.display = 'block';
}

function updateMsgBlockLabel(value) {
    if (!activeMsgBlockId) return;
    const block = document.getElementById(activeMsgBlockId);
    if (!block) return;
    
    block.querySelector('.block-label-input').value = value;
    const separator = block.querySelector('.label-separator');
    const textSpan = block.querySelector('.label-text');
    
    if (separator && textSpan) {
        separator.innerText = value ? ': ' : '';
        textSpan.innerText = value;
    }
    debouncedSaveMsgState();
}

function updateMsgBlockData(selector, value) {
    if (!activeMsgBlockId) return;
    const block = document.getElementById(activeMsgBlockId);
    if (!block) return;
    
    const field = block.querySelector(selector);
    if (field) {
        field.value = value;
        updateMsgPreview();
        debouncedSaveMsgState();
    }
}

function updateMsgBlockAlign(align) {
    if (!activeMsgBlockId) return;
    updateMsgBlockData('.align-input', align);
    
    const alignButtons = document.querySelectorAll('#msg-properties-content .align-group button');
    alignButtons.forEach(btn => btn.classList.remove('active'));
    
    const icons = { 'left': 'fa-align-left', 'center': 'fa-align-center', 'right': 'fa-align-right' };
    alignButtons.forEach(btn => {
        if (btn.querySelector(`.${icons[align]}`)) {
            btn.classList.add('active');
        }
    });
    saveMsgState();
}

function toggleMsgButtonLinkInput(action) {
    const props = document.getElementById('msg-properties-content');
    if (!props) return;
    const linkInput = props.querySelector('.btn-link-field');
    if (!linkInput) return;
    const selectedAction = action || props.querySelector('.btn-action-field')?.value || 'link';
    if (selectedAction === 'chat') {
        // set a sensible default for Chat but allow editing when properties are open
        if (!linkInput.value || linkInput.value.trim() === '') {
            linkInput.value = '../chat.php';
            updateMsgBlockData('.btn-link', '../chat.php');
        }
        // keep editable so the admin can override the chat link if needed
        linkInput.disabled = false;
        linkInput.style.opacity = '1';
    } else {
        linkInput.disabled = false;
        linkInput.style.opacity = '1';
    }
}

function toggleMsgBlockBold() {
    if (!activeMsgBlockId) return;
    const block = document.getElementById(activeMsgBlockId);
    if (!block) return;
    
    block.classList.toggle('is-bold');
    
    const btn = document.querySelector('#msg-properties-content .btn-block');
    if (btn) {
        if (block.classList.contains('is-bold')) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-bold"></i> Bold Active';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-bold"></i> Make Bold';
        }
    }
    updateMsgPreview();
    saveMsgState();
}

function duplicateMsgBlock(blockId) {
    const original = document.getElementById(blockId);
    if (!original) return;
    
    const type = original.dataset.type;
    const data = {
        custom_label: original.querySelector('.block-label-input').value
    };
    
    if (type === 'header') {
        data.text = original.querySelector('.header-text').value;
        data.color = original.querySelector('.color-input').value;
        data.bg = original.querySelector('.bg-input').value;
        data.align = original.querySelector('.align-input').value;
    } else if (type === 'text') {
        data.text = original.querySelector('.text-content').value;
        data.size = original.querySelector('.size-input').value;
        data.color = original.querySelector('.color-input').value;
        data.align = original.querySelector('.align-input').value;
        data.weight = original.classList.contains('is-bold') ? '700' : '400';
    } else if (type === 'image') {
        data.url = original.querySelector('.image-url').value;
        data.width = original.querySelector('.width-input').value;
        data.radius = original.querySelector('.radius-input').value;
        data.align = original.querySelector('.align-input').value;
        data.caption = original.querySelector('.caption-text').value;
        data.capSize = original.querySelector('.caption-size').value;
        data.capColor = original.querySelector('.caption-color').value;
        data.capWeight = original.classList.contains('is-caption-bold') ? '700' : '400';
    } else if (type === 'button') {
        data.text = original.querySelector('.btn-text').value;
        data.link = original.querySelector('.btn-link').value;
        data.action = original.querySelector('.btn-action')?.value || 'link';
        data.color = original.querySelector('.color-input').value;
        data.bg = original.querySelector('.bg-input').value;
        data.size = original.querySelector('.size-input').value;
        data.padding = original.querySelector('.padding-input').value;
        data.width = original.querySelector('.width-input').value;
        data.weight = original.classList.contains('is-bold') ? '700' : '600';
        data.align = original.querySelector('.align-input').value;
    } else if (type === 'footer') {
        data.text = original.querySelector('.footer-text').value;
        data.align = original.querySelector('.align-input').value;
    }
    
    addMsgBlock(type, data.text || '', data);
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Block Duplicated',
        showConfirmButton: false,
        timer: 1500
    });
    saveMsgState();
}

function getMsgBlocksData() {
    const blocks = document.querySelectorAll('.msg-canvas-block');
    const data = [];
    blocks.forEach(block => {
        const type = block.dataset.type;
        const blockData = { 
            type: type, 
            id: block.id,
            custom_label: block.querySelector('.block-label-input')?.value || ''
        };
        
        if (type === 'header') {
            blockData.text = block.querySelector('.header-text')?.value || '';
            blockData.color = block.querySelector('.color-input')?.value || '#ffffff';
            blockData.bg = block.querySelector('.bg-input')?.value || '#003580';
            blockData.align = block.querySelector('.align-input')?.value || 'center';
        } else if (type === 'text') {
            blockData.text = block.querySelector('.text-content')?.value || '';
            blockData.size = block.querySelector('.size-input')?.value || '16';
            blockData.color = block.querySelector('.color-input')?.value || '#334155';
            blockData.align = block.querySelector('.align-input')?.value || 'left';
            blockData.weight = block.classList.contains('is-bold') ? '700' : '400';
        } else if (type === 'image') {
            blockData.url = block.querySelector('.image-url')?.value || '';
            blockData.width = block.querySelector('.width-input')?.value || '100';
            blockData.radius = block.querySelector('.radius-input')?.value || '8';
            blockData.align = block.querySelector('.align-input')?.value || 'center';
            blockData.caption = block.querySelector('.caption-text')?.value || '';
            blockData.capSize = block.querySelector('.caption-size')?.value || '14';
            blockData.capColor = block.querySelector('.caption-color')?.value || '#64748b';
            blockData.capWeight = block.classList.contains('is-caption-bold') ? '700' : '400';
        } else if (type === 'button') {
            blockData.text = block.querySelector('.btn-text')?.value || 'Book Your Trip';
            blockData.link = block.querySelector('.btn-link')?.value || '';
            blockData.action = block.querySelector('.btn-action')?.value || 'link';
            blockData.color = block.querySelector('.color-input')?.value || '#ffffff';
            blockData.bg = block.querySelector('.bg-input')?.value || '#003580';
            blockData.size = block.querySelector('.size-input')?.value || '16';
            blockData.padding = block.querySelector('.padding-input')?.value || '12';
            blockData.width = block.querySelector('.width-input')?.value || 'auto';
            blockData.weight = block.classList.contains('is-bold') ? '700' : '600';
            blockData.align = block.querySelector('.align-input')?.value || 'center';
        } else if (type === 'footer') {
            blockData.text = block.querySelector('.footer-text')?.value || '';
            blockData.align = block.querySelector('.align-input')?.value || 'center';
        }
        data.push(blockData);
    });
    return data;
}

function updateMsgPreview() {
    const blocksData = getMsgBlocksData();
    const previewContainer = document.getElementById('msg-email-preview-container');
    if (!previewContainer) return;

    const blocksHtml = blocksData.map(block => {
        let content = '';
        const escapeNL = (text) => text ? text.replace(/\n/g, '<br>') : '';
        
        if (block.type === 'header') {
            content = `<div style="background: ${block.bg}; padding: 25px 20px; text-align: ${block.align};"><h2 style="color: ${block.color}; margin: 0; font-family: 'Outfit', sans-serif; font-size: 24px; font-weight:700;">${block.text}</h2></div>`;
        } else if (block.type === 'footer') {
            content = `
                <div style="padding: 25px; text-align: ${block.align}; background: #f8fafc; border-top: 1px solid #e2e8f0; font-family: sans-serif;">
                    <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.5;">${escapeNL(block.text)}</p>
                    <div style="margin-top: 12px; font-size: 11px;">
                        <a href="https://heydreamtravel.kesug.com/" style="color: #003580; text-decoration: none; font-weight:600;">Visit Website</a>
                    </div>
                </div>`;
        } else if (block.type === 'text') {
            content = `<div style="margin-bottom: 15px; padding: 0 25px; text-align: ${block.align}; font-family: sans-serif;"><p style="color: ${block.color}; font-size: ${block.size}px; font-weight: ${block.weight}; line-height: 1.6; margin: 0;">${escapeNL(block.text) || 'Type text here...'}</p></div>`;
        } else if (block.type === 'image') {
            const imgHtml = `<img src="${block.url}" style="width: 100%; border-radius: ${block.radius}px; display: block;" alt="Preview Image">`;
            if (block.caption) {
                const textHtml = `<div style="flex: 1; color: ${block.capColor}; font-size: ${block.capSize}px; font-weight: ${block.capWeight}; line-height: 1.5; padding: 10px; text-align: ${block.align === 'center' ? 'center' : 'left'}; font-family: sans-serif;">${escapeNL(block.caption)}</div>`;
                let containerStyle = '';
                if (block.align === 'left') containerStyle = `display: flex; align-items: center; gap: 15px; flex-direction: row;`;
                else if (block.align === 'right') containerStyle = `display: flex; align-items: center; gap: 15px; flex-direction: row-reverse;`;
                else containerStyle = `display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px;`;
                content = `<div style="margin-bottom: 15px; padding: 0 25px;"><div style="${containerStyle}"><div style="width: ${block.width}%; flex-shrink: 0;">${imgHtml}</div>${textHtml}</div></div>`;
            } else {
                content = `<div style="margin-bottom: 15px; padding: 0 25px; text-align: ${block.align};"><img src="${block.url}" style="width: ${block.width}%; border-radius: ${block.radius}px;" alt="Preview Image"></div>`;
            }
        } else if (block.type === 'button') {
            const href = block.action === 'chat' ? '../chat.php' : (block.link || '#');
            content = `<div style="text-align: ${block.align}; margin: 15px 0; padding: 0 25px; font-family: sans-serif;"><a href="${href}" style="display: inline-block; background: ${block.bg}; color: ${block.color}; padding: ${block.padding}px 24px; text-decoration: none; border-radius: 8px; font-weight: ${block.weight}; font-size: ${block.size}px; width: ${block.width}; box-sizing: border-box; text-align:center;">${block.text}</a></div>`;
        } else if (block.type === 'divider') {
            content = `<div style="padding: 0 25px;"><hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;"></div>`;
        }
        return `<div class="msg-preview-item" data-id="${block.id}" style="padding: 5px 0;">${content}</div>`;
    }).join('');

    previewContainer.innerHTML = `
        <div style="font-family: 'Poppins', sans-serif; background: #f1f5f9; padding: 15px; min-height: 100%;">
            <div style="width: 100%; max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <div id="msg-preview-sortable-list">
                    ${blocksHtml}
                </div>
            </div>
        </div>
    `;

    // Initialize sortable for preview items if needed
    const listEl = document.getElementById('msg-preview-sortable-list');
    if (listEl && typeof Sortable !== 'undefined') {
        if (msgPreviewSortable) msgPreviewSortable.destroy();
        msgPreviewSortable = new Sortable(listEl, {
            animation: 150,
            draggable: '.msg-preview-item',
            onEnd: () => {
                const previewItems = document.querySelectorAll('.msg-preview-item');
                const builderCanvas = document.getElementById('msg-builder-canvas');
                if (builderCanvas) {
                    const frag = document.createDocumentFragment();
                    previewItems.forEach(item => {
                        const block = document.getElementById(item.dataset.id);
                        if (block) frag.appendChild(block);
                    });
                    builderCanvas.appendChild(frag);
                }
            }
        });
    }
}

function setMsgPreviewSize(size, el) {
    const wrapper = document.getElementById('msg-email-preview-wrapper');
    if (!wrapper) return;
    const controls = document.querySelectorAll('#email-message .device-toggles button');
    controls.forEach(b => b.classList.remove('active'));
    
    wrapper.className = 'preview-' + size;
    if (el) el.classList.add('active');
}

function loadMsgDefaultState() {
    const canvas = document.getElementById('msg-builder-canvas');
    if (!canvas) return;
    canvas.innerHTML = '';
    
    addMsgBlock('header', 'HeyDream Travel & Tours');
    
    const welcomeText = `Dear Valued Customer,

We are excited to share the latest travel updates from HeyDream! As we welcome the new season, we have curated exclusive, high-value packages for flights, luxury cruises, and hotels tailored just for you.

Let us help you turn your next travel dream into a reality. Click the button below to browse our latest custom packages.`;
    
    addMsgBlock('text', welcomeText);
    addMsgBlock('button', 'Message us here!');
    addMsgBlock('divider');
    addMsgBlock('footer', 'You received this email because you are a registered customer or partner of HeyDream Travel and Tours.');
}

// ── AUDIENCE PICKER MODAL ────────────────────────────────────────────────────
let _selectedAudience = null;

function openAudiencePickerModal() {
    const subject = document.getElementById('email-msg-subject')?.value.trim();
    const blocks = getMsgBlocksData();
    if (!subject) return Swal.fire('Validation Error', 'Please enter a subject line first.', 'warning');
    if (blocks.length === 0) return Swal.fire('Validation Error', 'Please add at least one content block first.', 'warning');

    _selectedAudience = null;
    ['inquiries', 'website', 'partners'].forEach(a => {
        const el = document.getElementById('aud-opt-' + a);
        if (el) { el.style.borderColor = '#e2e8f0'; el.style.background = ''; }
    });
    const pl = document.getElementById('aud-partners-list');
    if (pl) pl.style.display = 'none';
    const cb = document.getElementById('aud-confirm-btn');
    if (cb) cb.disabled = true;

    const modal = document.getElementById('msg-audience-modal');
    if (modal) modal.style.display = 'flex';
}

function closeAudiencePickerModal() {
    const modal = document.getElementById('msg-audience-modal');
    if (modal) modal.style.display = 'none';
    _selectedAudience = null;
}

function selectAudience(type) {
    _selectedAudience = type;
    ['inquiries', 'website', 'partners'].forEach(a => {
        const el = document.getElementById('aud-opt-' + a);
        if (el) {
            el.style.borderColor = a === type ? '#003580' : '#e2e8f0';
            el.style.background  = a === type ? '#f0f7ff' : '';
        }
    });

    const partnersList = document.getElementById('aud-partners-list');
    const confirmBtn   = document.getElementById('aud-confirm-btn');

    if (type === 'partners') {
        if (partnersList) partnersList.style.display = 'block';
        const partners  = getBizPartners();
        const container = document.getElementById('aud-partners-checkboxes');
        const noMsg     = document.getElementById('aud-no-partners-msg');
        if (container) container.innerHTML = '';
        if (partners.length === 0) {
            if (noMsg) noMsg.style.display = 'block';
            if (confirmBtn) confirmBtn.disabled = true;
        } else {
            if (noMsg) noMsg.style.display = 'none';
            partners.forEach(p => {
                const row = document.createElement('label');
                row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 10px;background:white;border-radius:8px;border:1px solid #e2e8f0;cursor:pointer;font-size:0.85rem;';
                row.innerHTML = `
                    <input type="checkbox" class="partner-send-check" value="${p.id}" style="width:16px;height:16px;accent-color:#003580;">
                    <div>
                        <div style="font-weight:600;color:#0f172a;">${p.name || p.email}</div>
                        ${p.name ? `<div style="font-size:0.75rem;color:#64748b;">${p.email}</div>` : ''}
                    </div>`;
                if (container) container.appendChild(row);
            });
            if (confirmBtn) confirmBtn.disabled = false;
        }
    } else {
        if (partnersList) partnersList.style.display = 'none';
        if (confirmBtn) confirmBtn.disabled = false;
    }
}

async function confirmSendEmailMessage() {
    if (!_selectedAudience) {
        return Swal.fire('No Audience Selected', 'Please select an audience before sending (Website Users, Inquiry Leads, or Business Partners).', 'warning');
    }

    const targetAudience = _selectedAudience;

    let partnerEmails = [];
    if (targetAudience === 'partners') {
        const checks = document.querySelectorAll('.partner-send-check:checked');
        const all    = getBizPartners();
        checks.forEach(ch => {
            const p = all.find(x => x.id === ch.value);
            if (p) partnerEmails.push(p.email);
        });
        if (partnerEmails.length === 0) {
            return Swal.fire('No Partners Selected', 'Please tick at least one business partner.', 'warning');
        }
    }
    closeAudiencePickerModal();
    await sendEmailMessage(targetAudience, partnerEmails);
}

async function sendEmailMessage(audience, partners = []) {
    const subject = document.getElementById('email-msg-subject')?.value.trim();
    const blocks  = getMsgBlocksData();

    const sendBtn = document.getElementById('email-msg-send-btn');
    const origHtml = sendBtn ? sendBtn.innerHTML : '';

    Swal.fire({
        title: 'Sending Email Message',
        text: 'Please wait while we process and send the messages...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    }

    const formData = new FormData();
    formData.append('action',   'send_direct_message');
    formData.append('subject',  subject);
    formData.append('blocks',   JSON.stringify(blocks));
    formData.append('audience', audience);
    if (audience === 'partners') {
        formData.append('partners', partners.join(','));
    }

    try {
        const response = await fetch('api/marketing_api.php', { method: 'POST', body: formData });
        const result   = await response.json();
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sent Successfully!',
                text: result.message || 'Email message has been sent to the selected audience.',
                confirmButtonColor: '#003580'
            });
            closeMessageModal();
        } else {
            Swal.fire('Error Sending Message', result.message || 'An unexpected error occurred.', 'error');
        }
    } catch (err) {
        console.error('sendEmailMessage error:', err);
        Swal.fire('Network Error', 'Could not connect to the marketing server.', 'error');
    } finally {
        if (sendBtn) { sendBtn.disabled = false; sendBtn.innerHTML = origHtml; }
    }
}

// ── BUSINESS PARTNERS MANAGEMENT ─────────────────────────────────────────────
const BIZ_PARTNERS_KEY = 'heydream_biz_partners';

function getBizPartners() {
    try {
        let partners = JSON.parse(localStorage.getItem(BIZ_PARTNERS_KEY) || '[]');
        const originalLength = partners.length;
        partners = partners.filter(p => p && p.email && p.email.toLowerCase().trim() !== 'asuelajohnkostya30@gmail.com');
        if (partners.length !== originalLength) {
            localStorage.setItem(BIZ_PARTNERS_KEY, JSON.stringify(partners));
        }
        return partners;
    }
    catch { return []; }
}

function saveBizPartners(list) {
    localStorage.setItem(BIZ_PARTNERS_KEY, JSON.stringify(list));
    updatePartnersCount();
}

function updatePartnersCount() {
    const count = getBizPartners().length;
    const el = document.getElementById('msg-partners-count');
    if (el) el.textContent = `${count} partner${count !== 1 ? 's' : ''} saved`;
}

function openBusinessPartnersModal() {
    renderBizPartnersList();
    const modal = document.getElementById('msg-biz-partners-modal');
    if (modal) modal.style.display = 'flex';
    const ei = document.getElementById('new-partner-email-input');
    const ni = document.getElementById('new-partner-name-input');
    if (ei) { ei.value = ''; ei.style.borderColor = ''; }
    if (ni) ni.value = '';
    if (ei) ei.focus();
}

function closeBusinessPartnersModal() {
    const modal = document.getElementById('msg-biz-partners-modal');
    if (modal) modal.style.display = 'none';
}

function addBusinessPartner() {
    const emailInput = document.getElementById('new-partner-email-input');
    const nameInput  = document.getElementById('new-partner-name-input');
    const email = emailInput?.value.trim();
    const name  = nameInput?.value.trim();

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (emailInput) { emailInput.style.borderColor = '#ef4444'; setTimeout(() => emailInput.style.borderColor = '', 1500); }
        return;
    }
    const partners = getBizPartners();
    if (partners.find(p => p.email.toLowerCase() === email.toLowerCase())) {
        return Swal.fire({ icon: 'warning', title: 'Already Added', text: 'This email is already in your business partners list.', confirmButtonColor: '#003580' });
    }
    partners.push({ id: 'bp_' + Date.now(), email, name });
    saveBizPartners(partners);
    renderBizPartnersList();
    if (emailInput) { emailInput.value = ''; emailInput.focus(); }
    if (nameInput)  nameInput.value = '';
}

function removeBusinessPartner(id) {
    saveBizPartners(getBizPartners().filter(p => p.id !== id));
    renderBizPartnersList();
}

function renderBizPartnersList() {
    const container = document.getElementById('biz-partners-list');
    const noMsg     = document.getElementById('no-biz-partners-msg');
    if (!container) return;
    Array.from(container.children).forEach(c => { if (c.id !== 'no-biz-partners-msg') c.remove(); });
    const partners = getBizPartners();
    if (partners.length === 0) { if (noMsg) noMsg.style.display = 'block'; return; }
    if (noMsg) noMsg.style.display = 'none';
    partners.forEach(p => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;';
        row.innerHTML = `
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#003580,#0056b3);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-user-tie" style="color:white;font-size:0.85rem;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:0.88rem;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.name || p.email}</div>
                ${p.name ? `<div style="font-size:0.75rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.email}</div>` : ''}
            </div>
            <button onclick="removeBusinessPartner('${p.id}')" style="background:none;border:none;cursor:pointer;color:#ef4444;padding:4px 8px;border-radius:6px;" title="Remove">
                <i class="fas fa-trash-alt"></i>
            </button>`;
        container.appendChild(row);
    });
}

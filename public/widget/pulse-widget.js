(async function () {
    const script = document.currentScript;
    if (!script) return;

    const sourceIdentifier = script.dataset.source;
    if (!sourceIdentifier) {
        console.error('[PulseWidget] data-source is required');
        return;
    }

    const apiBase = (script.dataset.api || window.location.origin).replace(/\/+$/, '');
    const endpoint = `${apiBase}/api/widget`;
    const position = script.dataset.position === 'left' ? 'left' : 'right';

    const defaultIconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>`;

    let config = {
        title: 'Поддержка',
        subtitle: 'Обычно отвечаем за пару минут',
        primaryColor: '#4f46e5', 
        textColor: '#ffffff',
        iconSvg: defaultIconSvg
    };

    try {
        const res = await fetch(`${endpoint}/config-ui?source=${encodeURIComponent(sourceIdentifier)}`);
        if (res.ok) {
            const serverConfig = await res.json();
            config = { ...config, ...serverConfig };
            if (!config.iconSvg) {
                config.iconSvg = defaultIconSvg;
            }
        }
    } catch (e) {
        console.warn('[PulseWidget] Failed to load UI config, using defaults.', e);
    }

    const closeIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
    const sendIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>`;

    const storageKey = `pulse_widget_vid_${sourceIdentifier}`;
    const chatTokenKey = `pulse_widget_chat_token_${sourceIdentifier}`;
    const chatIdKey = `pulse_widget_chat_id_${sourceIdentifier}`;

    let visitorId = localStorage.getItem(storageKey);
    if (!visitorId) {
        visitorId = crypto && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}_${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(storageKey, visitorId);
    }

    // 1. Создаем Host-элемент и открываем Shadow DOM
    const host = document.createElement('div');
    host.id = 'pulse-widget-host';
    // Фиксируем хост, чтобы он не влиял на поток документа сайта
    host.style.position = 'fixed';
    host.style.zIndex = '2147483646';
    host.style.bottom = '24px';
    host.style[position] = '24px';
    document.body.appendChild(host);

    const shadowRoot = host.attachShadow({ mode: 'open' });

    // 2. Создаем внутренний контейнер
    const root = document.createElement('div');
    root.id = 'pulse-widget-root';
    root.style.setProperty('--pw-primary', config.primaryColor);
    root.style.setProperty('--pw-text', config.textColor);
    root.style.fontFamily = '"Inter", system-ui, -apple-system, sans-serif';
    
    // 3. Стили теперь живут ВНУТРИ Shadow DOM и ни с чем не конфликтуют
    const style = document.createElement('style');
    style.textContent = `
      :host { all: initial; } /* Сбрасываем всё, что могло просочиться на сам host */
      * { box-sizing: border-box; margin: 0; padding: 0; }
      
      button { outline: none; font-family: inherit; }
      textarea { outline: none; font-family: inherit; }
      
      .pw-fab { width: 60px; height: 60px; border-radius: 50%; border: none; color: var(--pw-text); background: var(--pw-primary); box-shadow: 0 10px 24px -6px rgba(0,0,0,0.25); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease; position: absolute; bottom: 0; ${position}: 0; z-index: 2;}
      .pw-fab:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 14px 28px -6px rgba(0,0,0,0.3); }
      .pw-fab:active { transform: scale(0.95); }
      .pw-fab svg { fill: currentColor; }
      
      .pw-panel { width: min(380px, calc(100vw - 32px)); height: min(680px, calc(100vh - 32px)); background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px -8px rgba(0,0,0,0.15), 0 1px 3px rgba(0,0,0,0.05); display: none; flex-direction: column; overflow: hidden; opacity: 0; transform: translateY(20px) scale(0.98); transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); border: 1px solid #f1f5f9; transform-origin: bottom ${position}; position: absolute; bottom: 0; ${position}: 0; z-index: 1;}
      .pw-panel.pw-open { display: flex; opacity: 1; transform: translateY(0) scale(1); }
      
      .pw-head { padding: 24px 20px; color: var(--pw-text); background: var(--pw-primary); display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; position: relative; }
      .pw-head::after { content: ''; position: absolute; bottom: -10px; left: 0; right: 0; height: 10px; background: linear-gradient(to bottom, rgba(0,0,0,0.05), transparent); pointer-events: none; z-index: 1; }
      .pw-title { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.2; }
      .pw-sub { font-size: 13px; opacity: 0.9; margin-top: 4px; font-weight: 400; }
      .pw-close { border: none; background: rgba(255,255,255,0.15); border-radius: 50%; width: 32px; height: 32px; color: inherit; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s, transform 0.2s; }
      .pw-close:hover { background: rgba(255,255,255,0.25); transform: rotate(90deg); }
      
      .pw-msgs { flex: 1; overflow-y: auto; padding: 20px; background: #f8fafc; display: flex; flex-direction: column; gap: 16px; scroll-behavior: smooth; }
      .pw-msgs::-webkit-scrollbar { width: 6px; }
      .pw-msgs::-webkit-scrollbar-track { background: transparent; }
      .pw-msgs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
      
      .pw-msg { max-width: 80%; padding: 12px 16px; font-size: 14px; line-height: 1.5; word-break: break-word; position: relative; animation: pw-slide-up 0.3s ease forwards; }
      @keyframes pw-slide-up { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
      
      .pw-msg--me { align-self: flex-end; background: var(--pw-primary); color: var(--pw-text); border-radius: 18px 18px 4px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
      .pw-msg--bot { align-self: flex-start; background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 18px 18px 18px 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
      .pw-time { display: block; margin-top: 4px; font-size: 11px; opacity: 0.6; text-align: right; user-select: none; }
      
      .pw-form-container { background: #ffffff; padding: 16px; border-top: 1px solid #f1f5f9; box-shadow: 0 -4px 12px rgba(0,0,0,0.02); }
      .pw-status { font-size: 12px; color: #94a3b8; margin-bottom: 12px; text-align: center; font-weight: 500; }
      .pw-form { display: flex; gap: 10px; align-items: flex-end; background: #f1f5f9; padding: 6px; border-radius: 20px; border: 1px solid transparent; transition: border-color 0.2s, background 0.2s; }
      .pw-form:focus-within { background: #ffffff; border-color: var(--pw-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
      
      .pw-input { flex: 1; resize: none; min-height: 24px; max-height: 120px; padding: 10px 12px; border: none; background: transparent; font-size: 14px; color: #0f172a; line-height: 1.4; }
      .pw-input::placeholder { color: #94a3b8; }
      
      .pw-send { width: 36px; height: 36px; border: none; border-radius: 50%; background: var(--pw-primary); color: var(--pw-text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s, transform 0.15s; flex-shrink: 0; margin-bottom: 4px; margin-right: 4px; }
      .pw-send:hover { transform: scale(1.05) translateY(-1px); }
      .pw-send:active { transform: scale(0.95); }
      .pw-send[disabled] { opacity: 0.5; cursor: not-allowed; transform: none; background: #cbd5e1; }
      
      @media (max-width: 640px) {
        :host { bottom: 0 !important; left: 0 !important; right: 0 !important; top: 0 !important; pointer-events: none; width: 100%; height: 100%; }
        #pulse-widget-root { width: 100%; height: 100%; pointer-events: none; }
        .pw-fab { bottom: 20px; ${position}: 20px; pointer-events: auto; }
        .pw-panel { width: 100%; height: 100%; max-height: 100vh; border-radius: 0; border: none; pointer-events: auto; bottom: 0; ${position}: 0; }
        .pw-head { border-radius: 0; }
      }
    `;
    
    // Подключаем стили и HTML в Shadow DOM, а не в body
    shadowRoot.appendChild(style);

    root.innerHTML = `
      <button class="pw-fab" type="button" aria-label="Open chat">
        ${config.iconSvg}
      </button>
      <section class="pw-panel" role="dialog" aria-label="Pulse chat widget">
        <header class="pw-head">
          <div>
            <div class="pw-title">${config.title}</div>
            <div class="pw-sub">${config.subtitle}</div>
          </div>
          <button class="pw-close" type="button" aria-label="Close">
            ${closeIcon}
          </button>
        </header>
        <div class="pw-msgs"></div>
        <div class="pw-form-container">
            <div class="pw-status">Готов к подключению</div>
            <form class="pw-form">
            <textarea class="pw-input" placeholder="Напишите сообщение..." rows="1"></textarea>
            <button class="pw-send" type="submit" disabled aria-label="Send">
                ${sendIcon}
            </button>
            </form>
        </div>
      </section>
    `;
    
    shadowRoot.appendChild(root);

    // 4. Логика (ищем элементы внутри shadowRoot, а не в document)
    const fab = shadowRoot.querySelector('.pw-fab');
    const panel = shadowRoot.querySelector('.pw-panel');
    const closeBtn = shadowRoot.querySelector('.pw-close');
    const msgs = shadowRoot.querySelector('.pw-msgs');
    const status = shadowRoot.querySelector('.pw-status');
    const form = shadowRoot.querySelector('.pw-form');
    const input = shadowRoot.querySelector('.pw-input');
    const sendBtn = shadowRoot.querySelector('.pw-send');

    let chatToken = localStorage.getItem(chatTokenKey) || null;
    let chatId = localStorage.getItem(chatIdKey) || null;
    let lastMessageId = 0;
    let lastSentMessageId = 0;
    let pollTimer = null;
    let echoChannel = null;

    function setOpen(open) {
        if (open) {
            panel.classList.add('pw-open');
            fab.style.display = 'none';
            input.focus();
        } else {
            panel.classList.remove('pw-open');
            setTimeout(() => { fab.style.display = 'flex'; }, 300);
        }
    }

    function setStatus(text) { status.textContent = text; }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    function renderMessage(message) {
        if (message.id <= lastMessageId) return;
        lastMessageId = Math.max(lastMessageId, message.id);

        const isMe = message.sender_type === 'client';
        const wrap = document.createElement('div');
        wrap.className = `pw-msg ${isMe ? 'pw-msg--me' : 'pw-msg--bot'}`;
        wrap.innerHTML = `
            <div>${(message.text || '').replace(/[<>&]/g, (m) => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[m]))}</div>
            <span class="pw-time">${formatTime(message.created_at)}</span>
        `;
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
    }

    async function request(path, options = {}) {
        const res = await fetch(`${endpoint}${path}`, {
            ...options,
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function ensureSession() {
        const payload = { source_identifier: sourceIdentifier, visitor_id: visitorId, meta: { url: window.location.href, title: document.title, referrer: document.referrer || null, user_agent: navigator.userAgent } };
        if (!chatToken) {
            const result = await request('/session', { method: 'POST', body: JSON.stringify(payload) });
            chatToken = result.chat_token;
            localStorage.setItem(chatTokenKey, chatToken);
            if (result.chat && result.chat.id != null) { chatId = String(result.chat.id); localStorage.setItem(chatIdKey, chatId); }
        }
        sendBtn.disabled = !chatToken;
        setStatus(chatToken ? 'Оператор на связи' : 'Ошибка подключения');
    }

    async function loadMessages() {
        if (!chatToken) return;
        const result = await request(`/messages?chat_token=${encodeURIComponent(chatToken)}&limit=100`);
        msgs.innerHTML = ''; lastMessageId = 0;
        for (const message of result.messages || []) renderMessage(message);
        if (!result.messages || result.messages.length === 0) setStatus('Напишите нам, мы скоро ответим');
    }

    async function pollMessages() {
        if (!chatToken) return;
        const result = await request(`/messages?chat_token=${encodeURIComponent(chatToken)}&limit=30`);
        for (const message of result.messages || []) renderMessage(message);
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const el = document.createElement('script'); el.src = src; el.onload = resolve; el.onerror = reject; document.head.appendChild(el);
        });
    }

    function connectReverb() {
        if (!chatId || echoChannel) return;
        request('/config').then(function (data) {
            if (!data.reverb || !data.reverb.key) {
                if (chatToken && !pollTimer) pollTimer = setInterval(function () { pollMessages().catch(function () {}); }, 3000);
                return;
            }
            const r = data.reverb;
            const useTls = r.scheme === 'https';
            const channelName = 'widget-chat.' + chatId;
            function subscribe() {
                const Echo = window.Echo; if (!Echo) return;
                const echo = new Echo({
                    broadcaster: 'pusher',
                    key: r.key,
                    cluster: r.cluster || 'mt1',
                    wsHost: r.host,
                    wssHost: r.host,
                    wsPort: r.port,
                    wssPort: r.port,
                    wsPath: r.wsPath || '/app',
                    forceTLS: useTls,
                    disableStats: true,
                    enabledTransports: useTls ? ['ws', 'wss'] : ['ws'],
                });
                echoChannel = echo.channel(channelName);
                function onNewMessage(e) {
                    if (e.chatId !== parseInt(chatId, 10) || e.messageId === lastSentMessageId) return;
                    renderMessage({ id: e.messageId, text: e.text || '', sender_type: 'moderator', created_at: new Date().toISOString() });
                }
                echoChannel.listen('.App.Events.NewChatMessage', onNewMessage);
                echoChannel.listen('.App\\Events\\NewChatMessage', onNewMessage);
            }
            if (window.Pusher && window.Echo) { subscribe(); return; }
            loadScript('https://js.pusher.com/8.3.0/pusher.min.js')
                .then(function () { return loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.min.js'); })
                .then(subscribe).catch(function (err) { console.warn('[PulseWidget] Reverb failed', err); });
        }).catch(function () {});
    }

    fab.addEventListener('click', async () => {
        setOpen(true);
        try {
            setStatus('Подключение...');
            await ensureSession();
            await loadMessages();
            connectReverb();
        } catch (error) {
            setStatus('Ошибка подключения');
            console.error('[PulseWidget] init error', error);
        }
    });

    closeBtn.addEventListener('click', () => setOpen(false));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        input.value = ''; input.style.height = 'auto';
        try {
            const result = await request('/messages', { method: 'POST', body: JSON.stringify({ chat_token: chatToken, text: text, payload: {} }) });
            if (result.message) {
                lastSentMessageId = result.message.id;
                renderMessage({ id: result.message.id, text: result.message.text, sender_type: 'client', created_at: result.message.created_at });
            }
        } catch (error) { setStatus('Не удалось отправить. Повторите'); }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
    });
})();
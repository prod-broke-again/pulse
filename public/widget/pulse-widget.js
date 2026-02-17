(function () {
    const script = document.currentScript;
    if (!script) return;

    const sourceIdentifier = script.dataset.source;
    if (!sourceIdentifier) {
        console.error('[PulseWidget] data-source is required');
        return;
    }

    const apiBase = (script.dataset.api || window.location.origin).replace(/\/+$/, '');
    const title = script.dataset.title || 'Поддержка';
    const primary = script.dataset.primary || '#0ea5e9';
    const position = script.dataset.position === 'left' ? 'left' : 'right';
    const endpoint = `${apiBase}/api/widget`;
    const storageKey = `pulse_widget_vid_${sourceIdentifier}`;
    const chatTokenKey = `pulse_widget_chat_token_${sourceIdentifier}`;

    let visitorId = localStorage.getItem(storageKey);
    if (!visitorId) {
        visitorId = crypto && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}_${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(storageKey, visitorId);
    }

    const root = document.createElement('div');
    root.setAttribute('id', 'pulse-widget-root');
    root.style.position = 'fixed';
    root.style.zIndex = '2147483646';
    root.style.bottom = '20px';
    root.style[position] = '20px';
    root.style.fontFamily = 'Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif';
    root.style.color = '#111827';
    document.body.appendChild(root);

    const style = document.createElement('style');
    style.textContent = `
      #pulse-widget-root * { box-sizing: border-box; }
      .pw-fab { width: 60px; height: 60px; border-radius: 999px; border: 0; color: #fff; background: ${primary}; box-shadow: 0 14px 30px rgba(2,132,199,.35); cursor: pointer; font-size: 28px; }
      .pw-panel { width: min(380px, calc(100vw - 24px)); height: min(640px, calc(100vh - 24px)); border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 24px 64px rgba(0,0,0,.22); display: none; flex-direction: column; border: 1px solid #e5e7eb; }
      .pw-head { padding: 14px 16px; color: #fff; background: linear-gradient(120deg, ${primary}, #0284c7); display: flex; justify-content: space-between; align-items: center; gap: 8px; }
      .pw-title { font-size: 15px; font-weight: 700; }
      .pw-sub { font-size: 12px; opacity: .9; }
      .pw-close { border: 0; background: transparent; color: #fff; font-size: 20px; cursor: pointer; }
      .pw-msgs { flex: 1; overflow-y: auto; padding: 14px; background: #f8fafc; display: flex; flex-direction: column; gap: 10px; }
      .pw-msg { max-width: 82%; padding: 10px 12px; border-radius: 14px; font-size: 14px; line-height: 1.35; white-space: pre-wrap; word-break: break-word; }
      .pw-msg--me { align-self: flex-end; background: ${primary}; color: #fff; border-bottom-right-radius: 4px; }
      .pw-msg--bot { align-self: flex-start; background: #fff; color: #111827; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
      .pw-time { margin-top: 4px; font-size: 11px; opacity: .75; }
      .pw-form { border-top: 1px solid #e5e7eb; padding: 10px; background: #fff; display: flex; gap: 8px; }
      .pw-input { resize: none; min-height: 42px; max-height: 120px; padding: 10px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; width: 100%; outline: none; }
      .pw-input:focus { border-color: ${primary}; box-shadow: 0 0 0 3px rgba(14,165,233,.15); }
      .pw-send { border: 0; border-radius: 10px; background: ${primary}; color: #fff; font-weight: 600; padding: 0 14px; cursor: pointer; }
      .pw-send[disabled] { opacity: .55; cursor: not-allowed; }
      .pw-status { font-size: 12px; color: #6b7280; padding: 8px 12px; }
      @media (max-width: 640px) {
        #pulse-widget-root { left: 0 !important; right: 0 !important; bottom: 0 !important; }
        .pw-panel { width: 100vw; height: 100vh; border-radius: 0; border: 0; }
        .pw-fab { position: fixed; ${position}: 16px; bottom: 16px; }
      }
    `;
    document.head.appendChild(style);

    root.innerHTML = `
      <button class="pw-fab" type="button" aria-label="Open chat">💬</button>
      <section class="pw-panel" role="dialog" aria-label="Pulse chat widget">
        <header class="pw-head">
          <div>
            <div class="pw-title">${title}</div>
            <div class="pw-sub">Обычно отвечаем за пару минут</div>
          </div>
          <button class="pw-close" type="button" aria-label="Close">×</button>
        </header>
        <div class="pw-msgs"></div>
        <div class="pw-status">Подключение...</div>
        <form class="pw-form">
          <textarea class="pw-input" placeholder="Напишите сообщение..." rows="1"></textarea>
          <button class="pw-send" type="submit" disabled>Отправить</button>
        </form>
      </section>
    `;

    const fab = root.querySelector('.pw-fab');
    const panel = root.querySelector('.pw-panel');
    const closeBtn = root.querySelector('.pw-close');
    const msgs = root.querySelector('.pw-msgs');
    const status = root.querySelector('.pw-status');
    const form = root.querySelector('.pw-form');
    const input = root.querySelector('.pw-input');
    const sendBtn = root.querySelector('.pw-send');

    let chatToken = localStorage.getItem(chatTokenKey) || null;
    let lastMessageId = 0;
    let pollTimer = null;

    function setOpen(open) {
        panel.style.display = open ? 'flex' : 'none';
        fab.style.display = open ? 'none' : 'inline-flex';
        if (open) input.focus();
    }

    function setStatus(text) {
        status.textContent = text;
    }

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
            <div class="pw-time">${formatTime(message.created_at)}</div>
        `;
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
    }

    async function request(path, options = {}) {
        const res = await fetch(`${endpoint}${path}`, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });
        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(errorText || `HTTP ${res.status}`);
        }
        return res.json();
    }

    async function ensureSession() {
        if (chatToken) return;
        const payload = {
            source_identifier: sourceIdentifier,
            visitor_id: visitorId,
            meta: {
                url: window.location.href,
                title: document.title,
                referrer: document.referrer || null,
                user_agent: navigator.userAgent,
            },
        };
        const result = await request('/session', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        chatToken = result.chat_token;
        localStorage.setItem(chatTokenKey, chatToken);
        sendBtn.disabled = false;
        setStatus('Оператор на связи');
    }

    async function loadMessages() {
        if (!chatToken) return;
        const result = await request(`/messages?chat_token=${encodeURIComponent(chatToken)}&limit=100`);
        msgs.innerHTML = '';
        lastMessageId = 0;
        for (const message of result.messages || []) {
            renderMessage(message);
        }
        if (!result.messages || result.messages.length === 0) {
            setStatus('Напишите нам, мы скоро ответим');
        }
    }

    async function pollMessages() {
        if (!chatToken) return;
        const result = await request(`/messages?chat_token=${encodeURIComponent(chatToken)}&limit=30`);
        for (const message of result.messages || []) {
            renderMessage(message);
        }
    }

    async function sendMessage(text) {
        if (!chatToken || !text.trim()) return;
        await request('/messages', {
            method: 'POST',
            body: JSON.stringify({
                chat_token: chatToken,
                text: text.trim(),
                payload: {},
            }),
        });
    }

    fab.addEventListener('click', async () => {
        setOpen(true);
        try {
            setStatus('Подключение...');
            await ensureSession();
            await loadMessages();
            if (!pollTimer) {
                pollTimer = setInterval(() => {
                    pollMessages().catch(() => {});
                }, 3000);
            }
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

        input.value = '';
        input.style.height = 'auto';
        try {
            await sendMessage(text);
            await pollMessages();
        } catch (error) {
            setStatus('Не удалось отправить. Повторите');
            console.error('[PulseWidget] send error', error);
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
})();

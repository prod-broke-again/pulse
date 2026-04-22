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
    const themeModeAttr = script.dataset.theme === 'light' || script.dataset.theme === 'dark' || script.dataset.theme === 'system'
        ? script.dataset.theme
        : 'system';

    const defaultIconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>`;
    const iconBellOn = '<svg class="pw-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';
    const iconBellOff = '<svg class="pw-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.7 3h6.6c.3 0 .5.1.7.2l-8 8H4.6c-.3 0-.5-.1-.6-.2a.9.9 0 0 1-.1-.4V7.5A4.4 4.4 0 0 1 8.7 3Z"/><path d="M8.7 4.5 3.3 9.8a.7.7 0 0 0-.1.4v.3c0 5 1.3 6.3 1.3 6.3"/><path d="M14.4 3.5c1.1.5 1.7 1.4 1.7 2.3v.3"/><path d="M9.2 20.5a1.8 1.8 0 0 0 2.1.5"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    const closeIcon = `<svg class="pw-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
    const sendIcon = `<svg class="pw-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>`;
    const readIconSvg = '<span class="pw-read-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg></span>';
    const iconUser = '<svg class="pw-icon pw-icon--sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

    const defaultResponseSlaText = 'Стараемся ответить в течение 2 часов в рабочее время.';
    const defaultCloseTabNotificationText = 'Если закроете вкладку: при ответе оператора вы услышите сигнал, увидите число в заголовке страницы и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если оставили email в анкете — дублируем ответ письмом.';

    let config = {
        title: 'Поддержка',
        subtitle: 'Обычно отвечаем за пару минут',
        responseSlaText: defaultResponseSlaText,
        closeTabNotificationText: defaultCloseTabNotificationText,
        primaryColor: '#55175e',
        textColor: '#ffffff',
        iconSvg: defaultIconSvg
    };

    let allowedOrigins = [window.location.origin];
    let guestDataFromParent = { name: null, email: null };

    try {
        const res = await fetch(`${endpoint}/config-ui?source=${encodeURIComponent(sourceIdentifier)}`);
        if (res.ok) {
            const serverConfig = await res.json();
            config = { ...config, ...serverConfig };
            if (!config.iconSvg) {
                config.iconSvg = defaultIconSvg;
            }
            if (Array.isArray(serverConfig.allowed_origins) && serverConfig.allowed_origins.length) {
                allowedOrigins = serverConfig.allowed_origins;
            }
        }
    } catch (e) {
        console.warn('[PulseWidget] Failed to load UI config, using defaults.', e);
    }
    if (typeof config.responseSlaText !== 'string' || !String(config.responseSlaText).trim()) {
        config.responseSlaText = defaultResponseSlaText;
    }
    if (typeof config.closeTabNotificationText !== 'string' || !String(config.closeTabNotificationText).trim()) {
        config.closeTabNotificationText = defaultCloseTabNotificationText;
    }
    if (script.dataset.sla) {
        config.responseSlaText = script.dataset.sla;
    }
    if (script.dataset.closeHint) {
        config.closeTabNotificationText = script.dataset.closeHint;
    }

    if (script.dataset.mockGuest) {
        try {
            const mock = JSON.parse(script.dataset.mockGuest);
            if (mock && typeof mock.name === 'string') guestDataFromParent = { name: mock.name, email: mock.email || null };
        } catch (err) { /* empty */ }
    }

    window.addEventListener('message', function (event) {
        if (allowedOrigins.indexOf(event.origin) === -1) return;
        const d = event.data;
        if (!d || d.type !== 'PULSE_GUEST_DATA') return;
        if (typeof d.name === 'string' && d.name.trim()) {
            guestDataFromParent = { name: d.name.trim(), email: (typeof d.email === 'string' && d.email.trim()) ? d.email.trim() : null };
        }
    });

    const storageKey = `pulse_widget_vid_${sourceIdentifier}`;
    const chatTokenKey = `pulse_widget_chat_token_${sourceIdentifier}`;
    const chatIdKey = `pulse_widget_chat_id_${sourceIdentifier}`;
    const guestDataKey = `pulse_widget_guest_${sourceIdentifier}`;
    const widgetSoundKey = `pulse_widget_sound_${sourceIdentifier}`;
    const themeStorageKey = `pulse_widget_theme_${sourceIdentifier}`;

    function getGuestData() {
        if (guestDataFromParent.name) return guestDataFromParent;
        try {
            const stored = localStorage.getItem(guestDataKey);
            if (stored) {
                const parsed = JSON.parse(stored);
                if (parsed && typeof parsed.name === 'string') return { name: parsed.name, email: parsed.email || null };
            }
        } catch (e) { /* empty */ }
        const mock = typeof window.__PULSE_MOCK_GUEST__ !== 'undefined' ? window.__PULSE_MOCK_GUEST__ : null;
        if (mock && typeof mock.name === 'string') return { name: mock.name, email: mock.email || null };
        return { name: null, email: null };
    }

    function setGuestDataStored(name, email) {
        try {
            localStorage.setItem(guestDataKey, JSON.stringify({ name: name, email: email || null }));
        } catch (e) { /* empty */ }
    }

    function getWidgetSoundSettings() {
        try {
            const raw = localStorage.getItem(widgetSoundKey);
            if (!raw) return { enabled: true, volume: 1 };
            const o = JSON.parse(raw);
            return {
                enabled: o.enabled !== false,
                volume: typeof o.volume === 'number' ? Math.min(1, Math.max(0, o.volume)) : 1,
            };
        } catch (e) {
            return { enabled: true, volume: 1 };
        }
    }
    function saveWidgetSoundSettings(s) {
        try {
            localStorage.setItem(widgetSoundKey, JSON.stringify(s));
        } catch (e) { /* empty */ }
    }

    function readStoredThemeMode() {
        try {
            const raw = localStorage.getItem(themeStorageKey);
            if (raw === 'light' || raw === 'dark' || raw === 'system') return raw;
        } catch (e) { /* empty */ }
        return themeModeAttr;
    }

    function detectSystemDark() {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function resolveDarkByMode(mode) {
        if (mode === 'dark') return true;
        if (mode === 'light') return false;
        return detectSystemDark();
    }

    let visitorId = localStorage.getItem(storageKey);
    if (!visitorId) {
        visitorId = crypto && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}_${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(storageKey, visitorId);
    }

    const host = document.createElement('div');
    host.id = 'pulse-widget-host';
    host.style.position = 'fixed';
    host.style.zIndex = '2147483646';
    host.style.bottom = '24px';
    host.style[position] = '24px';
    document.body.appendChild(host);

    const shadowRoot = host.attachShadow({ mode: 'open' });
    const root = document.createElement('div');
    root.id = 'pulse-widget-root';
    let themeMode = readStoredThemeMode();
    function applyPwTheme() {
        const isDark = resolveDarkByMode(themeMode);
        root.dataset.pwTheme = isDark ? 'dark' : 'light';
        root.style.setProperty('--pw-primary', config.primaryColor);
        root.style.setProperty('--pw-on-primary', config.textColor);
    }
    applyPwTheme();
    root.style.fontFamily = 'Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        const mm = window.matchMedia('(prefers-color-scheme: dark)');
        const onSystem = function () { if (themeMode === 'system') applyPwTheme(); };
        if (typeof mm.addEventListener === 'function') mm.addEventListener('change', onSystem);
        else if (typeof mm.addListener === 'function') mm.addListener(onSystem);
    }

    const style = document.createElement('style');
    style.textContent = `
      :host { all: initial; }
      * { box-sizing: border-box; margin: 0; padding: 0; }
      button { outline: none; font-family: inherit; cursor: pointer; }
      textarea { outline: none; font-family: inherit; }
      .pw-icon { width: 20px; height: 20px; display: block; }
      .pw-icon--sm { width: 16px; height: 16px; }

      #pulse-widget-root { --pw-radius-lg: 14px; --pw-radius-md: 10px; --pw-radius-full: 9999px; font-size: 15px; }
      #pulse-widget-root[data-pw-theme="light"] {
        --pw-bg-panel: #ffffff; --pw-bg-thread: #f8f7fa; --pw-bg-bubble-in: #ffffff; --pw-bg-bubble-out: #55175e;
        --pw-text: #1a1a1a; --pw-text-muted: #6b6578; --pw-text-on-brand: #ffffff;
        --pw-border: #e8e4ed; --pw-shadow: 0 4px 16px rgba(85, 23, 94, 0.08);
        --pw-form-bg: #ffffff; --pw-input-placeholder: #9b95a3;
        --pw-focus-ring: color-mix(in srgb, var(--pw-primary) 20%, transparent);
      }
      #pulse-widget-root[data-pw-theme="dark"] {
        --pw-bg-panel: #201a26; --pw-bg-thread: #1c1722; --pw-bg-bubble-in: #2d2535; --pw-bg-bubble-out: #55175e;
        --pw-text: #e8e4ed; --pw-text-muted: #9b95a3; --pw-text-on-brand: #ffffff;
        --pw-border: #2d2535; --pw-shadow: 0 4px 20px rgba(0,0,0,0.4);
        --pw-form-bg: #241e2a; --pw-input-placeholder: #6b6578;
        --pw-focus-ring: color-mix(in srgb, var(--pw-primary) 28%, transparent);
      }

      .pw-fab-wrap { position: absolute; bottom: 0; ${position}: 0; z-index: 2; }
      .pw-fab {
        width: 56px; height: 56px; border-radius: var(--pw-radius-full); border: none;
        color: var(--pw-on-primary); background: var(--pw-primary);
        box-shadow: var(--pw-shadow);
        display: flex; align-items: center; justify-content: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease; position: relative;
      }
      .pw-fab:hover { transform: scale(1.06); }
      .pw-fab:active { transform: scale(0.96); }
      .pw-fab-badge {
        position: absolute; top: -2px; right: -2px; min-width: 20px; height: 20px; padding: 0 5px;
        border-radius: var(--pw-radius-full); background: #ef4444; color: #fff;
        font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; display: none;
      }
      .pw-fab-badge.pw-show { display: inline-block; }

      .pw-panel {
        width: min(400px, calc(100vw - 32px)); height: min(680px, calc(100vh - 32px));
        background: var(--pw-bg-panel); border-radius: var(--pw-radius-lg);
        box-shadow: var(--pw-shadow);
        display: none; flex-direction: column; overflow: hidden; opacity: 0;
        transform: translateY(12px) scale(0.99);
        transition: opacity 0.25s ease, transform 0.25s ease;
        border: 1px solid var(--pw-border);
        transform-origin: bottom ${position};
        position: absolute; bottom: 0; ${position}: 0; z-index: 1;
        color: var(--pw-text);
      }
      .pw-panel.pw-open { display: flex; opacity: 1; transform: translateY(0) scale(1); }

      .pw-head {
        padding: 14px 16px;
        background: var(--pw-bg-panel);
        border-bottom: 1px solid var(--pw-border);
        display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-shrink: 0;
      }
      .pw-head-brand { min-width: 0; }
      .pw-title { font-size: 16px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.25; color: var(--pw-text); }
      .pw-sub { font-size: 12.5px; color: var(--pw-text-muted); margin-top: 2px; }
      .pw-op-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
      .pw-op-row.pw-hidden { display: none; }
      .pw-op-avatar {
        width: 40px; height: 40px; border-radius: var(--pw-radius-full); flex-shrink: 0;
        background: color-mix(in srgb, var(--pw-primary) 18%, var(--pw-bg-panel));
        color: var(--pw-primary); display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 600; overflow: hidden;
      }
      .pw-op-avatar img { width: 100%; height: 100%; object-fit: cover; }
      .pw-op-meta { min-width: 0; }
      .pw-op-name { font-size: 14px; font-weight: 600; color: var(--pw-text); }
      .pw-op-line2 { font-size: 12px; color: var(--pw-text-muted); min-height: 1.1em; margin-top: 2px; }
      .pw-op-line2.pw-typing { color: #9a5fa8; }

      .pw-head-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
      .pw-icon-btn {
        width: 34px; height: 34px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-full);
        background: var(--pw-bg-panel); color: var(--pw-text-muted);
        display: flex; align-items: center; justify-content: center;
        transition: background 0.15s, color 0.15s;
      }
      .pw-icon-btn:hover { background: var(--pw-bg-thread); color: var(--pw-text); }

      .pw-theme-toggle { font-size: 10px; font-weight: 600; padding: 0 8px; border-radius: 999px; }
      .pw-sla {
        padding: 10px 16px; flex-shrink: 0; border-bottom: 1px solid var(--pw-border);
        background: color-mix(in srgb, var(--pw-primary) 7%, var(--pw-bg-panel));
      }
      .pw-sla__main { font-size: 12.5px; font-weight: 600; color: var(--pw-text); line-height: 1.45; margin: 0; }
      .pw-sla__note { font-size: 11.5px; color: var(--pw-text-muted); line-height: 1.45; margin: 8px 0 0; }
      .pw-msgs { flex: 1; overflow-y: auto; padding: 16px; background: var(--pw-bg-thread);
        display: flex; flex-direction: column; gap: 8px; scroll-behavior: smooth; }
      .pw-msgs::-webkit-scrollbar { width: 6px; }
      .pw-msgs::-webkit-scrollbar-thumb { background: var(--pw-border); border-radius: 10px; }
      .pw-msg-row { display: flex; flex-direction: column; max-width: 88%; }
      .pw-msg-row--me { align-self: flex-end; align-items: flex-end; }
      .pw-msg-row--mod { align-self: flex-start; align-items: flex-start; }
      .pw-msg-label { font-size: 11px; font-weight: 600; color: var(--pw-text-muted); margin: 0 2px 4px; }
      .pw-msg { padding: 10px 14px; font-size: 14px; line-height: 1.5; word-break: break-word;
        position: relative; border-radius: var(--pw-radius-lg); animation: pw-in 0.2s ease; }
      @keyframes pw-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
      .pw-msg--me { background: var(--pw-bg-bubble-out); color: var(--pw-text-on-brand); border-bottom-right-radius: 6px; }
      .pw-msg--mod { background: var(--pw-bg-bubble-in); color: var(--pw-text); border: 1px solid var(--pw-border);
        border-bottom-left-radius: 6px; }
      .pw-msg-row--sys { align-self: center; max-width: 95%; }
      .pw-msg--sys { background: color-mix(in srgb, var(--pw-text-muted) 10%, var(--pw-bg-bubble-in)); color: var(--pw-text-muted);
        border: 1px dashed var(--pw-border); font-size: 12.5px; border-radius: var(--pw-radius-md); }
      .pw-time { display: block; margin-top: 4px; font-size: 11px; text-align: right; color: var(--pw-text-muted); user-select: none; }
      .pw-msg--me .pw-time { color: color-mix(in srgb, var(--pw-text-on-brand) 75%, transparent); }
      .pw-read-icon { display: inline-block; width: 14px; height: 14px; margin-left: 4px; vertical-align: middle; opacity: 0.9; }
      .pw-read-icon svg { width: 100%; height: 100%; }
      .pw-onboarding { align-self: stretch; max-width: 100%; padding: 16px; margin-top: 4px;
        background: color-mix(in srgb, var(--pw-primary) 8%, var(--pw-bg-panel));
        border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); font-size: 14px; line-height: 1.5; }
      .pw-onboarding p { margin: 0 0 12px 0; }
      .pw-onboarding label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; }
      .pw-onboarding input { width: 100%; padding: 8px 12px; margin-bottom: 12px; border: 1px solid var(--pw-border);
        border-radius: 8px; font-size: 14px; background: var(--pw-bg-panel); color: var(--pw-text); }
      .pw-onboarding button { padding: 10px 20px; background: var(--pw-primary); color: var(--pw-on-primary);
        border: none; border-radius: var(--pw-radius-md); font-weight: 600; font-size: 14px; cursor: pointer; }
      .pw-form-container { background: var(--pw-bg-panel); padding: 12px 14px; border-top: 1px solid var(--pw-border);
        box-shadow: 0 -2px 10px rgba(0,0,0,0.04); flex-shrink: 0; }
      .pw-status { font-size: 12px; color: var(--pw-text-muted); margin-bottom: 10px; text-align: center; font-weight: 500; }
      .pw-typing { font-size: 12px; color: #9a5fa8; margin-bottom: 6px; min-height: 18px; }
      .pw-form { display: flex; gap: 8px; align-items: flex-end; background: var(--pw-form-bg);
        border: 1px solid var(--pw-border); padding: 6px; border-radius: var(--pw-radius-md); }
      .pw-form:focus-within { border-color: color-mix(in srgb, var(--pw-primary) 45%, var(--pw-border));
        box-shadow: 0 0 0 3px var(--pw-focus-ring); }
      .pw-input { flex: 1; resize: none; min-height: 24px; max-height: 120px; padding: 8px 10px; border: none; background: transparent;
        font-size: 14px; color: var(--pw-text); line-height: 1.4; }
      .pw-input::placeholder { color: var(--pw-input-placeholder); }
      .pw-send { width: 36px; height: 36px; border: none; border-radius: var(--pw-radius-full);
        background: var(--pw-primary); color: var(--pw-on-primary); display: flex; align-items: center; justify-content: center;
        transition: opacity 0.2s, transform 0.15s; flex-shrink: 0; }
      .pw-send:hover { transform: scale(1.04); }
      .pw-send[disabled] { opacity: 0.45; cursor: not-allowed; transform: none; }

      @media (max-width: 640px) {
        :host { bottom: 0 !important; left: 0 !important; right: 0 !important; top: 0 !important; pointer-events: none; width: 100%; height: 100%; }
        #pulse-widget-root { width: 100%; height: 100%; pointer-events: none; }
        .pw-fab-wrap { bottom: 20px; ${position}: 20px; pointer-events: auto; }
        .pw-panel { width: 100%; height: 100%; max-height: 100vh; border-radius: 0; border: none; bottom: 0; ${position}: 0; pointer-events: auto; }
      }
    `;

    shadowRoot.appendChild(style);

    function escHtml(t) {
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    const sla1 = escHtml(config.responseSlaText);
    const sla2 = escHtml(config.closeTabNotificationText);

    root.innerHTML = `
      <div class="pw-fab-wrap">
        <button class="pw-fab" type="button" aria-label="Открыть чат">
          <span class="pw-fab-badge" aria-hidden="true">0</span>
          ${config.iconSvg}
        </button>
      </div>
      <section class="pw-panel" role="dialog" aria-label="Чат Pulse">
        <header class="pw-head">
          <div class="pw-head-brand">
            <div class="pw-title">${String(config.title).replace(/</g, '&lt;')}</div>
            <div class="pw-sub">${String(config.subtitle).replace(/</g, '&lt;')}</div>
            <div class="pw-op-row pw-hidden" id="pw-op-row">
              <div class="pw-op-avatar" id="pw-op-avatar" aria-hidden="true">${iconUser}</div>
              <div class="pw-op-meta">
                <div class="pw-op-name" id="pw-op-name"></div>
                <div class="pw-op-line2" id="pw-op-line2"></div>
              </div>
            </div>
          </div>
          <div class="pw-head-actions">
            <button class="pw-icon-btn pw-theme-toggle" type="button" data-pw-theme-cycle title="Тема" aria-label="Сменить тему">А</button>
            <button class="pw-icon-btn pw-sound" type="button" aria-label="Звук" title="Звук уведомлений"></button>
            <button class="pw-icon-btn pw-close" type="button" aria-label="Закрыть">${closeIcon}</button>
          </div>
        </header>
        <div class="pw-sla" role="region" aria-label="Сроки ответа и уведомления">
          <p class="pw-sla__main">Время ожидания: ${sla1}</p>
          <p class="pw-sla__note">${sla2}</p>
        </div>
        <div class="pw-msgs"></div>
        <div class="pw-form-container">
            <div class="pw-status">Готов к подключению</div>
            <form class="pw-form">
            <textarea class="pw-input" placeholder="Напишите сообщение…" rows="1"></textarea>
            <button class="pw-send" type="submit" disabled aria-label="Отправить">${sendIcon}</button>
            </form>
        </div>
      </section>
    `;

    shadowRoot.appendChild(root);

    const fabWrap = root.querySelector('.pw-fab-wrap');
    const fab = root.querySelector('.pw-fab');
    const fabBadge = root.querySelector('.pw-fab-badge');
    const panel = root.querySelector('.pw-panel');
    const closeBtn = root.querySelector('.pw-close');
    const soundBtn = root.querySelector('.pw-sound');
    const themeBtn = root.querySelector('[data-pw-theme-cycle]');
    const msgs = root.querySelector('.pw-msgs');
    const status = root.querySelector('.pw-status');
    const opRow = root.querySelector('#pw-op-row');
    const opAvatar = root.querySelector('#pw-op-avatar');
    const opName = root.querySelector('#pw-op-name');
    const opLine2 = root.querySelector('#pw-op-line2');
    const form = root.querySelector('.pw-form');
    const input = root.querySelector('.pw-input');
    const sendBtn = root.querySelector('.pw-send');

    let chatToken = localStorage.getItem(chatTokenKey) || null;
    let chatId = localStorage.getItem(chatIdKey) || null;
    let lastMessageId = 0;
    let lastSentMessageId = 0;
    let pollTimer = null;
    let echoChannel = null;
    let unreadCount = 0;
    let operatorTypingTimeout = null;
    let debounceTypingTimer = null;
    const originalDocumentTitle = document.title;
    let guestOnboardingShown = false;
    let guestOnboardingCompleted = false;
    const onboardingText = 'Спасибо за ваше сообщение! Чтобы специалист знал, как к вам обращаться, и мог ответить вам, даже если вы случайно закроете эту страницу, пожалуйста, укажите ваше имя. Также вы можете оставить свою электронную почту (это по желанию).';
    let lastModeratorName = null;
    let lastModeratorAvatar = null;

    function setOpen(open) {
        if (open) {
            panel.classList.add('pw-open');
            fabWrap.style.display = 'none';
            void input.focus();
            clearNotification();
            if (chatToken && lastMessageId) {
                void markMessagesAsRead(lastMessageId);
            }
        } else {
            panel.classList.remove('pw-open');
            setTimeout(function () { fabWrap.style.display = 'block'; }, 250);
        }
    }

    var widgetNotifyAudio = null;
    function playNotificationSound() {
        const s = getWidgetSoundSettings();
        if (!s.enabled) return;
        try {
            const url = apiBase + '/sounds/notifications/notification_simple-01.wav';
            if (!widgetNotifyAudio) {
                widgetNotifyAudio = new Audio(url);
            }
            widgetNotifyAudio.volume = s.volume;
            widgetNotifyAudio.currentTime = 0;
            widgetNotifyAudio.play().catch(function () { /* empty */ });
        } catch (e) { /* empty */ }
    }

    function syncSoundButton() {
        if (!soundBtn) return;
        const s = getWidgetSoundSettings();
        soundBtn.innerHTML = s.enabled ? iconBellOn : iconBellOff;
        soundBtn.title = s.enabled ? 'Звук включён' : 'Звук выключен';
    }
    syncSoundButton();
    if (soundBtn) {
        soundBtn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            const s = getWidgetSoundSettings();
            s.enabled = !s.enabled;
            saveWidgetSoundSettings(s);
            syncSoundButton();
        });
    }

    function cycleTheme() {
        const order = ['system', 'light', 'dark'];
        const i = order.indexOf(themeMode);
        themeMode = order[(i + 1) % order.length];
        try { localStorage.setItem(themeStorageKey, themeMode); } catch (e) { /* empty */ }
        applyPwTheme();
        if (themeBtn) {
            const labels = { system: 'Авто', light: 'Свет', dark: 'Тёмн' };
            themeBtn.textContent = labels[themeMode] || 'Авто';
        }
    }
    if (themeBtn) {
        const labels = { system: 'Авто', light: 'Свет', dark: 'Тёмн' };
        themeBtn.textContent = labels[themeMode] || 'Авто';
        themeBtn.addEventListener('click', function (e) { e.stopPropagation(); cycleTheme(); });
    }

    function postUnreadToParent() {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'PULSE_UNREAD', count: unreadCount }, '*');
            }
        } catch (e) { /* empty */ }
    }

    function updateTitleAndBadge() {
        document.title = unreadCount > 0 ? '(' + unreadCount + ') ' + originalDocumentTitle : originalDocumentTitle;
        if (fabBadge) {
            fabBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            fabBadge.classList.toggle('pw-show', unreadCount > 0);
        }
        postUnreadToParent();
    }

    function clearNotification() {
        unreadCount = 0;
        const t = document.title;
        if (/^\(\d+\)\s/.test(t)) {
            document.title = t.replace(/^\(\d+\)\s*/, '') || originalDocumentTitle;
        }
        updateTitleAndBadge();
    }

    function incrementUnread() {
        unreadCount += 1;
        updateTitleAndBadge();
    }

    function setStatus(text) { if (status) status.textContent = text; }

    function setOperatorPresence(isOnline) {
        if (!status) return;
        if (isOnline === true) {
            setStatus('Оператор на связи');
        } else {
            setStatus('Сейчас операторов нет в сети — ответим, как только появимся');
        }
        if (opLine2 && !operatorTypingTimeout) {
            if (isOnline === true) opLine2.textContent = 'На связи';
            else if (isOnline === false) opLine2.textContent = 'Офлайн';
        }
    }

    function updateOperatorHeader(name, avatarUrl) {
        if (!opRow || !opName || !opAvatar) return;
        if (!name) return;
        lastModeratorName = name;
        if (typeof avatarUrl === 'string' && avatarUrl) lastModeratorAvatar = avatarUrl;
        opRow.classList.remove('pw-hidden');
        opName.textContent = name;
        if (lastModeratorAvatar) {
            opAvatar.innerHTML = '<img src="" alt="">';
            const img = opAvatar.querySelector('img');
            if (img) { img.src = lastModeratorAvatar; img.alt = name; }
        } else {
            opAvatar.textContent = initialsFromName(name);
        }
        if (opLine2 && !operatorTypingTimeout) {
            opLine2.classList.remove('pw-typing');
        }
    }

    function showOperatorTyping(senderName) {
        if (opLine2 && opName && opRow) {
            if (!lastModeratorName && senderName) {
                updateOperatorHeader(senderName, null);
            }
            if (opLine2) {
                opLine2.classList.add('pw-typing');
                opLine2.textContent = (senderName || lastModeratorName || 'Оператор') + ' печатает…';
            }
            if (operatorTypingTimeout) clearTimeout(operatorTypingTimeout);
            operatorTypingTimeout = setTimeout(function () {
                operatorTypingTimeout = null;
                if (opLine2) { opLine2.classList.remove('pw-typing'); opLine2.textContent = ''; }
            }, 4000);
        }
    }

    function sendTyping() {
        if (!chatToken) return;
        request('/typing', { method: 'POST', body: JSON.stringify({ chat_token: chatToken }) }).catch(function () { /* empty */ });
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function initialsFromName(name) {
        if (!name) return 'М';
        const p = name.trim().split(/\s+/);
        if (p.length >= 2) return (p[0].charAt(0) + p[1].charAt(0)).toUpperCase();
        return name.trim().charAt(0).toUpperCase() || 'М';
    }

    function renderMessage(message) {
        const id = message.id;
        if (id <= lastMessageId) return;
        if (msgs.querySelector('[data-message-id="' + id + '"]')) return;
        lastMessageId = Math.max(lastMessageId, id);

        const isSystem = message.sender_type === 'system';
        const isMe = message.sender_type === 'client';
        const isRead = !!message.is_read;
        const row = document.createElement('div');
        row.setAttribute('data-message-id', String(message.id));
        const sName = message.sender_name;
        const sUrl = message.sender_avatar_url;
        if (!isMe && !isSystem && message.sender_type === 'moderator' && sName) {
            updateOperatorHeader(sName, sUrl || null);
        }

        if (isSystem) {
            row.className = 'pw-msg-row pw-msg-row--sys';
        } else {
            row.className = isMe ? 'pw-msg-row pw-msg-row--me' : 'pw-msg-row pw-msg-row--mod';
        }
        const guestName = (getGuestData().name || '').trim();
        const labelHtml = !isMe && !isSystem && sName
            ? '<div class="pw-msg-label">' + sName.replace(/</g, '&lt;') + '</div>'
            : (isMe && guestName ? '<div class="pw-msg-label">' + guestName.replace(/</g, '&lt;') + '</div>' : '');

        const wrap = document.createElement('div');
        const shape = isSystem ? 'sys' : (isMe ? 'me' : 'mod');
        wrap.className = 'pw-msg pw-msg--' + shape + (isRead && !isSystem ? ' pw-msg--read' : '');
        const body = (message.text || '').replace(/[<>&]/g, function (m) { return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[m]); });
        const timeHtml = isSystem
            ? '<span class="pw-time">' + formatTime(message.created_at) + '</span>'
            : '<span class="pw-time">' + formatTime(message.created_at) + (isRead ? readIconSvg : '') + '</span>';
        wrap.innerHTML = body + timeHtml;

        if (labelHtml) {
            row.innerHTML = labelHtml;
        }
        row.appendChild(wrap);
        msgs.appendChild(row);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function setMessagesRead(messageIds) {
        if (!messageIds || !messageIds.length) return;
        messageIds.forEach(function (id) {
            const row = msgs.querySelector('[data-message-id="' + id + '"]');
            if (!row) return;
            const bubble = row.querySelector('.pw-msg--mod, .pw-msg--me') || row;
            bubble.classList.add('pw-msg--read');
            const timeEl = row.querySelector('.pw-time');
            if (timeEl && !timeEl.querySelector('.pw-read-icon')) {
                timeEl.insertAdjacentHTML('beforeend', readIconSvg);
            }
        });
    }

    function markMessagesAsRead(upToMessageId) {
        if (!chatToken) return;
        return request('/messages/read', {
            method: 'POST',
            body: JSON.stringify({ chat_token: chatToken, up_to_message_id: upToMessageId || lastMessageId })
        }).catch(function () { /* empty */ });
    }

    function showOnboardingBlock() {
        if (guestOnboardingCompleted || guestOnboardingShown) return;
        const existing = msgs.querySelector('.pw-onboarding');
        if (existing) return;
        guestOnboardingShown = true;
        const wrap = document.createElement('div');
        wrap.className = 'pw-onboarding';
        wrap.setAttribute('data-pulse-onboarding', '1');
        wrap.innerHTML = '<p>' + onboardingText.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' +
            '<label for="pw-guest-name">Имя *</label><input type="text" id="pw-guest-name" name="name" placeholder="Ваше имя" required maxlength="255" autocomplete="name">' +
            '<label for="pw-guest-email">Email (необязательно)</label><input type="email" id="pw-guest-email" name="email" placeholder="email@example.com" maxlength="255" autocomplete="email">' +
            '<button type="button" id="pw-onboarding-save">Продолжить</button>';
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;

        const nameInput = wrap.querySelector('#pw-guest-name');
        const emailInput = wrap.querySelector('#pw-guest-email');
        const saveBtn = wrap.querySelector('#pw-onboarding-save');

        function submitOnboarding() {
            const nameVal = (nameInput && nameInput.value) ? nameInput.value.trim() : '';
            if (!nameVal) return;
            saveBtn.disabled = true;
            const emailVal = (emailInput && emailInput.value) ? emailInput.value.trim() : null;
            guestDataFromParent = { name: nameVal, email: emailVal || null };
            const payload = {
                source_identifier: sourceIdentifier,
                visitor_id: visitorId,
                name: nameVal,
                email: emailVal || undefined,
                meta: { url: window.location.href, title: document.title, referrer: document.referrer || null, user_agent: navigator.userAgent }
            };
            request('/session', { method: 'POST', body: JSON.stringify(payload) })
                .then(function () {
                    guestOnboardingCompleted = true;
                    setGuestDataStored(nameVal, emailVal);
                    if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                })
                .catch(function () { saveBtn.disabled = false; });
        }

        saveBtn.addEventListener('click', submitOnboarding);
        if (nameInput) nameInput.focus();
    }

    function request(path, options) {
        options = options || {};
        return fetch(endpoint + path, {
            ...options,
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    async function ensureSession() {
        const guest = getGuestData();
        const payload = {
            source_identifier: sourceIdentifier,
            visitor_id: visitorId,
            name: guest.name || undefined,
            email: guest.email || undefined,
            meta: { url: window.location.href, title: document.title, referrer: document.referrer || null, user_agent: navigator.userAgent }
        };
        var sessionResponse = null;
        if (!chatToken) {
            const result = await request('/session', { method: 'POST', body: JSON.stringify(payload) });
            sessionResponse = result;
            chatToken = result.chat_token;
            localStorage.setItem(chatTokenKey, chatToken);
            if (result.chat && result.chat.id != null) {
                chatId = String(result.chat.id);
                localStorage.setItem(chatIdKey, chatId);
            }
            if (result.chat && result.chat.guest_name) {
                guestDataFromParent = { name: result.chat.guest_name, email: result.chat.guest_email || null };
                guestOnboardingCompleted = true;
                setGuestDataStored(guestDataFromParent.name, guestDataFromParent.email);
            }
        }
        const existingGuest = getGuestData();
        if (existingGuest.name) guestOnboardingCompleted = true;
        sendBtn.disabled = !chatToken;
        if (chatToken) {
            if (sessionResponse && typeof sessionResponse.is_online === 'boolean') {
                setOperatorPresence(sessionResponse.is_online);
            } else {
                setStatus('Оператор на связи');
            }
        } else {
            setStatus('Ошибка подключения');
        }
    }

    async function loadMessages() {
        if (!chatToken) return;
        const result = await request('/messages?chat_token=' + encodeURIComponent(chatToken) + '&limit=100');
        msgs.innerHTML = '';
        lastMessageId = 0;
        for (const message of (result.messages || [])) {
            renderMessage({
                id: message.id,
                text: message.text,
                sender_type: message.sender_type,
                created_at: message.created_at,
                is_read: message.is_read,
                sender_name: message.sender_name,
                sender_avatar_url: message.sender_avatar_url
            });
        }
        if (typeof result.is_online === 'boolean') {
            setOperatorPresence(result.is_online);
        } else if (!result.messages || result.messages.length === 0) {
            setStatus('Напишите нам, мы скоро ответим');
        }
    }

    async function pollMessages() {
        if (!chatToken) return;
        const result = await request('/messages?chat_token=' + encodeURIComponent(chatToken) + '&limit=30');
        for (const message of (result.messages || [])) {
            renderMessage({
                id: message.id,
                text: message.text,
                sender_type: message.sender_type,
                created_at: message.created_at,
                is_read: message.is_read,
                sender_name: message.sender_name,
                sender_avatar_url: message.sender_avatar_url
            });
        }
        if (typeof result.is_online === 'boolean') {
            setOperatorPresence(result.is_online);
        }
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            const el = document.createElement('script');
            el.src = src;
            el.onload = resolve;
            el.onerror = reject;
            document.head.appendChild(el);
        });
    }

    function connectReverb() {
        if (!chatId || echoChannel) return;
        request('/config').then(function (data) {
            if (!data.reverb || !data.reverb.key) {
                if (chatToken && !pollTimer) {
                    pollTimer = setInterval(function () { pollMessages().catch(function () { /* empty */ }); }, 3000);
                }
                return;
            }
            const r = data.reverb;
            const useTls = r.scheme === 'https';
            const channelName = 'widget-chat.' + chatId;
            function subscribe() {
                const Echo = window.Echo;
                if (!Echo) return;
                const echo = new Echo({
                    broadcaster: 'pusher',
                    key: r.key,
                    cluster: r.cluster || 'mt1',
                    wsHost: r.host,
                    wssHost: r.host,
                    wsPort: r.port,
                    wssPort: r.port,
                    wsPath: ('wsPath' in r ? r.wsPath : '/app'),
                    forceTLS: useTls,
                    disableStats: true,
                    enabledTransports: useTls ? ['ws', 'wss'] : ['ws'],
                });
                echoChannel = echo.channel(channelName);
                function onNewMessage(e) {
                    if (e.chatId !== parseInt(chatId, 10)) return;
                    const senderType = (e.sender_type === 'client' || e.sender_type === 'moderator' || e.sender_type === 'system') ? e.sender_type : 'moderator';
                    if (senderType === 'client') {
                        if (e.messageId === lastSentMessageId) return;
                        if (e.messageId <= lastMessageId) return;
                    }
                    if (e.sender_type === 'moderator' && e.sender_name) {
                        updateOperatorHeader(e.sender_name, e.sender_avatar_url || null);
                    }
                    renderMessage({
                        id: e.messageId,
                        text: e.text || '',
                        sender_type: senderType,
                        is_read: false,
                        created_at: new Date().toISOString(),
                        sender_name: e.sender_name || null,
                        sender_avatar_url: e.sender_avatar_url || null
                    });
                    const chatClosed = !panel.classList.contains('pw-open');
                    const tabHidden = document.hidden;
                    if (e.sender_type === 'moderator' && (chatClosed || tabHidden)) {
                        incrementUnread();
                        playNotificationSound();
                    }
                }
                echoChannel.listen('.App.Events.NewChatMessage', onNewMessage);
                echoChannel.listen('.App\\Events\\NewChatMessage', onNewMessage);
                echoChannel.listen('.App.Events.MessageRead', function (e) {
                    if (e.chatId === parseInt(chatId, 10) && e.messageIds && e.messageIds.length) {
                        setMessagesRead(e.messageIds);
                    }
                });
                echoChannel.listen('.App\\Events\\MessageRead', function (e) {
                    if (e.chatId === parseInt(chatId, 10) && e.messageIds && e.messageIds.length) {
                        setMessagesRead(e.messageIds);
                    }
                });
                echoChannel.listen('typing', function (e) {
                    if (e.chat_id !== parseInt(chatId, 10)) return;
                    if (e.sender_type === 'moderator') {
                        showOperatorTyping(e.sender_name || null);
                    }
                });
            }
            if (window.Pusher && window.Echo) { subscribe(); return; }
            loadScript('https://js.pusher.com/8.3.0/pusher.min.js')
                .then(function () { return loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.min.js'); })
                .then(subscribe)
                .catch(function (err) { console.warn('[PulseWidget] Reverb failed', err); });
        }).catch(function () { /* empty */ });
    }

    if (fab) {
        fab.addEventListener('click', async function () {
            setOpen(true);
            try {
                setStatus('Подключение…');
                await ensureSession();
                await loadMessages();
                if (lastMessageId) {
                    await markMessagesAsRead(lastMessageId);
                }
                connectReverb();
            } catch (error) {
                setStatus('Ошибка подключения');
                console.error('[PulseWidget] init error', error);
            }
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });

    if (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const text = (input && input.value) ? input.value.trim() : '';
            if (!text) return;
            if (input) { input.value = ''; input.style.height = 'auto'; }
            try {
                const result = await request('/messages', { method: 'POST', body: JSON.stringify({ chat_token: chatToken, text: text, payload: {} }) });
                if (result.message) {
                    lastSentMessageId = result.message.id;
                    renderMessage({ id: result.message.id, text: result.message.text, sender_type: 'client', created_at: result.message.created_at, sender_name: null, sender_avatar_url: null });
                    if (!guestOnboardingCompleted && !getGuestData().name) showOnboardingBlock();
                }
                if (typeof result.is_online === 'boolean') {
                    setOperatorPresence(result.is_online);
                }
            } catch (error) { setStatus('Не удалось отправить. Повторите.'); }
        });
    }

    if (input) {
        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            if (debounceTypingTimer) clearTimeout(debounceTypingTimer);
            debounceTypingTimer = setTimeout(function () { sendTyping(); debounceTypingTimer = null; }, 400);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); if (form) form.requestSubmit(); }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        if (panel && panel.classList.contains('pw-open')) {
            clearNotification();
            if (lastMessageId) void markMessagesAsRead(lastMessageId);
        }
    });
    window.addEventListener('focus', function () {
        if (document.hidden) return;
        if (panel && panel.classList.contains('pw-open')) {
            clearNotification();
        }
    });

    if (chatId) {
        ensureSession().catch(function () { /* empty */ }).finally(function () { connectReverb(); });
    }

    updateTitleAndBadge();
})();

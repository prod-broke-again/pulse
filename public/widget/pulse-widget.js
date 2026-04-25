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
    /** Стабильный бренд для поля ввода, кнопки «Отправить» и кольца фокуса (независимо от primaryColor в конфиге). */
    const PULSE_COMPOSER_BRAND = '#55175e';
    const readIconSvg = '<span class="pw-read-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg></span>';
    const iconUser = '<svg class="pw-icon pw-icon--sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    /** Встроенные копии public/widget/icons/*.svg — без cross-origin fetch (CORS на статике не нужен для UI). */
    const PW_ICONS = {
        'send.svg': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>',
        'close-outline.svg': '<svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M368 368L144 144M368 144L144 368"/></svg>',
        'volume-high-outline.svg': '<svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path d="M126 192H56a8 8 0 00-8 8v112a8 8 0 008 8h69.65a15.93 15.93 0 0110.14 3.54l91.47 74.89A8 8 0 00240 392V120a8 8 0 00-12.74-6.43l-91.47 74.89A15 15 0 01126 192zM320 320c9.74-19.38 16-40.84 16-64 0-23.48-6-44.42-16-64M368 368c19.48-33.92 32-64.06 32-112s-12-77.74-32-112M416 416c30-46 48-91.43 48-160s-18-113-48-160" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"/></svg>',
        'volume-mute-outline.svg': '<svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="32" d="M416 432L64 80"/><path d="M224 136.92v33.8a4 4 0 001.17 2.82l24 24a4 4 0 006.83-2.82v-74.15a24.53 24.53 0 00-12.67-21.72 23.91 23.91 0 00-25.55 1.83 8.27 8.27 0 00-.66.51l-31.94 26.15a4 4 0 00-.29 5.92l17.05 17.06a4 4 0 005.37.26zM224 375.08l-78.07-63.92a32 32 0 00-20.28-7.16H64v-96h50.72a4 4 0 002.82-6.83l-24-24a4 4 0 00-2.82-1.17H56a24 24 0 00-24 24v112a24 24 0 0024 24h69.76l91.36 74.8a8.27 8.27 0 00.66.51 23.93 23.93 0 0025.85 1.69A24.49 24.49 0 00256 391.45v-50.17a4 4 0 00-1.17-2.82l-24-24a4 4 0 00-6.83 2.82zM125.82 336zM352 256c0-24.56-5.81-47.88-17.75-71.27a16 16 0 00-28.5 14.54C315.34 218.06 320 236.62 320 256q0 4-.31 8.13a8 8 0 002.32 6.25l19.66 19.67a4 4 0 006.75-2A146.89 146.89 0 00352 256zM416 256c0-51.19-13.08-83.89-34.18-120.06a16 16 0 00-27.64 16.12C373.07 184.44 384 211.83 384 256c0 23.83-3.29 42.88-9.37 60.65a8 8 0 001.9 8.26l16.77 16.76a4 4 0 006.52-1.27C410.09 315.88 416 289.91 416 256z"/><path d="M480 256c0-74.26-20.19-121.11-50.51-168.61a16 16 0 10-27 17.22C429.82 147.38 448 189.5 448 256c0 47.45-8.9 82.12-23.59 113a4 4 0 00.77 4.55L443 391.39a4 4 0 006.4-1C470.88 348.22 480 307 480 256z"/></svg>',
        'sunny-outline.svg': '<svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="32" d="M256 48v48M256 416v48M403.08 108.92l-33.94 33.94M142.86 369.14l-33.94 33.94M464 256h-48M96 256H48M403.08 403.08l-33.94-33.94M142.86 142.86l-33.94-33.94"/><circle cx="256" cy="256" r="80" fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="32"/></svg>',
        'moon-outline.svg': '<svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512"><path d="M160 136c0-30.62 4.51-61.61 16-88C99.57 81.27 48 159.32 48 248c0 119.29 96.71 216 216 216 88.68 0 166.73-51.57 200-128-26.39 11.49-57.38 16-88 16-119.29 0-216-96.71-216-216z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"/></svg>',
        'x.svg': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
    };

    /** Базовый URL папки со скриптом (для PNG MAX рядом с `pulse-widget.js`). */
    const widgetScriptBase = (function () {
        try {
            const s = script.getAttribute('src') || script.src || '';
            if (!s || !/^https?:\/\//i.test(s)) return '';
            return String(s).replace(/\/[^/]+$/, '');
        } catch (e) {
            return '';
        }
    })();
    const PW_SOCIAL_MAX_IMG = widgetScriptBase ? `${widgetScriptBase}/social/max-logo-2025.png` : '';
    /** Telegram: исходник из брендового набора (круг + градиент). */
    const PW_SOCIAL_ICON_TELEGRAM =
        '<svg class="pw-stub__link__svg pw-stub__link__svg--round" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs><linearGradient id="pwGradTg" x1="50%" y1="0%" x2="50%" y2="99.2583404%"><stop stop-color="#2AABEE" offset="0%"/><stop stop-color="#229ED9" offset="100%"/></linearGradient></defs><circle fill="url(#pwGradTg)" cx="500" cy="500" r="500"/><path fill="#FFFFFF" d="M226.328419,494.722069 C372.088573,431.216685 469.284839,389.350049 517.917216,369.122161 C656.772535,311.36743 685.625481,301.334815 704.431427,301.003532 C708.567621,300.93067 717.815839,301.955743 723.806446,306.816707 C728.864797,310.92121 730.256552,316.46581 730.922551,320.357329 C731.588551,324.248848 732.417879,333.113828 731.758626,340.040666 C724.234007,419.102486 691.675104,610.964674 675.110982,699.515267 C668.10208,736.984342 654.301336,749.547532 640.940618,750.777006 C611.904684,753.448938 589.856115,731.588035 561.733393,713.153237 C517.726886,684.306416 492.866009,666.349181 450.150074,638.200013 C400.78442,605.66878 432.786119,587.789048 460.919462,558.568563 C468.282091,550.921423 596.21508,434.556479 598.691227,424.000355 C599.00091,422.680135 599.288312,417.758981 596.36474,415.160431 C593.441168,412.561881 589.126229,413.450484 586.012448,414.157198 C581.598758,415.158943 511.297793,461.625274 375.109553,553.556189 C355.154858,567.258623 337.080515,573.934908 320.886524,573.585046 C303.033948,573.199351 268.692754,563.490928 243.163606,555.192408 C211.851067,545.013936 186.964484,539.632504 189.131547,522.346309 C190.260287,513.342589 202.659244,504.134509 226.328419,494.722069 Z"/></svg>';
    /** VK: официальный Blue SVG 64×64 (Logo_VK / SVG / Blue). */
    const PW_SOCIAL_ICON_VK =
        '<svg class="pw-stub__link__svg pw-stub__link__svg--round" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g clip-path="url(#pwVkClip)"><path d="M30.6867 64H33.3517C47.8182 64 55.0528 64 59.5456 59.5072C64.0384 55.0144 64 47.7824 64 33.3517V30.6483C64 16.2202 64 8.98561 59.5456 4.49281C55.0912 1.26953e-05 47.8182 0 33.3517 0H30.6867C16.2176 0 8.9856 1.26953e-05 4.4928 4.49281C-2.92969e-06 8.98561 0 16.215 0 30.6483V33.3517C0 47.7824 -2.92969e-06 55.0144 4.4928 59.5072C8.9856 64 16.2176 64 30.6867 64Z" fill="#0077FF"/><path d="M34.3434 46.1437C19.9127 46.1437 11.1549 36.1316 10.8145 19.4941H18.1233C18.3511 31.7156 23.9114 36.9021 28.1738 37.9594V19.4941H35.1805V30.0388C39.2919 29.5831 43.5927 24.7857 45.0417 19.4941H51.9306C50.8273 26.0042 46.145 30.8017 42.8324 32.7805C46.145 34.3805 51.4749 38.5687 53.5306 46.1437H45.9556C44.3556 41.08 40.4337 37.1581 35.1805 36.6257V46.1437H34.3434Z" fill="white"/></g><defs><clipPath id="pwVkClip"><rect width="64" height="64" fill="white"/></clipPath></defs></svg>';

    const defaultResponseSlaText = 'Стараемся ответить в течение рабочего дня.';
    const defaultCloseTabNotificationText =
        'Пока эта страница открыта, при ответе вы услышите сигнал, увидите число на вкладке и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если вы закроете страницу и не оставили email, переписка не пропадёт: ответ останется в чате — его можно прочитать, снова открыв виджет на этом сайте. С email в анкете дублируем ответ письмом.';

    let config = {
        title: 'Поддержка',
        subtitle: 'Напишите — мы ответим в рабочее время',
        responseSlaText: defaultResponseSlaText,
        closeTabNotificationText: defaultCloseTabNotificationText,
        primaryColor: '#55175e',
        textColor: '#ffffff',
        iconSvg: defaultIconSvg,
        widgetEnabled: true,
        disabledTitle: 'Сейчас чат на сайте недоступен',
        disabledText: 'Пожалуйста, напишите нам в удобный мессенджер — мы ответим там.',
        contactLinks: { telegram: null, vk: null, max: null }
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
    if (typeof config.widgetEnabled !== 'boolean') {
        config.widgetEnabled = true;
    }
    const clIn = config.contactLinks;
    if (!clIn || typeof clIn !== 'object') {
        config.contactLinks = { telegram: null, vk: null, max: null };
    } else {
        config.contactLinks = {
            telegram: typeof clIn.telegram === 'string' && String(clIn.telegram).trim() ? String(clIn.telegram).trim() : null,
            vk: typeof clIn.vk === 'string' && String(clIn.vk).trim() ? String(clIn.vk).trim() : null,
            max: typeof clIn.max === 'string' && String(clIn.max).trim() ? String(clIn.max).trim() : null
        };
    }
    if (typeof config.disabledTitle !== 'string' || !String(config.disabledTitle).trim()) {
        config.disabledTitle = 'Сейчас чат на сайте недоступен';
    }
    if (typeof config.disabledText !== 'string' || !String(config.disabledText).trim()) {
        config.disabledText = 'Пожалуйста, напишите нам в удобный мессенджер — мы ответим там.';
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
    const slaDismissKey = `pulse_widget_sla_dismiss_${sourceIdentifier}`;

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

    /** Согласован с @media (max-width: 640px) в shadow-стилях виджета. */
    const PW_LAYOUT_MOBILE_MAX = 640;
    function applyPulseHostInsets(hostEl) {
        if (!hostEl || !hostEl.style) return;
        const edge = typeof window !== 'undefined' && window.innerWidth > PW_LAYOUT_MOBILE_MAX ? '16px' : '24px';
        hostEl.style.bottom = edge;
        hostEl.style[position] = edge;
        const other = position === 'right' ? 'left' : 'right';
        hostEl.style.removeProperty(other);
        hostEl.style.removeProperty('top');
    }
    let pulseHostResizeTimer = null;
    function attachPulseHostInsetListener(hostEl) {
        function onResize() {
            if (pulseHostResizeTimer) clearTimeout(pulseHostResizeTimer);
            pulseHostResizeTimer = setTimeout(function () {
                applyPulseHostInsets(hostEl);
            }, 120);
        }
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', onResize);
        }
    }

    if (config.widgetEnabled === false) {
        (function mountWidgetDisabled() {
            function escHtmlStub(t) {
                return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }
            const headTitle = String(config.title).replace(/</g, '&lt;');
            const stubTitle = escHtmlStub(config.disabledTitle);
            const stubText = escHtmlStub(config.disabledText);
            const hostD = document.createElement('div');
            hostD.id = 'pulse-widget-host';
            hostD.style.position = 'fixed';
            hostD.style.zIndex = '2147483646';
            applyPulseHostInsets(hostD);
            attachPulseHostInsetListener(hostD);
            document.body.appendChild(hostD);
            const shadowD = hostD.attachShadow({ mode: 'open' });
            const rootD = document.createElement('div');
            rootD.id = 'pulse-widget-root';
            let themeModeD = readStoredThemeMode();
            function applyPwThemeD() {
                const isDark = resolveDarkByMode(themeModeD);
                rootD.dataset.pwTheme = isDark ? 'dark' : 'light';
                rootD.style.setProperty('--pw-primary', config.primaryColor);
                rootD.style.setProperty('--pw-on-primary', config.textColor);
                rootD.style.setProperty('--pw-composer', PULSE_COMPOSER_BRAND);
                rootD.style.setProperty('--pw-composer-ring', 'color-mix(in srgb, ' + PULSE_COMPOSER_BRAND + ' 24%, transparent)');
            }
            applyPwThemeD();
            rootD.style.fontFamily = 'Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
            if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
                const mm = window.matchMedia('(prefers-color-scheme: dark)');
                const onSystem = function () { if (themeModeD === 'system') applyPwThemeD(); };
                if (typeof mm.addEventListener === 'function') mm.addEventListener('change', onSystem);
                else if (typeof mm.addListener === 'function') mm.addListener(onSystem);
            }
            const styleD = document.createElement('style');
            styleD.textContent = `
      :host { all: initial; }
      * { box-sizing: border-box; margin: 0; padding: 0; }
      button { outline: none; font-family: inherit; cursor: pointer; }
      .pw-icon { width: 20px; height: 20px; display: block; }
      #pulse-widget-root { --pw-radius-lg: 14px; --pw-radius-md: 10px; --pw-radius-full: 9999px; font-size: 15px; accent-color: var(--pw-composer); }
      #pulse-widget-root[data-pw-theme="light"] {
        --pw-bg-panel: #ffffff; --pw-bg-thread: #f8f7fa; --pw-text: #1a1a1a; --pw-text-muted: #6b6578; --pw-border: #e8e4ed; --pw-shadow: 0 4px 16px rgba(85, 23, 94, 0.08);
      }
      #pulse-widget-root[data-pw-theme="dark"] {
        --pw-bg-panel: #201a26; --pw-bg-thread: #1c1722; --pw-text: #e8e4ed; --pw-text-muted: #9b95a3; --pw-border: #2d2535; --pw-shadow: 0 4px 20px rgba(0,0,0,0.4);
      }
      .pw-fab-wrap { position: absolute; bottom: 0; ${position}: 0; z-index: 2; }
      .pw-fab {
        width: 56px; height: 56px; border-radius: var(--pw-radius-full); border: none;
        color: #ffffff; background: var(--pw-composer);
        box-shadow: 0 4px 16px color-mix(in srgb, var(--pw-composer) 28%, transparent);
        display: flex; align-items: center; justify-content: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease; position: relative;
      }
      .pw-fab:hover { transform: scale(1.06); }
      .pw-fab-badge { display: none !important; }
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
        padding: 14px 16px; background: var(--pw-bg-panel);
        border-bottom: 1px solid var(--pw-border);
        display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-shrink: 0;
      }
      .pw-title { font-size: 16px; font-weight: 700; color: var(--pw-text); }
      .pw-head-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
      .pw-icon-btn {
        width: 34px; height: 34px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-full);
        background: var(--pw-bg-panel); color: var(--pw-text-muted);
        display: flex; align-items: center; justify-content: center;
      }
      .pw-icon--xs { width: 10px !important; height: 10px !important; }
      .pw-theme-dual { display: inline-flex; align-items: center; gap: 0; width: 20px; height: 20px; }
      .pw-stub { flex: 1; overflow-y: auto; padding: 20px 16px; background: var(--pw-bg-thread); }
      .pw-stub__title { font-size: 15px; font-weight: 700; color: var(--pw-text); margin: 0 0 10px; line-height: 1.35; }
      .pw-stub__text { font-size: 14px; color: var(--pw-text); line-height: 1.5; margin: 0 0 16px; }
      .pw-stub__links { display: flex; flex-direction: column; gap: 10px; }
      .pw-stub__link {
        display: flex; align-items: center; justify-content: center; gap: 10px;
        padding: 12px 16px; border-radius: var(--pw-radius-md);
        font-weight: 600; font-size: 14px; line-height: 1.25; text-decoration: none;
        border: 1px solid transparent;
        color: #ffffff;
        transition: transform 0.15s ease, filter 0.15s ease, box-shadow 0.15s ease;
      }
      .pw-stub__link:hover { transform: translateY(-1px); filter: brightness(1.04); }
      .pw-stub__link:focus-visible { outline: 2px solid #ffffff; outline-offset: 2px; }
      .pw-stub__link__svg { width: 24px; height: 24px; flex-shrink: 0; display: block; }
      .pw-stub__link__svg--round { border-radius: 9999px; }
      .pw-stub__link__icon { flex-shrink: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; }
      .pw-stub__link__icon img { width: 28px; height: 28px; display: block; border-radius: 8px; object-fit: contain; }
      .pw-stub__link__text { text-align: left; }
      .pw-stub__link--telegram { background: #229ED9; box-shadow: 0 2px 10px rgba(34, 158, 217, 0.35); }
      .pw-stub__link--vk { background: #0077FF; box-shadow: 0 2px 10px rgba(0, 119, 255, 0.35); }
      .pw-stub__link--max {
        background: linear-gradient(135deg, #2ec4ff 0%, #5b2d9e 55%, #4a1f7a 100%);
        box-shadow: 0 2px 12px rgba(91, 45, 158, 0.35);
      }
      .pw-stub__hint { font-size: 12.5px; color: var(--pw-text-muted); line-height: 1.45; }
      @media (max-width: 640px) {
        :host { bottom: 0 !important; left: 0 !important; right: 0 !important; top: 0 !important; width: 100%; height: 100%; }
        .pw-fab-wrap { bottom: 20px; ${position}: 20px; }
        .pw-panel { width: 100%; height: 100%; max-height: 100vh; border-radius: 0; border: none; }
      }
    `;
            shadowD.appendChild(styleD);
            const svgPanelCloseD = PW_ICONS['close-outline.svg'];
            const svgSunD = PW_ICONS['sunny-outline.svg'];
            const svgMoonD = PW_ICONS['moon-outline.svg'];
            function svgWithClassD(svg, extraClass) {
                if (!svg) return '';
                return svg.replace('<svg', '<svg class="pw-icon' + (extraClass ? ' ' + extraClass : '') + '" focusable="false" aria-hidden="true"');
            }
            rootD.innerHTML = `
      <div class="pw-fab-wrap">
        <button class="pw-fab" type="button" aria-label="Открыть сообщение">
          ${config.iconSvg || defaultIconSvg}
        </button>
      </div>
      <section class="pw-panel" role="dialog" aria-label="Сообщение">
        <header class="pw-head">
          <div class="pw-title">${headTitle}</div>
          <div class="pw-head-actions">
            <button class="pw-icon-btn pw-theme-toggle" type="button" data-pw-theme-cycle title="Смена темы" aria-label="Сменить тему"></button>
            <button class="pw-icon-btn pw-close" type="button" aria-label="Закрыть"></button>
          </div>
        </header>
        <div class="pw-stub">
          <p class="pw-stub__title">${stubTitle}</p>
          <p class="pw-stub__text">${stubText}</p>
          <div class="pw-stub__links" id="pw-stub-links-d"></div>
          <p class="pw-stub__hint" id="pw-stub-hint-d" hidden>Ссылок на мессенджеры пока не настроено — загляните позже.</p>
        </div>
      </section>
    `;
            shadowD.appendChild(rootD);
            const linksWrap = rootD.querySelector('#pw-stub-links-d');
            const hintEl = rootD.querySelector('#pw-stub-hint-d');
            const linkPairs = [
                { label: 'Написать в Telegram', url: config.contactLinks.telegram, css: 'telegram', icon: PW_SOCIAL_ICON_TELEGRAM },
                { label: 'Написать в VK', url: config.contactLinks.vk, css: 'vk', icon: PW_SOCIAL_ICON_VK },
                { label: 'Написать в MAX', url: config.contactLinks.max, css: 'max', icon: '' }
            ];
            let hasAny = false;
            linkPairs.forEach(function (p) {
                if (!p.url) return;
                hasAny = true;
                const a = document.createElement('a');
                a.className = 'pw-stub__link pw-stub__link--' + p.css;
                a.href = p.url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                let iconHtml = '';
                if (p.css === 'max' && PW_SOCIAL_MAX_IMG) {
                    iconHtml = '<span class="pw-stub__link__icon" aria-hidden="true"><img src="' + String(PW_SOCIAL_MAX_IMG).replace(/"/g, '') + '" alt="" width="28" height="28" decoding="async" loading="lazy"/></span>';
                } else if (p.icon) {
                    iconHtml = p.icon;
                }
                a.innerHTML = iconHtml + '<span class="pw-stub__link__text">' + escHtmlStub(p.label) + '</span>';
                if (linksWrap) linksWrap.appendChild(a);
            });
            if (!hasAny && hintEl) {
                hintEl.hidden = false;
            }
            const fabWrapD = rootD.querySelector('.pw-fab-wrap');
            const fabD = rootD.querySelector('.pw-fab');
            const panelD = rootD.querySelector('.pw-panel');
            const closeD = rootD.querySelector('.pw-close');
            const themeBtnD = rootD.querySelector('[data-pw-theme-cycle]');
            if (closeD && svgPanelCloseD) { closeD.innerHTML = svgWithClassD(svgPanelCloseD); }
            function renderThemeButtonD() {
                if (!themeBtnD) return;
                const themeTitles = { system: 'Как в системе', light: 'Светлая тема', dark: 'Тёмная тема' };
                const modeLabel = themeTitles[themeModeD] || themeTitles.system;
                themeBtnD.setAttribute('title', modeLabel);
                themeBtnD.setAttribute('aria-label', 'Сменить тему: ' + modeLabel);
                if (themeModeD === 'light' && svgSunD) {
                    themeBtnD.innerHTML = svgWithClassD(svgSunD);
                } else if (themeModeD === 'dark' && svgMoonD) {
                    themeBtnD.innerHTML = svgWithClassD(svgMoonD);
                } else if (svgSunD && svgMoonD) {
                    themeBtnD.innerHTML = '<span class="pw-theme-dual" aria-hidden="true">' + svgWithClassD(svgSunD, 'pw-icon--xs') + svgWithClassD(svgMoonD, 'pw-icon--xs') + '</span>';
                }
            }
            function setOpenD(open) {
                if (open) {
                    if (panelD) panelD.classList.add('pw-open');
                    if (fabWrapD) fabWrapD.style.display = 'none';
                } else {
                    if (panelD) panelD.classList.remove('pw-open');
                    setTimeout(function () { if (fabWrapD) fabWrapD.style.display = 'block'; }, 250);
                }
            }
            function cycleThemeD() {
                const order = ['system', 'light', 'dark'];
                const i = order.indexOf(themeModeD);
                themeModeD = order[(i + 1) % order.length];
                try { localStorage.setItem(themeStorageKey, themeModeD); } catch (e) { /* empty */ }
                applyPwThemeD();
                renderThemeButtonD();
            }
            renderThemeButtonD();
            if (themeBtnD) { themeBtnD.addEventListener('click', function (e) { e.stopPropagation(); cycleThemeD(); }); }
            if (fabD) { fabD.addEventListener('click', function () { setOpenD(true); }); }
            if (closeD) { closeD.addEventListener('click', function () { setOpenD(false); }); }
        })();
        return;
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
    applyPulseHostInsets(host);
    attachPulseHostInsetListener(host);
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
        root.style.setProperty('--pw-composer', PULSE_COMPOSER_BRAND);
        root.style.setProperty('--pw-composer-ring', 'color-mix(in srgb, ' + PULSE_COMPOSER_BRAND + ' 24%, transparent)');
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

      #pulse-widget-root { --pw-radius-lg: 14px; --pw-radius-md: 10px; --pw-radius-full: 9999px; font-size: 15px; accent-color: var(--pw-composer); }
      #pulse-widget-root[data-pw-theme="light"] {
        --pw-bg-panel: #ffffff; --pw-bg-thread: #f8f7fa; --pw-bg-bubble-in: #ffffff; --pw-bg-bubble-out: #55175e;
        --pw-text: #1a1a1a; --pw-text-muted: #6b6578; --pw-text-on-brand: #ffffff;
        --pw-border: #e8e4ed; --pw-shadow: 0 4px 16px rgba(85, 23, 94, 0.08);
        --pw-form-bg: #ffffff; --pw-input-placeholder: #9b95a3;
        --pw-focus-ring: color-mix(in srgb, var(--pw-composer) 20%, transparent);
      }
      #pulse-widget-root[data-pw-theme="dark"] {
        --pw-bg-panel: #201a26; --pw-bg-thread: #1c1722; --pw-bg-bubble-in: #2d2535; --pw-bg-bubble-out: #55175e;
        --pw-text: #e8e4ed; --pw-text-muted: #9b95a3; --pw-text-on-brand: #ffffff;
        --pw-border: #2d2535; --pw-shadow: 0 4px 20px rgba(0,0,0,0.4);
        --pw-form-bg: #241e2a; --pw-input-placeholder: #6b6578;
        --pw-focus-ring: color-mix(in srgb, var(--pw-composer) 28%, transparent);
      }

      .pw-fab-wrap { position: absolute; bottom: 0; ${position}: 0; z-index: 2; }
      .pw-fab {
        width: 56px; height: 56px; border-radius: var(--pw-radius-full); border: none;
        color: #ffffff; background: var(--pw-composer);
        box-shadow: 0 4px 16px color-mix(in srgb, var(--pw-composer) 28%, transparent);
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
      .pw-icon-btn svg, .pw-icon-btn .pw-icon { display: block; width: 20px; height: 20px; flex-shrink: 0; object-fit: contain; }
      .pw-sound { line-height: 0; }
      .pw-icon--xs { width: 10px !important; height: 10px !important; }
      .pw-theme-dual { display: inline-flex; align-items: center; justify-content: center; gap: 0; width: 20px; height: 20px; }

      .pw-sla {
        padding: 10px 12px 10px 16px; flex-shrink: 0; border-bottom: 1px solid var(--pw-border);
        background: color-mix(in srgb, var(--pw-composer) 7%, var(--pw-bg-panel));
      }
      .pw-sla--dismissed { display: none !important; }
      .pw-sla__row { display: flex; align-items: flex-start; gap: 6px; }
      .pw-sla__copy { flex: 1; min-width: 0; }
      .pw-sla__close {
        width: 32px; height: 32px; margin: -2px -4px 0 0; padding: 0; flex-shrink: 0; border: 1px solid transparent;
        background: transparent; border-radius: var(--pw-radius-full); color: var(--pw-text-muted);
        display: flex; align-items: center; justify-content: center; cursor: pointer; line-height: 0;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
      }
      .pw-sla__close:hover { background: color-mix(in srgb, var(--pw-text) 6%, var(--pw-bg-panel)); color: var(--pw-text); border-color: var(--pw-border); }
      .pw-sla__close:focus-visible { outline: 2px solid var(--pw-composer); outline-offset: 1px; }
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
        border-radius: 8px; font-size: 14px; background: var(--pw-bg-panel); color: var(--pw-text);
        outline: none; accent-color: var(--pw-composer); }
      .pw-onboarding input:focus-visible { border-color: color-mix(in srgb, var(--pw-composer) 50%, var(--pw-border));
        box-shadow: 0 0 0 3px var(--pw-composer-ring); }
      .pw-onboarding button { padding: 10px 20px; background: var(--pw-composer); color: #ffffff;
        border: none; border-radius: var(--pw-radius-md); font-weight: 600; font-size: 14px; cursor: pointer; }
      .pw-form-container { background: var(--pw-bg-panel); padding: 12px 14px; border-top: 1px solid var(--pw-border);
        box-shadow: 0 -2px 10px rgba(0,0,0,0.04); flex-shrink: 0; }
      .pw-status { font-size: 12.5px; color: var(--pw-text-muted); margin-bottom: 10px; text-align: center; font-weight: 500; line-height: 1.45; max-width: 100%; }
      .pw-status--empty { display: none; margin-bottom: 0; }
      .pw-typing { font-size: 12px; color: #9a5fa8; margin-bottom: 6px; min-height: 18px; }
      .pw-form { display: flex; gap: 8px; align-items: flex-end; background: var(--pw-form-bg);
        border: 1px solid var(--pw-border); padding: 6px; border-radius: var(--pw-radius-md); }
      .pw-form:focus-within { border-color: color-mix(in srgb, var(--pw-composer) 45%, var(--pw-border));
        box-shadow: 0 0 0 3px var(--pw-composer-ring); }
      .pw-input { flex: 1; resize: none; min-height: 24px; max-height: 120px; padding: 8px 10px; border: none; background: transparent;
        font-size: 14px; color: var(--pw-text); line-height: 1.4; outline: none; -webkit-tap-highlight-color: transparent;
        caret-color: var(--pw-composer); }
      .pw-input::placeholder { color: var(--pw-input-placeholder); }
      .pw-send { width: 36px; height: 36px; border: none; border-radius: var(--pw-radius-full);
        background: var(--pw-composer); color: #ffffff; display: flex; align-items: center; justify-content: center;
        transition: opacity 0.2s, transform 0.15s; flex-shrink: 0; line-height: 0; }
      .pw-send:hover { transform: scale(1.04); }
      .pw-send[disabled] { opacity: 0.45; cursor: not-allowed; transform: none; }
      .pw-send:focus, .pw-send:focus-visible { outline: none; box-shadow: none; }
      .pw-form:focus-within .pw-send:focus-visible { box-shadow: 0 0 0 2px var(--pw-bg-panel), 0 0 0 4px var(--pw-composer); }
      .pw-icon-btn:focus, .pw-icon-btn:focus-visible { outline: 2px solid var(--pw-composer); outline-offset: 1px; }

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
    const sublineOnlineText = 'На связи';
    const sublineOfflineShortText = 'Не в сети';
    const genericOperatorLabel = 'Команда поддержки';
    const offlinePresenceText =
        typeof config.responseSlaText === 'string' && config.responseSlaText.trim()
            ? config.responseSlaText.trim()
            : 'Стараемся ответить в течение рабочего дня.';

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
            <div class="pw-op-row pw-hidden" id="pw-op-row">
              <div class="pw-op-avatar" id="pw-op-avatar" aria-hidden="true">${iconUser}</div>
              <div class="pw-op-meta">
                <div class="pw-op-name" id="pw-op-name"></div>
                <div class="pw-op-line2" id="pw-op-line2"></div>
              </div>
            </div>
          </div>
          <div class="pw-head-actions">
            <button class="pw-icon-btn pw-theme-toggle" type="button" data-pw-theme-cycle title="Смена темы" aria-label="Сменить тему"></button>
            <button class="pw-icon-btn pw-sound" type="button" aria-label="Звук" title="Звук уведомлений"></button>
            <button class="pw-icon-btn pw-close" type="button" aria-label="Закрыть"></button>
          </div>
        </header>
        <div class="pw-sla" id="pw-sla" role="region" aria-label="Сроки ответа и уведомления">
          <div class="pw-sla__row">
            <div class="pw-sla__copy">
              <p class="pw-sla__main">Время ожидания: ${sla1}</p>
              <p class="pw-sla__note">${sla2}</p>
            </div>
            <button type="button" class="pw-sla__close" data-pw-sla-close aria-label="Скрыть подсказку" title="Скрыть"></button>
          </div>
        </div>
        <div class="pw-msgs"></div>
        <div class="pw-form-container">
            <div class="pw-status pw-status--empty" id="pw-status" aria-hidden="true"></div>
            <form class="pw-form">
            <textarea class="pw-input" placeholder="Напишите сообщение…" rows="1"></textarea>
            <button class="pw-send" type="submit" disabled aria-label="Отправить"></button>
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
    const slaEl = root.querySelector('#pw-sla');
    const slaCloseBtn = root.querySelector('[data-pw-sla-close]');

    const svgSend = PW_ICONS['send.svg'];
    const svgPanelClose = PW_ICONS['close-outline.svg'];
    const svgVolOn = PW_ICONS['volume-high-outline.svg'];
    const svgVolOff = PW_ICONS['volume-mute-outline.svg'];
    const svgSun = PW_ICONS['sunny-outline.svg'];
    const svgMoon = PW_ICONS['moon-outline.svg'];
    const svgSlaClose = PW_ICONS['x.svg'];

    function svgWithClass(svg, extraClass) {
        if (!svg) return '';
        return svg.replace('<svg', '<svg class="pw-icon' + (extraClass ? ' ' + extraClass : '') + '" focusable="false" aria-hidden="true"');
    }
    function renderThemeButton() {
        if (!themeBtn) return;
        const themeTitles = { system: 'Как в системе', light: 'Светлая тема', dark: 'Тёмная тема' };
        const modeLabel = themeTitles[themeMode] || themeTitles.system;
        themeBtn.setAttribute('title', modeLabel);
        themeBtn.setAttribute('aria-label', 'Сменить тему: ' + modeLabel);
        if (themeMode === 'light' && svgSun) {
            themeBtn.innerHTML = svgWithClass(svgSun);
        } else if (themeMode === 'dark' && svgMoon) {
            themeBtn.innerHTML = svgWithClass(svgMoon);
        } else if (svgSun && svgMoon) {
            themeBtn.innerHTML = '<span class="pw-theme-dual" aria-hidden="true">' + svgWithClass(svgSun, 'pw-icon--xs') + svgWithClass(svgMoon, 'pw-icon--xs') + '</span>';
        }
    }
    function tryDismissSlaFromStorage() {
        if (!slaEl) return;
        try {
            if (localStorage.getItem(slaDismissKey) === '1') {
                slaEl.classList.add('pw-sla--dismissed');
            }
        } catch (e) { /* empty */ }
    }
    tryDismissSlaFromStorage();
    if (slaCloseBtn && slaEl) {
        slaCloseBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            slaEl.classList.add('pw-sla--dismissed');
            try { localStorage.setItem(slaDismissKey, '1'); } catch (err) { /* empty */ }
        });
    }
    if (sendBtn && svgSend) { sendBtn.innerHTML = svgWithClass(svgSend); }
    if (closeBtn && svgPanelClose) { closeBtn.innerHTML = svgWithClass(svgPanelClose); }
    if (slaCloseBtn && svgSlaClose) { slaCloseBtn.innerHTML = svgWithClass(svgSlaClose); }
    renderThemeButton();
    syncSoundButton();

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
    const onboardingText =
        'Спасибо за ваше сообщение! Укажите, пожалуйста, имя для обращения. Почта по желанию — с неё удобнее не потерять ответ, если вы уйдёте с сайта; без email ответ останется в чате и будет доступен при следующем визите.';
    let lastModeratorName = null;
    let lastModeratorAvatar = null;
    let lastPresenceIsOnline = null;

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
        if (s.enabled && svgVolOn) {
            soundBtn.innerHTML = svgWithClass(svgVolOn);
        } else if (!s.enabled && svgVolOff) {
            soundBtn.innerHTML = svgWithClass(svgVolOff);
        } else {
            soundBtn.innerHTML = '';
        }
        soundBtn.title = s.enabled ? 'Звук включён' : 'Звук выключен';
    }
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
        renderThemeButton();
    }
    if (themeBtn) {
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

    function setStatus(text) {
        if (!status) return;
        const t = text == null ? '' : String(text);
        status.textContent = t;
        status.classList.toggle('pw-status--empty', t.trim() === '');
        if (t.trim() === '') {
            status.setAttribute('aria-hidden', 'true');
        } else {
            status.removeAttribute('aria-hidden');
        }
    }

    function showGenericOperatorRow() {
        if (!opName || !opAvatar) return;
        if (lastModeratorName) return;
        opName.textContent = genericOperatorLabel;
        opAvatar.innerHTML = iconUser;
    }

    function applyOperatorSublineFromPresence() {
        if (!opLine2) return;
        if (operatorTypingTimeout) return;
        opLine2.classList.remove('pw-typing');
        if (lastPresenceIsOnline === true) {
            opLine2.textContent = sublineOnlineText;
        } else if (lastModeratorName) {
            opLine2.textContent = sublineOfflineShortText;
        } else {
            opLine2.textContent = offlinePresenceText;
        }
    }

    function setOperatorPresence(isOnline) {
        lastPresenceIsOnline = isOnline === true;
        if (!opRow) return;
        opRow.classList.remove('pw-hidden');
        if (!lastModeratorName) {
            showGenericOperatorRow();
        }
        applyOperatorSublineFromPresence();
        setStatus('');
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
            applyOperatorSublineFromPresence();
        }
    }

    function showOperatorTyping(senderName) {
        if (!opLine2 || !opName || !opRow) return;
        opRow.classList.remove('pw-hidden');
        if (!lastModeratorName && senderName) {
            updateOperatorHeader(senderName, null);
        }
        opLine2.classList.add('pw-typing');
        opLine2.textContent = (senderName || lastModeratorName || 'Оператор') + ' печатает…';
        if (operatorTypingTimeout) clearTimeout(operatorTypingTimeout);
        operatorTypingTimeout = setTimeout(function () {
            operatorTypingTimeout = null;
            if (opLine2) { opLine2.classList.remove('pw-typing'); }
            applyOperatorSublineFromPresence();
        }, 4000);
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
            if (result.chat) {
                if (result.chat.id != null) {
                    chatId = String(result.chat.id);
                    localStorage.setItem(chatIdKey, chatId);
                } else {
                    chatId = null;
                    try { localStorage.removeItem(chatIdKey); } catch (e) { /* empty */ }
                }
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
            if (sessionResponse) {
                if (typeof sessionResponse.is_online === 'boolean') {
                    setOperatorPresence(sessionResponse.is_online);
                } else {
                    setOperatorPresence(false);
                }
            }
        } else {
            setStatus('Ошибка подключения');
        }
    }

    async function loadMessages() {
        if (!chatToken) return;
        lastModeratorName = null;
        lastModeratorAvatar = null;
        if (opRow) {
            opRow.classList.add('pw-hidden');
        }
        const result = await request('/messages?chat_token=' + encodeURIComponent(chatToken) + '&limit=100');
        msgs.innerHTML = '';
        lastMessageId = 0;
        lastPresenceIsOnline = result.is_online === true;
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
        setOperatorPresence(lastPresenceIsOnline);
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
                if (result.chat_token) {
                    chatToken = result.chat_token;
                    localStorage.setItem(chatTokenKey, chatToken);
                }
                if (result.chat && result.chat.id != null) {
                    chatId = String(result.chat.id);
                    localStorage.setItem(chatIdKey, chatId);
                    if (!echoChannel) {
                        connectReverb();
                    }
                }
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

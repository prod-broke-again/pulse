/* eslint-disable no-restricted-globals */
self.addEventListener('push', function (event) {
    if (!event.data) return;
    let payload = { title: 'Pulse', body: '', data: { url: '/' } };
    try {
        payload = event.data.json();
    } catch (_) {
        payload.body = event.data.text();
    }
    const options = {
        body: payload.body || '',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: payload.data || { url: '/' },
        tag: payload.tag || (payload.data?.chat_id ? 'chat-' + payload.data.chat_id : undefined),
        renotify: true,
    };
    event.waitUntil(
        self.registration.showNotification(payload.title || 'Pulse', options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const data = event.notification.data || {};
    const url = data.url || '/';
    const fullUrl = new URL(url, self.location.origin).href;
    const chatId = data.chat_id;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            var origin = self.location.origin;
            var chatPath = origin + '/chat';
            for (let i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                try {
                    var u = new URL(client.url);
                    if (u.origin === origin && (u.pathname === '/chat' || u.pathname === '/chat/')) {
                        client.focus();
                        if (chatId != null && 'postMessage' in client) {
                            client.postMessage({ type: 'OPEN_CHAT', chatId: chatId });
                        }
                        return;
                    }
                } catch (_) {}
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(fullUrl);
            }
        })
    );
});

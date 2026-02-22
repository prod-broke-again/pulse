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
        tag: payload.data?.chat_id ? 'chat-' + payload.data.chat_id : undefined,
        renotify: true,
    };
    event.waitUntil(
        self.registration.showNotification(payload.title || 'Pulse', options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    const fullUrl = new URL(url, self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url === fullUrl || client.url.startsWith(fullUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(fullUrl);
            }
        })
    );
});

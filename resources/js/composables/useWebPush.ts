import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { api } from '@/lib/api';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function useWebPush() {
    const page = usePage();
    const vapidPublicKey = computed(() => page.props.vapidPublicKey as string | null | undefined);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const supported = typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window;

    async function registerSw(): Promise<ServiceWorkerRegistration | null> {
        if (!supported) return null;
        const reg = await navigator.serviceWorker.getRegistration();
        if (reg?.active) return reg;
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await registration.ready;
        return registration;
    }

    async function subscribe(): Promise<boolean> {
        if (!supported || !vapidPublicKey.value) {
            error.value = 'Push not supported or VAPID key not configured';
            return false;
        }
        loading.value = true;
        error.value = null;
        try {
            const registration = await registerSw();
            if (!registration) {
                error.value = 'Service worker not available';
                return false;
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                error.value = 'Permission denied';
                return false;
            }
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
            });
            const sub = subscription.toJSON() as { endpoint: string; keys?: { p256dh?: string; auth?: string } };
            const keys = sub.keys;
            if (!keys?.p256dh || !keys?.auth) {
                const p256dh = subscription.getKey('p256dh');
                const auth = subscription.getKey('auth');
                sub.keys = {
                    p256dh: p256dh ? arrayBufferToBase64(p256dh) : '',
                    auth: auth ? arrayBufferToBase64(auth) : '',
                };
            }
            await api.post('/push-subscriptions', {
                endpoint: sub.endpoint,
                keys: sub.keys ?? {},
                user_agent: navigator.userAgent,
            });
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to subscribe';
            return false;
        } finally {
            loading.value = false;
        }
    }

    return { supported, vapidPublicKey, loading, error, subscribe, registerSw };
}

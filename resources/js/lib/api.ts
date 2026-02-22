const BASE = '/api/v1';

function getCsrfToken(): string | null {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) {
        return decodeURIComponent(match[1]);
    }
    return null;
}

async function request<T>(
    path: string,
    options: RequestInit & { params?: Record<string, string | number | undefined> } = {},
): Promise<T> {
    const { params, ...init } = options;
    let url = path.startsWith('http') ? path : `${BASE}${path}`;
    if (params) {
        const search = new URLSearchParams();
        for (const [k, v] of Object.entries(params)) {
            if (v !== undefined && v !== '') search.set(k, String(v));
        }
        const q = search.toString();
        if (q) url += (url.includes('?') ? '&' : '?') + q;
    }
    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...((init.headers as Record<string, string>) ?? {}),
    };
    const csrf = getCsrfToken();
    if (csrf) headers['X-XSRF-TOKEN'] = csrf;
    if (init.body && typeof init.body === 'string' && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
    }
    const res = await fetch(url, {
        ...init,
        credentials: 'include',
        headers: { ...init.headers, ...headers },
    });
    if (res.status === 401) {
        window.location.href = '/login';
        throw new Error('Unauthorized');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = data.message ?? data.error ?? `Request failed: ${res.status}`;
        throw new Error(Array.isArray(msg) ? msg.join(' ') : msg);
    }
    return data as T;
}

export const api = {
    get<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
        return request<T>(path, { method: 'GET', params });
    },
    post<T>(path: string, body?: unknown, params?: Record<string, string | number | undefined>): Promise<T> {
        return request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined, params });
    },
    delete<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
        return request<T>(path, { method: 'DELETE', params });
    },
};

export type ApiChat = {
    id: number;
    source_id: number;
    department_id: number | null;
    external_user_id: string;
    user_metadata: Record<string, unknown>;
    status: string;
    assigned_to: number | null;
    source?: { id: number; name: string; type: string };
    department?: { id: number; name: string } | null;
    assignee?: { id: number; name: string } | null;
    latest_message?: { id: number; text: string; sender_type: string; created_at: string } | null;
    is_urgent?: boolean;
    created_at: string;
    updated_at: string;
};

export type ApiMessage = {
    id: number;
    chat_id: number;
    sender_id: number | null;
    sender_type: string;
    text: string;
    payload: Record<string, unknown>;
    attachments?: Array<{ id: number; name: string; url: string; mime_type?: string }>;
    is_read: boolean;
    created_at: string;
    updated_at: string;
};

export type ApiCannedResponse = {
    id: number;
    code: string;
    title: string;
    text: string;
    source_id: number | null;
};

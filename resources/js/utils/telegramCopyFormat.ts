import type { ApiMessage } from '@/lib/api';

function part(parts: Intl.DateTimeFormatPart[], type: Intl.DateTimeFormatPartTypes): string {
    return parts.find((p) => p.type === type)?.value ?? '';
}

/** `[dd.mm.yyyy HH:mm]` in the browser's local timezone. */
export function formatRuDateTimeBracket(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '[]';
    }
    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(d);
    const day = part(parts, 'day');
    const month = part(parts, 'month');
    const year = part(parts, 'year');
    const hour = part(parts, 'hour');
    const minute = part(parts, 'minute');
    return `[${day}.${month}.${year} ${hour}:${minute}]`;
}

function attachmentSummary(m: ApiMessage): string {
    const atts = m.attachments ?? [];
    if (atts.length === 0) return '';
    const allImg = atts.every((a) => (a.mime_type ?? '').startsWith('image/'));
    if (allImg) return atts.length > 1 ? `[Фото ×${atts.length}]` : '[Фото]';
    const allAudio = atts.every((a) => (a.mime_type ?? '').startsWith('audio/'));
    if (allAudio) return atts.length > 1 ? `[Аудио ×${atts.length}]` : '[Аудио]';
    if (atts.length > 1) return `[Вложений: ${atts.length}]`;
    return `[${atts[0]!.name}]`;
}

function bodyLine(m: ApiMessage): string {
    const t = (m.text ?? '').trim();
    if (t !== '') return t;
    return attachmentSummary(m);
}

export function formatApiMessagesTelegramStyle(
    timeline: ApiMessage[],
    selectedIds: ReadonlySet<string>,
    peerName: string,
    moderatorName: string,
): string {
    const lines: string[] = [];
    for (const m of timeline) {
        if (m.sender_type === 'system') continue;
        const sid = String(m.id);
        if (!selectedIds.has(sid)) continue;
        const who = m.sender_type === 'moderator' ? moderatorName : peerName;
        lines.push(`${formatRuDateTimeBracket(m.created_at)} ${who}: ${bodyLine(m)}`);
    }
    return lines.join('\n');
}

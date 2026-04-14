import type { ApiMessage } from '@/lib/api';

function pad2(n: number): string {
    return n.toString().padStart(2, '0');
}

export function formatRuDateTimeBracket(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        const x = new Date();
        return `[${pad2(x.getDate())}.${pad2(x.getMonth() + 1)}.${x.getFullYear()} ${pad2(x.getHours())}:${pad2(x.getMinutes())}]`;
    }
    return `[${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}]`;
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

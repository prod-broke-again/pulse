import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'
import initSqlJs, { type Database } from 'sql.js'
import { app } from 'electron'

/** Payload для POST /chats/{id}/send (сериализуется в outbox). */
export type OutboxPayload = {
  chatId: number
  text: string
  attachments: string[]
  clientMessageId: string
  replyMarkup?: { text: string; url: string }[]
}

let db: Database | null = null
let dbFilePath = ''

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const require = createRequire(import.meta.url)

function sqlJsWasmDir(): string {
  const wasmName = 'sql-wasm.wasm'
  const candidates: string[] = []
  try {
    // Robust for npm workspaces/hoisted deps.
    const resolvedWasm = require.resolve('sql.js/dist/sql-wasm.wasm')
    candidates.push(path.dirname(resolvedWasm))
  } catch {
    /* ignore and fallback to static paths */
  }
  if (app.isReady()) {
    candidates.push(path.join(app.getAppPath(), 'node_modules', 'sql.js', 'dist'))
  }
  candidates.push(path.join(__dirname, '../../node_modules/sql.js/dist'))
  candidates.push(path.join(__dirname, '../../../../node_modules/sql.js/dist'))
  for (const dir of candidates) {
    if (fs.existsSync(path.join(dir, wasmName))) {
      return dir
    }
  }
  return candidates[0] ?? path.join(__dirname, '../../node_modules/sql.js/dist')
}

function persist(): void {
  if (!db || !dbFilePath) {
    return
  }
  const data = db.export()
  fs.writeFileSync(dbFilePath, Buffer.from(data))
}

export async function initLocalDb(): Promise<void> {
  if (db) {
    return
  }
  const SQL = await initSqlJs({
    locateFile: (file) => path.join(sqlJsWasmDir(), file),
  })
  dbFilePath = path.join(app.getPath('userData'), 'pulse-local.db')
  let buffer: Uint8Array | undefined
  if (fs.existsSync(dbFilePath)) {
    buffer = new Uint8Array(fs.readFileSync(dbFilePath))
  }
  db = buffer ? new SQL.Database(buffer) : new SQL.Database()
  db.exec(`
    CREATE TABLE IF NOT EXISTS message_cache (
      chat_id INTEGER PRIMARY KEY,
      json TEXT NOT NULL,
      updated_at INTEGER NOT NULL
    );
    CREATE TABLE IF NOT EXISTS outbox (
      id TEXT PRIMARY KEY,
      chat_id INTEGER NOT NULL,
      payload TEXT NOT NULL,
      created_at INTEGER NOT NULL,
      attempts INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS idx_outbox_chat ON outbox(chat_id);
  `)
  persist()
}

export function closeLocalDb(): void {
  try {
    db?.close()
  } catch {
    /* ignore */
  }
  db = null
}

export function getMessageCacheJson(chatId: number): string | null {
  if (!db) {
    return null
  }
  const stmt = db.prepare('SELECT json FROM message_cache WHERE chat_id = ?')
  stmt.bind([chatId])
  if (!stmt.step()) {
    stmt.free()
    return null
  }
  const row = stmt.getAsObject()
  stmt.free()
  const json = row.json
  return typeof json === 'string' ? json : json != null ? String(json) : null
}

export function setMessageCacheJson(chatId: number, json: string): void {
  if (!db) {
    return
  }
  const now = Date.now()
  db.run(
    'INSERT INTO message_cache (chat_id, json, updated_at) VALUES (?, ?, ?) ON CONFLICT(chat_id) DO UPDATE SET json = excluded.json, updated_at = excluded.updated_at',
    [chatId, json, now],
  )
  persist()
}

export function outboxList(): Array<{ id: string; chat_id: number; payload: string; created_at: number; attempts: number }> {
  if (!db) {
    return []
  }
  const stmt = db.prepare('SELECT id, chat_id, payload, created_at, attempts FROM outbox ORDER BY created_at ASC')
  const rows: Array<{ id: string; chat_id: number; payload: string; created_at: number; attempts: number }> = []
  while (stmt.step()) {
    const o = stmt.getAsObject()
    rows.push({
      id: String(o.id),
      chat_id: Number(o.chat_id),
      payload: String(o.payload),
      created_at: Number(o.created_at),
      attempts: Number(o.attempts),
    })
  }
  stmt.free()
  return rows
}

export function outboxAdd(id: string, chatId: number, payloadJson: string): void {
  if (!db) {
    return
  }
  const now = Date.now()
  db.run('INSERT INTO outbox (id, chat_id, payload, created_at, attempts) VALUES (?, ?, ?, ?, 0)', [
    id,
    chatId,
    payloadJson,
    now,
  ])
  persist()
}

export function outboxRemove(id: string): void {
  if (!db) {
    return
  }
  db.run('DELETE FROM outbox WHERE id = ?', [id])
  persist()
}

export function outboxIncrementAttempts(id: string): void {
  if (!db) {
    return
  }
  db.run('UPDATE outbox SET attempts = attempts + 1 WHERE id = ?', [id])
  persist()
}

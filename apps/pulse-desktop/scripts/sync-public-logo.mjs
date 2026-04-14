import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const appRoot = path.resolve(__dirname, '..')
const src = path.join(appRoot, 'logo.png')
const dest = path.join(appRoot, 'public', 'logo.png')

if (!fs.existsSync(src)) {
  console.warn('[sync-public-logo] logo.png not found, skip')
  process.exit(0)
}
fs.mkdirSync(path.dirname(dest), { recursive: true })
fs.copyFileSync(src, dest)

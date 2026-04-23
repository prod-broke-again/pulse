import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const appRoot = path.resolve(__dirname, '..')
const src = path.resolve(appRoot, '..', 'pulse-mobile', 'assets', 'logo.png')
const targets = [
  path.join(appRoot, 'logo.png'),
  path.join(appRoot, 'public', 'logo.png'),
]

if (!fs.existsSync(src)) {
  console.warn('[sync-public-logo] logo.png not found, skip')
  process.exit(0)
}
for (const target of targets) {
  fs.mkdirSync(path.dirname(target), { recursive: true })
  fs.copyFileSync(src, target)
}

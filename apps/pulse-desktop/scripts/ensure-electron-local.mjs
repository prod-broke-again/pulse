import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const appRoot = path.resolve(__dirname, '..')
const repoRoot = path.resolve(appRoot, '../..')
const electronSrc = path.join(repoRoot, 'node_modules', 'electron')
const nm = path.join(appRoot, 'node_modules')
const electronDest = path.join(nm, 'electron')

if (!fs.existsSync(electronSrc)) {
  console.error('[ensure-electron-local] electron not found at', electronSrc)
  process.exit(1)
}
if (!fs.existsSync(nm)) {
  fs.mkdirSync(nm, { recursive: true })
}
if (fs.existsSync(electronDest)) {
  process.exit(0)
}
const type = process.platform === 'win32' ? 'junction' : 'dir'
fs.symlinkSync(path.resolve(electronSrc), electronDest, type)

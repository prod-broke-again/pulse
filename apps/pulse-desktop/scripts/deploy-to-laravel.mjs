import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const srcDir = path.resolve(__dirname, '../dist-web')
const destDir = path.resolve(__dirname, '../../../public/messenger')

console.log('🚀 Начинаем копирование веб-сборки в public/messenger...')

try {
  // Проверяем наличие исходной папки
  if (!fs.existsSync(srcDir)) {
    console.error(`❌ Ошибка: Исходная папка ${srcDir} не найдена. Сначала запустите сборку!`)
    process.exit(1)
  }

  // Очищаем/создаем целевую папку в Laravel
  if (fs.existsSync(destDir)) {
    fs.rmSync(destDir, { recursive: true, force: true })
  }
  fs.mkdirSync(destDir, { recursive: true })

  // Рекурсивная функция копирования
  const copyFolderSync = (from, to) => {
    fs.mkdirSync(to, { recursive: true })
    fs.readdirSync(from).forEach((element) => {
      const srcEl = path.join(from, element)
      const destEl = path.join(to, element)
      if (fs.lstatSync(srcEl).isDirectory()) {
        copyFolderSync(srcEl, destEl)
      } else {
        fs.copyFileSync(srcEl, destEl)
      }
    })
  }

  copyFolderSync(srcDir, destDir)
  console.log(`✨ Успешно скопировано в: ${destDir}`)
  console.log(`🔗 Теперь веб-клиент доступен по адресу: http://ваш-домен/messenger/`)
} catch (error) {
  console.error('❌ Ошибка копирования:', error)
  process.exit(1)
}

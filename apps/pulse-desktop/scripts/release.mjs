import { spawn } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import process from 'node:process'

const APP_SUBPATH = 'apps/pulse-desktop'
const RELEASE_REPO = 'prod-broke-again/pulse-desktop-releases'
const DIFF_LIMIT = 32000

function parseArgs(argv) {
  const out = {
    bump: 'patch',
    notesFile: '',
    notesText: '',
    provider: 'auto',
    noAi: false,
    dryRun: false,
    help: false,
  }
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i]
    if (a === '--help' || a === '-h') {
      out.help = true
      continue
    }
    if (a === '--dry-run') {
      out.dryRun = true
      continue
    }
    if (a === '--no-ai') {
      out.noAi = true
      continue
    }
    if (a === '--notes-file') {
      out.notesFile = (argv[i + 1] ?? '').trim()
      i += 1
      continue
    }
    if (a === '--notes') {
      out.notesText = (argv[i + 1] ?? '').trim()
      i += 1
      continue
    }
    if (a === '--provider') {
      out.provider = (argv[i + 1] ?? 'auto').trim().toLowerCase() || 'auto'
      i += 1
      continue
    }
    if (a === 'patch' || a === 'minor' || a === 'major') {
      out.bump = a
      continue
    }
  }
  return out
}

function printHelp() {
  console.log(`
Usage:
  npm run release:patch
  npm run release:minor
  npm run release:major

Optional:
  --notes-file <path>   Use release notes from file (supports "@path")
  --notes "<text>"      Use inline release notes text
  --no-ai               Disable AI notes generation (template fallback)
  --provider <name>     AI for release notes: auto (default), timeweb, gptunnel
  --dry-run             Print actions without executing
  --help                Show help
`)
}

function run(cmd, args, opts = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(cmd, args, {
      cwd: opts.cwd ?? process.cwd(),
      stdio: 'inherit',
      shell: process.platform === 'win32',
      env: process.env,
    })
    child.on('exit', (code) => {
      if (code === 0) {
        resolve()
      } else {
        reject(new Error(`Command failed (${code}): ${cmd} ${args.join(' ')}`))
      }
    })
  })
}

function runCapture(cmd, args, cwd) {
  return new Promise((resolve, reject) => {
    const child = spawn(cmd, args, {
      cwd,
      stdio: ['ignore', 'pipe', 'pipe'],
      shell: process.platform === 'win32',
      env: process.env,
    })
    let out = ''
    let err = ''
    child.stdout.on('data', (d) => {
      out += d.toString()
    })
    child.stderr.on('data', (d) => {
      err += d.toString()
    })
    child.on('exit', (code) => {
      if (code === 0) {
        resolve(out.trim())
      } else {
        reject(new Error((err || out || `Command failed (${code})`).trim()))
      }
    })
  })
}

function incVersion(version, bump) {
  const m = /^(\d+)\.(\d+)\.(\d+)$/.exec(version)
  if (!m) {
    throw new Error(`Unsupported version format: ${version}`)
  }
  let major = Number(m[1])
  let minor = Number(m[2])
  let patch = Number(m[3])
  if (bump === 'major') {
    major += 1
    minor = 0
    patch = 0
  } else if (bump === 'minor') {
    minor += 1
    patch = 0
  } else {
    patch += 1
  }
  return `${major}.${minor}.${patch}`
}

function createDefaultNotes(version) {
  return `## Pulse Desktop ${version}

Технический патч.

- Улучшения стабильности и исправления по текущим изменениям.

Если автообновление не сработало, скачайте установщик **Pulse-Windows-${version}-Setup.exe** из ассетов релиза ниже.
`
}

function readJsonFileSafe(filePath) {
  try {
    if (!fs.existsSync(filePath)) {
      return null
    }
    const raw = fs.readFileSync(filePath, 'utf8')
    return JSON.parse(raw)
  } catch {
    return null
  }
}

function normalizeProvider(value) {
  const v = String(value ?? '').trim().toLowerCase()
  if (v === 'claude' || v === 'anthropic') {
    return 'anthropic'
  }
  if (v === 'google' || v === 'gemini') {
    return 'gemini'
  }
  if (v === 'timeweb') {
    return 'timeweb'
  }
  if (v === 'gptunnel') {
    return 'gptunnel'
  }
  if (v === 'openai') {
    return 'openai'
  }
  return ''
}

/**
 * @param {'auto' | 'timeweb' | 'gptunnel'} providerOverride
 *        auto: order by env RELEASE_NOTES_PROVIDER or smart-commit defaultProvider
 *        timeweb | gptunnel: try that provider first, then the other
 */
function resolveAiCandidatesFromSmartCommit(providerOverride = 'auto') {
  const smartCommitGlobalPath = path.join(os.homedir(), '.smart-commit', 'config.json')
  const smartCommitCfg = readJsonFileSafe(smartCommitGlobalPath) ?? {}
  const apiKeys = (smartCommitCfg.apiKeys && typeof smartCommitCfg.apiKeys === 'object')
    ? smartCommitCfg.apiKeys
    : {}
  const baseUrls = (smartCommitCfg.baseUrls && typeof smartCommitCfg.baseUrls === 'object')
    ? smartCommitCfg.baseUrls
    : {}

  const envProvider = normalizeProvider(process.env.RELEASE_NOTES_PROVIDER)
  const cfgDefaultProvider = normalizeProvider(smartCommitCfg.defaultProvider) || 'gptunnel'
  const mode = (providerOverride ?? 'auto').trim().toLowerCase() || 'auto'
  const defaultProvider = mode === 'auto'
    ? (envProvider || cfgDefaultProvider)
    : (mode === 'timeweb' ? 'timeweb' : 'gptunnel')

  const providersOrder = defaultProvider === 'timeweb'
    ? ['timeweb', 'gptunnel']
    : ['gptunnel', 'timeweb']

  const seen = new Set()
  const orderedProviders = []
  for (const p of providersOrder) {
    if (!seen.has(p)) {
      seen.add(p)
      orderedProviders.push(p)
    }
  }

  const candidates = []
  for (const provider of orderedProviders) {
    const providerEnvVar = `RELEASE_NOTES_${provider.toUpperCase()}_API_KEY`
    const providerBaseEnvVar = `RELEASE_NOTES_${provider.toUpperCase()}_BASE`

    const apiKey = String(
      process.env[providerEnvVar]
      ?? apiKeys[provider]
      ?? smartCommitCfg.apiKey
      ?? '',
    ).trim()

    if (apiKey === '') {
      continue
    }

    const baseUrlRaw = String(
      process.env[providerBaseEnvVar]
      ?? baseUrls[provider]
      ?? (provider === 'gptunnel' ? 'https://gptunnel.ru/v1' : 'https://agent.timeweb.cloud')
      ?? '',
    ).trim()
    const baseUrl = baseUrlRaw.replace(/\/+$/, '')

    const model = String(
      process.env.RELEASE_NOTES_MODEL
      ?? smartCommitCfg.defaultModel
      ?? (provider === 'timeweb' ? 'gpt-4o-mini' : 'gpt-4.1-mini'),
    ).trim()

    const temperatureRaw = process.env.RELEASE_NOTES_TEMPERATURE ?? smartCommitCfg.temperature
    const maxTokensRaw = process.env.RELEASE_NOTES_MAX_TOKENS ?? smartCommitCfg.maxTokens
    const temperature = Number.isFinite(Number(temperatureRaw)) ? Number(temperatureRaw) : 0.2
    const maxTokens = Number.isFinite(Number(maxTokensRaw)) ? Number(maxTokensRaw) : 700
    const useWalletBalance = Boolean(
      String(process.env.RELEASE_NOTES_USE_WALLET_BALANCE ?? smartCommitCfg.useWalletBalance ?? 'true') === 'true',
    )

    candidates.push({
      provider,
      apiKey,
      baseUrl,
      model,
      temperature,
      maxTokens,
      useWalletBalance,
      source: smartCommitCfg.defaultProvider ? 'smart-commit-global-config' : 'env',
      configPath: smartCommitGlobalPath,
    })
  }

  if (candidates.length === 0) {
    const legacyApiKey = String(
      process.env.RELEASE_NOTES_API_KEY
      ?? process.env.GPT_API_KEY
      ?? process.env.OPENAI_API_KEY
      ?? '',
    ).trim()
    if (legacyApiKey !== '') {
      const baseUrl = String(
        process.env.RELEASE_NOTES_API_BASE
        ?? process.env.OPENAI_BASE_URL
        ?? 'https://api.openai.com/v1',
      ).trim().replace(/\/+$/, '')
      const style = normalizeProvider(process.env.RELEASE_NOTES_API_STYLE) || (baseUrl.includes('gptunnel.ru') ? 'gptunnel' : 'openai')
      candidates.push({
        provider: style || 'openai',
        apiKey: legacyApiKey,
        baseUrl,
        model: String(process.env.RELEASE_NOTES_MODEL ?? 'gpt-4.1-mini').trim(),
        temperature: Number(process.env.RELEASE_NOTES_TEMPERATURE ?? 0.2),
        maxTokens: Number(process.env.RELEASE_NOTES_MAX_TOKENS ?? 700),
        useWalletBalance: String(process.env.RELEASE_NOTES_USE_WALLET_BALANCE ?? 'true') === 'true',
        source: 'legacy-env',
        configPath: '',
      })
    }
  }

  return {
    candidates,
    defaultProvider,
    providerMode: mode,
    smartCommitGlobalPath,
  }
}

function truncate(value, max) {
  if (value.length <= max) {
    return value
  }
  return `${value.slice(0, max)}\n... [diff truncated]`
}

async function collectReleaseContext(root) {
  const changedFilesRaw = await runCapture(
    'git',
    ['diff', '--name-only', 'HEAD', '--', APP_SUBPATH],
    root,
  )
  const changedFiles = changedFilesRaw
    .split(/\r?\n/)
    .map((v) => v.trim())
    .filter(Boolean)

  const diff = await runCapture(
    'git',
    ['diff', 'HEAD', '--', APP_SUBPATH],
    root,
  )

  return {
    changedFiles,
    diff: truncate(diff, DIFF_LIMIT),
  }
}

async function generateAiNotes({ version, bump, context, ai }) {
  const systemPrompt = [
    'Ты release-редактор для desktop-приложения.',
    'Пиши только на русском.',
    'Нужен короткий и точный markdown для релиза.',
    'Не используй эмодзи.',
    'Не выдумывай изменения, опирайся только на переданный diff и список файлов.',
    'Если уверенности недостаточно, пиши нейтрально: "улучшена стабильность" и т.п.',
    'Не добавляй вступлений и пояснений вне markdown.',
  ].join(' ')

  const userPrompt = `
Собери release notes для версии ${version}.
Тип релиза: ${bump}.

Формат строго такой:

## Pulse Desktop ${version}

### Что изменилось
- ...

### Исправления и стабильность
- ...

### Обновление
- Если автообновление не сработало, скачайте установщик **Pulse-Windows-${version}-Setup.exe** из ассетов релиза ниже.

Ограничения:
- 4-8 буллетов суммарно во всех секциях.
- Без эмодзи.
- Без слов "мы сделали", только факты.
- Не упоминай то, чего нет в diff.

Измененные файлы:
${context.changedFiles.join('\n') || '(нет списка)'}

Diff:
${context.diff || '(нет diff)'}
`.trim()

  const url = `${ai.baseUrl}/chat/completions`
  const isGptunnel = ai.provider === 'gptunnel'
  const headers = {
    'Content-Type': 'application/json',
    Authorization: isGptunnel ? ai.apiKey : `Bearer ${ai.apiKey}`,
  }

  const body = {
    model: ai.model,
    messages: [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: userPrompt },
    ],
    temperature: ai.temperature,
    max_tokens: ai.maxTokens,
  }
  if (isGptunnel) {
    body.useWalletBalance = ai.useWalletBalance
  }

  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: JSON.stringify(body),
  })

  if (!res.ok) {
    const errText = await res.text()
    throw new Error(`AI request failed: ${res.status} ${errText}`)
  }

  const json = await res.json()
  const content = json?.choices?.[0]?.message?.content
  if (typeof content !== 'string' || content.trim() === '') {
    throw new Error('AI returned empty notes')
  }

  return `${content.trim()}\n`
}

async function main() {
  const args = parseArgs(process.argv.slice(2))
  if (args.help) {
    printHelp()
    return
  }

  const validProviders = new Set(['auto', 'timeweb', 'gptunnel'])
  if (!validProviders.has(args.provider)) {
    throw new Error(`Invalid --provider "${args.provider}". Use: auto, timeweb, gptunnel`)
  }

  const appDir = process.cwd()
  const root = await runCapture('git', ['rev-parse', '--show-toplevel'], appDir)
  const relAppDir = path.relative(root, appDir).replace(/\\/g, '/')
  if (relAppDir !== APP_SUBPATH) {
    throw new Error(`Run this script from ${APP_SUBPATH}`)
  }

  const pkgPath = path.join(appDir, 'package.json')
  const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'))
  const prevVersion = String(pkg.version)
  const nextVersion = incVersion(prevVersion, args.bump)
  const tag = `v${nextVersion}`
  const releaseDir = path.join(appDir, 'release', nextVersion)
  const generatedNotesPath = path.join(appDir, 'distribution', `release-body-v${nextVersion}.md`)

  let notesFilePath = ''
  let notesToWrite = ''
  const aiPlan = resolveAiCandidatesFromSmartCommit(args.provider)

  if (args.notesText !== '') {
    notesFilePath = generatedNotesPath
    notesToWrite = `${args.notesText}\n`
  } else if (args.notesFile !== '') {
    const raw = args.notesFile.startsWith('@') ? args.notesFile.slice(1) : args.notesFile
    notesFilePath = path.isAbsolute(raw) ? raw : path.resolve(root, raw)
    if (!fs.existsSync(notesFilePath)) {
      throw new Error(`Notes file not found: ${notesFilePath}`)
    }
  } else {
    notesFilePath = generatedNotesPath
    notesToWrite = ''
  }

  const commitMessage = `chore(pulse-desktop): release ${nextVersion}

Bump desktop app version to ${nextVersion}, build installer assets, and publish release notes.`

  const actions = [
    ['npm', ['run', 'build'], appDir],
    ['git', ['add', `${APP_SUBPATH}`], root],
    ['git', ['commit', '-m', commitMessage], root],
    ['git', ['push', 'origin', 'master'], root],
  ]

  if (args.dryRun) {
    console.log(`[dry-run] ${prevVersion} -> ${nextVersion}`)
    console.log(`[dry-run] Notes: ${notesFilePath}`)
    if (args.notesText !== '' || args.notesFile !== '') {
      console.log('[dry-run] Notes file will be created/overwritten from provided text/template')
    } else if (!args.noAi && aiPlan.candidates.length > 0) {
      const chain = aiPlan.candidates.map((c) => `${c.provider}:${c.model}`).join(' -> ')
      console.log(`[dry-run] AI provider mode: ${aiPlan.providerMode} (order: first ${aiPlan.defaultProvider})`)
      console.log(`[dry-run] AI notes enabled (${chain})`)
      if (aiPlan.smartCommitGlobalPath !== '') {
        console.log(`[dry-run] Smart Commit config: ${aiPlan.smartCommitGlobalPath}`)
      }
    } else {
      console.log('[dry-run] AI notes disabled/unavailable, template notes will be used')
    }
    for (const [cmd, cmdArgs, cwd] of actions) {
      console.log(`[dry-run] (${cwd}) ${cmd} ${cmdArgs.join(' ')}`)
    }
    return
  }

  if (notesToWrite === '' && args.notesFile === '') {
    if (!args.noAi && aiPlan.candidates.length > 0) {
      const context = await collectReleaseContext(root)
      for (const ai of aiPlan.candidates) {
        try {
          notesToWrite = await generateAiNotes({
            version: nextVersion,
            bump: args.bump,
            context,
            ai,
          })
          console.log(`[release] AI notes generated via ${ai.provider}/${ai.model} (${ai.source})`)
          break
        } catch (e) {
          console.warn(`[release] AI provider failed (${ai.provider}): ${e.message}`)
        }
      }
      if (notesToWrite === '') {
        console.warn('[release] All AI providers failed, fallback to template notes')
        notesToWrite = createDefaultNotes(nextVersion)
      }
    } else {
      notesToWrite = createDefaultNotes(nextVersion)
    }
  }

  if (notesToWrite !== '') {
    fs.writeFileSync(notesFilePath, notesToWrite, 'utf8')
  }

  pkg.version = nextVersion
  fs.writeFileSync(pkgPath, `${JSON.stringify(pkg, null, 2)}\n`, 'utf8')

  for (const [cmd, cmdArgs, cwd] of actions) {
    await run(cmd, cmdArgs, { cwd })
  }

  const setupExe = path.join(releaseDir, `Pulse-Windows-${nextVersion}-Setup.exe`)
  const blockmap = `${setupExe}.blockmap`
  const latestYml = path.join(releaseDir, 'latest.yml')
  for (const p of [setupExe, blockmap, latestYml]) {
    if (!fs.existsSync(p)) {
      throw new Error(`Missing release artifact: ${p}`)
    }
  }

  await run(
    'gh',
    [
      'release',
      'create',
      tag,
      setupExe,
      blockmap,
      latestYml,
      '--repo',
      RELEASE_REPO,
      '--title',
      `Pulse Desktop ${nextVersion}`,
      '--notes-file',
      notesFilePath,
    ],
    { cwd: root },
  )

  console.log(`Release published: https://github.com/${RELEASE_REPO}/releases/tag/${tag}`)
}

main().catch((e) => {
  console.error(`[release] ${e.message}`)
  process.exit(1)
})


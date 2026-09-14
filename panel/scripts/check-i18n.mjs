import { readFileSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const panelDirectory = dirname(dirname(fileURLToPath(import.meta.url)))
const localePaths = [
  join(panelDirectory, 'i18n/locales/en.json'),
  join(panelDirectory, 'i18n/locales/ar.json'),
]
const locales = localePaths.map(path => JSON.parse(readFileSync(path, 'utf8')))
const failures = []

function valueType(value) {
  if (Array.isArray(value)) return 'array'
  if (value === null) return 'null'
  return typeof value
}

function placeholders(value) {
  return [...value.matchAll(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g)]
    .map(match => match[1])
    .sort()
}

function compareLocales(left, right, key = '') {
  const leftType = valueType(left)
  const rightType = valueType(right)

  if (leftType !== rightType) {
    failures.push(`${key}: English is ${leftType}, Arabic is ${rightType}`)
    return
  }

  if (leftType === 'object') {
    const leftKeys = Object.keys(left).sort()
    const rightKeys = Object.keys(right).sort()
    for (const childKey of new Set([...leftKeys, ...rightKeys])) {
      const childPath = key ? `${key}.${childKey}` : childKey
      if (!(childKey in left)) failures.push(`${childPath}: missing from English`)
      else if (!(childKey in right)) failures.push(`${childPath}: missing from Arabic`)
      else compareLocales(left[childKey], right[childKey], childPath)
    }
    return
  }

  if (leftType === 'array') {
    if (left.length !== right.length) {
      failures.push(`${key}: English has ${left.length} items, Arabic has ${right.length}`)
      return
    }
    left.forEach((value, index) => compareLocales(value, right[index], `${key}.${index}`))
    return
  }

  if (leftType === 'string') {
    const leftPlaceholders = placeholders(left)
    const rightPlaceholders = placeholders(right)
    if (leftPlaceholders.join('|') !== rightPlaceholders.join('|')) {
      failures.push(`${key}: placeholder mismatch (${leftPlaceholders.join(', ')} / ${rightPlaceholders.join(', ')})`)
    }
  }
}

function resolveKey(locale, key) {
  return key.split('.').reduce((value, segment) => value?.[segment], locale)
}

function sourceFiles(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) return sourceFiles(path)
    return /\.(ts|vue)$/.test(entry.name) ? [path] : []
  })
}

compareLocales(locales[0], locales[1])

const files = sourceFiles(join(panelDirectory, 'app'))
const staticTextKeys = new Set()
const dynamicRoots = new Set()
const arrayKeys = new Set()

for (const path of files) {
  const source = readFileSync(path, 'utf8')

  for (const match of source.matchAll(/\bt\(\s*(['"])([^'"]+)\1/g)) {
    staticTextKeys.add(match[2])
  }

  for (const match of source.matchAll(/\bt\(\s*`([^`]+)`/g)) {
    const root = match[1].split('${')[0].replace(/\.$/, '')
    dynamicRoots.add(root)
  }

  for (const match of source.matchAll(/\buseTranslatedArray\(\s*(['"])([^'"]+)\1/g)) {
    arrayKeys.add(match[2])
  }

  if (!path.endsWith('useTranslatedArray.ts') && /\btm\(/.test(source)) {
    failures.push(`${path}: use useTranslatedArray() instead of tm() directly`)
  }
}

for (const key of staticTextKeys) {
  locales.forEach((locale, index) => {
    const value = resolveKey(locale, key)
    if (valueType(value) !== 'string') failures.push(`${key}: text translation missing from ${localePaths[index]}`)
  })
}

for (const root of dynamicRoots) {
  locales.forEach((locale, index) => {
    const value = resolveKey(locale, root)
    if (valueType(value) !== 'object') failures.push(`${root}: dynamic namespace missing from ${localePaths[index]}`)
  })
}

for (const key of arrayKeys) {
  locales.forEach((locale, index) => {
    if (!Array.isArray(resolveKey(locale, key))) failures.push(`${key}: translated array missing from ${localePaths[index]}`)
  })
}

const requiredDynamicKeys = {
  'profile.roles': ['admin', 'hr', 'supervisor', 'employee'],
  'employees.connection': ['online', 'stale', 'offline'],
  'case.statuses': ['pending', 'accepted', 'overdue', 'in_progress', 'completed', 'rejected', 'cancelled'],
  'case.assignmentStatuses': ['unassigned', 'awaiting_acceptance', 'scheduled', 'in_progress', 'completed', 'rejected', 'cancelled', 'overdue'],
  'case.priorities': ['normal', 'high', 'urgent'],
  'employees.histories.reasons': ['gps_disabled', 'network_disabled', 'flight_mode', 'permission_revoked', 'service_interrupted'],
  'cases.detail.events': ['createdNote', 'acceptedNote', 'rejectedNote', 'startedNote', 'overdueNote', 'completedNote', 'cancelledNote'],
}

for (const [root, keys] of Object.entries(requiredDynamicKeys)) {
  locales.forEach((locale, index) => {
    for (const key of keys) {
      if (valueType(resolveKey(locale, `${root}.${key}`)) !== 'string') {
        failures.push(`${root}.${key}: runtime translation missing from ${localePaths[index]}`)
      }
    }
  })
}

if (failures.length > 0) {
  failures.forEach(failure => console.error(`i18n: ${failure}`))
  process.exit(1)
}

console.log(`i18n OK: ${staticTextKeys.size} text keys, ${dynamicRoots.size} dynamic namespaces, ${arrayKeys.size} translated arrays`)

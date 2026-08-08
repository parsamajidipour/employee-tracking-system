import { chromium } from '@playwright/test'

const errors = []
const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
page.on('console', m => { if (m.type() === 'error') errors.push(m.text()) })
page.on('pageerror', e => errors.push('pageerror: ' + e.message))

await page.goto('http://localhost:13000/login', { waitUntil: 'networkidle' })
await page.fill('input[type=email]', process.env.EMAIL)
await page.fill('input[type=password]', process.env.PASS)
await Promise.all([page.waitForURL('**/map').catch(() => {}), page.click('button[type=submit]')])
await page.waitForTimeout(9000)
await page.screenshot({ path: '/tmp/s-map.png' })

for (const [path, name] of [['/employees', 'employees'], ['/shift-templates', 'templates']]) {
  await page.goto('http://localhost:13000' + path, { waitUntil: 'networkidle' })
  await page.waitForTimeout(1800)
  await page.screenshot({ path: `/tmp/s-${name}.png` })
}

console.log('ERRORS:', errors.length ? errors.join('\n  ') : 'none')
await browser.close()

import { chromium } from 'playwright'

const browser = await chromium.launch()
const page = await browser.newPage()
page.on('pageerror', (e) => console.log('[pageerror]', e.message))
page.on('response', (r) => {
  if (r.url().includes('58000')) console.log('[http]', r.status(), r.url())
})

await page.goto('http://localhost:53000/')
await page.waitForLoadState('networkidle')
console.log('[nav] index.vue redirected to:', page.url())

if (new URL(page.url()).pathname !== '/login') {
  console.log('[unexpected] not on /login as expected for a signed-out visitor')
}

await page.waitForSelector('input#field-email')
await page.waitForTimeout(500)
await page.fill('input#field-email', 'test@example.com')
await page.fill('input#field-password', 'password')
await page.click('button[type="submit"]')
await page.waitForFunction(() => location.pathname === '/map', { timeout: 10000 }).catch(() => {})

console.log('[final url]', page.url())
if (new URL(page.url()).pathname === '/login') {
  console.log('[still on login] body:', await page.textContent('body').then((t) => t?.slice(0, 100)))
}

await browser.close()

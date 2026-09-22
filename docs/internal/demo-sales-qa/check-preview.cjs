const { chromium } = require('C:/Users/Lenovo/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const assert = require('assert/strict');
const publicDir = path.resolve(__dirname, '../../public');
const report = { viewports: [], remoteRequestsSent: 0, errors: [], passed: false };
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    const page = await browser.newPage({ reducedMotion: 'reduce' });
    // Only the rendered fixture and local assets are available. No app/database requests.
    await page.route('**/*', async route => {
      const request = route.request();
      const url = new URL(request.url());
      if (url.protocol === 'file:') return route.continue();
      if (['localhost', 'demo.misechoku.jp'].includes(url.hostname) && url.pathname.startsWith('/assets/') && request.method() === 'GET') {
        const file = path.resolve(publicDir, '.' + decodeURIComponent(url.pathname));
        if (file.startsWith(publicDir + path.sep) && fs.existsSync(file)) {
          const ext = path.extname(file);
          const mime = { '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml', '.png': 'image/png' };
          return route.fulfill({ body: fs.readFileSync(file), contentType: mime[ext] || 'application/octet-stream' });
        }
      }
      return route.abort();
    });
    const url = pathToFileURL(path.join(__dirname, 'preview.html')).href;
    for (const [width, height] of [[320, 700], [390, 844], [768, 1024], [820, 1180], [1180, 820]]) {
      await page.setViewportSize({ width, height });
      await page.goto(url);
      await page.locator('h1').waitFor();
      const scope = page.locator('section[aria-label="営業デモの準備"]');
      assert.equal(await scope.locator('[data-sales-demo-form]').count(), 4);
      assert.equal(await scope.locator('select option').count(), 20);
      const dimensions = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth }));
      if (dimensions.scroll > dimensions.width + 1) {
        await page.screenshot({ path: path.join(__dirname, 'overflow.png') });
        report.overflowElements = await page.locator('body *').evaluateAll(nodes => nodes.filter(n => {
          const box = n.getBoundingClientRect();
          return box.width > 0 && box.height > 0 && box.right > innerWidth + 1 && getComputedStyle(n).visibility !== 'hidden';
        }).slice(0, 18).map(n => ({ tag: n.tagName, id: n.id, class: String(n.className), width: n.getBoundingClientRect().width })));
      }
      assert(dimensions.scroll <= dimensions.width + 1, 'Horizontal overflow at ' + width);
      const buttons = await scope.locator('button').evaluateAll(ns => ns.map(n => ({ width: n.getBoundingClientRect().width, height: n.getBoundingClientRect().height })));
      assert(buttons.every(b => b.width >= 44 && b.height >= 44), 'Small action at ' + width);
      report.viewports.push({ width, height, actions: buttons.length, overflow: false });
      if ([390, 820].includes(width)) {
        await page.screenshot({ path: path.join(__dirname, width + '-top.png') });
        await scope.locator('#scene-title').scrollIntoViewIfNeeded();
        await page.screenshot({ path: path.join(__dirname, width + '-scenes.png') });
      }
    }
    // Verify double-tap prevention and restoration after returning, without submitting.
    await page.locator('[data-sales-demo-form]').first().evaluate(form => form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })));
    assert.equal(await page.locator('[data-sales-demo-form] button:disabled').count(), 4);
    await page.evaluate(() => dispatchEvent(new Event('pageshow')));
    assert.equal(await page.locator('[data-sales-demo-form] button:disabled').count(), 0);
    assert.equal(await page.locator('[data-sales-demo-form] button').first().innerText(), 'この場面を用意する');
    report.passed = true;
  } catch (error) { report.errors.push(error.stack); process.exitCode = 1; }
  finally { await browser.close(); fs.writeFileSync(path.join(__dirname, 'results.json'), JSON.stringify(report, null, 2)); console.log(JSON.stringify(report, null, 2)); }
})();

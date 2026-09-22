const { chromium } = require('C:/Users/Lenovo/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const vm = require('vm');
const assert = require('assert/strict');
const output = __dirname;
const htmlPath = path.resolve(__dirname, '../../public-sales/shop-sales-manual.html');
const url = pathToFileURL(htmlPath).href;
const report = { file: htmlPath, checks: [], screenshots: [], pageErrors: [], remoteRequests: [], overflow: [] };
const pass = (name, details) => { report.checks.push({ name, passed: true, details }); console.log('PASS ' + name); };
const writeReport = () => fs.writeFileSync(path.join(output, 'quality-results.json'), JSON.stringify(report, null, 2));
async function shot(page, name, fullPage = true) {
  if (fullPage) { await page.evaluate(() => { window.scrollTo(0, 0); }); await page.evaluate(() => new Promise(requestAnimationFrame)); }
  await page.screenshot({ path: path.join(output, name + '.png'), fullPage });
  report.screenshots.push(name + '.png');
}
async function overflow(page, description) {
  const result = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth }));
  if (result.scroll > result.width + 1) report.overflow.push({ description, ...result });
}
async function go(page, id) {
  const desktopLink = page.locator(`.sidebar .nav-link[href="#${id}"]`);
  if (await desktopLink.isVisible()) await desktopLink.click();
  else {
    await page.locator('#menu-open').click();
    await page.locator(`.menu-link[href="#${id}"]`).click();
  }
  await page.locator(`#${id}`).waitFor({ state: 'visible' });
}
async function openSearch(page) {
  if (await page.locator('#search-dialog').isVisible()) return;
  await page.locator('[data-open-search]:visible').first().click();
  await page.locator('#manual-search').waitFor({ state: 'visible' });
}
async function printManual(page) {
  if (await page.locator('#print-button').isVisible()) await page.locator('#print-button').click();
  else { await page.locator('#menu-open').click(); await page.locator('#menu-print').click(); }
}
async function run() {
  const html = fs.readFileSync(htmlPath, 'utf8');
  assert(!html.includes('\ufffd'), 'Replacement character');
  assert(!html.includes('REMAINING_PANELS') && !html.includes('MANUAL_SCRIPT'));
  for (const [, script] of html.matchAll(/<script>([\s\S]*?)<\/script>/g)) new vm.Script(script);
  pass('UTF-8 / JavaScript syntax / complete content');
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, permissions: ['clipboard-read', 'clipboard-write'], reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', e => report.pageErrors.push(e.message));
    page.on('request', req => { if (/^https?:/i.test(req.url())) report.remoteRequests.push(req.url()); });
    await page.goto(url);
    const structure = await page.evaluate(() => {
      const all = [...document.querySelectorAll('[id]')].map(x => x.id);
      return {
        duplicates: all.filter((id, i) => all.indexOf(id) !== i),
        brokenLinks: [...document.querySelectorAll('a[href^="#"]')].filter(a => !document.getElementById(a.getAttribute('href').slice(1))).map(a => a.outerHTML),
        brokenCopies: [...document.querySelectorAll('[data-copy]')].filter(x => !document.getElementById(x.dataset.copy)).length,
        searchIdsMissing: [...document.querySelectorAll('[data-search-title]')].filter(x => !x.id).length,
        unnamedButtons: [...document.querySelectorAll('button')].filter(x => !x.textContent.trim() && !x.getAttribute('aria-label')).length,
        language: document.documentElement.lang,
        externalAssets: [...document.querySelectorAll('link[href],script[src],img[src],iframe[src]')].length
      };
    });
    assert.deepEqual(structure.duplicates, []); assert.deepEqual(structure.brokenLinks, []);
    assert.equal(structure.brokenCopies, 0); assert.equal(structure.searchIdsMissing, 0);
    assert.equal(structure.unnamedButtons, 0); assert.equal(structure.language, 'ja'); assert.equal(structure.externalAssets, 0);
    pass('Semantic structure / unique IDs / all internal and copy links / no external assets', structure);
    assert.equal(await page.locator('[data-panel]:visible').count(), 1);
    pass('One active chapter on opening');

    const widths = [[1440, 1000], [1366, 1024], [1180, 820], [1024, 768], [820, 1180], [768, 1024], [430, 932], [390, 844], [320, 700], [844, 390]];
    for (const [width, height] of widths) {
      await page.setViewportSize({ width, height });
      for (const id of ['start', 'demo', 'prepare', 'register', 'help', 'after', 'words', 'team']) {
        await go(page, id); await overflow(page, `${width} ${id}`);
        assert.equal(await page.locator('[data-panel]:visible').count(), 1);
        if (id === 'start' && [1440, 820, 390].includes(width)) await shot(page, `${width}-start`);
        if (width === 820 && id === 'register') await shot(page, '820-register');
      }
      await go(page, 'demo');
      for (const mode of ['full', 'short']) {
        await page.locator(`button[data-mode="${mode}"]`).click();
        const total = mode === 'full' ? 7 : 5;
        for (let i = 0; i < total; i++) {
          await page.locator('#step-nav button').nth(i).click();
          await overflow(page, `${width} ${mode}-${i + 1}`);
          assert.equal(await page.locator('#' + mode + '-' + (i + 1)).isVisible(), true);
          assert.equal(await page.locator('.script-step:visible').count(), 1);
          if (width === 820 && mode === 'full' && i === 4) await shot(page, '820-bonus');
          if (width === 390 && mode === 'full' && i === 0) await shot(page, '390-script');
        }
      }
      await page.locator('button[data-mode="full"]').click();
      await page.locator('#step-nav button').first().click();
      await page.locator('#font-toggle').click();
      await overflow(page, `${width} full-1 enlarged`);
      await page.locator('#speech-toggle').click();
      await overflow(page, `${width} full-1 speech enlarged`);
      assert.equal(await page.locator('#full-1 .step-aside').isVisible(), false);
      await page.locator('#speech-toggle').click(); await page.locator('#font-toggle').click();
    }
    assert.deepEqual(report.overflow, []);
    pass('Ten viewport sizes in portrait and landscape, all eight chapters and twelve script scenes, enlarged text and speech view', widths);

    for (const [width, height] of [[320, 700], [390, 844], [844, 390], [820, 1180], [1180, 820]]) {
      await page.setViewportSize({ width, height });
      await go(page, 'demo');
      await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
      const controls = await page.locator('.step-controls').boundingBox();
      const dock = await page.locator('.reader-dock').boundingBox();
      assert(controls.y >= 0 && controls.y + controls.height <= dock.y + 1, 'Controls overlap dock');
      assert(dock.y + dock.height <= height + 1, 'Dock off screen');
      const taps = await page.locator('.reader-dock a,.reader-dock button,.step-controls button').evaluateAll(nodes => nodes.filter(x => !x.disabled).map(x => ({ label: x.textContent, width: x.getBoundingClientRect().width, height: x.getBoundingClientRect().height })));
      assert(taps.every(x => x.width >= 44 && x.height >= 44), 'Small touch target');
      await page.locator('#menu-open').click();
      assert.equal(await page.locator('.menu-link').count(), 8);
      assert.equal(await page.locator('.menu-link[aria-current="page"]').getAttribute('href'), '#demo');
      await page.locator('#menu-dialog [data-close-sheet]').click();
      assert.equal(await page.locator('#menu-open').evaluate(x => x === document.activeElement), true);
      await openSearch(page);
      assert.equal(await page.locator('#manual-search').evaluate(x => getComputedStyle(x).fontSize), '16px');
      await page.locator('#manual-search').fill('写真');
      const sheet = await page.locator('#search-dialog').boundingBox();
      assert(sheet.x >= 0 && sheet.y >= 0 && sheet.x + sheet.width <= width + 1 && sheet.y + sheet.height <= height + 1, 'Search dialog off screen');
      await page.locator('#search-dialog [data-close-sheet]').click();
      if (width === 390) {
        await page.locator('#menu-open').click(); await shot(page, '390-menu', false);
        await page.locator('#menu-dialog [data-close-sheet]').click();
        await openSearch(page); await shot(page, '390-search', false);
        await page.locator('#search-dialog [data-close-sheet]').click();
        await page.locator('.reader-dock a[href="#demo"]').click(); await shot(page, '390-reader', false);
      }
      if (width === 1180) { await page.locator('.reader-dock a[href="#demo"]').click(); await shot(page, '1180-reader', false); }
      if (width === 844) { await page.locator('.reader-dock a[href="#demo"]').click(); await shot(page, '844-landscape', false); }
    }
    pass('Touch navigation, persistent scene buttons, 44px targets, all chapters, modal focus return and search layout');

    const touch = await browser.newContext({ viewport: { width: 1366, height: 1024 }, hasTouch: true, isMobile: true });
    const touchPage = await touch.newPage(); await touchPage.goto(url);
    assert.equal(await touchPage.locator('.reader-dock').isVisible(), true);
    assert.equal(await touchPage.locator('.sidebar').isVisible(), false);
    await touchPage.locator('#menu-open').tap(); await touchPage.locator('.menu-link[href="#register"]').tap();
    assert.equal(await touchPage.locator('#register').isVisible(), true);
    await touchPage.locator('.reader-dock a[href="#demo"]').tap();
    assert.equal(await touchPage.locator('#full-1').isVisible(), true);
    const sceneTop = await touchPage.locator('#full-1').evaluate(el => el.getBoundingClientRect().top);
    assert(sceneTop >= 68 && sceneTop < 120, 'Dock should return to current scene');
    await touchPage.setViewportSize({ width: 390, height: 420 });
    await openSearch(touchPage); await touchPage.locator('#manual-search').fill('登録');
    const reducedSheet = await touchPage.locator('#search-dialog').boundingBox();
    assert(reducedSheet.y + reducedSheet.height <= 420, 'Search should fit a reduced viewport');
    await touch.close(); pass('Large tablet touch mode uses the tablet layout and touch chapter navigation');

    await page.setViewportSize({ width: 820, height: 1180 });
    await go(page, 'demo'); await page.locator('button[data-mode="full"]').click();
    await page.locator('#step-nav button').first().click();
    assert.equal(await page.locator('#step-prev').isDisabled(), true);
    await page.locator('#step-next').click(); assert.equal(await page.locator('#full-2').isVisible(), true);
    await page.locator('#step-prev').click(); assert.equal(await page.locator('#full-1').isVisible(), true);
    await page.locator('#step-nav button').last().click();
    await page.locator('#step-next').click(); assert.equal(await page.locator('#register').isVisible(), true);
    await page.goBack(); assert.equal(await page.locator('#full-7').isVisible(), true);
    await page.reload(); assert.equal(await page.locator('#full-7').isVisible(), true);
    pass('Previous / next / registration handoff / browser back / deep-link reload');

    await openSearch(page); await page.locator('#manual-search').fill('メールが届かない');
    await page.locator('.search-result').first().click();
    assert.equal(await page.locator('#faq-mail').getAttribute('open'), '');
    assert.equal(await page.locator('#help').isVisible(), true);
    const targetTop = await page.locator('#faq-mail summary').evaluate(el => el.getBoundingClientRect().top);
    const headerBottom = await page.locator('.topbar').evaluate(el => el.getBoundingClientRect().bottom);
    assert(targetTop >= headerBottom - 1, 'Search target hidden by sticky header');
    await openSearch(page); await page.locator('#manual-search').fill('存在しない検索語ZZZXCV123');
    assert.match(await page.locator('#search-results').innerText(), /見つかりません/);
    await page.locator('#search-clear').click(); assert.equal(await page.locator('#manual-search').inputValue(), '');
    await page.locator('#manual-search').fill('接客タイプ');
    await page.locator('#manual-search').press('ArrowDown');
    assert.equal(await page.evaluate(() => document.activeElement.classList.contains('search-result')), true);
    await page.keyboard.press('Enter'); assert.equal(await page.locator('#search-results').isVisible(), false);
    await openSearch(page); await page.locator('#manual-search').fill('料金'); await page.locator('#manual-search').press('Escape');
    assert.equal(await page.locator('#search-results').isVisible(), false);
    if (await page.locator('#search-dialog').isVisible()) await page.locator('#search-dialog [data-close-sheet]').click();
    pass('Search results / detail expansion / sticky focus visibility / empty results / keyboard / Escape');

    await go(page, 'help'); await page.locator('#faq-expand').click();
    assert.equal(await page.locator('details.faq[open]').count(), 14);
    await shot(page, '820-help');
    await page.locator('#faq-expand').click(); assert.equal(await page.locator('details.faq[open]').count(), 0);
    pass('All 14 FAQ answers expand and collapse');

    await go(page, 'prepare'); await page.locator('[data-reset-checks="visit"]').click();
    await page.locator('[data-check="visit-1"]').check(); await page.reload();
    assert.equal(await page.locator('[data-check="visit-1"]').isChecked(), true);
    assert.match(await page.locator('#visit-check [data-check-count]').innerText(), /^1 \/ 9/);
    await page.locator('[data-reset-checks="visit"]').click();
    assert.equal(await page.locator('[data-check="visit-1"]').isChecked(), false);
    await go(page, 'register'); await page.locator('[data-check="handoff-2"]').check();
    await page.reload(); assert.equal(await page.locator('[data-check="handoff-2"]').isChecked(), true);
    await page.locator('[data-reset-checks="handoff"]').click();
    pass('Both checklists save, reload, count and reset correctly');

    await go(page, 'start'); await page.locator('[data-copy="mission-copy"]').click();
    assert.match(await page.evaluate(() => navigator.clipboard.readText()), /高額なスカウトバック/);
    pass('Clipboard copy works from the standalone local file');
    await page.evaluate(() => Object.defineProperty(navigator.clipboard, 'writeText', { value: () => Promise.reject(new Error('Test denied')) }));
    await page.locator('[data-copy="mission-copy"]').click();
    assert.equal(await page.locator('#copy-fallback').isVisible(), true);
    assert.match(await page.locator('#copy-text').inputValue(), /採用報酬/);
    await page.locator('#copy-text').press('Shift+Tab'); assert.equal(await page.locator('#copy-close').evaluate(el => el === document.activeElement), true);
    await page.locator('#copy-close').press('Tab'); assert.equal(await page.locator('#copy-text').evaluate(el => el === document.activeElement), true);
    await page.keyboard.press('Escape'); assert.equal(await page.locator('#copy-fallback').isVisible(), false);
    assert.equal(await page.locator('[data-copy="mission-copy"]').evaluate(el => el === document.activeElement), true);
    pass('Denied clipboard fallback, focus trap and focus return');

    const noStorage = await browser.newContext({ viewport: { width: 390, height: 844 } });
    await noStorage.addInitScript(() => { Storage.prototype.setItem = () => { throw new Error('Unavailable for test'); }; });
    const noStoragePage = await noStorage.newPage();
    await noStoragePage.goto(url + '#prepare');
    await noStoragePage.locator('[data-check="visit-1"]').check();
    assert.match(await noStoragePage.locator('#visit-check .storage-note').innerText(), /保存できません/);
    await noStorage.close(); pass('Graceful fallback when local storage is unavailable');

    const noJS = await browser.newContext({ javaScriptEnabled: false });
    const staticPage = await noJS.newPage(); await staticPage.goto(url);
    assert.equal(await staticPage.locator('[data-panel]:visible').count(), 8);
    assert.equal(await staticPage.locator('.script-step:visible').count(), 12);
    await staticPage.locator('#faq-mail summary').click(); assert.equal(await staticPage.locator('#faq-mail .detail-body').isVisible(), true);
    await noJS.close(); pass('Readable without JavaScript, including both scripts and native FAQ disclosure');

    await context.setOffline(true); await page.reload(); assert.equal(await page.locator('#start').isVisible(), true);
    await go(page, 'demo'); assert.equal(await page.locator('.script-step:visible').count(), 1);
    assert.deepEqual(report.remoteRequests, []); pass('Offline file reload and navigation; zero external requests');
    await context.setOffline(false);

    await go(page, 'help');
    await page.evaluate(() => { window.originalPrint = window.print; window.print = () => window.dispatchEvent(new Event('beforeprint')); });
    await printManual(page);
    assert.equal(await page.locator('details:not([open])').count(), 0);
    await page.evaluate(() => { window.dispatchEvent(new Event('afterprint')); window.print = window.originalPrint; });
    pass('Print button preserves expanded answers until asynchronous printing finishes');

    await page.emulateMedia({ media: 'print' });
    await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
    assert.equal(await page.locator('[data-panel]:visible').count(), 8);
    assert.equal(await page.locator('.script-step:visible').count(), 12);
    assert.equal(await page.locator('details:not([open])').count(), 0);
    await shot(page, 'print-preview', false);
    const printBlocks = await page.locator('#main h1,#main h2,#main h3,#main h4,#main p,#main td,#main dd').evaluateAll(nodes => nodes.filter(x => x.getClientRects().length && x.innerText.trim()).map(x => x.innerText.trim()));
    fs.writeFileSync(path.join(output, 'print-text-blocks.json'), JSON.stringify(printBlocks, null, 2));
    await page.pdf({ path: path.join(output, 'print-check.pdf'), preferCSSPageSize: true, printBackground: true });
    await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
    await page.emulateMedia({ media: 'screen' });
    assert.equal(await page.locator('[data-panel]:visible').count(), 1);
    pass('Print includes all chapters, both scripts and all answers; screen state restores');
    assert.deepEqual(report.pageErrors, []); pass('No browser JavaScript errors', report.pageErrors);
    await context.close();
    report.passed = true; writeReport();
  } finally { await browser.close(); }
}
run().catch(e => { report.passed = false; report.failure = e.stack; writeReport(); console.error(e); process.exitCode = 1; });

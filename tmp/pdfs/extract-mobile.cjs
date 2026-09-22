const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require('playwright');

(async () => {
  const root = process.cwd();
  const dir = path.join(root, 'tmp/pdfs');
  fs.mkdirSync(path.join(dir, 'figures'), { recursive: true });
  const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const page = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  await page.goto(pathToFileURL(path.join(root, 'docs/misechoku-service-video-script-review.html')).href);
  await page.evaluate(() => document.fonts.ready);
  const data = await page.evaluate(() => {
    const text = e => e ? e.innerText.trim() : '';
    return {
      intro: text(document.querySelector('.hero-copy')),
      disclosure: [...document.querySelectorAll('.material-note p')].map(e => e.textContent.trim()),
      scenes: [...document.querySelectorAll('.scene')].map(s => {
        const panel = s.querySelector('.script-panel,.concept-content');
        const paths = s.querySelector('.screen-path');
        return {
          id: s.id,
          category: text(s.querySelector('.scene-no')),
          title: text(s.querySelector('.scene-head h2')),
          screen: text(s.querySelector('.screen-name')).replace(/^該当画面：/, ''),
          benefit: text(panel.querySelector('.merit-block p')),
          facts: [...panel.querySelectorAll('.fact-list > li')].map(text),
          details: [...panel.querySelectorAll('.detail-list > div')].map(e => ({ title: text(e.querySelector('dt')), text: text(e.querySelector('dd')) })),
          purpose: text(panel.querySelector('.purpose-note p')),
          conditions: [...panel.querySelectorAll('.condition-note')].map(text),
          paths: paths ? [...paths.querySelectorAll('.path-list li')].map(e => ({ label: text(e.querySelector('span')), code: text(e.querySelector('code')) })) : [],
          pathNotes: paths ? [...paths.querySelectorAll('p')].map(text) : [],
          caption: text(s.querySelector('.figcap')),
          figure: s.querySelector('.phone,.device-pair') ? `figures/${s.id}.png` : null,
          diagramContext: text(s.querySelector('.diagram-context')),
          trail: [...s.querySelectorAll('.trail-steps li')].map(e => ({ title: text(e.querySelector('strong')), text: text(e.querySelector('small')) })),
        };
      })
    };
  });
  // Only the illustrative screens are adjusted. The source HTML stays intact.
  await page.addStyleTag({ content: `
    .phone{width:310px;max-width:100%;box-shadow:none}
    .phone-screen{min-height:390px}
    .visual-panel{padding:18px;background:#17131c}
    .phone .hero-photo,.phone .profile-avatar{height:110px}
    .phone .mock-body{padding:12px}
  ` });
  await page.evaluate(() => {
    for (const el of document.querySelectorAll('.phone *')) {
      if (el.children.length === 0 && el.textContent.trim()) {
        const size = parseFloat(getComputedStyle(el).fontSize);
        if (size < 12) el.style.fontSize = '12px';
      }
    }
  });
  for (const scene of data.scenes) {
    if (!scene.figure) continue;
    const loc = page.locator(`#${scene.id} .phone, #${scene.id} .device-pair`).first();
    scene.figureSize = await loc.boundingBox();
    await loc.screenshot({ path: path.join(dir, scene.figure) });
  }
  // Store the two halves separately, so no comparison needs to be reduced to tiny type.
  for (const world of ['before', 'after']) {
    for (const kind of ['relationship', 'money']) {
      const loc = page.locator(`.world-${world} .${kind}-section`);
      await loc.screenshot({ path: path.join(dir, `figures/${world}-${kind}.png`) });
    }
  }
  fs.writeFileSync(path.join(dir, 'content.json'), JSON.stringify(data, null, 2), 'utf8');
  console.log(JSON.stringify(data.scenes.map(s => ({ id: s.id, figure: s.figureSize && { width: s.figureSize.width, height: s.figureSize.height }, paths: s.paths.length })), null, 2));
  await browser.close();
})();

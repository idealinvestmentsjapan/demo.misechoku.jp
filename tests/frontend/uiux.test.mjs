import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { JSDOM, VirtualConsole } from 'jsdom';

async function page(html, path = '/cast/search/list') {
    const errors = [];
    const console = new VirtualConsole();
    console.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM('<!doctype html><html><body>' + html + '</body></html>', { url: 'https://test.invalid' + path, runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole: console });
    dom.__errors = errors;
    await new Promise(resolve => dom.window.addEventListener('load', resolve, { once: true }));
    const w = dom.window;
    w.scrollTo = () => {};
    w.HTMLElement.prototype.scrollTo = () => {};
    w.HTMLElement.prototype.scrollIntoView = () => {};
    // JSDOMにはレイアウトエンジンがないため、可視性のみを模擬する。
    w.HTMLElement.prototype.getClientRects = function () { return this.closest('[hidden]') ? [] : [{}]; };
    Object.defineProperty(w.HTMLElement.prototype, 'offsetParent', { get() { return this.closest('[hidden]') ? null : this.parentElement; } });
    return dom;
}
function boot(dom, name, instrumentNavigation = false) {
    let source = fs.readFileSync('public/assets/js/' + name + '.js', 'utf8');
    if (instrumentNavigation) source = source.replace('window.location.href = listUrl + query;', 'window.__navigation = listUrl + query;');
    dom.window.eval(source);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
}
function timers(w) {
    let clock = 0, id = 0;
    const queue = new Map();
    w.setTimeout = (fn, delay = 0) => { const key = ++id; queue.set(key, { fn, at: clock + delay }); return key; };
    w.clearTimeout = key => queue.delete(key);
    return function tick(ms) {
        const until = clock + ms;
        while (true) {
            const next = [...queue].filter(([, item]) => item.at <= until).sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            clock = next[1].at; queue.delete(next[0]); next[1].fn();
        }
        clock = until;
    };
}
const settle = () => new Promise(resolve => setImmediate(resolve));
const searchHtml = `<input id="search-keyword" value="銀座"><button id="search-keyword-submit">検索</button><input id="search-sort-current" value="new"><button id="open-detail-search">条件</button>
<div id="detail-search-modal" aria-hidden="true"><form id="detail-search-form"><input type="hidden" name="_token" value="secret"><input type="hidden" name="hourly_wage" value="5000"><input type="hidden" name="reward" value="100000"><input type="hidden" name="current_lat" value="35.6"><input type="hidden" name="current_lng" value="139.7"><input type="radio" name="location_mode" value="current" checked><input type="radio" name="location_mode" value="none"><input type="checkbox" name="industry_ids[]" value="2" checked><input type="checkbox" name="industry_ids[]" value="4" checked><input type="number" name="age_min" value="20"></form><button data-detail-search-reset>クリア</button><button data-detail-search-submit>検索</button></div>`;

test('検索で時給・ボーナス・位置・複数条件を正規ルートへ渡す', async t => {
    const d = await page(searchHtml); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); }); boot(d, 'search-detail', true);
    d.window.document.querySelector('#search-keyword-submit').click();
    const url = new URL(d.window.__navigation, d.window.location.href);
    assert.equal(url.pathname, '/cast/search/list');
    for (const [name, value] of Object.entries({ keyword: '銀座', sort: 'new', hourly_wage: '5000', reward: '100000', current_lat: '35.6', current_lng: '139.7', age_min: '20', filters_applied: '1' })) assert.equal(url.searchParams.get(name), value);
    assert.deepEqual(url.searchParams.getAll('industry_ids[]'), ['2', '4']);
    assert.equal(url.searchParams.has('_token'), false);
});
test('条件クリアで金額・タグを解除し、日本語の変換確定では検索しない', async t => {
    const d = await page(searchHtml); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); }); boot(d, 'search-detail', true);
    const doc = d.window.document;
    doc.querySelector('#search-keyword').dispatchEvent(new d.window.KeyboardEvent('keydown', { key: 'Enter', isComposing: true, bubbles: true }));
    assert.equal(d.window.__navigation, undefined);
    doc.querySelector('[data-detail-search-reset]').click();
    assert.equal(doc.querySelector('[name="hourly_wage"]').value, '');
    assert.equal(doc.querySelector('[name="reward"]').value, '');
    assert.equal(doc.querySelectorAll('input[type="checkbox"]:checked').length, 0);
    assert.equal(doc.querySelector('[name="location_mode"]:checked').value, 'none');
    assert.equal(doc.querySelector('[name="age_min"]').value, '');
    doc.querySelector('#search-keyword-submit').click();
    const url = new URL(d.window.__navigation, d.window.location.href);
    assert.equal(url.searchParams.has('current_lat'), false);
    assert.equal(url.searchParams.has('current_lng'), false);
});
const registerHtml = `<form class="register-form"><section class="register-card"><div class="register-card-head"><h2>アカウント</h2></div><label class="register-field"><span>メール<em>必須</em></span><input name="email" type="email"></label><label class="register-field"><span>パスワード<em>必須</em></span><input name="password" type="password"></label><input name="password_confirmation" type="password"></section><section class="register-card"><div class="register-card-head"><h2>プロフィール</h2></div><label class="register-field"><span>名前<em>必須</em></span><input name="nickname"></label></section><section class="register-card register-card-compact"><input name="terms" type="checkbox"></section><div class="register-actions"><button class="register-submit">登録</button></div></form>`;
test('表示中のパスワードを保存せず、古いドラフトからも取り除く', async t => {
    const d = await page(registerHtml, '/cast/register'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    d.window.document.body.className = 'page-auth-register-cast';
    d.window.sessionStorage.setItem('register-form-draft-v2:cast', JSON.stringify({ ts: Date.now(), data: { password: 'dummy-secret', password_confirmation: 'dummy-secret', email: 'dummy@example.invalid' } }));
    boot(d, 'register-wizard');
    assert.equal(JSON.parse(d.window.sessionStorage.getItem('register-form-draft-v2:cast')).data.password, undefined);
    const doc = d.window.document;
    doc.querySelector('.rw-pass-toggle').click();
    const password = doc.querySelector('[name="password"]');
    assert.equal(password.type, 'text');
    password.value = 'new-dummy-secret';
    password.dispatchEvent(new d.window.Event('input', { bubbles: true }));
    const stored = JSON.parse(d.window.sessionStorage.getItem('register-form-draft-v2:cast')).data;
    assert.equal(stored.password, undefined); assert.equal(stored.password_confirmation, undefined);
});
test('登録エラー後も該当ステップへ移り、IME確定でフォーカスを移動しない', async t => {
    const d = await page(registerHtml, '/cast/register'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    const doc = d.window.document;
    doc.querySelector('form').dataset.validationErrors = JSON.stringify({ nickname: ['名前を確認してください'] });
    doc.querySelector('form').insertAdjacentHTML('afterbegin', '<div class="register-alert-error">名前を確認してください</div>');
    boot(d, 'register-wizard');
    assert.ok(doc.querySelector('.rw-nav'));
    assert.equal(doc.querySelector('[name="nickname"]').closest('.register-card').hidden, false);
    assert.equal(doc.querySelector('[name="email"]').closest('.register-card').hidden, true);
    const input = doc.querySelector('[name="nickname"]'); input.focus();
    input.dispatchEvent(new d.window.KeyboardEvent('keydown', { key: 'Enter', isComposing: true, bubbles: true }));
    assert.equal(doc.activeElement, input);
    assert.equal(input.getAttribute('aria-invalid'), 'true');
});
test('登録送信時、以前のステップの未入力を検出する', async t => {
    const d = await page(registerHtml); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); }); boot(d, 'register-wizard');
    const event = new d.window.Event('submit', { bubbles: true, cancelable: true });
    d.window.document.querySelector('form').dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(d.window.document.querySelector('[name="email"]').closest('.register-card').hidden, false);
});
const aiHtml = `<section data-ai-chat-root data-endpoint="/cast/search/ai-chat" data-area-options='["大阪府 大阪市","福岡県 福岡市"]'><header class="ai-chat__header"></header><div data-ai-thread></div><div data-ai-quick-replies></div></section>`;
test('AI診断は地域に対応し、連打で質問を飛ばさず前問に戻れる', async t => {
    const d = await page(aiHtml); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); }); const tick = timers(d.window); boot(d, 'ai-chat'); tick(300);
    const doc = d.window.document;
    const choice = doc.querySelector('.ai-chat__quick'); assert.equal(choice.textContent, '大阪府 大阪市');
    choice.click(); choice.click(); tick(250);
    assert.equal(doc.querySelectorAll('.ai-chat__msg--user').length, 1);
    assert.ok(doc.querySelector('[data-ai-thread]').textContent.includes('Q2/5'));
    [...doc.querySelectorAll('.ai-chat__quick')].find(el => el.textContent === '前の質問に戻る').click();
    assert.equal(doc.querySelector('.ai-chat__quick').textContent, '大阪府 大阪市');
});
test('AIの通信失敗後に5問の回答を維持して再試行できる', async t => {
    const d = await page(aiHtml); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); }); const tick = timers(d.window); const bodies = [];
    d.window.fetch = async (_url, options) => { bodies.push(options.body); throw new Error('offline'); };
    boot(d, 'ai-chat'); tick(300);
    for (let i = 0; i < 5; i++) { d.window.document.querySelector('.ai-chat__quick').click(); tick(250); }
    await settle(); tick(500);
    [...d.window.document.querySelectorAll('.ai-chat__quick')].find(el => el.textContent === '同じ条件で再試行する').click();
    await settle(); assert.equal(bodies.length, 2); assert.equal(bodies[0], bodies[1]);
});
test('モーダルを開くとフォーカスを移し、Tabで閉じ込め、閉じると戻す', async t => {
    const d = await page('<main id="background"><button id="open">開く</button></main><div id="dialog" role="dialog" aria-modal="true" hidden><button id="first">閉じる</button><button id="last">保存</button></div>'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    const doc = d.window.document; doc.querySelector('#open').focus(); boot(d, 'ux-accessibility');
    doc.querySelector('#dialog').hidden = false; await settle();
    assert.equal(doc.activeElement.id, 'first'); assert.equal(doc.querySelector('#background').inert, true);
    doc.querySelector('#last').focus();
    doc.dispatchEvent(new d.window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
    assert.equal(doc.activeElement.id, 'first');
    doc.querySelector('#dialog').hidden = true; await settle(); assert.equal(doc.activeElement.id, 'open');
});
test('エラートーストは時間で消えず、閉じるボタンで終了する', async t => {
    const d = await page(''); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); }); const tick = timers(d.window); boot(d, 'app-toast');
    d.window.appToast('入力を確認してください', 'error'); tick(60000);
    assert.ok(d.window.document.querySelector('.app-toast').classList.contains('is-visible'));
    d.window.document.querySelector('.app-toast button').click();
    assert.equal(d.window.document.querySelector('.app-toast').classList.contains('is-visible'), false);
});
const talkHtml = `<div id="chat-messages"></div><form id="chat-form" data-url="/cast/talk/send" data-partner-id="s-test"><input name="_token" value="dummy" type="hidden"><textarea name="message"></textarea><button type="submit" id="talk-send-btn">送信</button></form>`;
test('トーク通信失敗後に本文と送信IDを維持し、再試行成功時だけ消す', async t => {
    const d = await page(talkHtml, '/cast/talk/room/s-test'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    d.window.document.body.dataset.navigationScope = 'cast:c-test'; d.window.isCastTalkRoom = true;
    const sent = [];
    d.window.fetch = async (_url, options) => { sent.push(JSON.parse(options.body)); if (sent.length === 1) throw new Error('offline'); return { ok: true, json: async () => ({ success: true, data: { message_id: 11, time: '12:00' } }) }; };
    boot(d, 'talk-room');
    const doc = d.window.document, input = doc.querySelector('textarea'); input.value = 'テストの文章';
    doc.querySelector('form').dispatchEvent(new d.window.Event('submit', { cancelable: true })); await settle();
    assert.equal(input.value, 'テストの文章');
    assert.ok(d.window.sessionStorage.getItem('talk-draft-v1:cast:c-test:s-test').includes('テストの文章'));
    doc.querySelector('.talk-delivery-notice .btn-secondary-cta').click(); await settle();
    assert.equal(sent[0].client_request_id, sent[1].client_request_id); assert.equal(input.value, '');
    assert.equal(doc.querySelectorAll('.message-row').length, 1); assert.equal(doc.querySelector('.msg-status').textContent, '送信済み');
});
test('Service WorkerはAPIを横取りせず、オフラインでページに専用画面を返す', async () => {
    const handlers = {}, stored = { offline: true }; let fallbackKey;
    const context = { self: { addEventListener: (name, fn) => handlers[name] = fn, location: { origin: 'https://test.invalid' } }, URL, Response,
        fetch: async () => { throw new Error('offline'); }, caches: { open: async () => ({ match: async key => { fallbackKey = key; return stored; } }) } };
    vm.runInNewContext(fs.readFileSync('public/sw.js', 'utf8'), context);
    let result;
    handlers.fetch({ request: { url: 'https://test.invalid/api/messages', method: 'GET' }, respondWith: () => { throw new Error('API must bypass cache'); } });
    handlers.fetch({ request: { url: 'https://test.invalid/cast/home', method: 'GET', mode: 'navigate' }, respondWith: value => result = value });
    assert.equal(await result, stored); assert.equal(fallbackKey, '/offline.html');
    handlers.fetch({ request: { url: 'https://test.invalid/assets/photo.png', method: 'GET' }, respondWith: value => result = value });
    await result; assert.notEqual(fallbackKey, '/offline.html');
});

for (const httpStatus of [419, 422, 500]) {
    test('トークのHTTP ' + httpStatus + '応答でも本文を失わない', async t => {
        const d = await page(talkHtml, '/cast/talk/room/s-test'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
        d.window.fetch = async () => ({ ok: false, status: httpStatus, json: async () => ({ message: '確認用エラー' }) });
        boot(d, 'talk-room');
        const input = d.window.document.querySelector('textarea'); input.value = '失いたくない文章';
        d.window.document.querySelector('form').dispatchEvent(new d.window.Event('submit', { cancelable: true })); await settle();
        assert.equal(input.value, '失いたくない文章'); assert.equal(input.readOnly, false);
        assert.equal(d.window.document.querySelector('.talk-delivery-notice').hidden, false);
        assert.equal(!!d.window.document.querySelector('.talk-delivery-notice .btn-secondary-cta'), httpStatus === 500);
    });
}
test('詳細の戻るリンクは同じアカウントの検索条件を保持し、外部URLを拒否する', async t => {
    const d = await page('<header id="global-header"><a class="btn-back" href="/cast/search/list">戻る</a></header>', '/cast/shopprofile/s1'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    d.window.document.body.dataset.navigationScope = 'cast:one';
    const to = d.window.location.href;
    d.window.sessionStorage.setItem('misechoku-navigation-v1:cast:one', JSON.stringify({ from: 'https://test.invalid/cast/search/list?keyword=銀座&page=2', to, y: 900, ts: Date.now() }));
    boot(d, 'ux-accessibility');
    assert.equal(new URL(d.window.document.querySelector('.btn-back').href).searchParams.get('page'), '2');
    d.window.document.querySelector('.btn-back').href = '/cast/search/list';
    d.window.sessionStorage.setItem('misechoku-navigation-v1:cast:one', JSON.stringify({ from: 'https://external.invalid/cast/search/list', to, ts: Date.now() }));
    d.window.dispatchEvent(new d.window.Event('pageshow'));
    assert.equal(d.window.document.querySelector('.btn-back').href, 'https://test.invalid/cast/search/list');
});
test('入力メーターは必須・任意を分け、非表示の項目を数えない', async t => {
    const d = await page('<form data-completion-meter><label>必須<input required name="name" value="記入済み"></label><label>任意<input name="comment"></label><div hidden><input name="hidden_detail" required></div></form>'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    boot(d, 'form-enhance');
    assert.equal(d.window.document.querySelector('[data-meter-value]').textContent, '必須 1 / 1・任意 0 / 1');
});
test('変更ガードは送信が検証で中断された場合に解除されない', async t => {
    const d = await page('<form data-form-guard><input name="name"></form>'); t.after(() => { assert.deepEqual(d.__errors, []); d.window.close(); });
    boot(d, 'form-enhance'); const form = d.window.document.querySelector('form');
    form.dispatchEvent(new d.window.Event('input', { bubbles: true }));
    form.addEventListener('submit', event => event.preventDefault());
    form.dispatchEvent(new d.window.Event('submit', { cancelable: true })); await settle();
    const unload = new d.window.Event('beforeunload', { cancelable: true }); d.window.dispatchEvent(unload);
    assert.equal(unload.defaultPrevented, true);
});

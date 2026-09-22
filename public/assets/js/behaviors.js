/* =============================================================
   behaviors.js — data属性駆動の共通インタラクション（素スクリプト版 / B運用）
   バンドラなし。<script src="{{ asset('assets/js/behaviors.js') }}" defer> で読む。
   画面側は「対象に data 属性を置くだけ」。挙動は全画面でここに一本化。
   ============================================================= */
(function () {
  'use strict';

  /* ① スクロールでヘッダーをグラス化
     使い方: スクロール領域に data-scroll-area、ヘッダーに data-scroll-reveal */
  function initScrollReveal() {
    var headers = document.querySelectorAll('[data-scroll-reveal]');
    if (!headers.length) return;
    var area = document.querySelector('[data-scroll-area]') || window;
    var top = function () { return area === window ? window.scrollY : area.scrollTop; };
    var onScroll = function () {
      var s = top() > 20;
      headers.forEach(function (h) { h.classList.toggle('is-scrolled', s); });
    };
    (area === window ? window : area).addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ② タブ切替
     使い方: 親に data-tabs、ボタンに data-tab="x"、表示面に data-tab-panel="x" */
  function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach(function (group) {
      var buttons = group.querySelectorAll('[data-tab]');
      var scope = group.closest('[data-tabs-scope]') || group.parentElement || document;
      var panels = scope.querySelectorAll('[data-tab-panel]');
      var activate = function (id) {
        buttons.forEach(function (b) { b.classList.toggle('is-active', b.dataset.tab === id); });
        panels.forEach(function (p) { p.classList.toggle('is-active', p.dataset.tabPanel === id); });
      };
      buttons.forEach(function (b) { b.addEventListener('click', function () { activate(b.dataset.tab); }); });
      var init = group.querySelector('[data-tab].is-active') || buttons[0];
      if (init) activate(init.dataset.tab);
    });
  }

  /* ③ スワイプデッキ（ドラッグ＆ボタン両対応）
     使い方: 親 data-swipe-deck / カード data-swipe-card /
             操作ボタン data-swipe-action="like|nope|super"
     結果は 'swipe' CustomEvent(detail:{action, card}) を deck に発火 */
  function initSwipeDecks() {
    document.querySelectorAll('[data-swipe-deck]').forEach(function (deck) {
      var topCard = function () { return deck.querySelector('[data-swipe-card]:last-child'); };
      var start = null, card = null;

      var fly = function (c, action) {
        if (!c) return;
        var dir = action === 'nope' ? -1 : 1;
        var x = action === 'super' ? 0 : dir * (window.innerWidth + 200);
        var y = action === 'super' ? -(window.innerHeight + 200) : -40;
        c.classList.add('is-leaving');
        c.style.transform = 'translate(' + x + 'px,' + y + 'px) rotate(' + (dir * 18) + 'deg)';
        c.style.opacity = '0';
        deck.dispatchEvent(new CustomEvent('swipe', { detail: { action: action, card: c } }));
        setTimeout(function () { c.remove(); }, 300);
      };

      deck.addEventListener('pointerdown', function (e) {
        card = topCard();
        if (!card || !card.contains(e.target)) return;
        start = { x: e.clientX, y: e.clientY };
        if (card.setPointerCapture) card.setPointerCapture(e.pointerId);
      });
      deck.addEventListener('pointermove', function (e) {
        if (!start || !card) return;
        var dx = e.clientX - start.x, dy = e.clientY - start.y;
        card.style.transform = 'translate(' + dx + 'px,' + dy + 'px) rotate(' + (dx * 0.05) + 'deg)';
      });
      deck.addEventListener('pointerup', function (e) {
        if (!start || !card) return;
        var dx = e.clientX - start.x;
        if (Math.abs(dx) > 110) fly(card, dx > 0 ? 'like' : 'nope');
        else card.style.transform = '';
        start = null; card = null;
      });

      deck.querySelectorAll('[data-swipe-action]').forEach(function (btn) {
        btn.addEventListener('click', function () { fly(topCard(), btn.dataset.swipeAction); });
      });
    });
  }

  /* ④ FAB: 押下で 'fab' CustomEvent を document に発火 */
  function initFab() {
    document.querySelectorAll('[data-fab]').forEach(function (fab) {
      fab.addEventListener('click', function () {
        document.dispatchEvent(new CustomEvent('fab', { detail: { el: fab } }));
      });
    });
  }

  /* ⑤ メッセージ送信（楽観的追加 + 'message:send' 発火）
     data-message-form 内に data-message-list / data-message-input / data-message-send、
     複製元 <template data-message-template>（中身は自分の吹き出し） */
  function initMessageForms() {
    document.querySelectorAll('[data-message-form]').forEach(function (form) {
      var list = form.querySelector('[data-message-list]');
      var input = form.querySelector('[data-message-input]');
      var tpl = form.querySelector('template[data-message-template]');
      var send = function () {
        var text = (input && input.value ? input.value : '').trim();
        if (!text) return;
        if (list && tpl) {
          var node = tpl.content.firstElementChild.cloneNode(true);
          node.textContent = text; // 吹き出しの class は Blade 側に存在 → purge安全
          list.appendChild(node);
          list.scrollTop = list.scrollHeight;
        }
        form.dispatchEvent(new CustomEvent('message:send', { detail: { text: text } }));
        if (input) input.value = '';
      };
      var sendBtn = form.querySelector('[data-message-send]');
      if (sendBtn) sendBtn.addEventListener('click', send);
      if (input) input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) { e.preventDefault(); send(); }
      });
    });
  }

  function initBehaviors() {
    initScrollReveal();
    initTabs();
    initSwipeDecks();
    initFab();
    initMessageForms();
    initNumberSteppers();
  }

  /**
   * 数値入力の ± ステッパー
   *   <div class="metric-input-wrap" data-stepper data-step="1">
   *     <input type="number" min="130" max="200" ...>
   *     <span class="metric-unit">cm</span>
   *   </div>
   * → JS が - と + ボタンを注入し、input の min/max を尊重して増減。
   * data-step が無ければ 1、data-step="100" のように任意単位も可。
   * オートリピート（長押しで加速）対応。
   */
  function initNumberSteppers() {
    var wraps = document.querySelectorAll('.metric-input-wrap[data-stepper]');
    Array.prototype.forEach.call(wraps, function (wrap) {
      if (wrap.__stepperBound) return;
      wrap.__stepperBound = true;

      var input = wrap.querySelector('input');
      if (!input) return;

      var step = parseFloat(wrap.getAttribute('data-step') || input.getAttribute('step') || '1');
      if (!isFinite(step) || step <= 0) step = 1;
      var min  = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : null;
      var max  = input.hasAttribute('max') ? parseFloat(input.getAttribute('max')) : null;

      var mkBtn = function (dir) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'metric-step metric-step--' + (dir < 0 ? 'minus' : 'plus');
        b.setAttribute('aria-label', dir < 0 ? '減らす' : '増やす');
        b.textContent = dir < 0 ? '−' : '＋';
        return b;
      };

      var apply = function (delta) {
        var curRaw = (input.value || '').toString().replace(/[^\d.-]/g, '');
        var cur = parseFloat(curRaw);
        if (!isFinite(cur)) {
          cur = (min !== null && isFinite(min)) ? min : 0;
        }
        var next = cur + delta;
        if (min !== null && next < min) next = min;
        if (max !== null && next > max) next = max;
        // 整数ステップなら整数に丸める
        if (Number.isInteger(step)) next = Math.round(next);
        input.value = String(next);
        input.dispatchEvent(new Event('input',  { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      };

      var minusBtn = mkBtn(-1);
      var plusBtn  = mkBtn(1);

      // オートリピート（120ms 間隔、300ms 後に加速）
      function attachHold(btn, dir) {
        var timer = null;
        var accel = null;
        var start = function (e) {
          e.preventDefault();
          apply(dir * step);
          accel = setTimeout(function () {
            timer = setInterval(function () { apply(dir * step); }, 90);
          }, 320);
        };
        var stop = function () {
          if (accel)  { clearTimeout(accel);  accel = null; }
          if (timer)  { clearInterval(timer); timer = null; }
        };
        btn.addEventListener('pointerdown', start);
        btn.addEventListener('pointerup',   stop);
        btn.addEventListener('pointerleave',stop);
        btn.addEventListener('pointercancel',stop);
      }
      attachHold(minusBtn, -1);
      attachHold(plusBtn,   1);

      // input の左に - 、右に + を挿入（.metric-unit があればその手前）
      wrap.insertBefore(minusBtn, input);
      var unit = wrap.querySelector('.metric-unit');
      if (unit) wrap.insertBefore(plusBtn, unit.nextSibling);
      else wrap.appendChild(plusBtn);
    });
  }

  if (document.readyState !== 'loading') initBehaviors();
  else document.addEventListener('DOMContentLoaded', initBehaviors);
})();

/**
 * ミセチョク — AI コンシェルジュ（QA 診断方式）
 *
 * いくつかの質問に選択肢で答えると、回答をまとめて /cast/search/ai-chat に送信し、
 * 自分に合う店舗のレコメンドカードを表示する。
 * 5問の選択式診断。途中の回答変更と同じ条件での通信再試行に対応。
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatReply(text) {
        return escapeHtml(text || '').replace(/\n/g, '<br>');
    }

    function getCsrf() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-ai-chat-root]');
        if (!root) return;

        var endpoint = root.getAttribute('data-endpoint');
        var avatar = root.getAttribute('data-avatar') || '';
        var personalityType = (root.getAttribute('data-personality-type') || '').trim();
        var thread = root.querySelector('[data-ai-thread]');
        var quickReplyArea = root.querySelector('[data-ai-quick-replies]');

        var isBusy = false;
        var isAnswering = false;
        var nextQuestionTimer = null;

        // ------------------------------------------------------------
        // QA 診断フロー定義
        // ------------------------------------------------------------
        var QA_FLOW = [
            { q: 'まずはエリア！どのあたりで働きたい？', opts: ['六本木', '新宿・歌舞伎町', '渋谷', '銀座', 'エリアは問わない'] },
            { q: '気になる業種はある？', opts: ['キャバクラ', 'ラウンジ', 'ガールズバー', 'スナック', 'こだわらない'] },
            { q: '希望の時給はどれくらい？', opts: ['時給3,000円以上', '時給4,000円以上', '時給5,000円以上', 'こだわらない'] },
            { q: 'ナイトワークの経験は？', opts: ['未経験', '少しだけ経験あり', '経験豊富'] },
            { q: '最後に、いちばん重視したいポイントは？', opts: ['高収入', 'ノルマなし', '自由出勤', '未経験サポート'] }
        ];
        var qaIndex = -1;
        var qaAnswers = [];
        try {
            var areas = JSON.parse(root.getAttribute('data-area-options') || '[]');
            if (Array.isArray(areas) && areas.length) QA_FLOW[0].opts = areas.concat(['エリアは問わない']);
        } catch (e) { /* Keep the displayed fallback choices. */ }

        // ------------------------------------------------------------
        // 描画ヘルパ
        // ------------------------------------------------------------
        function scrollToBottom(smooth) {
            window.setTimeout(function () {
                if (smooth && thread.scrollTo) {
                    thread.scrollTo({ top: thread.scrollHeight, behavior: 'smooth' });
                } else {
                    thread.scrollTop = thread.scrollHeight;
                }
            }, 30);
        }

        function appendUser(text) {
            var div = document.createElement('div');
            div.className = 'ai-chat__msg ai-chat__msg--user';
            div.innerHTML = '<div class="ai-chat__bubble ai-chat__bubble--user">' + escapeHtml(text) + '</div>';
            thread.appendChild(div);
            scrollToBottom();
        }

        function appendTyping() {
            var div = document.createElement('div');
            div.className = 'ai-chat__msg ai-chat__msg--ai ai-chat__msg--typing';
            div.setAttribute('aria-live', 'polite');
            div.setAttribute('aria-label', 'AI が返信を作成しています');
            div.innerHTML =
                '<div class="ai-chat__avatar">' +
                (avatar ? '<img src="' + escapeHtml(avatar) + '" alt="">' : '<i class="fas fa-robot"></i>') +
                '</div>' +
                '<div class="ai-chat__bubble ai-chat__bubble--ai">' +
                '  <span class="ai-chat__dot"></span>' +
                '  <span class="ai-chat__dot"></span>' +
                '  <span class="ai-chat__dot"></span>' +
                '</div>';
            thread.appendChild(div);
            scrollToBottom();
            return div;
        }

        function appendAi(text, opts) {
            var div = document.createElement('div');
            div.className = 'ai-chat__msg ai-chat__msg--ai';
            var source = opts && opts.source ? opts.source : '';
            var badge = source === 'llm'
                ? '<span class="ai-chat__source" title="オープンソース LLM で生成"><i class="fas fa-microchip"></i> AI</span>'
                : '';
            div.innerHTML =
                '<div class="ai-chat__avatar">' +
                (avatar ? '<img src="' + escapeHtml(avatar) + '" alt="">' : '<i class="fas fa-robot"></i>') +
                '</div>' +
                '<div class="ai-chat__bubble ai-chat__bubble--ai">' +
                '  <span class="ai-chat__body" data-ai-body></span>' +
                (badge ? ('<span class="ai-chat__meta">' + badge + '</span>') : '') +
                '</div>';
            thread.appendChild(div);

            var target = div.querySelector('[data-ai-body]');
            if (opts && opts.instant) {
                target.innerHTML = formatReply(text);
                scrollToBottom();
            } else {
                revealTextGradually(target, text);
            }
        }

        function revealTextGradually(el, text) {
            var chars = String(text || '').split('');
            var i = 0;
            var chunk = Math.max(1, Math.floor(chars.length / 40));
            (function step() {
                var slice = chars.slice(0, Math.min(i + chunk, chars.length)).join('');
                el.innerHTML = formatReply(slice);
                if (i < chars.length) {
                    i += chunk;
                    scrollToBottom();
                    window.setTimeout(step, 18);
                } else {
                    scrollToBottom(true);
                }
            })();
        }

        function appendCards(recs) {
            if (!recs || !recs.length) return;
            var wrap = document.createElement('div');
            wrap.className = 'ai-chat__cards';

            recs.forEach(function (r) {
                var area = [r.pref, r.city].filter(Boolean).join(' ');
                var wage = r.wage ? '時給 ' + r.wage.toLocaleString() + '円〜' : '';
                var reward = r.reward ? '採用報酬 ' + r.reward.toLocaleString() + '円' : '';
                var meta = [area, wage, reward].filter(Boolean).join(' / ');

                wrap.insertAdjacentHTML('beforeend',
                    '<a href="' + escapeHtml(r.url) + '" class="ai-chat__card">' +
                    '  <div class="ai-chat__card-thumb">' +
                    '    <img src="' + escapeHtml(r.image) + '" alt="' + escapeHtml(r.name || '') + '" loading="lazy">' +
                    '  </div>' +
                    '  <div class="ai-chat__card-body">' +
                    '    <p class="ai-chat__card-title">' + escapeHtml(r.name) + '</p>' +
                    '    <p class="ai-chat__card-meta">' + escapeHtml(meta) + '</p>' +
                    '    <p class="ai-chat__card-reason"><i class="fas fa-wand-magic-sparkles"></i> ' + escapeHtml(r.reason || '') + '</p>' +
                    '  </div>' +
                    '  <span class="ai-chat__card-cta">求人を見る →</span>' +
                    '</a>'
                );
            });

            thread.appendChild(wrap);
            scrollToBottom();
        }

        // 選択肢ボタン：[{label, onClick}] または文字列（文字列は無視せずそのままラベル+ハンドラ必須のため非推奨）
        function renderChoices(items) {
            if (!quickReplyArea) return;
            quickReplyArea.innerHTML = '';
            if (!items || !items.length) return;
            items.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ai-chat__quick';
                btn.textContent = item.label;
                btn.addEventListener('click', function () {
                    if (isBusy) return;
                    item.onClick(item.label);
                });
                quickReplyArea.appendChild(btn);
            });
        }

        // ------------------------------------------------------------
        // QA フロー進行
        // ------------------------------------------------------------
        function askNext() {
            nextQuestionTimer = null;
            qaIndex++;
            if (qaIndex >= QA_FLOW.length) {
                finishQa();
                return;
            }
            var step = QA_FLOW[qaIndex];
            isAnswering = false;
            appendAi('Q' + (qaIndex + 1) + '/' + QA_FLOW.length + '　' + step.q, { instant: qaIndex > 0 });
            var choices = step.opts.map(function (opt) {
                return { label: opt, onClick: handleAnswer };
            });
            if (qaIndex > 0) choices.push({ label: '前の質問に戻る', onClick: previousQuestion });
            renderChoices(choices);
        }

        function handleAnswer(label) {
            if (isBusy || isAnswering) return;
            isAnswering = true;
            renderChoices([]);
            appendUser(label);
            qaAnswers.push(label);
            nextQuestionTimer = window.setTimeout(askNext, 250);
        }

        function previousQuestion() {
            if (isBusy || isAnswering || qaIndex < 1) return;
            window.clearTimeout(nextQuestionTimer);
            qaAnswers.pop();
            qaIndex -= 2;
            appendAi('前の回答を変更できます。', { instant: true });
            askNext();
        }

        function restartQa() {
            window.clearTimeout(nextQuestionTimer);
            isAnswering = true;
            qaIndex = -1;
            qaAnswers = [];
            renderChoices([]);
            appendAi('もう一度診断するね！✨', { instant: true });
            nextQuestionTimer = window.setTimeout(askNext, 250);
        }

        function finishQa() {
            var parts = qaAnswers.filter(function (t) {
                return !/こだわらない|問わない/.test(t);
            });
            if (personalityType) parts.push('接客タイプ' + personalityType);
            var msg = (parts.length ? parts.join(' ') : 'おすすめ') + ' に合うお店を探して';

            appendAi('ありがとう✨ 回答に合わせてピッタリのお店を探すね！', { instant: true });
            fetchRecommendation(msg);
        }

        // ------------------------------------------------------------
        // サーバへ送信（QA 回答のまとめ）
        // ------------------------------------------------------------
        function fetchRecommendation(msg) {
            if (isBusy) return;
            isBusy = true;
            renderChoices([]);

            var typingEl = appendTyping();
            var minWait = 700 + Math.floor(Math.random() * 400);
            var startedAt = Date.now();
            var controller = new AbortController();
            var timeout = window.setTimeout(function () { controller.abort(); }, 30000);

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                credentials: 'same-origin',
                signal: controller.signal,
                body: JSON.stringify({ message: msg, history: [] }),
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('AI chat failed: ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    var wait = Math.max(0, minWait - (Date.now() - startedAt));
                    window.setTimeout(function () {
                        if (typingEl && typingEl.parentNode) typingEl.parentNode.removeChild(typingEl);
                        appendAi(data.reply || '', { source: data.source || '' });
                        appendCards(data.recommendations || []);
                        renderChoices([{ label: '条件を変えて診断する', onClick: restartQa }]);
                        isBusy = false;
                    }, wait);
                })
                .catch(function () {
                    window.setTimeout(function () {
                        if (typingEl && typingEl.parentNode) typingEl.parentNode.removeChild(typingEl);
                        appendAi('ごめん、いま少し繋がりにくいみたい💦 もう一度試してみてね。', { instant: true });
                        renderChoices([
                            { label: '同じ条件で再試行する', onClick: function () { fetchRecommendation(msg); } },
                            { label: '条件を変更する', onClick: restartQa },
                            { label: '通常の検索で探す', onClick: function () { window.location.href = '/cast/search/list'; } }
                        ]);
                        isBusy = false;
                    }, 500);
                }).finally(function () { window.clearTimeout(timeout); });
        }

        // ------------------------------------------------------------
        // リセットボタン（ヘッダー右）
        // ------------------------------------------------------------
        function injectResetButton() {
            if (root.querySelector('[data-ai-reset]')) return;
            var head = root.querySelector('.ai-chat__header');
            if (!head) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ai-chat__reset';
            btn.setAttribute('data-ai-reset', '');
            btn.setAttribute('aria-label', '診断をやり直す');
            btn.innerHTML = '<i class="fas fa-arrow-rotate-left"></i>';
            btn.title = '診断をやり直す';
            btn.addEventListener('click', function () {
                if (isBusy) return;
                window.clearTimeout(nextQuestionTimer);
                isAnswering = true;
                renderChoices([]);
                thread.innerHTML = '';
                qaIndex = -1;
                qaAnswers = [];
                initialGreeting();
            });
            head.appendChild(btn);
        }

        // ------------------------------------------------------------
        // 起動時：あいさつ → Q1
        // ------------------------------------------------------------
        function initialGreeting() {
            var greet = personalityType
                ? 'こんにちは✨ いくつかの質問に答えるだけで、あなたにピッタリのお店をAIが探すよ！\n接客タイプ診断（' + personalityType + '）も加味して提案するね💎'
                : 'こんにちは✨ いくつかの質問に答えるだけで、あなたにピッタリのお店をAIが探すよ！';
            appendAi(greet, { instant: true });
            isAnswering = true;
            nextQuestionTimer = window.setTimeout(askNext, 300);
        }

        injectResetButton();
        initialGreeting();
    });
})();

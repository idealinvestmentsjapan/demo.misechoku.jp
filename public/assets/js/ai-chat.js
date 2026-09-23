/**
 * Misechoku - AI concierge chat.
 *
 * Guided QA flow: each question shows 2-3 personalized choice chips
 * (built from saved search preferences / profile address passed via
 * data-ai-suggest) plus an "other" chip that reveals a free-text input.
 * Answers are combined and sent to /cast/search/ai-chat, which returns
 * DB-grounded shop recommendations (LLM reply when enabled).
 * After the first recommendation the input stays open for free chat,
 * with conversation history sent along for context.
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
        var inputForm = root.querySelector('[data-ai-input-form]');
        var inputField = root.querySelector('[data-ai-input]');

        var isBusy = false;
        var isAnswering = false;
        var nextQuestionTimer = null;
        var mode = 'qa'; // 'qa' during the guided flow, 'chat' after the first result
        var history = []; // {role: 'user'|'ai', content} pairs sent for LLM context

        var OTHER_LABEL = 'その他（入力する）';

        function parseJsonAttr(name, fallback) {
            try {
                var parsed = JSON.parse(root.getAttribute(name) || '');
                return parsed == null ? fallback : parsed;
            } catch (e) {
                return fallback;
            }
        }

        // ------------------------------------------------------------
        // QA flow: 2-3 personalized chips per question + "other" input
        // ------------------------------------------------------------
        var suggest = parseJsonAttr('data-ai-suggest', {});
        var allAreas = parseJsonAttr('data-area-options', []);

        function buildQaFlow() {
            var areas = Array.isArray(suggest.areas) && suggest.areas.length
                ? suggest.areas.slice(0, 2)
                : (Array.isArray(allAreas) ? allAreas.slice(0, 2) : []);

            var industries = Array.isArray(suggest.industries) && suggest.industries.length
                ? suggest.industries.slice(0, 2)
                : ['キャバクラ', 'ガールズバー'];

            var wageBase = parseInt(suggest.wage_min, 10) > 0 ? parseInt(suggest.wage_min, 10) : 3000;
            var wages = [
                '時給' + wageBase.toLocaleString() + '円以上',
                '時給' + (wageBase + 1000).toLocaleString() + '円以上',
                'こだわらない',
            ];

            var priorities = ['高収入', 'ノルマなし', '自由出勤', '未経験サポート'];
            if (personalityType.indexOf('R') !== -1) {
                priorities = ['ノルマなし', '自由出勤', '高収入', '未経験サポート'];
            }

            return [
                { q: 'まずはエリア！どのあたりで働きたい？', opts: areas.concat(['エリアは問わない']), placeholder: 'エリアを入力してね（例：中目黒）' },
                { q: '気になる業種はある？', opts: industries.concat(['こだわらない']), placeholder: '業種を入力してね（例：スナック）' },
                { q: '希望の時給はどれくらい？', opts: wages, placeholder: '希望を入力してね（例：時給3,500円以上）' },
                { q: 'ナイトワークの経験は？', opts: ['未経験', '少しだけ経験あり', '経験豊富'], placeholder: '経験を入力してね' },
                { q: '最後に、いちばん重視したいポイントは？', opts: priorities.slice(0, 3), placeholder: '重視したいことを入力してね' },
            ];
        }

        var QA_FLOW = buildQaFlow();
        var qaIndex = -1;
        var qaAnswers = [];

        // ------------------------------------------------------------
        // Rendering helpers
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

        // Choice chips: items = [{label, onClick, className?}]
        function renderChoices(items) {
            if (!quickReplyArea) return;
            quickReplyArea.innerHTML = '';
            if (!items || !items.length) return;
            items.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ai-chat__quick' + (item.className ? ' ' + item.className : '');
                btn.textContent = item.label;
                btn.addEventListener('click', function () {
                    if (isBusy) return;
                    item.onClick(item.label);
                });
                quickReplyArea.appendChild(btn);
            });
        }

        // ------------------------------------------------------------
        // Free-text input (shown by the "other" chip / free chat mode)
        // ------------------------------------------------------------
        function showInput(placeholder, focus) {
            if (!inputForm || !inputField) return;
            inputForm.hidden = false;
            if (placeholder) inputField.placeholder = placeholder;
            if (focus) inputField.focus();
        }

        function hideInput() {
            if (!inputForm || !inputField) return;
            if (mode === 'chat') return; // stays open during free chat
            inputForm.hidden = true;
            inputField.value = '';
        }

        if (inputForm && inputField) {
            inputForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (isBusy) return;
                var text = inputField.value.trim();
                if (text === '') return;
                inputField.value = '';
                if (mode === 'qa') {
                    inputForm.hidden = true;
                    handleAnswer(text);
                } else {
                    sendFollowUp(text);
                }
            });
        }

        // ------------------------------------------------------------
        // QA flow control
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
            hideInput();
            appendAi('Q' + (qaIndex + 1) + '/' + QA_FLOW.length + '　' + step.q, { instant: qaIndex > 0 });
            var choices = step.opts.map(function (opt) {
                return { label: opt, onClick: handleAnswer };
            });
            choices.push({
                label: OTHER_LABEL,
                className: 'ai-chat__quick--other',
                onClick: function () {
                    showInput(step.placeholder || '希望を入力してね', true);
                },
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
            hideInput();
            qaAnswers.pop();
            qaIndex -= 2;
            appendAi('前の回答を変更できます。', { instant: true });
            askNext();
        }

        function restartQa() {
            window.clearTimeout(nextQuestionTimer);
            isAnswering = true;
            mode = 'qa';
            qaIndex = -1;
            qaAnswers = [];
            renderChoices([]);
            hideInput();
            appendAi('もう一度ヒアリングするね！✨', { instant: true });
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

        // Free chat after the first recommendation (history included).
        function sendFollowUp(msg) {
            if (isBusy) return;
            appendUser(msg);
            fetchRecommendation(msg);
        }

        // ------------------------------------------------------------
        // Server round trip
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
                body: JSON.stringify({ message: msg, history: history.slice(-12) }),
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

                        history.push({ role: 'user', content: msg });
                        history.push({ role: 'ai', content: String(data.reply || '') });
                        if (history.length > 12) history = history.slice(-12);

                        mode = 'chat';
                        showInput('追加の希望や質問を入力してね', false);
                        var chips = (Array.isArray(data.quick_replies) ? data.quick_replies : [])
                            .slice(0, 2)
                            .map(function (label) {
                                return { label: label, onClick: sendFollowUp };
                            });
                        chips.push({ label: '条件を変えてやり直す', onClick: restartQa });
                        renderChoices(chips);
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
        // Reset button (header right)
        // ------------------------------------------------------------
        function injectResetButton() {
            if (root.querySelector('[data-ai-reset]')) return;
            var head = root.querySelector('.ai-chat__header');
            if (!head) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ai-chat__reset';
            btn.setAttribute('data-ai-reset', '');
            btn.setAttribute('aria-label', 'はじめからやり直す');
            btn.innerHTML = '<i class="fas fa-arrow-rotate-left"></i>';
            btn.title = 'はじめからやり直す';
            btn.addEventListener('click', function () {
                if (isBusy) return;
                window.clearTimeout(nextQuestionTimer);
                isAnswering = true;
                mode = 'qa';
                history = [];
                renderChoices([]);
                hideInput();
                thread.innerHTML = '';
                qaIndex = -1;
                qaAnswers = [];
                initialGreeting();
            });
            head.appendChild(btn);
        }

        // ------------------------------------------------------------
        // Boot: greeting then Q1
        // ------------------------------------------------------------
        function initialGreeting() {
            var greet = personalityType
                ? 'こんにちは✨ AIコンシェルジュだよ！かんたんな質問に答えるだけで、あなたにピッタリのお店を提案するね！\n接客タイプ診断（' + personalityType + '）も加味するよ💎'
                : 'こんにちは✨ AIコンシェルジュだよ！かんたんな質問に答えるだけで、あなたにピッタリのお店を提案するね！';
            appendAi(greet, { instant: true });
            isAnswering = true;
            nextQuestionTimer = window.setTimeout(askNext, 300);
        }

        injectResetButton();
        initialGreeting();
    });
})();

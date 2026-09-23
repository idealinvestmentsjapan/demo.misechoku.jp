/* タイムライン行・スワイプ・プロフィールのクイックアクション（KEEP）トグル
 * - <a> の中ではなく外側に配置し、stopPropagation で行リンクへの伝播を防ぐ
 * - 結果は API レスポンスで is_active を受けて aria-pressed をトグル
 */
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (!csrfToken) return;

    const endpoint = '/api/favorites/toggle';

    /* ---------------- ページ間の状態同期 ----------------
     * スワイプ → プロフィール詳細 → 「戻る」(bfcache) のように画面を行き来すると、
     * サーバーレンダリング時点の古い状態が表示されて挙動がズレて見える。
     * 直近のトグル結果を sessionStorage に保存し、ページ表示時に同じ対象の
     * ボタンへ適用することで、全画面で KEEP の状態を一致させる。 */
    const SYNC_KEY = 'fav-sync-v1';

    function readSyncStates() {
        try { return JSON.parse(sessionStorage.getItem(SYNC_KEY) || '{}'); } catch (e) { return {}; }
    }
    function writeSyncState(action, itemType, itemId, isActive) {
        try {
            const s = readSyncStates();
            s[action + ':' + itemType + ':' + itemId] = { a: isActive ? 1 : 0 };
            sessionStorage.setItem(SYNC_KEY, JSON.stringify(s));
        } catch (e) { /* private mode 等では同期なしで続行 */ }
    }
    function applySyncStates() {
        const s = readSyncStates();
        Object.keys(s).forEach(function (key) {
            const st = s[key];
            const sep1 = key.indexOf(':');
            const sep2 = key.indexOf(':', sep1 + 1);
            if (sep1 < 0 || sep2 < 0) return;
            const action = key.slice(0, sep1);
            const itemType = key.slice(sep1 + 1, sep2);
            const itemId = key.slice(sep2 + 1);
            const isActive = st.a === 1;

            document.querySelectorAll(
                '[data-fav-toggle][data-action="' + action + '"][data-item-type="' + itemType + '"][data-item-id="' + itemId + '"]'
            ).forEach(function (btn) {
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                btn.classList.toggle('is-active', isActive);
            });
        });
    }
    document.addEventListener('DOMContentLoaded', applySyncStates);
    // bfcache から復元されたときも最新のローカル状態を反映
    window.addEventListener('pageshow', function (e) { if (e.persisted) applySyncStates(); });

    /* ---------------- 状態適用ヘルパ ----------------
       対象（action × item）に紐づくページ内の全ボタンへ一括反映する。 */
    function applyStateToDom(action, itemType, itemId, isActive) {
        document.querySelectorAll(
            '[data-fav-toggle][data-action="' + action + '"][data-item-type="' + itemType + '"][data-item-id="' + itemId + '"]'
        ).forEach(function (el) {
            el.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            el.classList.toggle('is-active', isActive);
        });
    }

    /* ---------------- 連打対応：コアレスされたトグル ----------------
       旧実装は fetch 完了まで `is-busy` で入力をブロックしていたため:
         (a) 連打すると 2 発目以降が黙って捨てられて "切り替えできない" 体感になる
         (b) 押した直後に他の場所をタップして誤操作を修正しようとしても、
             既に送信中のトグルは取り消せず「解除されたように見える」ズレが起きる
       そこで:
         - クリックごとに UI（aria-pressed）は即時反転（楽観的更新）
         - サーバ通信は "サーバ側の確定値" と "UI 上の目標値" の差分に対して
           1 回だけ送るように 300ms でコアレス
         - コアレス中に再度クリックされたらタイマーを再セットして最新の目標値を反映
         - 偶数回の連打（結果的に元に戻る）は通信そのものを送らない
       これで連打しても最終状態がユーザーの意図と一致し、
       誤操作の "解除に見える" 症状も消える。 */
    const COALESCE_MS = 300;
    const pending = new Map(); // key -> { serverState, targetState, timer, action, itemType, itemId, btn }

    function toggleKey(btn) {
        return btn.dataset.action + ':' + btn.dataset.itemType + ':' + btn.dataset.itemId;
    }

    function queueToggle(btn) {
        const key = toggleKey(btn);
        let entry = pending.get(key);
        if (!entry) {
            entry = {
                serverState: btn.getAttribute('aria-pressed') === 'true', // last known committed state
                targetState: btn.getAttribute('aria-pressed') === 'true',
                timer: null,
                action: btn.dataset.action,
                itemType: btn.dataset.itemType,
                itemId: btn.dataset.itemId,
                btn: btn,
            };
            pending.set(key, entry);
        }
        entry.targetState = !entry.targetState;
        entry.btn = btn; // remember the most recently-tapped instance for row-removal UX
        applyStateToDom(entry.action, entry.itemType, entry.itemId, entry.targetState);
        if (navigator.vibrate) { try { navigator.vibrate(10); } catch (e) {} }

        if (entry.timer) clearTimeout(entry.timer);
        entry.timer = setTimeout(function () { flush(key); }, COALESCE_MS);
    }

    async function flush(key) {
        const entry = pending.get(key);
        if (!entry) return;
        // Net-zero clicks (odd->even) — no request needed, just clean up.
        if (entry.targetState === entry.serverState) {
            pending.delete(key);
            return;
        }
        const payload = { action: entry.action, item_type: entry.itemType, item_id: entry.itemId };
        const targetState = entry.targetState;
        const prevServerState = entry.serverState;
        pending.delete(key);

        function rollback() {
            applyStateToDom(entry.action, entry.itemType, entry.itemId, prevServerState);
        }

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            if (res.status === 401) {
                rollback();
                const path = window.location.pathname || '';
                const loginUrl = path.startsWith('/cast')
                    ? '/cast/login'
                    : (path.startsWith('/shop') ? '/shop/login' : '/login/demo');
                window.location.href = loginUrl;
                return;
            }
            if (res.status === 419) {
                rollback();
                showToast('セッションの有効期限が切れました。再読み込みします…');
                setTimeout(function () { window.location.reload(); }, 900);
                return;
            }
            if (res.status === 422) {
                rollback();
                const data = await res.json().catch(() => ({}));
                showToast(data.error || '操作できませんでした');
                return;
            }
            if (!res.ok) {
                rollback();
                showToast('通信エラーが発生しました');
                return;
            }

            const data = await res.json();
            const isActive = !!data.is_active;
            // If the user tapped again during the fetch, a new pending entry
            // may already exist. Fold the server response into that entry so
            // the next flush uses the true committed baseline.
            const later = pending.get(key);
            if (later) {
                later.serverState = isActive;
                // Don't overwrite the user's in-progress targetState.
                writeSyncState(payload.action, payload.item_type, payload.item_id, later.targetState);
                return;
            }
            // No further taps queued — commit server truth to the DOM.
            applyStateToDom(payload.action, payload.item_type, payload.item_id, isActive);
            writeSyncState(payload.action, payload.item_type, payload.item_id, isActive);

            // KEEP 一覧では「解除した」=「行が消える」UX
            if (!isActive) {
                const row = entry.btn.closest('[data-fav-remove-on-deactivate]');
                if (row) {
                    row.classList.add('tl-row--removing');
                    setTimeout(function () { row.remove(); }, 280);
                }
            }

            showToast(
                isActive
                    ? '🔖 保存しました（SEARCH ＞ 保存済みから確認できます）'
                    : '保存済みから外しました',
                isActive ? 'keep' : null
            );
        } catch (e) {
            rollback();
            showToast('通信エラーが発生しました');
        }
    }

    let toastTimer = null;
    let toastEl = null;
    function ensureToast() {
        if (toastEl) return toastEl;
        toastEl = document.createElement('div');
        toastEl.className = 'fav-toast';
        toastEl.setAttribute('role', 'status');
        document.body.appendChild(toastEl);
        return toastEl;
    }
    function showToast(msg, variant) {
        if (!msg) return;
        const el = ensureToast();
        el.textContent = msg;
        // variant: 'keep' (gold) / null (default)
        el.classList.remove('is-like', 'is-keep');
        if (variant === 'keep') el.classList.add('is-keep');
        el.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('is-visible'); }, 1900);
    }

    /* ---------------- クリック捕捉 ----------------
       バブリング委譲だと、途中の要素（スワイプカードの stop-propagation 等）が
       stopPropagation するとハンドラまで届かず「押しても反応しない」事故が起きる。
       キャプチャ段階（document → target の下り）で捕捉することで、
       どの画面のどんな入れ子構造でも確実に1回だけ処理する。 */
    /* ---------------- キープ一覧での解除確認 ----------------
       一覧では解除すると行が即座に消えて戻せないため、
       「キープから削除しますか？」の確認ポップアップを挟む。 */
    function showKeepConfirm(onOk) {
        let ov = document.getElementById('fav-keep-confirm');
        if (!ov) {
            ov = document.createElement('div');
            ov.id = 'fav-keep-confirm';
            ov.className = 'fav-confirm-overlay';
            ov.innerHTML =
                '<div class="fav-confirm-panel" role="alertdialog" aria-modal="true" aria-label="保存済みから外す確認">' +
                '  <p class="fav-confirm-text">保存済みから外しますか？</p>' +
                '  <div class="fav-confirm-actions">' +
                '    <button type="button" class="fav-confirm-btn fav-confirm-btn--cancel" data-confirm-cancel>やめる</button>' +
                '    <button type="button" class="fav-confirm-btn fav-confirm-btn--ok" data-confirm-ok>外す</button>' +
                '  </div>' +
                '</div>';
            document.body.appendChild(ov);
        }
        ov.classList.add('is-visible');
        const okBtn = ov.querySelector('[data-confirm-ok]');
        const cancelBtn = ov.querySelector('[data-confirm-cancel]');
        function close() {
            ov.classList.remove('is-visible');
            okBtn.onclick = cancelBtn.onclick = ov.onclick = null;
        }
        okBtn.onclick = function () { close(); onOk(); };
        cancelBtn.onclick = close;
        ov.onclick = function (e) { if (e.target === ov) close(); };
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-fav-toggle]');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        e.stopPropagation();

        // キープ一覧（解除で行が消える画面）で ON→OFF にする時だけ確認を挟む。
        // 一覧では「取り消せない不可逆操作」なので debounce 経由ではなく、
        // 明示的な確認 → 即トグル送信のフローを維持する。
        const isDeactivating = btn.getAttribute('aria-pressed') === 'true';
        const inRemovableList = !!btn.closest('[data-fav-remove-on-deactivate]');
        if (isDeactivating && inRemovableList) {
            showKeepConfirm(function () { queueToggle(btn); flushNow(btn); });
            return;
        }
        queueToggle(btn);
    }, true);

    function flushNow(btn) {
        const key = toggleKey(btn);
        const entry = pending.get(key);
        if (entry && entry.timer) {
            clearTimeout(entry.timer);
            flush(key);
        }
    }
})();

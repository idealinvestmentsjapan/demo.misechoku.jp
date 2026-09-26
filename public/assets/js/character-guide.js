document.addEventListener('DOMContentLoaded', function() {
    const characterGuide = document.getElementById('character-guide');
    const characterWrap = characterGuide ? characterGuide.querySelector('.guide-character-wrap') : null;
    const messageContent = document.getElementById('character-message-content');
    const closeBtn = document.getElementById('character-guide-close');

    const STORAGE_KEY = 'character-guide-dismissed';

    function isDismissedForThisPage() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            var paths = JSON.parse(raw);
            return Array.isArray(paths) && paths.indexOf(window.location.pathname) !== -1;
        } catch (e) {
            return false;
        }
    }

    if (characterGuide && isDismissedForThisPage()) {
        characterGuide.classList.add('is-dismissed');
    }

    function hideBubble() {
        if (window.forceCharacterGuideVisible) {
            return;
        }
        if (characterGuide && !characterGuide.classList.contains('is-hidden') && !characterGuide.classList.contains('is-dismissed')) {
            characterGuide.classList.add('bubble-hidden');
        }
    }

    function showBubble() {
        if (characterGuide) {
            characterGuide.classList.remove('bubble-hidden');
        }
    }

    function toggleBubble(e) {
        if (e) {
            e.stopPropagation();
        }
        if (!characterGuide || characterGuide.classList.contains('is-hidden') || characterGuide.classList.contains('is-dismissed')) {
            return;
        }

        if (characterGuide.classList.contains('bubble-hidden')) {
            showBubble();
            return;
        }

        hideBubble();
    }

    if (characterWrap && characterGuide) {
        // AIコンシェルジュ入口モード（キャスト領域）：タップで AI 画面へ遷移。
        // それ以外は従来どおり吹き出しの表示/非表示をトグルする。
        var aiUrl = characterGuide.getAttribute('data-ai-url');
        characterWrap.addEventListener('click', function (e) {
            if (aiUrl) {
                e.stopPropagation();
                window.location.href = aiUrl;
                return;
            }
            toggleBubble(e);
        });
        if (aiUrl) {
            characterWrap.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = aiUrl;
                }
            });
        }
    }

    if (closeBtn && characterGuide) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            try {
                var raw = sessionStorage.getItem(STORAGE_KEY);
                var paths = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(paths)) paths = [];
                if (paths.indexOf(window.location.pathname) === -1) {
                    paths.push(window.location.pathname);
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(paths));
                }
            } catch (err) {}
            characterGuide.classList.add('is-dismissed');
        });
    }

    // スクロール中は body に is-scrolling を付けて、ガイドを半透明化する（CSS 側で opacity 0.35）。
    // 検索結果の視認性を邪魔しないための配慮。停止後 320ms でフェード復帰。
    if (characterGuide) {
        var scrollFadeTimer = null;
        var scrollFadePassive = { passive: true };
        var onScrollFade = function () {
            if (!document.body.classList.contains('is-scrolling')) {
                document.body.classList.add('is-scrolling');
            }
            if (scrollFadeTimer) window.clearTimeout(scrollFadeTimer);
            scrollFadeTimer = window.setTimeout(function () {
                document.body.classList.remove('is-scrolling');
            }, 320);
        };
        window.addEventListener('scroll', onScrollFade, scrollFadePassive);
        // タブページ本体（.tab-page-body 等）が独自スクロールする画面もあるため main も監視
        var mainEl = document.getElementById('main-content');
        if (mainEl) mainEl.addEventListener('scroll', onScrollFade, scrollFadePassive);
    }

    // 外部（Swiperなど）からメッセージを更新する関数（ホームのスワイプ画面ではオコジョを表示しない）
    window.updateCharacterMessage = function(newMessage) {
        if (!messageContent || !characterGuide) return;
        if (document.body.classList.contains('page-home')) return;

        // 運営管理（character_guide_settings）で「非表示」に設定された画面では、
        // 外部（オンボーディング等）からの updateCharacterMessage 呼び出しでも
        // オコジョを強制表示しない。設定情報を単一の真実にする。
        var serverEnabled = characterGuide.getAttribute('data-server-enabled') === '1';
        if (!serverEnabled) {
            return;
        }

        if (newMessage && newMessage.trim() !== "") {
            messageContent.innerHTML = newMessage.replace(/\n/g, '<br>');
            characterGuide.classList.remove('is-hidden');
            characterGuide.classList.remove('bubble-hidden');
            characterGuide.classList.remove('is-dismissed');
            characterGuide.style.opacity = '1';
            characterGuide.style.transform = 'translateY(0)';
        } else {
            characterGuide.classList.add('is-hidden');
        }
    };
});
/**
 * Discovery Home Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    
    const mainSwiperEl = document.querySelector('.main-swiper');
    const mainWrapper = mainSwiperEl ? mainSwiperEl.querySelector(':scope > .swiper-wrapper') : null;
    const mainSlides = mainWrapper ? Array.from(mainWrapper.children).filter((el) => el.classList.contains('swiper-slide')) : [];
    const slideCount = mainSlides.length;
    let isPhotoSwiping = false;

    function releasePhotoSwipeLock() {
        isPhotoSwiping = false;
    }

    /**
     * カード本文の "下からふわっ" アニメーションを再トリガーする。
     * mode: 'card'  → 上下スワイプ（カード切替）。スタガーで全要素を立ち上げる。
     *       'photo' → 左右スワイプ（写真切替）。軽量バリアント。
     */
    function refreshCardContent(card, mode) {
        if (!card) return;
        var fullClass = 'is-content-fresh';
        var photoClass = 'is-photo-fresh';
        // どちらのアニメーションも一旦リセット
        card.classList.remove(fullClass, photoClass, 'is-flipping');
        // 強制リフロー → 再付与で animation を再生
        // eslint-disable-next-line no-unused-expressions
        void card.offsetWidth;
        if (mode === 'photo') {
            card.classList.add(photoClass);
        } else {
            card.classList.add(fullClass, 'is-flipping');
        }
    }

    function refreshActiveCard(swiperInstance, mode) {
        if (!swiperInstance) return;
        var slide = swiperInstance.slides && swiperInstance.slides[swiperInstance.activeIndex];
        var card = slide && (slide.classList.contains('cast-card') ? slide : slide.querySelector('.cast-card'));
        refreshCardContent(card, mode || 'card');
    }

    // ================================================================
    // メインの上下スワイプ：Swiper 標準 JS モード（cssMode オフ）。
    // ----------------------------------------------------------------
    // 【方針】(2026-08-08 rev.3)
    //   以前は cssMode: true（CSS Scroll Snap 委譲）で 120Hz を狙っていたが、
    //   ・loop + cssMode の組み合わせで最後のカードから 1 枚目へ回り込む挙動が壊れる
    //   ・遷移途中にタップされると snap 位置が飛んで別カードへ着地する
    //   の 2 症状を実機で確認したため、Swiper 標準の JS エンジンへ戻す。
    //
    //   JS モードでは preventInteractionOnTransition が使えるので、
    //   「スワイプ中にタップして飛ぶ」問題は根本ブロックできる。
    //   loop も Swiper 標準の複製スライド方式で確実にラップする。
    // ================================================================
    const mainSwiper = new Swiper('.main-swiper', {
        direction: 'vertical',
        slidesPerView: 1,
        slidesPerGroup: 1,
        centeredSlides: true,
        // 2 枚以上あるなら常にループ（最後まで到達したら 1 枚目に戻る = 無限回転）
        loop: slideCount >= 2,
        rewind: false,
        loopAdditionalSlides: 1,

        // なめらか設定：遷移速度と抵抗を控えめに、スワイプは軽く反応
        // 2026-08-09 rev: 体感 sluggish 対策
        //   speed 320→220     : 1 カード遷移を Tinder 相当（~220ms）に
        //   touchAngle 55→40  : 縦許容角を狭め、22°〜55° の photo/main デッドゾーンを縮小
        speed: 220,
        threshold: 6,
        touchAngle: 40,
        touchRatio: 1,
        followFinger: true,
        resistance: true,
        resistanceRatio: 0.85,
        longSwipes: true,
        longSwipesRatio: 0.20,
        longSwipesMs: 260,
        shortSwipes: true,

        // ★ 遷移中のタッチ/クリックは無視 → 「途中でタップして挙動が飛ぶ」を封じる
        // 2026-08-09 rev: true だと 220ms 完全ロックで連続フリックが引っかかるため false に。
        //   JS モードでは cssMode 時のような「タップで飛ぶ」バグは起きにくいが、
        //   もし再発したら preventClicks/preventClicksPropagation で個別に抑える。
        preventInteractionOnTransition: false,
        // ネスト写真スワイパーとの競合を避けるため、クリックの propagation を切る
        preventClicks: true,
        preventClicksPropagation: true,

        mousewheel: {
            enabled: true,
            sensitivity: 0.5,
            thresholdDelta: 40,
            thresholdTime: 500,
            forceToAxis: true,
            releaseOnEdges: true,
        },
        grabCursor: true,
        keyboard: {
            enabled: true,
            onlyInViewport: true
        },
        passiveListeners: true,
        observer: false,
        observeParents: false,
        on: {
            init: function () {
                var self = this;
                requestAnimationFrame(function () {
                    self.update();
                    // 初回のみ本文フワッ演出（連続時は演出せず遷移だけに集中）
                    refreshActiveCard(self, 'card');
                });
            }
        }
    });

    // 各カード内の左右スワイプ（同一アカウントの複数写真）
    // ========================================================================
    // 写真セグメントバー（ストーリー型）
    // ドット式は廃止：写真枚数ぶんの等分バーで「今どこ/全何枚」が一目でわかる。
    // タップでその写真へジャンプ。
    // ========================================================================
    function buildPhotoSegBar(paginationEl, swiper, count) {
        // 旧 bullet 系のクラスが残っていると Swiper CDN CSS / home.css 内の
        // .swiper-pagination-bullets 系ルールに hit してセグメントが太くなる。
        // 完全に切り離すため一括で剥がす。
        paginationEl.classList.remove('swiper-pagination', 'swiper-pagination-bullets', 'swiper-pagination-horizontal');
        paginationEl.classList.add('photo-seg-bar');
        paginationEl.innerHTML = '';

        const segs = [];
        for (let i = 0; i < count; i++) {
            const seg = document.createElement('button');
            seg.type = 'button';
            seg.className = 'photo-seg stop-propagation';
            seg.setAttribute('aria-label', (i + 1) + '枚目の写真へ');
            seg.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                swiper.slideTo(i);
            });
            paginationEl.appendChild(seg);
            segs.push(seg);
        }

        function sync() {
            const active = swiper.activeIndex || 0;
            segs.forEach(function (seg, idx) {
                seg.classList.toggle('is-active', idx === active);
                seg.classList.toggle('is-passed', idx < active);
            });
        }
        swiper.on('slideChange', sync);
        sync();
    }

    const photoSwipers = [];
    document.querySelectorAll('.photo-swiper').forEach((el) => {
        // 2026-08-09 rev.2: Swiper 11 は loop:true でもクローンスライドを作らず
        // 実スライドを DOM 内で並び替える方式に変わったため、旧 rev で入れた
        // `.swiper-slide-duplicate` 検出は常に false で機能していなかった。
        // クローンが存在しない = 追加の nested Swiper は元々出ないので skip 条件不要。

        const paginationEl = el.querySelector('.photo-pagination');
        const photoSlideCount = el.querySelectorAll(':scope > .swiper-wrapper > .swiper-slide').length;

        // 1枚のみ：横Swiperを動かさない＝縦のメインSwiperと競合しない（キャスト側求人カードが軽いのと同じ挙動）
        if (photoSlideCount <= 1) {
            el.classList.add('photo-swiper--single');
            if (paginationEl) {
                paginationEl.style.display = 'none';
            }
            return;
        }

        // 写真スワイパー（横）：nested のため cssMode は使わず、JS モードで最軽量チューニング
        // 主要チューニング (2026-08-02 rev.2):
        //   - speed 260→220：さらに snap を詰めて即応
        //   - threshold 6→4：指を置いた瞬間の追従感を最大化
        //   - resistanceRatio 0.5→0.35：端でのゴム感を軽く（速い連続フリック時の詰まり回避）
        //   - spaceBetween 8→0：スライド間の隙間を消し、native cover の transform 計算を単純化
        //   - longSwipesRatio 0.20→0.15：少しの動きでも進めて写真切替を軽快に
        const options = {
            direction: 'horizontal',
            slidesPerView: 1,
            slidesPerGroup: 1,
            spaceBetween: 0,
            loop: false,
            nested: true,
            allowTouchMove: true,
            touchStartPreventDefault: false,
            touchReleaseOnEdges: true,
            touchAngle: 22,
            threshold: 4,
            speed: 220,
            effect: 'slide',
            resistance: true,
            resistanceRatio: 0.35,
            longSwipes: true,
            longSwipesRatio: 0.15,
            longSwipesMs: 240,
            shortSwipes: true,
            followFinger: true,
            watchOverflow: false,
            preventClicks: true,
            preventClicksPropagation: true,
            touchMoveStopPropagation: false,
            slideToClickedSlide: false,
            centeredSlides: false,
            passiveListeners: true,
            observer: false,
            observeParents: false,
            on: {
                touchStart: function () {
                    isPhotoSwiping = false;
                },
                touchMove: function () {
                    isPhotoSwiping = true;
                },
                touchEnd: function () {
                    releasePhotoSwipeLock();
                },
                touchCancel: function () {
                    releasePhotoSwipeLock();
                }
            }
        };
        const swiper = new Swiper(el, options);
        photoSwipers.push(swiper);

        // Swiper 標準の bullet はやめ、ゴム風粘性のカスタムページャーを敷く
        if (paginationEl) {
            buildPhotoSegBar(paginationEl, swiper, photoSlideCount);
            // 配置は「トークする」ボタンの直下（カスタムページャーなので swiper 外でも動作する）
            var cardEl = el.closest('.cast-card');
            var talkCta = cardEl && cardEl.querySelector('.swipe-talk-cta');
            if (talkCta) {
                talkCta.insertAdjacentElement('afterend', paginationEl);
            }
        }
    });

    // ============================================================
    // トークCTA：capture 段階で最優先に処理する（保険）。
    // Swiper の preventClicks が <a> の既定遷移を殺すケースでも
    // 確実にトークルームへ遷移させる（loop 複製スライドにも効く document 委譲）
    // ============================================================
    document.addEventListener('click', function (e) {
        var cta = e.target.closest('.swipe-talk-cta');
        if (!cta) return;
        e.preventDefault();
        e.stopPropagation();
        var href = cta.getAttribute('href');
        if (href) window.location.href = href;
    }, true);

    // ============================================================
    // プロフィール遷移：キャストカード・求人カード共通で
    // 「画像エリア（.home-photo-wrap）のタップのみ」をトリガーにする。
    // ・カード全体ハンドラは廃止（下部のボタン類が誤遷移する根本原因だった）
    // ・document 委譲なので Swiper loop の複製スライドにも自動で効く
    // ・URL はキャストカード = wrap の data-detail-url / 求人カード = カードの data-detail-url
    // ============================================================
    document.addEventListener('click', function (e) {
        // ボタン・リンク・ページネーションはそれぞれの本来動作を優先
        if (e.target.closest('a[href]')) return;
        if (e.target.closest('button')) return;
        if (e.target.closest('.stop-propagation')) return;
        if (e.target.closest('.photo-pagination')) return;
        var wrap = e.target.closest('.home-photo-wrap');
        if (!wrap) return;
        if (isPhotoSwiping) return;
        var recruitCard = wrap.closest('.cast-card--recruit');
        var url = wrap.getAttribute('data-detail-url')
            || (recruitCard ? recruitCard.getAttribute('data-detail-url') : null);
        if (url) window.location.href = url;
    });

    // リサイズ・ビューポート変化時に Swiper を更新（モバイルのアドレスバー表示切替など）
    // 2026-08-09 rev: rAF debounce + swiping guard を追加。
    //   iOS Safari のアドレスバー折りたたみで visualViewport.resize が連発し、
    //   スワイプの transform 計算とバッティングして遷移中カードが飛んでいた。
    //   1 フレーム 1 回に coalesce + アニメーション中は次フレームへ延期する。
    function updateAllPhotoSwipers() {
        photoSwipers.forEach(function (ps) {
            if (ps && typeof ps.update === 'function') ps.update();
        });
    }
    var swiperUpdateRafId = 0;
    function scheduleSwiperUpdate() {
        if (swiperUpdateRafId) return;
        swiperUpdateRafId = requestAnimationFrame(function tick() {
            // スワイプ・遷移中は update() を後回し（現在位置が壊れるのを防ぐ）
            if (mainSwiper && (mainSwiper.animating || mainSwiper.touches && mainSwiper.touches.diff)) {
                swiperUpdateRafId = requestAnimationFrame(tick);
                return;
            }
            swiperUpdateRafId = 0;
            if (mainSwiper) mainSwiper.update();
            updateAllPhotoSwipers();
        });
    }
    window.addEventListener('resize', scheduleSwiperUpdate);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', scheduleSwiperUpdate);
    }

    // 2026-08-11 rev: bfcache 復元 + フォワード遷移両対応のレイアウト崩れ対策。
    //   別画面 → グローバルフッター経由でスワイプ画面に戻ると、
    //   ・iOS Safari / Android Chrome のアドレスバー表示状態が初回計測時と異なり、
    //     100dvh の実測値が変わって aspect-ratio 4/5 のカード寸法が別サイズになる
    //   ・Swiper の translateY は前回のカード位置基準のままで、
    //     新しいカード寸法にスナップし直されないため、画像/情報の 65:35 帯が
    //     見た目上ズレる
    //   → update() だけでなく updateSize/updateSlides の後に
    //     slideTo(activeIndex, 0, false) で現在カードに再スナップさせる。
    //   → pageshow は persisted の有無を問わず呼び、非 bfcache のフォワード
    //     遷移直後（初回 layout が address bar と一致していないケース）にも効かせる。
    //   → window.load でフォント / 画像確定後にもう一度計測する。
    //   2 rAF 待ってから測るのは、iOS Safari のアドレスバー折りたたみ完了と
    //   dvh 反映を 1 フレーム空けて確実に拾うため。
    function forceSwiperRelayout() {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                if (mainSwiper && !mainSwiper.destroyed) {
                    mainSwiper.updateSize();
                    mainSwiper.updateSlides();
                    mainSwiper.updateSlidesClasses();
                    mainSwiper.update();
                    mainSwiper.slideTo(mainSwiper.activeIndex, 0, false);
                }
                photoSwipers.forEach(function (ps) {
                    if (!ps || ps.destroyed) return;
                    if (typeof ps.updateSize === 'function') ps.updateSize();
                    if (typeof ps.updateSlides === 'function') ps.updateSlides();
                    if (typeof ps.update === 'function') ps.update();
                    if (typeof ps.slideTo === 'function') ps.slideTo(ps.activeIndex, 0, false);
                });
            });
        });
    }
    window.addEventListener('pageshow', function () {
        forceSwiperRelayout();
    });
    window.addEventListener('load', function () {
        forceSwiperRelayout();
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') forceSwiperRelayout();
    });

    // 3. クリック伝播停止（誤・プロフィール遷移防止）
    //   ── 2026-08-09 rev ──
    //   旧: touchstart / mousedown で e.stopPropagation() していた。
    //   これが「下→上スワイプ」で親指が画面下部の Talk CTA (.stop-propagation) に
    //   着地したときに Swiper へ touchstart が届かず、次カードへ進めない主原因だった。
    //   Swiper 11 は preventClicks:true でドラッグ後の click を自動キャンセルするので
    //   touchstart / mousedown レベルで人力ブロックする必要は無い。
    //   click レベルの伝播だけ止めて、document 側の photo-wrap プロフィール遷移
    //   ハンドラに拾われないようにする（そちらは元々 `.stop-propagation` を除外
    //   しているので二重の保険）。
    document.querySelectorAll('.stop-propagation').forEach(el => {
        el.addEventListener('click', (e) => e.stopPropagation());
    });

    // 4. アクションボタンの簡易動作（Ajax連携）
    initActionButtons();

    // 5. 初回・久しぶり用オンボーディング（ホームスワイプガイド・オコジョは表示しない）
    const onboardingOverlay = document.getElementById('home-swipe-onboarding');
    const ONBOARDING_KEY = 'home_swipe_onboarding_last_shown_at';
    const ONBOARDING_INTERVAL_DAYS = 90;

    function shouldShowOnboarding() {
        try {
            const raw = localStorage.getItem(ONBOARDING_KEY);
            if (!raw) return true; // 新アカウント（初回）
            const last = parseInt(raw, 10);
            if (!last) return true;
            const diffMs = Date.now() - last;
            const thresholdMs = ONBOARDING_INTERVAL_DAYS * 24 * 60 * 60 * 1000;
            return diffMs >= thresholdMs;
        } catch (e) {
            return true;
        }
    }

    function markOnboardingShown() {
        try {
            localStorage.setItem(ONBOARDING_KEY, String(Date.now()));
        } catch (e) {
            // ignore
        }
    }

    function showOnboarding() {
        if (!onboardingOverlay) return;
        onboardingOverlay.classList.add('is-active');
        onboardingOverlay.setAttribute('aria-hidden', 'false');
        window.forceCharacterGuideVisible = true;
        if (typeof window.updateCharacterMessage === 'function') {
            window.updateCharacterMessage(
                "上下スワイプで次 / 前のアカウントに移動できるよ！\n" +
                "左右スワイプで同じアカウントの別の写真を見られるよ。\n" +
                "気になる人がいたら右側のボタンからアクションしてみてね。"
            );
        }
    }

    function hideOnboarding() {
        if (!onboardingOverlay) return;
        onboardingOverlay.classList.remove('is-active');
        onboardingOverlay.setAttribute('aria-hidden', 'true');
        markOnboardingShown();
    }

    if (onboardingOverlay && shouldShowOnboarding()) {
        showOnboarding();
        const closeHandler = (e) => {
            e.preventDefault();
            hideOnboarding();
        };
        onboardingOverlay.addEventListener('click', closeHandler, { once: true });
        onboardingOverlay.addEventListener('touchstart', closeHandler, { passive: true, once: true });
    }
});

/**
 * ボタンクリック時のエフェクト等
 */
function initActionButtons() {
    const buttons = document.querySelectorAll('.action-circle-btn');
    if (!buttons.length) return;

    const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.getAttribute('content') : null;

    buttons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (this.classList.contains('message')) {
                return;
            }
            // KEEP は favorite-quick.js（data-fav-toggle）に一本化。
            // ここでは何もしない（document への委譲ハンドラが処理する）。
            if (this.hasAttribute('data-fav-toggle')) {
                return;
            }

            e.preventDefault();

            const action = this.dataset.action;
            const itemId = this.dataset.itemId;
            const itemType = this.dataset.itemType;

            if (!action || !itemId || !itemType) {
                // 単純なエフェクトのみ
                this.style.transform = 'scale(1.2)';
                setTimeout(() => this.style.transform = 'scale(1)', 200);
                return;
            }

            const payload = {
                action: action,
                item_id: itemId,
                item_type: itemType,
            };

            fetch('/api/favorites/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            })
                .then(res => res.ok ? res.json() : Promise.reject(res))
                .then(data => {
                    if (!data || !data.ok) return;

                    if (typeof data.is_active !== 'undefined') {
                        this.classList.toggle('is-active', !!data.is_active);
                    }

                    this.style.transform = 'scale(1.2)';
                    setTimeout(() => this.style.transform = 'scale(1)', 200);
                })
                .catch((res) => {
                    // 失敗を握りつぶさず、原因別にフィードバックする
                    if (res && res.status === 401) {
                        window.location.href = '/login';
                        return;
                    }
                    if (res && res.status === 419) {
                        (window.appToast || window.alert)('セッションの有効期限が切れました。再読み込みします…');
                        setTimeout(() => window.location.reload(), 900);
                        return;
                    }
                    (window.appToast || window.alert)('通信エラーで保存できませんでした。もう一度お試しください。', 'error');
                });
        });
    });
}
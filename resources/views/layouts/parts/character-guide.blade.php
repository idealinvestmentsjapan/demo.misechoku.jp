@php
    use App\Services\CharacterGuideService;

    // 表示判定とセリフは運営管理 DB（character_guide_settings）に一本化。
    // 設定が無い／無効化されている画面では何も表示しない（デフォルト文言は持たない）。
    $resolvedGuideMessage = '';
    $shouldShow = false;

    $routeName = optional(request()->route())->getName();
    if ($routeName) {
        try {
            $setting = app(CharacterGuideService::class)->getForRoute($routeName);
            if ($setting['enabled'] && $setting['message'] !== '') {
                $resolvedGuideMessage = $setting['message'];
                $shouldShow = true;
            }
        } catch (\Throwable $e) {
            // DB 未準備時などは静かにスキップ
        }
    }

    // ===== AIコンシェルジュ入口（2026-07-20）=====
    // サブヘッダーの AI タブを廃止し、SEARCH（さがす）画面内のみオコジョのタップで
    // AI コンシェルジュへ遷移させる。AI 画面自身では自己リンクになるため除外。
    $onAiPage = request()->routeIs('cast.search.index') && request('tab') === 'ai';
    $aiEntryUrl = (request()->is('cast/search*') && !$onAiPage)
        ? route('cast.search.index', ['tab' => 'ai'])
        : null;
@endphp

{{-- オコジョガイド：右下浮遊デザイン。閉じるボタンはキャラクター右下に配置（吹き出し外）
     data-server-enabled は運営管理での ON/OFF を JS に伝えるためのフラグ。
     オンボーディング等の外部から updateCharacterMessage を呼んでも、無効化された画面では
     表示させないためのゲート。 --}}
<div id="character-guide"
     class="discovery-guide {{ $shouldShow ? '' : 'is-hidden' }} {{ $aiEntryUrl ? 'guide--ai-entry' : '' }}"
     data-server-enabled="{{ $shouldShow ? '1' : '0' }}"
     @if($aiEntryUrl) data-ai-url="{{ $aiEntryUrl }}" @endif>
    {{-- 左側の吹き出し --}}
    <div class="guide-speech-bubble">
        <p id="character-message-content">{!! nl2br(e($resolvedGuideMessage)) !!}</p>
    </div>
    {{-- 右側のキャラクター（×ボタンはキャラ右下に絶対配置） --}}
    <div class="guide-character-wrap"
         @if($aiEntryUrl) role="link" tabindex="0" aria-label="AIコンシェルジュを開く" @endif>
        <img src="{{ asset('assets/images/guide/guide-character.png') }}" alt="ガイド">
        @if($aiEntryUrl)
            <span class="guide-ai-badge" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i> AIコンシェルジュ</span>
        @endif
        <button type="button" class="guide-close-btn" id="character-guide-close" aria-label="ガイドを閉じる">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
</div>
{{-- フラッシュ防止：character-guide.js（ページ末尾・DOMContentLoaded 後）を待たず、
     要素直後の同期スクリプトで「このページで閉じた履歴」を描画前に反映する。
     これが無いと、一度閉じたページで「一瞬表示→消える」チラつきが発生する。 --}}
<script>
(function () {
    try {
        var el = document.getElementById('character-guide');
        if (!el) return;
        var raw = sessionStorage.getItem('character-guide-dismissed');
        if (!raw) return;
        var paths = JSON.parse(raw);
        if (Array.isArray(paths) && paths.indexOf(window.location.pathname) !== -1) {
            el.classList.add('is-dismissed');
        }
    } catch (e) { /* sessionStorage 不可環境では何もしない */ }
})();
</script>

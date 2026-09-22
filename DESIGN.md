# DESIGN.md — デザインシステムの単一の正

このファイルは UI 実装の **唯一の正（Single Source of Truth）** です。
全画面はここに定義されたトークンと x-ui コンポーネントだけで組みます。
CLAUDE.md から本ファイルを参照させ、画面移行のたびに先頭に読み込ませてください。

実体は `resources/css/app.css` の `@theme` ブロックにあります。本ファイルは
「何を・なぜ・どう使うか」を定義する仕様書です。

---

## 0. 一本化の決定事項（2枚のプロトタイプの食い違いを解消）

| 項目 | プロトA（mypage） | プロトB（その他画面） | 採用 |
|---|---|---|---|
| **プライマリボタン色** | アクセント追従（`#8B5CF6` / グラデ `#A78BFA→#7C3AED`） | 固定ディープパープル（`#581C87` / グラデ `#6B21A8→#3B0764`） | **アクセント追従を既定** |
| ボタン上の文字色 | `#E6DFFC` / グラデ `#F4F2FA` | `#F3E8FF` / グラデ `#FAF5FF` | アクセント追従（`--on-accent`） |
| アクセント枠 | `#A855F7/40` | `#A855F7/40` | 一致 → `border-line-accent/40` |
| 背景 / 文字 / サブ | `#050505 / #F5F5F5 / #A0A0A0` | 同左 | 一致 |

> プライマリボタンはアクセント追従を採用。現在の画面では amethyst を使用する。ライト／ダークの利用者向け切替は提供しない。

---

## 1. カラートークン

| 用途 | トークン（utility） | 値 |
|---|---|---|
| アプリ背景 | `bg-base` | `#050505` |
| 3Dカード上端グラデ | `from-surface-from` | `#1a1a1a`（→ `to-base`） |
| 本文 | `text-text-main` | `#F5F5F5` |
| サブ文字 | `text-text-sub` | `#A0A0A0` |
| 通常ボーダー | `border-line` | `#2A2A2A` |
| アクセント枠 | `border-line-accent/40` | `#A855F7` @40% |
| アクセント面 | `bg-accent` | テーマ追従 |
| アクセント文字 | `text-accent-text` | テーマ追従 |
| アクセントグラデ | `from-accent-grad-from to-accent-grad-to` | テーマ追従 |
| アクセント上の文字 | `text-on-accent` / `text-on-accent-strong` | テーマ追従 |
| ディープパープル | `bg-deep-purple` | `#9333EA` |
| ボーナス金グラデ | `from-gold-from to-gold-to` | `#D4AF37 → #B8860B` |
| 明るい入力背景 | `bg-input-light` | `#F5F2FA` |

**禁止：** `bg-[#050505]` のような任意値、`text-white` 等のベタ指定（白はアイコンのみ可）。

---

## 2. テーマ切替

ルート要素の `data-theme` を切り替える（`amethyst`（既定）/ `lilac` / `light_blue` / `light_pink`）。
アクセント系トークンはすべて CSS 変数経由なので、属性を変えるだけで全画面が一括追従する。
画面ごとに色分岐を書かない。

```html
<html data-theme="amethyst"> ... </html>
```

---

## 3. タイポグラフィ

| 用途 | utility |
|---|---|
| 本文 | `font-sans`（Noto Sans JP・既定） |
| 見出し・英字ロゴ | `.app-title`（Montserrat / letter-spacing 0.05em） |

---

## 4. シャドウ（3D / ニューモーフィズム）

| utility | 用途 |
|---|---|
| `shadow-btn-3d` / `shadow-btn-3d-active` | ボタン（押下時は `active:` で差し替え） |
| `shadow-badge-3d` | バッジ |
| `shadow-fab-3d` | FAB |
| `shadow-card-3d` | カード |
| `shadow-header` / `shadow-footer` | グラスヘッダー / フッター |
| `shadow-input-dark` / `shadow-input-light` | 入力欄 |
| `shadow-gold-3d` | ボーナス金バッジ |
| `shadow-nav-pill` | ナビ3Dスタイルのアクティブ丸 |

**禁止：** `shadow-[inset_0_4px_6px_...]` の任意値。上記トークンを使う。

---

## 5. アイコン辞書（Phosphor）

**生クラス（`ph-fill ph-house` 等）を画面に直書きしない。** 必ず意味名で `<x-ui.icon>` を呼ぶ。
新しいアイコンが要るときは、まずこの表に意味名を追加してから使う。

| 意味名（name） | Phosphor クラス |
|---|---|
| `home` | `ph-fill ph-house` |
| `search` | `ph-bold ph-magnifying-glass` |
| `likes` | `ph-fill ph-heart` |
| `talk` | `ph-fill ph-chat-teardrop-text` |
| `mypage` | `ph-fill ph-user` |
| `swipe` | `ph-fill ph-cards` |
| `back` | `ph-bold ph-caret-left` |
| `share` | `ph-bold ph-share-network` |
| `like`（スワイプ） | `ph-fill ph-heart` |
| `nope`（スワイプ） | `ph-bold ph-x` |
| `super`（スワイプ） | `ph-fill ph-star` |
| `forward` | `ph-bold ph-caret-right` |
| `settings` | `ph-bold ph-gear-six` |
| `close` | `ph-bold ph-x` |
| `plus` | `ph-bold ph-plus` |
| `check` | `ph-bold ph-check` |
| `list` | `ph-bold ph-list`（ハンバーガー / メニュー） |
| `bell` | `ph-bold ph-bell`（通知） |
| `task` | `ph-fill ph-check-circle`（タスク） |
| `edit` | `ph-bold ph-pencil-simple`（編集） |
| `staff` | `ph-fill ph-users-three`（スタッフ・チーム） |
| `crown` | `ph-fill ph-crown-simple`（Premium） |

> 現在の読込元は `components/ui/assets.blade.php` のCDN。バンドラーからの読み込みには移行していない。

---

## 6. アニメーション / トランジション

| utility | 効果 |
|---|---|
| `animate-slide-up` | 下から30pxフェードイン（カード・モーダル登場） |
| `animate-fade-in` | scale 0.95→1 のフェードイン |
| `transition-all duration-300` | 既定のトランジション尺（ホバー・状態変化） |

keyframes はコンパイル済み CSS にのみ存在する。**画面側に `<style>` で keyframes を書かない。**

---

## 7. コンポーネント一覧（x-ui.*）

各画面はこれらを組み合わせるだけ。class レシピ（= 実装の根拠）を併記。
`is3D` / `isGrad` 等のバリエーションは props で出し分ける。

| コンポーネント | class レシピ（既定） |
|---|---|
| `x-ui.button`（primary） | `inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-full font-bold bg-accent text-on-accent shadow-btn-3d active:translate-y-1.5 active:shadow-btn-3d-active transition-all duration-300` |
| `x-ui.button`（grad） | 上記の `bg-accent` を `bg-gradient-to-r from-accent-grad-from to-accent-grad-to text-on-accent-strong` に置換 |
| `x-ui.badge` | `px-4 py-2 rounded-full bg-accent text-on-accent shadow-badge-3d font-bold text-sm` |
| `x-ui.premium-badge` | 優良店バッヂ（ゴールド・全画面統一）。props: `size` (sm/md/lg) / `off` (未達成表示) / `label` |
| `x-ui.view-count` | 閲覧数メダル（M=千。1,000 銅 / 5,000 銀 / 10,000 金） |
| `x-ui.fab` | `fixed bottom-[90px] right-5 w-14 h-14 rounded-full flex items-center justify-center bg-accent text-on-accent shadow-fab-3d active:translate-y-1.5 transition-all z-30` |
| `x-ui.card` | `rounded-card overflow-hidden border border-line-accent/40 bg-gradient-to-br from-surface-from to-base shadow-card-3d transition-all duration-300`（flat 時は bg/shadow を外す） |
| `x-ui.menu-card` | card に `p-4 flex items-center justify-between rounded-panel cursor-pointer group` |
| `x-ui.header`（glass） | `bg-deep-purple/30 backdrop-blur-md border-b border-line-accent/40 shadow-header pt-safe` |
| `x-ui.bottom-nav` | `bg-deep-purple/30 backdrop-blur-md border-t border-line-accent/40 shadow-footer pb-safe`（中身は §8 参照） |
| `x-ui.icon` | `<i class="{Phosphorクラス}">`（name → §5 で解決） |
| `x-ui.input` | dark: `bg-accent/10 border border-line-accent/40 shadow-input-dark text-text-main` / light: `bg-input-light shadow-input-light text-input-light-text` |
| message bubble（自分） | `px-4 py-2.5 rounded-[20px] rounded-tr-sm bg-accent text-on-accent shadow-badge-3d font-medium text-[13px] leading-relaxed w-fit` |
| message bubble（相手） | `... rounded-tl-sm` + card と同じ面 |

---

## 8. インタラクション / アクション一覧（data属性駆動の共通JS）

挙動は画面ごとに書かず、**1つの behaviors.js が data 属性を見て付与する。**
画面の Blade は「属性を置くだけ」。これでアクションが全画面で完全に一致する。

| data 属性 | 挙動 |
|---|---|
| `data-swipe-deck` / `data-swipe-action="like\|nope\|super"` | カードのドラッグ＆スワイプ判定・アクション発火 |
| `data-bottom-nav` / `data-nav-style="neon\|flat\|3d"` | 下部ナビのアクティブ表示・スタイル出し分け |
| `data-fab` | FABの押下挙動 |
| `data-tabs` / `data-tab-panel="..."` | タブ切替（例：gallery / details） |
| `data-scroll-reveal` | スクロール量に応じたヘッダーの出し入れ（`isScrolled` 相当） |
| `data-message-form` | メッセージ送信・吹き出し追加 |
| `data-scroll-target="..."` | 押下で該当セレクタへ `scrollIntoView`（採用管理サマリーカードなど） |
| `data-fav-toggle` | KEEP のトグル（capture-phase の delegation） |

---

## 9. 画面移行ルール（Claude Code 向け）

**必ず守る：**
- 本ファイルのトークンと `x-ui.*` だけで組む
- アイコンは `<x-ui.icon name="...">`、アクションは data 属性
- 1画面ずつ。完了後に `npm run tw:build` を通し、purge 警告ゼロを確認 → 目視確認 → 移行チェックリストを更新

**禁止：**
- 任意値（`bg-[#...]` `shadow-[...]` `text-[13px]` 等。`text-[13px]` のような一回限りのサイズは可だが色・影は不可）
- Phosphor 生クラスの直書き
- 画面内の `<style>` での keyframes / 色定義
- 文字列連結によるクラス生成（`'bg-' + x`）。purge で消える
- 画面ごとの色分岐（テーマは `data-theme` に一元化）

---

## 10. ボタン / CTA の役割ルール（色・深度の使い分け）

「意味のある違い」だけを許可する。同じ役割は全画面で同じ見た目にする。
レガシー CSS のブレは `public/assets/css/ui-consistency.css`（全ページ CSS の後に
読み込む上書き専用ファイル）で吸収する。新規画面はこの表に従って直接組む。

### ★ 正解レシピ（`ui-consistency.css §8c` で定義済み・そのまま class に付けて使う）

新規ボタンは**まずこの 4 クラスの利用を検討する**こと。既存のレガシークラス
（`.btn-gold-submit` `.recruit-cta-btn` 等）は `ui-consistency.css` の overrides で
これと同じ見た目に揃えているため、既存画面の作り直しは不要。

| クラス | 役割 | 修飾子 |
|---|---|---|
| `.btn-primary-cta` | 保存・送信・応募・トーク送信など画面の主要アクション | `--full`（幅100%）/ `--pill`（角丸999px）/ `--sm`（min-h 40px） |
| `.btn-secondary-cta` | 補助導線・キャンセル・戻る | `--full` / `--pill` |
| `.btn-ghost-cta` | 「編集」「閉じる」等の小さなユーティリティ | — |
| `.btn-destructive-cta` | 削除・ログアウト（アウトライン）/ `--solid`（確定操作のベタ赤） | `--solid` |

**このクラスを付けるだけで**：
- 立体感（Primary は 3D、Secondary/Ghost/Destructive はフラット）
- アクセントトークン追従（テーマスイッチ対応）
- hover の明暗変化なし・active の沈み込みあり（§8b 準拠）
- 適切なタップ領域（min-height 44px 以上、Primary は 48px）

**それでも独自スタイルが必要なとき**は、必ず下の役割表と「深度の原則」に従うこと。


| 役割 | 色 | 深度 | レシピ |
|---|---|---|---|
| **Primary CTA**（保存・送信・応募・TALK。1画面に原則1つ） | アクセントグラデ `from-accent-grad-from to-accent-grad-to` + `text-on-accent(-strong)` | **立体**（inset ハイライト + ドロップ影） | `.btn-gold-submit` / `x-ui.button` が正 |
| **Secondary**（補助導線・チップ・キャンセル） | 薄い accent 面 `bg-accent/10` + 枠 `border-line-accent/40` + `text-accent-text` | **フラット** | `x-ui.button variant=outline` |
| **Ghost**（小さな編集・閉じる等） | 枠なし。`text-text-sub` → hover `text-accent-text` | **フラット** | MyPage ひとこと「編集」ボタンが正 |
| **Destructive**（削除・ログアウト） | danger トークン（赤アウトライン。確定操作のみベタ赤） | フラット（確定ボタンのみ立体可） | `.btn-logout` / `x-ui.button variant=danger` |
| **金銭・実績の表示**（ボーナス額・優良店・KEEP アクティブ） | ゴールド（`--color-gold-from/to`, `#f6d36a` 系） | **フラット** | **ボタン背景には使わない** |
| **感情アクション**（LIKE） | **固定ピンク（`#d670a2`）**。テーマには追従させない | アクティブ時のみグロー | `.fav-circle--like` |
| **注意喚起カード**（要対応・振込待ち・未登録） | 暖色アンバー（`#b45309` / bg `rgba(217,119,6,0.07)`） | フラット | 採用管理サマリーカード `.case-summary-card.is-action` |
| **Premium（ゴールド系）** | 濃ゴールド `#b8860b`（テキスト） + 淡ゴールド面 | フラット + 微グロー | `.plan-card--premium` / Premiumチップ |

**深度の原則：**
- 立体（3D）＝「押すと何かが起こる主要アクション」だけ
- ネオングロー＝「状態フィードバック」だけ（LIKE/KEEP アクティブ・focus ring・ナビ active）。装飾目的で光らせない
- **バッジは常にフラット**（ベタ塗り + 暗背景からの切り抜きリングのみ。グラデ・立体影は禁止）。
  通常カウント = accent ベタ / 未済・緊急・要対応 = danger ベタ（#e15c5c）
- それ以外はフラット

**hue の原則：**
- ポイント要素（ボタン・アイコン・バッジ・アクティブ状態）＝ `var(--accent)` 系（テーマ追従。ハードコード禁止）。
  **既定テーマは `amethyst`（紫）**（2026-07-12 に mauve_pink から変更。導線アイコンを紫で統一）
- アンビエント（枠線・面のうっすら紫）＝ アメジスト固定（`rgba(168,85,247,…)`）— 既存設計どおり
- 例外（テーマに追従させない意味色）：ゴールド＝金銭・Premium、赤＝未済/危険、アンバー＝要対応、**LIKE＝固定ピンク**、ヘッダー上のアイコン＝白

---

## 11. ライトモード（light-theme.css）／ プレミアムホワイト（premium-white.css）

ライト画面は薄ラベンダー基調（#f5f2fb）で、**SWIPE（`*/home*`）と公開共有ページ（`share/*`）以外の全画面**に適用される。
MyPage とプロフィール詳細（`cast.mypage.index` / `shop.mypage.index` / `cast.shopprofile.show` / `shop.castprofileview.show`）は
「プレミアムホワイト」テーマ（`premium-white.css`）で白基調 + 高級感の見え方に切り替わる。

### 判定と適用
`layouts/app-v2.blade.php` の以下フラグ:

```php
$isDarkPage = request()->is('*/home*') || request()->is('share/*');
$naturalPremiumWhite = request()->routeIs(
    'cast.mypage.index', 'shop.mypage.index',
    'cast.shopprofile.show', 'shop.castprofileview.show',
);
$naturalLightTheme = !$isDarkPage && !$naturalPremiumWhite;
// ページ標準テーマを適用。ユーザーのライト/ダーク切替は廃止済み。
$isPremiumWhite = $naturalPremiumWhite;
$isLightTheme = $naturalLightTheme;
```

body に `theme-light` / `theme-premium-white` クラスを付与。どちらも付かない画面はダーク表示。
上書きは `public/assets/css/light-theme.css` および `public/assets/css/premium-white.css` のみで完結させる
（画面ごとに `<style>` を書かない）。

### 章構成（`light-theme.css`）
| 章 | 対象 |
|---|---|
| §1 | トークン反転（Tailwind `@theme` + 旧レイアウト変数） |
| §2 | クローム（ヘッダー / サブヘッダー / ボトムナビ / サイドメニュー / トースト）は紫ダーク維持 |
| §3 | 汎用補正（入力系・main 内の白ハードコード） |
| §4 | SEARCH |
| §5 | TALK 入力欄 |
| §7 | モーダル・ポップオーバー |
| §8 | OFFICIAL / SUPPORT |
| §9 | TALK 詳細（面談モーダル・結果送信） |
| §10 | プロフィール KEEP 円形ボタン |
| §11 | 面談モーダル |
| §12 | 採用・入金管理（サマリー・ケースカード・CTA バー・shop-action-modal） |
| §13 | サイドメニュー配下ページ（settings・support・policy） |
| §14 | ハードコード白/薄グレー文字の一括補正 + `.app-toast` 基本CSS |

### 配色（ライト画面の正）
| 用途 | 値 |
|---|---|
| ベース面 | `#f5f2fb`（薄ラベンダー）／カード面は `#ffffff` |
| 本文 | `#241f33` |
| 補助 | `#5f5876` |
| 罫線 | `rgba(124, 58, 237, 0.16)` |
| アクセント文字 | `#7c3aed`（`--gold` は紫に置換） |

### 禁止事項
- ライト画面の Blade `@push('styles')` に `color: #fff` `#f5f5f5` `#a0a0a0` を直書きしない
- 万一混入した場合は §14 に追加して一括上書きする（個別 blade を編集しない）

---

## 12. タイトル方針（ヘッダー中央統一）

**方針**：深階層ページのタイトルは **ヘッダー中央に日本語** で表示。ページ内には h1 を置かない。

### 実装
- `resources/views/layouts/parts/header.blade.php` の `$jaByRoute` / `$jaByLast` / `$jaBySecond` マップで route → 日本語タイトルを解決
- タイトルは `.header-center-title`（absolute + translate で真ん中）に配置。左右アイコン群との重なりは max-width で回避、超過はエリプシス
- 和文用クラス `.header-title-ja`（`.is-long` で自動縮小）と英字用 `.header-title-serif` を切替
- トップ5画面（SWIPE / SEARCH / TALK / MYPAGE / KEEPS）と非対象ページは従来の英語ラベルのまま

### 禁止
- ページ内に大見出し h1 を新設（サブ見出し h2 は可）
- タイトル的な `.mypage-page-title` `.setting-title` `.support-form-title` などを新規に書かない（既存の掃除も進める）

---

## 13. 操作に必要な説明とオコジョガイド

**方針**：機能紹介や補足は `character_guide_settings` とガイドに集約する。申請条件・保存範囲・公開範囲・必須項目・通信失敗からの復旧など、判断や操作に必要な説明は該当する入力欄・ボタンの近くへ常時表示する。ガイドを閉じても重要な説明と機能への入口が残ること。

### 実装
- テーブル: `character_guide_settings`（`route_name` UNIQUE / `is_enabled` / `message`）
- サービス: `CharacterGuideService::getForRoute($routeName)` が現在のルートに対応するメッセージを返す
- 管理画面: `/admin/character-guide` で全画面の ON/OFF・文言を編集
- カタログの `group` 区分: `cast` / `shop` / `common`（設定・サポート）

### 禁止
- Blade に `p.page-lead` `.setting-lead` `.support-form-lead` を新規追加
- 説明文用の独自 CSS を Blade `@push('styles')` に書く

### 新規画面を追加したときの手順
1. `CharacterGuideService::CATALOG` に `route_name => ['label', 'group', 'default_enabled']` を追加
2. 開発者に SQL を渡す（`INSERT ... ON DUPLICATE KEY UPDATE` で route_name UNIQUE を利用）
3. 管理画面「オコジョガイド設定」でメッセージを確認・編集

---

## 14. 管理画面のモバイル対応（admin-mobile.css）

### 基盤
`layouts/admin.blade.php` で `admin.css` の後に `admin-mobile.css` を読み込む。全画面に効く。

### 主要パターン
| クラス／属性 | 効果 |
|---|---|
| `admin-table--stack` + `<td data-label="…">` | 640px以下で thead を隠し、1行=1カードの縦積みに変換 |
| `td.stack-actions` | 操作セル（ボタン群を全幅・縦積みで最下部に配置） |
| `admin-page-toolbar-filters` | 折り返しをやめ、1行の横スクロール（フィルタチップ・ソート） |
| `task-summary-row` | ダッシュボードのタスクチップ行も横スクロール化 |
| `admin-task-popover` | 520px以下では画面全幅のシート表示 |

### 使い方
- 新規テーブルは **必ず `admin-table--stack` を付ける**（PC表示は `admin.css` の従来通り）
- 各 `<td>` に `data-label="列名"` を付与（付けない td は「主見出しセル」扱いで左寄せ）
- 操作列は `class="stack-actions"` にする
- 空状態行（`<td colspan="…">`）は data-label 不要（自動でセンタリング）

### 禁止
- 管理画面の Blade 内に `<style>` でモバイル用 `@media` を書く（`admin-mobile.css` に集約）

---

## 15. グローバルトースト（`.app-toast`）

- `window.appToast(msg, variant, duration)` で表示（`variant`: `success` / `error` / `info`）
- CSS は `light-theme.css §14` に定義（ライト画面でもダーク画面でも読める濃色パネル + 白文字）
- 色分け:
  - success = 緑 `#059669`
  - error = 赤 `#dc2626`
  - info = アンバー `#b45309`

### 禁止
- 個別画面で `alert()` を使う（全て `window.appToast()` に置換）
- トーストの色を画面独自に上書きする


## 16. 入力保護・検索・アクセシビリティ（2026-09-13）

- ビューポートの拡大を制限しない。モーダルはラベル・フォーカス移動・Tab制御・背景の操作抑止・閉じた後の復帰を備える。
- 検索の一覧には結果件数・適用済み条件・並び順・距離の基準を表示する。0件時も条件変更・条件解除へ進める。
- 保存前の公開設定は「保存後に公開／非公開（未保存）」と表示する。申請ボタンには確定する操作を明記する。
- 入力メーターは必須と任意を区別し、非表示の項目を分母に含めない。
- エラー通知は利用者が閉じるまで表示する。入力エラーは該当箇所にも残す。成功・情報通知の既定表示時間は5秒。
- 登録の文章ドラフトはタブ単位・24時間。画像・書類・パスワードは対象外で、登録成功後に削除する。
- オフライン画面のみ外部CSSに依存しない最小のスタイルを同梱する。APIや画像にHTMLの代替を返さない。

- CSS読込順は Tailwind → 既存・ページ固有CSS → 共通UI補正 → ライト／プレミアムホワイト補正。新しい画面単位の上書きを追加する前に既存ルールを整理する。

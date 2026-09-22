# demo.misechoku.jp

## 店舗向け営業資料

店舗向けの紹介スライド、読み上げ原稿、営業マニュアルは [`docs/public-sales/`](docs/public-sales/) にあります。

[営業資料サイトを開く](https://mearin0424-tech.github.io/demo.misechoku.jp/)

## 自動テスト実行環境

本番は Plesk 上で動かしつつ、画面スモークテストは **本番 DB を使わずに別環境で実行** する前提です。

### 方針

- 本番の `.env` や MySQL を使わない
- テスト時は `.env.testing` と `database/testing.sqlite` を使う
- 画面スモークテストは `tests/Feature/Smoke` に集約する
- GitHub Actions でも同じ手順で実行する

### ローカル or サーバで必要な PHP 拡張

- `openssl`
- `mbstring`
- `sqlite3`
- `pdo_sqlite`
- `dom`
- `xml`
- `xmlwriter`

### 実行手順

```bash
composer install
composer test:smoke
```

`composer test:smoke` は次をまとめて実行します。

- `database/testing.sqlite` の作成
- `php artisan migrate:fresh --env=testing --force`
- `tests/Feature/Smoke` の実行

### Plesk での扱い

- 本番サーバ上で直接テストを流す場合も、必ず `.env.testing` を使う
- 本番 MySQL に向けたまま実行しない
- 可能なら GitHub Actions か Plesk のステージング環境で回す

---

## スマホにアプリとしてインストールする（PWA）

Chrome の「ショートカット」ではなく、**アプリとして**インストールするために以下を実施してください。

1. **アイコンの生成（必須）**  
   Chrome は 192x192 / 512x512 の **PNG** アイコンがないと「インストール可能」とみなされません。
   ```bash
   php artisan pwa:icons
   ```
   → `public/assets/images/pwa/icon-192.png` と `icon-512.png` が生成されます。

2. **HTTPS で配信する**  
   スマホで「アプリをインストール」を出すには **HTTPS** が必須です（localhost はデスクトップのみ）。

3. **インストール手順（Android Chrome）**  
   - サイトを開く → メニュー（⋮）→ **「アプリをインストール」** または **「ホーム画面に追加」**  
   - インストール後はアプリ一覧にアイコンが表示され、タップでブラウザのアドレスバーなしで開きます。

4. **iOS（Safari）**  
   - 共有 → **「ホーム画面に追加」** でスタンドアロン表示のアプリとして追加されます。

---

## PWA Push 通知のセットアップ

デスクトップ通知・アイコンバッジ・テスト送信を使う場合:

1. **パッケージのインストール**
   ```bash
   composer require minishlink/web-push ^8.0
   ```

2. **VAPID キーの生成と .env 設定**
   ```bash
   php artisan push:vapid
   ```
   表示された `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` を .env に追加する。

3. **マイグレーション**
   ```bash
   php artisan migrate
   ```

4. **テスト手順**
   - ブラウザでアプリを開く（HTTPS または localhost）
   - ヘッダーのベルアイコン → お知らせポップアップを開く
   - 「通知を有効にする」をクリック → 許可
   - 「テスト通知を送る」をクリック → デスクトップに通知が表示される
   - PWA としてインストールしている場合はタブ/アイコンにバッジも表示される

# 営業動画スライドのGitHub Pages公開

`main`ブランチへ `docs/public-sales/` の変更が反映されると、GitHub ActionsがこのフォルダだけをGitHub Pagesへ公開します。

公開対象：

- `index.html`（店舗向け動画スライド・トップページ。読み上げ台本・制作メモも同ファイル内に集約）
- `shop-sales-manual.html`

`docs/internal/` に置くデモ環境の設定資料、テスト結果、QA画像と、アプリ本体は公開物に含めません。

## GitHubで最初に行う設定

1. リポジトリの `Settings` → `Pages` を開く。
2. `Build and deployment` の `Source` を `GitHub Actions` にする。
3. `Actions` で `Publish sales storyboard to GitHub Pages` を開き、必要なら `Run workflow` を実行する。

公開先は通常、次のURLです。

`https://idealinvestmentsjapan.github.io/demo.misechoku.jp/`

以後は対象ファイルを `main` ブランチへ反映するたびに自動更新されます。

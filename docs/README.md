# docsの配置ルール

このディレクトリは、公開範囲をフォルダで判別できるように分けています。

## `public-sales/`

店舗や営業先へ公開できる資料だけを置きます。GitHub Pagesは、このフォルダだけを配信します。

- `index.html`：店舗向け動画スライド
- `shop-introduction-storyline.md`：読み上げ原稿
- `shop-sales-manual.html`：店舗営業マニュアル

アプリの仕様を資料へ反映するときは、同じリポジトリ内のBlade、サービス、`database/mock_demo.sql`、`DESIGN.md`を確認します。

## `internal/`

開発・運用・QA・デモ環境の設定資料を置きます。GitHub Pagesでは公開しません。

新しい資料を外部公開する場合は、内容に実在の会員情報、認証情報、デモ環境の設定、内部向け手順が含まれないことを確認してから `public-sales/` に追加します。

# ミセチョク UI/UX 検証レポート
検証日：2026-09-12

## 結論と確認範囲

最優先は、検索条件の欠落、共通画面のロール別導線、トーク送信失敗時の復旧、登録フォームの誤操作です。配色や装飾の調整に先行して修正することを推奨します。

キャスト・店舗・管理者のルート、共通レイアウト、登録、検索、AI診断、トーク、プロフィール、求人編集、採用・入金管理、通知、エラー画面、PWAをコードで確認しました。157個のBladeファイルを一覧化し、主要画面と共通部品を重点的に精査しました。157画面すべてを実操作したという意味ではありません。

- 公開デモはブラウザで ERR_INVALID_AUTH_CREDENTIALS となり表示できませんでした。ログイン後の実操作、スクリーンショット、実効コントラスト、実端末でのはみ出し、表示速度は未確認です。
- PHP 336ファイルを構文解析し、335件正常・1件に構文エラーを確認しました。実行PHPは8.4.20です。本番PHPでの互換性やBlade描画成功を保証する確認ではありません。
- フロントエンドJSとService Worker計25ファイルは構文チェックを通過しました。
- DBに接続せず、5件の問題を実装から切り出したJavaScriptと模擬入力で再現しました。ブラウザ全体を通したE2Eテストではありません。
- DESIGN.md、AUTO-TEST.md、database/mock_demo.sqlの関連スキーマを参照しました。DB操作・Git操作・アプリケーション修正は実施していません。DBを更新するFeature/Smokeテストも実行していません。

「確認済」はコード上の事実、「切り出し再現」は対象処理の実行確認、「実機確認要」は表示や利用者への影響を実端末で確かめる必要がある指摘です。
P1＝主要操作の成立・入力保護に関わる問題、P2＝迷い・誤操作・離脱を減らす改善、P3＝表示品質・保守面の改善。優先度は本レビューの判断です。

## 最優先の修正

### 1. [P1・切り出し再現] キャスト検索の条件が転送時に消える

検索画面の通常URL /cast/search/list で検索すると、JSは /cast/search?keyword=...&sort=... に移動します。そのルートはクエリを引き継がず /cast/search/list へリダイレクトします。キーワード・並び順・絞り込みを指定しても結果に反映されない経路です。

改善：検索先を正規ルートに統一し、互換リダイレクトでもクエリを保持する。
完了条件：通常画面からキーワード・並び順・複数条件を変更し、URL・入力欄・結果が一致する。

根拠：[public/assets/js/search-detail.js:499](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/search-detail.js:499>)、[routes/web.php:680](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/routes/web.php:680>)

### 2. [P1・切り出し再現] 時給・ボーナスのスライダーが検索に渡らない

スライダーは hourly_wage / reward のhidden入力へ値を入れますが、検索パラメータ生成はradio・checkbox・select・距離のみを収集しています。画面上は値が変わっても、この2条件は送信されません。「クリア」も該当hidden値とスライダーをリセットしていません。No.1とは独立した不具合です。

改善：送信対象フィールドを明示するかFormDataで一元化し、クリア・条件数・表示ラベルも同じ状態から更新する。
完了条件：時給5,000円以上などを選ぶとクエリに値が入り、クリアで両方とも「指定なし」に戻る。

根拠：[resources/views/casts/parts/detail-search-modal.blade.php:298](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/casts/parts/detail-search-modal.blade.php:298>)、[public/assets/js/search-detail.js:428](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/search-detail.js:428>)、[public/assets/js/search-detail.js:466](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/search-detail.js:466>)

### 3. [P1・確認済] キャストが共通設定画面を開くと、下部ナビが店舗用になる

下部ナビのロールは「URLがcast/*か、それ以外か」で決まります。/setting/account や /setting/notification はキャストでログインしていてもshop扱いになります。店舗ログインがない場合、下部ナビから店舗ログインへ送られ、有効期限切れと案内される経路です。

改善：ログイン中のロールを優先してナビゲーションを生成する。未ログイン・管理者も明示的に扱う。
完了条件：キャストで設定・規約・サポートを開いても、下部の各リンクがキャスト領域を指す。

根拠：[resources/views/layouts/app-v2.blade.php:170](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:170>)、[resources/views/layouts/app-v2.blade.php:845](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:845>)、[app/Http/Middleware/ShopAuth.php:17](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/app/Http/Middleware/ShopAuth.php:17>)

### 4. [P1・確認済] 通信失敗したトークを復旧しにくい

送信開始時に入力欄を空にし、通常のネットワーク障害・サーバーエラーでは吹き出しにエラーアイコンを付けるだけです。本文復元はスカウト上限とNGワードの場合に限られます。失敗した本文は画面内には残りますが、再送ボタンがなく、再読み込みで失われる可能性があります。

改善：「送信中／送信失敗／送信済み」を文字でも表示し、失敗した本文の保持・編集・再送を用意する。送信結果が不明な場合の重複防止も設計する。
完了条件：オフライン・419・500で文章を失わず、復旧後に同じメッセージを重複させず送れる。

根拠：[public/assets/js/talk-room.js:219](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/talk-room.js:219>)、[public/assets/js/talk-room.js:260](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/talk-room.js:260>)

### 5. [P1・切り出し再現] パスワードを表示すると、一時保存対象に入る

登録フォームはtype=passwordを一時保存から除外しますが、「表示」ボタンでtype=textへ変更します。その状態で入力・変更イベントが起きると、passwordがsessionStorageに平文で保存されます。ダミー値で保存されることを確認しました。実際の認証情報は使用していません。

改善：現在の表示typeではなくnameや機密フィールド指定に基づいて除外する。既存ドラフトからもパスワードキーを除去する。
完了条件：表示／非表示を切り替えても、passwordとpassword_confirmationがドラフトに含まれない。

根拠：[public/assets/js/register-wizard.js:67](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:67>)、[public/assets/js/register-wizard.js:144](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:144>)

### 6. [P1・切り出し再現] 日本語変換確定のEnterで次へ進む・検索される

登録フォームのEnter処理にIME変換中の除外がなく、変換確定で次の項目へフォーカスが移ります。検索欄にも同種の処理があります。登録側はisComposing=trueの模擬イベントでフォーカス移動を再現しました。端末ごとの発生条件は実機確認が必要です。

改善：変換中のEnterを除外し、必要なブラウザ互換処理も合わせる。
完了条件：日本語入力の確定では項目移動・検索・送信が発生せず、確定後のEnterだけが所定の操作になる。

根拠：[public/assets/js/register-wizard.js:285](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:285>)、[public/assets/js/search-detail.js:523](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/search-detail.js:523>)

## 操作設計の改善

### 7. [P2・切り出し再現] AI診断の選択肢を連打すると質問が飛ぶ

回答後250msの間、同じ選択肢が押せる状態です。2回押すと回答と次の質問への予約が2件作られます。通信中ガードは診断の回答確定には働きません。

改善：回答を受け取った瞬間に選択肢を無効化し、質問IDごとに1回答だけ受け付ける。
完了条件：連打しても1問だけ進み、回答数が質問数と一致する。

根拠：[public/assets/js/ai-chat.js:176](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/ai-chat.js:176>)、[public/assets/js/ai-chat.js:204](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/ai-chat.js:204>)

### 8. [P2・確認済] 詳細から戻ると、検索条件や元の一覧を失う

店舗・キャスト詳細の戻り先は固定の検索URLです。絞り込み条件・保存済みタブ・スクロール位置を保持せず、スワイプから詳細を開いた場合も検索へ戻ります。「前の画面へ戻る」というラベルとも一致しません。

改善：安全な同一サイト内の戻り先と一覧状態を保持し、直接アクセス時だけ親画面へフォールバックする。
完了条件：検索／保存済み／スワイプのどこから開いても、元の文脈へ戻れる。

根拠：[resources/views/layouts/app-v2.blade.php:46](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:46>)、[resources/views/layouts/parts/header.blade.php:145](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/parts/header.blade.php:145>)

### 9. [P2・確認済] ガイドを閉じるとAI相談への入口も消える

AI入口が検索画面のキャラクターに集約されています。ガイドを閉じるとそのページで非表示状態が保存され、管理設定の無効化・文言未設定でも入口が非表示になります。

改善：検索バー付近に常設の「AIでお店を探す」を置き、案内の表示設定と機能への入口を独立させる。
完了条件：ガイドを閉じた後、または管理側でガイドを無効にした状態でもAI診断へ移動できる。

根拠：[resources/views/common/search/index.blade.php:30](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/common/search/index.blade.php:30>)、[resources/views/layouts/parts/character-guide.blade.php:4](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/parts/character-guide.blade.php:4>)、[public/assets/js/character-guide.js:77](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/character-guide.js:77>)

### 10. [P2・設計見直し] 必須の説明までキャラクターに集約しない

DESIGN.mdはページの説明文をガイドへ集約する方針です。閉じられる案内に操作条件まで依存すると、必要な説明を再確認できません。実際には採用・入金カードなど、適切なインライン説明も存在します。

改善：本人確認が必要な理由、公開条件、申請条件、入力例などは操作の近くに残す。キャラクターは補足・使い方へ限定し、設計定義を調整する。
完了条件：ガイドがなくても主要手続きを理解し完了できる。

根拠：[DESIGN.md:329](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/DESIGN.md:329>)、[resources/views/layouts/parts/character-guide.blade.php:4](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/parts/character-guide.blade.php:4>)

### 11. [P2・確認済／実機確認要] ページ拡大を制限している

共通viewportにmaximum-scale=1.0とuser-scalable=noがあります。ブラウザによって扱いは異なりますが、拡大を制限する指定は外すべきです。

改善：拡大を許可し、大きな文字設定でも固定ヘッダー・入力欄・申請ボタンが使えるようにする。
完了条件：200%相当の拡大やOSの文字サイズ変更で主要操作が欠けない。

根拠：[resources/views/layouts/app-v2.blade.php:181](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:181>)

### 12. [P2・確認済] モーダルに入った後のキーボード操作が不十分

詳細検索では表示・スクロール制御・Escape終了はありますが、開いた直後のフォーカス移動、モーダル内へのフォーカス制限、閉じた後の復帰を確認できません。aria属性だけでは操作上の制御になりません。

改善：共通モーダルでフォーカス管理、背景操作の抑止、ラベル、終了方法を統一する。画像拡大や金銭関係のモーダルにも展開する。
完了条件：Tabが背景へ抜けず、閉じると起点のボタンに戻る。

根拠：[public/assets/js/search-detail.js:59](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/search-detail.js:59>)、[resources/views/layouts/app-v2.blade.php:885](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:885>)

### 13. [P2・確認済] 検索0件で次の行動が分からない

「該当する相手は見つかりませんでした。」だけで、条件の解除や検索範囲変更へ直接進めません。opacity-40も指定されています。

改善：指定条件を表示し、「条件をクリア」「条件を変更」「エリアを広げる」などの次の行動を提示する。
完了条件：0件画面から1操作で再検索の準備ができる。

根拠：[resources/views/common/search/index.blade.php:72](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/common/search/index.blade.php:72>)

### 14. [P2・確認済] 現在の検索条件・並び順・件数が一覧で把握しにくい

通常の検索バーはキーワードとアイコン中心です。条件の内容を一覧上で示すsearch-condition-summaryは共通JSが参照するものの、確認した現行フィルター部品には存在しません。距離の探索拠点もマイページへ移されており、どの地点の周辺か分かりにくい構成です。

改善：結果件数、現在の並び順、選択条件チップ、距離の基点を結果の直上で確認できるようにする。
完了条件：詳細フィルターを開かなくても、何の条件で表示されているか説明できる。

根拠：[resources/views/casts/parts/filter.blade.php:5](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/casts/parts/filter.blade.php:5>)、[resources/views/shops/search/parts/filter.blade.php:5](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/shops/search/parts/filter.blade.php:5>)、[public/assets/js/search-detail.js:415](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/search-detail.js:415>)

### 15. [P2・確認済] 登録エラー後だけ、段階式フォームが全展開になる

サーバーエラー表示があるとウィザード処理を中断し、全項目を表示します。入力中に覚えた画面構成が変わり、エラー一覧と修正項目の対応も追いにくくなります。

改善：ステップ構造を保ち、最初のエラーを含むステップを開く。項目直下のエラーと上部一覧をリンクさせる。
完了条件：入力エラー後も現在位置が分かり、修正項目に直接移動できる。

根拠：[public/assets/js/register-wizard.js:306](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:306>)、[resources/views/common/register.blade.php:117](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/common/register.blade.php:117>)

### 16. [P2・確認済] 入力完成度が「保存に必要な残り項目」を表していない

共通の完成度メーターはnameのある項目を数え、必須・任意を区別しません。CSS等で隠した通常入力も、disabledでなければ集計対象です。任意項目を埋めないと未完成に見える可能性があります。

改善：「必須あと2項目」と「任意プロフィール充実度」を分け、現在の求人種別に必要な項目だけを集計する。
完了条件：必須を満たせば登録可能と分かり、任意項目や非表示項目で迷わせない。

根拠：[public/assets/js/form-enhance.js:63](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/form-enhance.js:63>)、[resources/views/shops/recruit/edit.blade.php:645](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/shops/recruit/edit.blade.php:645>)

### 17. [P2・確認済] 登録の「自動一時保存」が実際の保存範囲を伝えていない

案内には「入力内容は自動的に一時保存されます」とありますが、画像・書類・パスワードは除外対象で、保存先はそのタブのsessionStorage、復元期限は24時間です。またドラフト削除は登録フォーム上のsuccess表示が条件ですが、正常登録はチュートリアルへmessage付きで転送されます。

改善：画像等は再選択が必要なことを伝え、正常登録後に確実にドラフトを消す。長い求人編集にも必要に応じて下書きを検討する。
完了条件：復元可能な範囲が明示され、登録後に同じタブで登録画面を開いても以前の個人情報を復元しない。

根拠：[public/assets/js/register-wizard.js:67](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:67>)、[public/assets/js/register-wizard.js:91](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:91>)、[public/assets/js/register-wizard.js:120](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/register-wizard.js:120>)、[app/Http/Controllers/Common/RegistrationController.php:196](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/app/Http/Controllers/Common/RegistrationController.php:196>)

### 18. [P2・確認済] AI相談の見た目と提供機能が一致しない

チャット画面に入力欄を残して「実装中」と無効化しています。実際は5問の選択式診断で、エリアも都内4候補か「問わない」に固定されています。利用者が期待する自由相談や対象地域とずれる可能性があります。

改善：現状に合わせて「5問でお店診断」と案内し、無効な入力欄を省く。対応エリアを明記するか、検索マスター・探索拠点から候補を作る。
完了条件：画面を見ただけで入力方法と対象エリアが分かる。

根拠：[public/assets/js/ai-chat.js:46](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/ai-chat.js:46>)、[public/assets/js/ai-chat.js:310](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/ai-chat.js:310>)

### 19. [P2・確認済] AIの通信失敗時に、5問を最初から答え直す

失敗時の選択肢は「もう一度診断する」のみです。回答を保持して同じ条件で再試行する導線、1問前に戻って変更する導線もありません。

改善：「同じ条件で再試行」「条件を変更」「通常検索で探す」を用意する。タイムアウト時も待ち続けないようにする。
完了条件：一時的な通信失敗で回答済みの内容を入力し直さずに済む。

根拠：[public/assets/js/ai-chat.js:269](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/ai-chat.js:269>)

### 20. [P2・確認済] ボーナス申請の確定ボタンが「完了」

チェックを終えた確認画面の「完了」が実際には入金申請を送信します。単に画面を閉じる操作と区別しにくい文言です。

改善：「この内容でボーナスを申請する」とし、対象店舗・金額・条件を同じ画面で確認できるようにする。完了後には申請済みと次の対応者を示す。
完了条件：ボタンの文言だけでも送信される操作であることが分かる。

根拠：[resources/views/casts/mypage/employment.blade.php:566](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/casts/mypage/employment.blade.php:566>)、[resources/views/common/talk/room.blade.php:849](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/common/talk/room.blade.php:849>)

### 21. [P2・確認済] 求人の公開切替が、保存前から「公開中」と表示される

公開スイッチのchangeで「公開中／非公開」を即時更新しますが、サーバーへの反映は別の保存操作です。現在の公開状態と編集中の設定が混ざります。

改善：「保存後に公開」「未保存の変更あり」を表示するか、明確な独立操作として即時保存する。保存成功後に確定表示へ変える。
完了条件：スイッチを触っただけの状態を、公開済みと誤認しない。

根拠：[resources/views/shops/recruit/edit.blade.php:1180](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/shops/recruit/edit.blade.php:1180>)、[resources/views/shops/recruit/edit.blade.php:1195](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/shops/recruit/edit.blade.php:1195>)

### 22. [P2・確認済] スタッフにもオーナー専用の「ステータス管理」が表示される

求人編集への主要リンクは@shopownerで制限されていますが、すぐ下の「ステータス管理」リンクは同じ編集先なのに制限がありません。権限のないスタッフが進めない導線です。

改善：オーナー専用操作の表示を統一し、スタッフには閲覧用の状態と「オーナーに変更を依頼」を示す。
完了条件：スタッフの通常画面に、押すと権限拒否になる管理リンクを置かない。

根拠：[resources/views/shops/mypage/index.blade.php:237](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/shops/mypage/index.blade.php:237>)、[resources/views/shops/mypage/index.blade.php:259](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/shops/mypage/index.blade.php:259>)、[routes/web.php:606](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/routes/web.php:606>)

### 23. [P2・確認済] エラーが短時間で消える通知とブラウザ警告に分散している

共通トーストの既定表示は成功・エラーとも2.2秒で、後の通知が前の内容を上書きします。トークの各種操作にはwindow.alertも多数残っています。

改善：成功は短いトースト、修正が必要なエラーは項目付近や操作パネルに残す。重要な確認は対象・操作内容を示す共通ダイアログへ統一する。
完了条件：読了前に重要エラーが消えず、何を修正すべきか再確認できる。

根拠：[public/assets/js/app-toast.js:25](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/app-toast.js:25>)、[public/assets/js/talk-room.js:1233](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/js/talk-room.js:1233>)

## 表示・性能・整合性

### 24. [P3・CSS確認済／実機確認要] 検索一覧の補助情報が小さい

現行の検索一覧で使われる更新時刻とマッチ情報に10pxの指定があります。実効寸法・配色はブラウザ未確認ですが、比較に必要な補助情報の読みやすさを重点確認すべきです。タップ領域には44pxの共通基盤があり、その適用状態も実機で確認します。

改善：重要な補助情報は読みやすいサイズへ引き上げ、主要タップ領域を44px程度に統一する。色の薄さで情報の優先度を付けすぎない。
完了条件：狭い画面、屋外、大きな文字設定でも時給・場所・更新時刻を読め、隣の操作を誤タップしない。

根拠：[public/assets/css/search.css:2403](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/css/search.css:2403>)、[resources/views/casts/parts/list-item.blade.php:83](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/casts/parts/list-item.blade.php:83>)、[resources/css/app.css:223](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/css/app.css:223>)

### 25. [P2・確認済] CSSの上書きが多く、読み込み順も定義と異なる

ページCSSを出す@stack('styles')の後に、Tailwindを含む@stack('head-styles')が出ます。プロジェクトが定める「Tailwindをページ固有CSSより前にする」と異なります。home.cssは約193KB・!important 933箇所、light-theme.cssは約88KB・!important 395箇所です。数値は未圧縮ソースの規模で、実転送量や表示速度の測定ではありません。

改善：読み込み順を正し、ページ固有の色・影・寸法を共通部品とトークンに寄せる。旧スタイルと後勝ち補正を段階的に整理する。
完了条件：同じボタン・入力・カードがページを変えても同じ役割と表示を保ち、代表画面の見た目が回帰しない。

根拠：[resources/views/layouts/app-v2.blade.php:788](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:788>)、[resources/views/components/ui/assets.blade.php:3](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/components/ui/assets.blade.php:3>)、[public/assets/css/home.css:1](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/css/home.css:1>)、[public/assets/css/light-theme.css:1](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/assets/css/light-theme.css:1>)

### 26. [P2・確認済／性能測定要] 検索が全件取得・全件描画になる

キャスト／店舗の検索はget()で候補を集め、PHPで追加の絞り込み・並び替えをして、画面で全件を描画します。確認した一覧にページ送り・追加読み込みはありません。件数増加時の応答と長いスクロールが懸念されます。現時点で遅いと実測したものではありません。

改善：可能な絞り込みをDB側へ寄せ、件数表示とページングまたは「もっと見る」を導入する。一覧位置の復元も合わせる。
完了条件：想定データ件数と遅い回線で表示時間・画像負荷・戻り操作を計測し、定めた目標を満たす。

根拠：[app/Http/Controllers/Casts/SearchController.php:173](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/app/Http/Controllers/Casts/SearchController.php:173>)、[app/Http/Controllers/Shops/SearchController.php:188](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/app/Http/Controllers/Shops/SearchController.php:188>)、[resources/views/common/search/index.blade.php:72](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/common/search/index.blade.php:72>)

### 27. [P2・確認済／実機確認要] オフライン時に別の内容へ置き換わる可能性がある

Service Workerは同一オリジンのGET全般を扱い、失敗時は要求したURLのキャッシュ、次にルートページのキャッシュを返します。JSONや画像のGETにもページHTMLを返し得る構造です。オフラインであることが利用者へ明確に伝わりません。

改善：画面遷移・API・画像を区別し、ナビゲーションにだけ専用オフライン画面を返す。再接続案内と入力保持を組み合わせる。
完了条件：通信断で画面が突然ホーム内容にならず、APIや画像にも不適切なHTMLを返さない。

根拠：[public/sw.js:52](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/sw.js:52>)、[public/sw.js:75](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/public/sw.js:75>)

### 28. [P3・確認済] デザイン定義と実装の正解がずれている

DESIGN.mdはCookieによるダーク強制を説明しますが、現行レイアウトはユーザー切替を廃止しています。ホームはダーク、検索はライト、マイページは別の白テーマで、画面単位の例外が積み重なっています。テーマ差自体は不具合ではありませんが、共有部品のコントラストや一貫性の検証負荷を増やします。

改善：採用するテーマ運用を定義へ反映し、3D装飾・ゴールド・紫の用途と各部品の明暗ペアを整理する。業務画面での読みやすさを優先する。
完了条件：デザイン文書から現在の画面の配色・振る舞いを一意に判断できる。

根拠：[DESIGN.md:256](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/DESIGN.md:256>)、[resources/views/layouts/app-v2.blade.php:142](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/resources/views/layouts/app-v2.blade.php:142>)

## UI以外で確認した技術上の問題

### A. [確認済] PHPの構文エラーが1件

IdentityRepositoryInterface.phpのstore宣言直後に不要なIdentityがあり、構文解析に失敗します。リポジトリ内でこのインターフェースを使用する箇所は検索で見つからず、現行画面の500エラーとしては再現していません。読み込まれた場合の障害と、全体構文チェック失敗の原因です。

改善：宣言を正し、不要な旧インターフェースなら利用・モデルの整合を確認して整理する。

根拠：[app/Repositories/Member/IdentityRepositoryInterface.php:9](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/app/Repositories/Member/IdentityRepositoryInterface.php:9>)

### B. [確認済] テスト方針と現在のファイル構成が一致していない

composer test:smokeはmigrate:freshによるテストDB準備を前提にしますが、現ワークスペースにdatabase/migrationsはありません。AUTO-TEST.mdで最優先とされるBillingManagementService／PlanSubscriptionServiceの専用テストファイルも、現在のtests一覧では確認できません。その他のテストによる部分的カバーはあり得ます。実際のテスト実行結果を示す指摘ではありません。

改善：MySQLスキーマに整合するテストDB準備手順を整え、請求・振込・Premiumの状態遷移と、今回見つかった検索・ロール別導線・IME・連打・通信失敗を検証対象に加える。

根拠：[AUTO-TEST.md:23](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/AUTO-TEST.md:23>)、[composer.json:42](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/composer.json:42>)、[tests/Support/SmokeRouteMatrix.php:1](<C:/Users/Lenovo/OneDrive/デスクトップ/mearin0424-tech/demo.misechoku.jp/tests/Support/SmokeRouteMatrix.php:1>)

## 維持したい実装

- 採用・入金カードに「要対応／待ち（店舗・運営・キャスト）／完了」と停滞日数がある。次の対応者を明示する仕組みを他の手続きにも展開できる。
- 編集フォームに未保存で離れる際の確認がある。
- 本人確認・店舗書類の審査一覧は、スマホ用カード表示と列ラベルを備える。
- 管理者の振込完了操作には口座・金額の確認と証跡添付がある。
- 共通のフォーカス表示、タップサイズ、トースト、フォーム部品の基盤は存在する。新設を増やすより適用漏れと例外を減らす方針が適している。

## 実画面で残っている確認

| 領域 | 次回確認すること |
|---|---|
| 端末・表示 | 320／375／390／768／1280px、文字拡大、実効コントラスト、横はみ出し、セーフエリア |
| 入力 | iOS Safari／Android Chrome、日本語変換、キーボード表示時のトーク欄、日付入力、画像の回転・大容量・形式違い |
| 遷移 | 直接URL、戻る・進む、検索・保存済み・スワイプへの復帰、各ロールの共通設定 |
| 状態 | 初回、空データ、大量データ、非公開求人、スタッフ権限、審査中・差戻し、ボーナス申請不可理由 |
| 通信 | 遅延、オフライン、419、422、429、500、二重押下、送信中に画面を離れる |
| 管理 | 入金報告・承認・振込の各段階、複数担当者での競合、失敗時の復旧、通知と表示状態の一致 |

推奨着手順：No.1〜6 → No.7〜23の主要導線 → No.24〜28の表示・性能整備。認証済み検証環境を用意できた段階で、上表の実操作を行い、未確認部分を更新してください。

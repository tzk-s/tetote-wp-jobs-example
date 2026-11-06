# tetote-wp-jobs-example

[![test](https://github.com/tzk-s/tetote-wp-jobs-example/actions/workflows/test.yml/badge.svg)](https://github.com/tzk-s/tetote-wp-jobs-example/actions/workflows/test.yml)

募集職種を WordPress のカスタム投稿で管理し、管理画面で「募集中／募集終了」を切り替えると公開側の一覧・詳細に反映される、プラグインの公開用サンプルです。

> このリポジトリは [tetote-application-example](https://github.com/tzk-s/tetote-application-example) とは連携しません。同じ募集情報を題材に、WordPress 側の登録・保存・表示だけを独立して確認できる公開用サンプルです。

自主制作サイト（架空企業の採用サイト「TETOTE」）では、募集情報を WordPress のテーマで管理しています。このリポジトリは、その考え方（職種 ID・募集終了の扱い）をもとに、**公開用にプラグインとして新しく書いたもの**です。元サイトのテーマのコードは含みません（詳しくは「[元サイトとの関係](#元サイトとの関係)」）。

- 元の公開サイト（架空の企業「TETOTE」の採用サイト）: https://recruit-tetote.preview-dev.website/

> 募集データは架空のものです。実在の求人ではありません。

## ブラウザーで試す（WordPress Playground）

インストール不要で、ブラウザーの中だけで一時的な WordPress が起動します。共有のユーザー名・パスワードはありません。変更はそのブラウザーの中だけで、閉じると消えます。

1. **[管理画面を試す](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tzk-s/tetote-wp-jobs-example/main/playground/blueprint-admin.json)** … 管理者としてログインした状態で「募集職種」の一覧が開きます
2. **[公開側の表示を見る](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tzk-s/tetote-wp-jobs-example/main/playground/blueprint-public.json)** … 募集一覧のページが開きます

2 つのリンクは、それぞれ別の WordPress を起動します。「管理画面を試す」で変えた内容は、「公開側の表示を見る」には反映されません（こちらは毎回初期データで開きます）。変更の反映を確かめるときは、「管理画面を試す」で開いた同じ画面のまま、公開側へ移動してください。

おすすめの確認手順:

1. 管理画面で「ソリューション営業」を開き、右側の「募集状態と職種 ID」で「募集中」のチェックを外して「更新」を押す
2. 画面上部のサイト名から公開側を開き、メニューの「募集一覧」を開く
3. 「ソリューション営業」が一覧の後ろに回り、「募集終了」と表示されていること、詳細ページに終了の案内が出ることを確認する

Playground は起動のたびに GitHub の main ブランチからプラグインを読み込みます（Blueprint は `playground/` にあります）。デモでは、このサンプルで使わない管理メニュー（投稿・メディア・コメント・ツール）と WordPress の初期サンプル記事を隠しています（`tetote-jobs/demo/admin.php`。プラグイン本体には含みません）。社内ネットワークなどで外部サイトが制限されている場合は開けないことがあります。初回の起動には数十秒かかります。

## このサンプルで見てほしいこと

- **カスタム投稿とフィールドを、プラグインに頼らず PHP で定義している。** `register_post_type` と `register_post_meta` で登録し、入力欄はメタボックスで作っています。項目の定義（項目名・入力の種類・更新担当者向けの説明）は `includes/fields.php` の 1 か所だけに置き、入力欄・保存・表示はそこから作ります。
- **保存時の確認。** nonce（この編集画面から送られたか）、自動保存・リビジョンでないか、`current_user_can( 'edit_post' )`（その投稿を編集できるか）を確かめてから保存します。値は項目の種類ごとに `sanitize_text_field` / `sanitize_textarea_field` でそろえ、文字数の上限で切ります。定義にないキーは保存しません。`register_post_meta` にも同じ `sanitize_callback` を付け、この画面以外から保存されても同じ規則になるようにしています。
- **出力時のエスケープ。** 公開側も管理画面も、出力する場所に合わせて `esc_html` / `esc_attr` / `esc_url` / `esc_textarea` を使います。複数行の値はエスケープしてから改行を `<br>` にしています（順番を逆にするとタグが通るため）。
- **募集終了の表示制御。** 募集終了の職種は一覧から消さず、後ろへ回して「募集終了」と文字で示します。詳細ページも 404 にせず、終了したことと一覧への道を示します。外部に残ったリンクから来た人が迷わないようにするためです。
- **職種 ID を変えさせない。** 投稿のスラッグを職種 ID として URL に使うため、正しい形式の ID で初めて公開したときに ID を控え、以後は `wp_insert_post_data` で変更を元に戻します（下書きに戻した場合や、クイック編集などの経路も含む）。日本語のタイトルから自動で作られたスラッグなど、形式の合わない ID は一覧に出さず、管理画面の一覧で警告します。この場合は控えないので、公開したまま正しい ID へ直せます。
- **更新担当者向けの説明。** 各入力欄に説明を付け、「チェックを外すと募集終了になる」「公開後は職種 ID を変えられない」など、操作の結果を画面上で伝えます。

## 構成

```
.
├── .github/workflows/test.yml   # GitHub Actions での結合テスト・PHP の構文チェック
├── tetote-jobs/                 # プラグイン本体（このフォルダーを wp-content/plugins/ に置く）
│   ├── tetote-jobs.php          # プラグインのヘッダーと読み込み
│   ├── includes/
│   │   ├── fields.php           # 募集要項の項目の定義とサニタイズ
│   │   ├── post-type.php        # カスタム投稿とカスタムフィールドの登録
│   │   ├── query.php            # 募集職種の取得・並べ替え・募集中の判定
│   │   ├── admin.php            # 入力欄・保存処理・管理画面の一覧の列・職種 ID の固定
│   │   └── frontend.php         # 公開側の一覧（ショートコード）と詳細の表示
│   ├── assets/tetote-jobs.css   # 公開側の最小限のスタイル
│   └── demo/                    # デモ用（Playground からだけ読み込む）
│       ├── seed.php             # 架空データの投入
│       └── admin.php            # 管理画面のメニューを絞る・一時環境の案内
├── playground/                  # WordPress Playground の Blueprint（管理画面用・公開側用）
└── tests/
    ├── integration.php          # WordPress 上で動かす結合テスト
    ├── blueprint.test.json      # テスト用の Blueprint
    ├── blueprint.local.json     # 手元で起動するときの Blueprint
    ├── run.mjs                  # テストの実行（Playground CLI）
    └── lint-php.mjs             # PHP の構文チェック
```

読み始めるなら `includes/fields.php` → `includes/admin.php` → `includes/frontend.php` の順がおすすめです。

公開側の一覧は、テーマのテンプレートを差し替えず、固定ページにショートコード `[tetote_jobs]` を置いて表示します。詳細ページは、本文の前に募集状態、後ろに募集要項を足します。どのテーマでも同じ内容が出るようにするためです。

## 手元で動かす・テストする

Node.js 24.22 以上と npm 11.16 以上があれば（npm 11.16 と Node.js 24.18 以上は、WordPress Playground の CLI が依存するパッケージの要件です）、WordPress や PHP、データベースを別に用意せずに動きます（WordPress Playground の CLI が WordPress 一式をダウンロードして起動します）。

```bash
npm ci            # 依存のインストール（Playground CLI）
npm start         # http://127.0.0.1:9400/ で起動（架空データ入り・管理者でログイン済み）
npm test          # 結合テスト（毎回まっさらな WordPress で実行）
npm run lint:php  # PHP の構文チェック（手元に PHP 8.0 以上が必要）
```

`npm test` は、プラグインを有効化した WordPress の中で `tests/integration.php` を実行し、結果を表示します。1 件でも失敗すると終了コードが 1 になります。

GitHub Actions（`.github/workflows/test.yml`）でも、push と PR のたびに同じテストを WordPress 6.5 と最新版で実行しています。結果は冒頭のバッジと Actions のタブから確認できます。

普段の WordPress に入れる場合は、`tetote-jobs/` フォルダーを `wp-content/plugins/` に置いて有効化し、固定ページに `[tetote_jobs]` を書いてください。パーマリンクは「投稿名」などの URL が読める形式を想定しています。

## 使用技術・実行環境

| 用途 | 使用しているもの |
| --- | --- |
| 言語 | PHP 8.0 以上（WordPress 6.5 以上） |
| CMS | WordPress のコア機能のみ（カスタム投稿・メタボックス・ショートコード）。Advanced Custom Fields などのプラグインやフレームワークは使っていません |
| デモ・テストの実行 | WordPress Playground（ブラウザー版・CLI 版） |

## 元サイトとの関係

| | 元サイト | このサンプル |
| --- | --- | --- |
| 形 | WordPress のテーマの一部 | 単独で動くプラグイン |
| フィールド | カスタムフィールドのプラグインで定義 | `register_post_meta` とメタボックスで定義 |
| 一覧・詳細 | テーマのテンプレート（元サイトのデザイン） | ショートコードと本文への追加（テーマに依存しない） |
| 応募フォームとの連携 | あり（職種 ID を応募フォームへ引き継ぐ） | なし。応募の受付・メール送信は対象外 |

職種 ID をスラッグにする、公開後は変えない、募集終了は一覧に残して明示する、募集中を先頭に並べる、という方針は元サイトと同じです。コードは公開用に新しく書いたもので、元サイトのテーマから抽出した部分はありません。ディレクトリ構成・関数名・項目も元サイトとは異なります。

## 確認済みの内容と未確認の事項

確認済み（2026 年 11 月 4 日、Linux、Node.js 22.23.4、Playground CLI 3.1、PHP 8.3。WordPress は CLI が既定でダウンロードする最新版ではなく、ネットワークの制限のため npm で配布されている WordPress 6.5 のビルドを使用）:

- `npm test` の結合テスト 27 件がすべて成功すること。内訳: 送信値のサニタイズ 6 件、保存の可否（nonce・権限）4 件、保存の流れ 3 件、職種 ID の固定 7 件、取得と並び 3 件、表示とエスケープ 4 件（職種 ID の固定の 4 件は 2026 年 11 月 6 日に追加。下書きに戻した場合、形式外の ID で公開した場合、ゴミ箱から戻した場合）
- 職種 ID の固定を「公開中のときだけ」に戻すと、追加したテストが失敗すること
- GitHub Actions（Node.js 24）で、WordPress 6.5 と最新版の両方で `npm test` がすべて成功すること
- nonce の確認を外すと、該当するテストが失敗して終了コードが 1 になること（テストが実際に検出できることの確認）
- PHP の構文チェック（`php -l`、9 ファイル）
- 手元で起動した WordPress（テーマは Twenty Twenty-Four）で、Chromium から次の操作
  - 管理画面で「ソリューション営業」の「募集中」を外して更新 → 公開側の一覧で後ろに回り「募集終了」と表示されること、詳細ページに終了の案内が出ること
  - 再び「募集中」にすると、一覧の元の位置に戻ること
  - スマートフォン幅（iPhone 13 相当）で詳細ページに横スクロールが出ないこと
- 「ブラウザーで試す」のリンクから、playground.wordpress.net でデモが起動すること（作者のブラウザーで確認）

未確認:

- 管理画面の見た目（確認環境では管理画面の CSS・JavaScript を読み込めず、画面の装飾なしで操作のみ確認しました）
- Safari・Firefox・実機、スクリーンリーダーでの読み上げ
- Twenty Twenty-Four 以外のテーマでの表示

## 著作権・ライセンス

- Copyright (c) 2026 tzk-s. All rights reserved.
- このリポジトリはポートフォリオとして公開しているもので、オープンソースライセンスは付与していません。コードの再利用・再配布・他の制作物への組み込みは許諾していません（著作権は作者に留保します）。
- 内容を評価するために、clone して手元や WordPress Playground で実行・テストすることは問題ありません。
- なお、GitHub の利用規約により、公開リポジトリは GitHub 上で閲覧・fork できます。
- 依存ライブラリはそれぞれのライセンスに従います（`package-lock.json` 参照）。リポジトリにはライブラリ本体を含めていません。
- 画像・フォント・元サイトのデザイン素材は含めていません。

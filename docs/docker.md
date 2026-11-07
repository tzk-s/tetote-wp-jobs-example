# Docker で動かす（ローカル検証用）

WordPress Playground とは別に、Docker で「普通の WordPress」を手元に立てて確認するための手順です。Playground と違い、データはコンテナを止めても消えません（名前付きボリュームに保存されます）。

> ローカルでの確認専用です。本番・公開サーバーには使わないでください。`.env.example` のパスワードなどは架空の値です。

## この構成で使うもの

| 言葉 | この構成では |
| --- | --- |
| イメージ | 起動の元になる配布物。`wordpress:7.1-php8.5-apache`・`mariadb:11.8`・`wordpress:cli-2.12-php8.5` の 3 つ（Docker Hub の公式イメージ） |
| コンテナ | イメージから起動した実体。`docker compose down` で消しても、作り直せば同じ状態に戻る |
| サービス | `compose.yaml` に書いた役割の単位。`wordpress`（Web サーバー + PHP）・`db`（データベース）・`cli`（初期設定用の WP-CLI。普段は起動しない） |
| ボリューム | データの保存場所。`db_data`（データベース）と `wp_data`（WordPress 本体・アップロード）。コンテナとは別に残る |
| ポート | `127.0.0.1:8088` → コンテナの 80 番。自分の PC からだけ開ける。データベースのポートは外に出していない |
| バインドマウント | 手元の `tetote-jobs/` をそのままコンテナのプラグインフォルダーに見せる。読み取り専用（コンテナ側からは書き換えない） |

イメージのタグは、メジャー・マイナーまで固定しています（`latest` は使わない）。

- `wordpress:7.1-php8.5-apache` … WordPress 7.1 の安定版と PHP 8.5（2026 年 11 月 7 日時点で PHP 8.5.12）。Apache 込みで、追加の Web サーバーが要らない
- `mariadb:11.8` … MariaDB の長期サポート版（LTS）
- `wordpress:cli-2.12-php8.5` … WP-CLI 2.12。PHP を WordPress 側とそろえている
- 3 つとも arm64（Apple シリコンの Mac）を含むマルチアーキテクチャのイメージで、`platform` の指定は不要です

同じタグでも、セキュリティ修正が出ると中身（PHP の修正版など）が更新されます。取り込むときは `docker compose pull` のあと `docker compose up -d` を実行してください。これで新しくなるのは PHP と Apache です。WordPress 本体は最初の起動時にボリューム（`wp_data`）へコピーしたものを使い続けるので、管理画面の「更新」か `docker compose --profile tools run --rm cli wp core update` で更新します。

## 必要なもの

- Docker Desktop（Mac・Windows）または Docker Engine + Docker Compose v2（`docker compose version` で確認）
- 8088 番ポートが空いていること（使われている場合は下の「ポートを変える」）

## 起動する

```bash
cp .env.example .env                                         # 1 回だけ
docker compose up -d                                         # 起動（初回はイメージのダウンロードで数分かかる）
docker compose --profile tools run --rm cli sh /opt/tetote-docker/setup.sh   # 初期設定・架空データの投入
```

- 公開側: http://127.0.0.1:8088/jobs-list/
- 管理画面: http://127.0.0.1:8088/wp-admin/ （ユーザー名・パスワードは `.env` の `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD`）

`setup.sh`（`docker/setup.sh`）は何度実行しても同じ結果になります。

- WordPress のインストールと初期サンプル記事の削除は、未インストールのときだけ
- パーマリンクは、未設定のときだけ「投稿名」にする（初回の実行が途中で止まっても、もう一度実行すれば設定される）
- 架空の募集職種と募集一覧のページは、同じ職種 ID・スラッグのものが無いときだけ作る（Playground と同じ `tetote-jobs/demo/seed.php` を使用）。管理画面で変えた内容は上書きしない

データを消す処理は含みません。

## プラグインを変更したとき

`tetote-jobs/` の PHP・CSS を保存すれば、ブラウザーを再読み込みするだけで反映されます（コンテナの再起動は不要）。PHP のキャッシュ（OPcache）の確認間隔が 2 秒のため、保存直後は 1〜2 秒遅れることがあります。

## 止める・再開する・様子を見る

```bash
docker compose stop            # 止める（コンテナもデータも残る）
docker compose start           # 再開
docker compose restart         # 再起動
docker compose down            # コンテナを消す（ボリューム＝データは残る。up -d で元どおり）
docker compose ps              # 状態の確認
docker compose logs -f wordpress   # ログを見る（Ctrl+C で抜ける）
docker compose exec wordpress bash # WordPress のコンテナに入る（exit で抜ける）
docker compose --profile tools run --rm cli wp post list --post_type=tetote_job   # WP-CLI を使う
```

`down` してもデータが残るのは、データベースと WordPress のファイルをコンテナの中ではなく名前付きボリューム（`db_data` / `wp_data`）に置いているからです。`docker volume ls` で `tetote-wp-jobs-example_db_data` などが見えます。

## 最初からやり直す（データが消えます）

> **注意:** 次のコマンドは、この環境のデータベースと WordPress のファイル（管理画面で変えた内容）をすべて削除します。元に戻せません。

```bash
docker compose --profile tools down -v
docker compose up -d
docker compose --profile tools run --rm cli sh /opt/tetote-docker/setup.sh
```

`-v` を付けたときだけボリュームが消えます。対象はこのプロジェクト名（既定は `tetote-wp-jobs-example`）のボリュームだけです。別のフォルダーに置いたコピーも同じプロジェクト名で動かしている場合は、そちらのデータも消えます（下の「2 つ目のコピーを動かす」）。

## ポートを変える

`.env` の `WP_PORT` を変えて、`docker compose up -d` を実行し直します（例: `WP_PORT=8090` → http://127.0.0.1:8090/ ）。サイトの URL もこの値から決めているので、設定し直しは要りません。

## 2 つ目のコピーを動かす

プロジェクト名は `compose.yaml` で `tetote-wp-jobs-example` に固定しています（どのフォルダー名で clone しても、`docker volume ls` に同じ名前で出るようにするため）。そのため、同じリポジトリを別のフォルダーにもう 1 つ置いて `up -d` すると、1 つ目と同じボリュームを使います。

分けたいときは、2 つ目の `.env` で `COMPOSE_PROJECT_NAME` と `WP_PORT` の両方を変えてください（`.env.example` にコメントアウトした例があります）。

```bash
# 2 つ目のフォルダーの .env
WP_PORT=8090
COMPOSE_PROJECT_NAME=tetote-wp-jobs-example-2
```

`docker compose config | head -1` で、どのプロジェクト名で動くかを確認できます。

## 使い終わったら

- 一時的に止めるだけなら `docker compose stop`
- 完全に片付けるなら、上の「最初からやり直す」の 1 行目でコンテナ・ネットワーク・ボリュームを削除し、必要ならイメージも削除します（`docker image rm wordpress:7.1-php8.5-apache wordpress:cli-2.12-php8.5 mariadb:11.8`）

## Playground との違い

| | WordPress Playground | Docker |
| --- | --- | --- |
| 必要なもの | ブラウザー（`npm start` / `npm test` は Node.js） | Docker |
| データ | 閉じると消える（毎回まっさら） | ボリュームに残る（`down -v` で消える） |
| 中身 | PHP を WebAssembly で動かす簡易版。データベースは SQLite | Apache・PHP・MariaDB の構成。普段のレンタルサーバーに近い |
| 向いている用途 | すぐ試す・結合テスト（`npm test`） | データを残したままの確認、MySQL 系データベースでの動作確認 |

デモ用の管理メニューの絞り込み（`tetote-jobs/demo/admin.php`）は Playground だけで使い、Docker では読み込みません。管理画面は英語表示です（WordPress の初期設定のまま）。

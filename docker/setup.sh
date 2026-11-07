#!/bin/sh
# 初期設定とデモデータの投入（docker compose run --rm cli sh /opt/tetote-docker/setup.sh）
#
# 何度実行しても同じ結果になるようにしている。
#   - WordPress のインストールと初期サンプル記事の削除は、未インストールのときだけ行う
#   - パーマリンクは未設定のときだけ「投稿名」にする（途中で止まっても、再実行で設定される）
#   - プラグインは無効なら有効化する
#   - 架空の募集職種と募集一覧のページは、同じ職種 ID・スラッグのものが無いときだけ作る（既存の内容は変えない）
set -eu

# WordPress コンテナが wp-config.php を作り終えるまで待つ（初回起動の直後に実行した場合）
i=0
until [ -f /var/www/html/wp-config.php ]; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "wp-config.php が見つかりません。docker compose up -d で WordPress を起動してから実行してください。" >&2
    exit 1
  fi
  sleep 1
done

if wp core is-installed 2>/dev/null; then
  echo "WordPress はインストール済みです（インストールと初期記事には触れません）。"
else
  echo "WordPress をインストールします。"
  wp core install \
    --url="http://127.0.0.1:${WP_PORT}" \
    --title="TETOTE Jobs Example（架空）" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
  FRESH_INSTALL=1
fi

# パーマリンクは、未設定（初期値の空）のときだけ「投稿名」にする。
# インストールの直後に止まった場合も、再実行すればここで設定される。管理画面で変えた設定は上書きしない
if [ -z "$(wp option get permalink_structure)" ]; then
  wp rewrite structure '/%postname%/'
fi

if wp plugin is-active tetote-jobs; then
  echo "プラグインは有効です。"
else
  wp plugin activate tetote-jobs
fi

# デモデータはプラグインの demo/seed.php（Playground と同じ処理）を使う
wp eval '
require TETOTE_JOBS_DIR . "demo/seed.php";
$result = tetote_jobs_demo_seed();
printf("募集一覧のページ: %d / 募集職種: %s\n", $result["list_page_id"], implode(", ", array_keys($result["job_ids"])));
'
if [ "${FRESH_INSTALL:-0}" = 1 ]; then
  wp eval 'require TETOTE_JOBS_DIR . "demo/seed.php"; tetote_jobs_demo_remove_defaults();'
fi

wp rewrite flush
echo "完了: http://127.0.0.1:${WP_PORT}/jobs-list/ （管理画面: http://127.0.0.1:${WP_PORT}/wp-admin/）"

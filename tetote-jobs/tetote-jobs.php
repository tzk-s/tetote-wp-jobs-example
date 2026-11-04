<?php
/**
 * Plugin Name:       TETOTE Jobs Example
 * Description:       募集職種をカスタム投稿で管理し、募集中／募集終了を一覧・詳細に反映する公開用サンプル。架空データで動作します。
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            tzk-s
 * Text Domain:       tetote-jobs
 *
 * 【役割の分け方】
 *   includes/fields.php    … 募集要項の項目の定義（項目名・入力の種類・更新担当者向けの説明）。ここ 1 か所だけで持つ
 *   includes/post-type.php … カスタム投稿「募集職種」とカスタムフィールドの登録
 *   includes/query.php     … 募集職種の取得・並べ替え・募集中かどうかの判定
 *   includes/admin.php     … 管理画面の入力欄・保存処理・一覧の列・職種 ID の固定
 *   includes/frontend.php  … 公開側の一覧（ショートコード）と詳細の表示
 *
 * 応募の受付・メール送信は扱わない（このサンプルの対象外）。
 */

if (!defined('ABSPATH')) {
  exit();
}

define('TETOTE_JOBS_VERSION', '1.0.0');
define('TETOTE_JOBS_DIR', plugin_dir_path(__FILE__));
define('TETOTE_JOBS_URL', plugin_dir_url(__FILE__));

require_once TETOTE_JOBS_DIR . 'includes/fields.php';
require_once TETOTE_JOBS_DIR . 'includes/post-type.php';
require_once TETOTE_JOBS_DIR . 'includes/query.php';
require_once TETOTE_JOBS_DIR . 'includes/admin.php';
require_once TETOTE_JOBS_DIR . 'includes/frontend.php';

/**
 * 有効化時にパーマリンクを作り直す（/jobs/<職種 ID>/ をすぐに開けるようにする）。
 * 投稿タイプを登録してから flush しないと、詳細ページが 404 になる。
 */
register_activation_hook(__FILE__, function () {
  tetote_jobs_register_post_type();
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
  flush_rewrite_rules();
});

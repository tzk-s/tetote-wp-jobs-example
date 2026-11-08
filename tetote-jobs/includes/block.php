<?php
/**
 * 募集一覧ブロック（tetote-jobs/jobs-list）
 *
 * 【役割の分け方】
 *   編集画面（設定欄・プレビュー） … src/jobs-list/*.tsx（React / TypeScript）。ビルドすると build/jobs-list/ に出力される
 *   公開側の表示・入力値の検証   … このファイル（PHP）。表示の HTML はショートコード [tetote_jobs] と同じ関数で作る
 *   編集画面が使う職種の一覧     … このファイルの REST API（/wp-json/tetote-jobs/v1/block-jobs）
 *
 * 【設定値】
 *   jobId … 表示する職種の ID（投稿のスラッグ）。空なら「すべての職種」。
 *           投稿 ID ではなく職種 ID を保存するのは、公開後に変わらない値だから（includes/admin.php で固定している）
 *   count … 「すべての職種」のときに表示する件数（1〜20）。1 職種を選んでいる間は使わないが、値は残す
 *
 * ブロックの編集画面のスクリプトは React の新しい JSX 変換（react-jsx-runtime）を使うため、WordPress 6.6 以上で登録する。
 * 6.5 ではブロックを登録せず、ショートコードだけが使える。
 */

if (!defined('ABSPATH')) {
  exit();
}

const TETOTE_JOBS_BLOCK_NAME = 'tetote-jobs/jobs-list';
const TETOTE_JOBS_BLOCK_MIN_WP = '6.6';
const TETOTE_JOBS_BLOCK_DEFAULT_COUNT = 3;
const TETOTE_JOBS_BLOCK_MAX_COUNT = 20;

/** ブロックを登録できる環境か（ビルド済みのファイルがあり、WordPress が 6.6 以上） */
function tetote_jobs_block_is_supported()
{
  return is_wp_version_compatible(TETOTE_JOBS_BLOCK_MIN_WP) && file_exists(TETOTE_JOBS_DIR . 'build/jobs-list/block.json');
}

add_action('init', function () {
  //  公開側の CSS。ショートコード（includes/frontend.php）とブロック（block.json の "style"）で共用する
  wp_register_style('tetote-jobs', TETOTE_JOBS_URL . 'assets/tetote-jobs.css', [], TETOTE_JOBS_VERSION);

  if (tetote_jobs_block_is_supported()) {
    register_block_type(TETOTE_JOBS_DIR . 'build/jobs-list');
  }
});

/**
 * 保存された設定値を、表示に使える値にそろえる
 * 編集画面から送られた値でも、投稿の HTML を直接書き換えた値でも同じ規則で扱う（編集画面の検証だけに頼らない）。
 *   - jobId: 文字列で、職種 ID の形式（英小文字・数字・ハイフン）に合うものだけ。空は「すべての職種」。形式外は「該当なし」として扱う
 *   - count: 整数にして 1〜20 に収める。数値でなければ初期値の 3
 * @return array{job_id: string, count: int, valid: bool}
 */
function tetote_jobs_block_normalize_attributes($attributes)
{
  $attributes = is_array($attributes) ? $attributes : [];

  $job_id = $attributes['jobId'] ?? '';
  $job_id = is_string($job_id) ? trim($job_id) : '';
  $valid = '' === $job_id || tetote_jobs_is_valid_id($job_id);

  $count = $attributes['count'] ?? TETOTE_JOBS_BLOCK_DEFAULT_COUNT;
  $count = is_numeric($count) ? (int) $count : TETOTE_JOBS_BLOCK_DEFAULT_COUNT;
  $count = max(1, min(TETOTE_JOBS_BLOCK_MAX_COUNT, $count));

  return ['job_id' => $job_id, 'count' => $count, 'valid' => $valid];
}

/**
 * 表示する職種を選ぶ（DB を使わない。テストで直接使う）
 *   すべての職種 … 公開側の並び（募集中 → 募集終了）の先頭から count 件
 *   1 職種       … その職種だけ。公開されていない・削除された・形式外の ID なら空（「該当なし」）
 * @param array<int, array> $jobs tetote_jobs_get_jobs() の結果
 */
function tetote_jobs_block_select_jobs(array $jobs, array $settings)
{
  if (!$settings['valid']) {
    return [];
  }
  if ('' === $settings['job_id']) {
    return array_slice($jobs, 0, $settings['count']);
  }
  return array_values(array_filter($jobs, fn($job) => $job['id'] === $settings['job_id']));
}

/**
 * 公開側の HTML。ショートコードと同じ一覧の HTML を、ブロック用のラッパー（配置などのクラス）で包む
 * 職種名の見出しは h3（ページ内の「募集中の職種」などの見出しの下に置く想定）。募集終了・該当なしの表示はショートコードと同じ
 */
function tetote_jobs_render_block($attributes)
{
  $settings = tetote_jobs_block_normalize_attributes($attributes);
  $jobs = tetote_jobs_block_select_jobs(tetote_jobs_get_jobs(), $settings);
  $wrapper = function_exists('get_block_wrapper_attributes') && WP_Block_Supports::$block_to_render ? get_block_wrapper_attributes() : 'class="wp-block-tetote-jobs-jobs-list"';
  return sprintf('<div %s>%s</div>', $wrapper, tetote_jobs_render_jobs($jobs, 3));
}

/*=======================================================================================
  編集画面用の REST API: GET /wp-json/tetote-jobs/v1/block-jobs
  ・公開中の職種（公開側と同じ並び）と、公開されていない職種（下書き・非公開など）を返す
  ・選択中の職種が公開側に出ない理由（非公開か、削除か）を編集画面で伝えるために使う
  ・投稿を編集できるユーザーだけが読める（公開されていない職種の名前を含むため）
=======================================================================================*/
add_action('rest_api_init', function () {
  register_rest_route('tetote-jobs/v1', '/block-jobs', [
    'methods' => 'GET',
    'callback' => 'tetote_jobs_block_rest_jobs',
    'permission_callback' => fn() => current_user_can('edit_posts'),
  ]);
});

function tetote_jobs_block_rest_jobs()
{
  $published = array_map(
    fn($job) => ['id' => $job['id'], 'name' => $job['name'], 'open' => $job['open'], 'status' => 'publish'],
    tetote_jobs_get_jobs(),
  );

  $query = new WP_Query([
    'post_type' => TETOTE_JOBS_POST_TYPE,
    'post_status' => ['draft', 'pending', 'private', 'future'],
    'posts_per_page' => -1,
    'no_found_rows' => true,
  ]);
  $unpublished = [];
  foreach ($query->posts as $post) {
    //  公開前の投稿はスラッグが空のことがあるので、公開済みのときに控えた ID も見る
    $id = '' !== $post->post_name ? $post->post_name : tetote_jobs_locked_id($post->ID);
    if (tetote_jobs_is_valid_id($id)) {
      $unpublished[] = ['id' => $id, 'name' => get_the_title($post), 'open' => tetote_jobs_is_open($post->ID), 'status' => $post->post_status];
    }
  }

  return rest_ensure_response(['published' => $published, 'unpublished' => $unpublished]);
}

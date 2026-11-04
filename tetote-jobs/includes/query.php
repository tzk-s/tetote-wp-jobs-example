<?php
/**
 * 募集職種の取得
 *
 * 公開側の一覧・詳細は、ここで取得した値だけを使う（テンプレート側で get_post_meta を直接呼ばない）。
 */

if (!defined('ABSPATH')) {
  exit();
}

/** 募集中か。値が未保存（このプラグインより前に作られた投稿など）のときは募集中として扱う */
function tetote_jobs_is_open($post_id)
{
  $value = get_post_meta($post_id, TETOTE_JOBS_OPEN_KEY, true);
  return '' === $value ? true : '1' === $value;
}

/** 職種 ID として使える形式か（日本語のタイトルから自動で作られたスラッグなどを除く） */
function tetote_jobs_is_valid_id($id)
{
  return is_string($id) && 1 === preg_match(TETOTE_JOBS_ID_PATTERN, $id);
}

/**
 * 1 職種分の表示用データ
 * @return array{id: string, name: string, open: bool, url: string, summary: string, rows: array<int, array{label: string, type: string, value: string}>}
 */
function tetote_jobs_get_job($post)
{
  $post = get_post($post);
  $rows = [];
  foreach (tetote_jobs_fields() as $key => $field) {
    if ('_tetote_job_summary' === $key) {
      continue;
    }
    $rows[] = ['label' => $field['label'], 'type' => $field['type'], 'value' => (string) get_post_meta($post->ID, $key, true)];
  }
  return [
    'id' => $post->post_name,
    'name' => get_the_title($post),
    'open' => tetote_jobs_is_open($post->ID),
    'url' => get_permalink($post),
    'summary' => (string) get_post_meta($post->ID, '_tetote_job_summary', true),
    'rows' => $rows,
  ];
}

/**
 * 公開中の募集職種をすべて取得する
 * 並び: 募集中 → 募集終了。それぞれの中は「順序」→ 公開日の順（元の並びを保つ）。
 * 職種 ID の形式に合わない投稿は、応募の照合に使えないため一覧に出さない（管理画面の一覧で警告する）。
 */
function tetote_jobs_get_jobs()
{
  $query = new WP_Query([
    'post_type' => TETOTE_JOBS_POST_TYPE,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
  ]);

  $jobs = [];
  foreach ($query->posts as $post) {
    if (tetote_jobs_is_valid_id($post->post_name)) {
      $jobs[] = tetote_jobs_get_job($post);
    }
  }
  return tetote_jobs_sort_jobs($jobs);
}

/** 募集中を先頭へ。終了した職種で応募の入口が埋もれないように（DB に依存しない。テストで直接使う） */
function tetote_jobs_sort_jobs(array $jobs)
{
  $open = array_values(array_filter($jobs, fn($job) => $job['open']));
  $closed = array_values(array_filter($jobs, fn($job) => !$job['open']));
  return array_merge($open, $closed);
}

/**
 * 募集一覧ページの URL（ショートコード [tetote_jobs] を置いた最初の公開ページ）。無ければトップページ。
 * 詳細ページの「募集一覧へ戻る」に使う。
 */
function tetote_jobs_list_url()
{
  global $wpdb;
  $page_id = wp_cache_get('list_page_id', 'tetote_jobs');
  if (false === $page_id) {
    $page_id = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
        '%' . $wpdb->esc_like('[tetote_jobs') . '%',
      ),
    );
    wp_cache_set('list_page_id', $page_id, 'tetote_jobs');
  }
  return $page_id ? get_permalink($page_id) : home_url('/');
}

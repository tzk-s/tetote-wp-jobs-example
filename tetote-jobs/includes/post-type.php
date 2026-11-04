<?php
/**
 * カスタム投稿「募集職種」とカスタムフィールドの登録
 *
 * 【スラッグ = 職種 ID】
 *   詳細ページの URL（/jobs/<職種 ID>/）と、応募フォームへ渡す職種の値を兼ねる。
 *   表示名（タイトル）を直しても ID は変わらないので、リンクや応募の照合が切れない。
 *   公開後は変更できないようにしている（admin.php）。
 *
 * 【カスタムフィールド】
 *   プラグインを使わず register_post_meta で登録する。sanitize_callback を付けておくと、
 *   この画面以外（REST API・update_post_meta の呼び出しなど）から保存されても同じ規則でそろう。
 */

if (!defined('ABSPATH')) {
  exit();
}

const TETOTE_JOBS_POST_TYPE = 'tetote_job';

function tetote_jobs_register_post_type()
{
  register_post_type(TETOTE_JOBS_POST_TYPE, [
    'labels' => [
      'name' => '募集職種',
      'singular_name' => '募集職種',
      'add_new' => '新規追加',
      'add_new_item' => '募集職種を追加',
      'edit_item' => '募集職種を編集',
      'new_item' => '新しい募集職種',
      'view_item' => '募集職種を表示',
      'search_items' => '募集職種を検索',
      'not_found' => '募集職種はありません',
      'not_found_in_trash' => 'ゴミ箱に募集職種はありません',
      'all_items' => '募集職種一覧',
      'menu_name' => '募集職種',
    ],
    'public' => true,
    //  一覧はショートコード [tetote_jobs] を置いた固定ページで出す（テーマに左右されない表示にするため）
    'has_archive' => false,
    'rewrite' => ['slug' => 'jobs', 'with_front' => false],
    'menu_icon' => 'dashicons-id-alt',
    'menu_position' => 20,
    //  page-attributes … 「順序」で一覧の並びを決められるようにする
    'supports' => ['title', 'editor', 'page-attributes', 'revisions'],
    'show_in_rest' => false,
  ]);

  register_post_meta(TETOTE_JOBS_POST_TYPE, TETOTE_JOBS_OPEN_KEY, [
    'type' => 'string',
    'single' => true,
    'default' => '1',
    'sanitize_callback' => 'tetote_jobs_sanitize_open',
    'auth_callback' => 'tetote_jobs_can_edit_meta',
    'show_in_rest' => false,
  ]);

  foreach (tetote_jobs_fields() as $key => $field) {
    $type = $field['type'];
    register_post_meta(TETOTE_JOBS_POST_TYPE, $key, [
      'type' => 'string',
      'single' => true,
      'sanitize_callback' => function ($value) use ($type) {
        return tetote_jobs_sanitize_field($value, $type);
      },
      'auth_callback' => 'tetote_jobs_can_edit_meta',
      'show_in_rest' => false,
    ]);
  }
}
add_action('init', 'tetote_jobs_register_post_type');

/** メタの書き換えは、その投稿を編集できる人だけ */
function tetote_jobs_can_edit_meta($allowed, $meta_key, $post_id)
{
  return current_user_can('edit_post', $post_id);
}

/**
 * この投稿タイプはクラシックエディターで編集する。
 * 入力欄（メタボックス）を本文のすぐ下に並べ、更新担当者が 1 画面で見渡せるようにするため。
 */
add_filter(
  'use_block_editor_for_post_type',
  function ($use, $post_type) {
    return TETOTE_JOBS_POST_TYPE === $post_type ? false : $use;
  },
  10,
  2,
);

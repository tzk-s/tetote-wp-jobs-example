<?php
/**
 * デモ用: 管理画面を見やすくする（WordPress Playground のときだけ読み込む）
 *
 * プラグイン本体からは読み込まない。Blueprint が mu-plugin から require する。
 * 権限やメニューの登録は変えず、表示だけを絞る（URL を直接開けば元の画面は使える）。
 *   - このデモで使わないメニュー（投稿・メディア・コメント・ツール）を隠す
 *   - 管理画面の上部に、一時環境であることと試してほしい操作を表示する
 */

if (!defined('ABSPATH')) {
  exit();
}

add_action(
  'admin_menu',
  function () {
    foreach (['edit.php', 'upload.php', 'edit-comments.php', 'tools.php'] as $slug) {
      remove_menu_page($slug);
    }
  },
  999,
);

add_action(
  'admin_bar_menu',
  function ($bar) {
    foreach (['wp-logo', 'comments', 'new-post', 'new-media', 'new-page', 'new-user'] as $id) {
      $bar->remove_node($id);
    }
  },
  999,
);

add_action('admin_notices', function () {
  echo '<div class="notice notice-info"><p>';
  echo '<strong>デモ用の一時環境です。</strong>変更はこのブラウザーの中だけに保存され、閉じると消えます。';
  printf(
    '「募集職種」で職種を開き、「募集中」のチェックを外して更新すると、<a href="%s">募集一覧</a>に「募集終了」と表示されます。',
    esc_url(home_url('/jobs-list/')),
  );
  echo '</p></div>';
});

<?php
/**
 * 管理画面: 入力欄・保存処理・一覧の列・職種 ID の固定
 *
 * 【保存時の確認（save_post）】
 *   1. nonce … この編集画面から送られたものか（別サイトから送らせる攻撃を防ぐ）
 *   2. 自動保存・リビジョンでないか … 下書きの自動保存で募集状態が変わらないように
 *   3. 権限 … この投稿を編集できるユーザーか
 *   4. 値 … 項目の種類ごとにサニタイズし、文字数の上限で切る（fields.php）
 *
 * 【出力時】入力欄の値も esc_attr / esc_textarea でエスケープする（保存済みの値を信用しない）。
 */

if (!defined('ABSPATH')) {
  exit();
}

const TETOTE_JOBS_NONCE_ACTION = 'tetote_jobs_save';
const TETOTE_JOBS_NONCE_NAME = 'tetote_jobs_nonce';

/*=======================================================================================
  入力欄
=======================================================================================*/
add_action('add_meta_boxes_' . TETOTE_JOBS_POST_TYPE, function () {
  add_meta_box('tetote-jobs-status', '募集状態と職種 ID', 'tetote_jobs_render_status_box', null, 'side', 'high');
  add_meta_box('tetote-jobs-fields', '募集要項', 'tetote_jobs_render_fields_box', null, 'normal', 'high');
});

function tetote_jobs_render_status_box($post)
{
  wp_nonce_field(TETOTE_JOBS_NONCE_ACTION, TETOTE_JOBS_NONCE_NAME);
  $open = tetote_jobs_is_open($post->ID);
  $id = $post->post_name;
  $locked = '' !== tetote_jobs_locked_id($post->ID);
  ?>
  <p>
    <label>
      <input type="checkbox" name="tetote_job_open" value="1" <?php checked($open); ?>>
      募集中
    </label>
  </p>
  <p class="description">
    チェックを外して保存すると「募集終了」になります。募集終了の職種は一覧の後ろに「募集終了」と表示され、応募の案内が出なくなります。投稿は消えません。
  </p>
  <hr>
  <p><strong>職種 ID</strong>：<code><?php echo '' === $id ? '（未設定）' : esc_html($id); ?></code></p>
  <p class="description">
    <?php if ($locked): ?>
      公開済みのため変更できません（下書きに戻しても同じです）。詳細ページの URL と応募フォームに渡す値に使っているため、変えるとリンクが切れます。
    <?php else: ?>
      「パーマリンク」の編集で、半角英小文字・数字・ハイフン（例：<code>sales</code>）を入れてから公開してください。公開後は変更できません。
    <?php endif; ?>
  </p>
  <?php if ('' !== $id && !tetote_jobs_is_valid_id($id)): ?>
    <p class="description" style="color:#b32d2e;">
      この職種 ID は形式が合わないため、募集一覧に表示されません。「パーマリンク」の編集で半角英小文字・数字・ハイフンに直して保存してください（直した ID で公開すると固定されます）。
    </p>
  <?php endif; ?>
  <?php
}

function tetote_jobs_render_fields_box($post)
{
  echo '<p class="description">仕事の詳しい紹介は上の本文に書いてください。ここは求人票の項目です。空欄の項目は公開側に表示されません。</p>';
  echo '<table class="form-table" role="presentation"><tbody>';
  foreach (tetote_jobs_fields() as $key => $field) {
    $id = 'tetote-job-' . str_replace('_', '-', trim($key, '_'));
    $value = (string) get_post_meta($post->ID, $key, true);
    $describedby = '' !== $field['description'] ? $id . '-description' : '';
    echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label></th><td>';
    if ('textarea' === $field['type']) {
      printf(
        '<textarea class="large-text" rows="3" id="%1$s" name="tetote_job_fields[%2$s]" maxlength="%3$d"%4$s>%5$s</textarea>',
        esc_attr($id),
        esc_attr($key),
        TETOTE_JOBS_MAX_LENGTH,
        $describedby ? ' aria-describedby="' . esc_attr($describedby) . '"' : '',
        esc_textarea($value),
      );
    } else {
      printf(
        '<input type="text" class="regular-text" id="%1$s" name="tetote_job_fields[%2$s]" value="%3$s" maxlength="%4$d"%5$s>',
        esc_attr($id),
        esc_attr($key),
        esc_attr($value),
        TETOTE_JOBS_MAX_LENGTH,
        $describedby ? ' aria-describedby="' . esc_attr($describedby) . '"' : '',
      );
    }
    if ($describedby) {
      echo '<p class="description" id="' . esc_attr($describedby) . '">' . esc_html($field['description']) . '</p>';
    }
    echo '</td></tr>';
  }
  echo '</tbody></table>';
}

/*=======================================================================================
  保存
=======================================================================================*/

/**
 * 保存してよいリクエストか（DB に書く前の確認だけ。テストで直接使う）
 * @param array $request $_POST
 */
function tetote_jobs_can_save($post_id, array $request)
{
  $nonce = isset($request[TETOTE_JOBS_NONCE_NAME]) ? sanitize_text_field(wp_unslash($request[TETOTE_JOBS_NONCE_NAME])) : '';
  if (!wp_verify_nonce($nonce, TETOTE_JOBS_NONCE_ACTION)) {
    return false;
  }
  if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
    return false;
  }
  return current_user_can('edit_post', $post_id);
}

/**
 * 送信値から保存する値を作る（DB に依存しない。テストで直接使う）
 * @return array<string, string> メタキー => 値
 */
function tetote_jobs_values_from_request(array $request)
{
  $posted = isset($request['tetote_job_fields']) && is_array($request['tetote_job_fields']) ? wp_unslash($request['tetote_job_fields']) : [];
  $values = [
    //  チェックボックスは外すと送られてこない。送られてこない = 募集終了
    TETOTE_JOBS_OPEN_KEY => tetote_jobs_sanitize_open(isset($request['tetote_job_open']) ? wp_unslash($request['tetote_job_open']) : '0'),
  ];
  //  定義にある項目だけを受け付ける（送信値に余計なキーがあっても保存しない）
  foreach (tetote_jobs_fields() as $key => $field) {
    $values[$key] = tetote_jobs_sanitize_field(isset($posted[$key]) ? $posted[$key] : '', $field['type']);
  }
  return $values;
}

function tetote_jobs_save_post($post_id)
{
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- tetote_jobs_can_save() で確認する
  if (!tetote_jobs_can_save($post_id, $_POST)) {
    return;
  }
  // phpcs:ignore WordPress.Security.NonceVerification.Missing
  foreach (tetote_jobs_values_from_request($_POST) as $key => $value) {
    update_post_meta($post_id, $key, $value);
  }
}
add_action('save_post_' . TETOTE_JOBS_POST_TYPE, 'tetote_jobs_save_post');

/*=======================================================================================
  職種 ID（スラッグ）を公開後に変えさせない

  【固定する条件】正しい形式の ID で初めて公開したときに、その ID をメタ（TETOTE_JOBS_LOCKED_ID_KEY）に控える。
    以後は投稿の状態に関係なく、控えた ID に戻す（下書きに戻してから変える、という抜け道を塞ぐ）。
  【形式の合わない ID で公開した場合】控えない。一覧には出ないので、公開したままでも正しい ID へ直せる。
    直した ID で保存した時点で固定する。
  【ゴミ箱】WordPress がスラッグに付ける「__trashed」は妨げない（戻すと控えた ID に戻る）。
  クイック編集・パーマリンクの編集・REST など、どの経路で変えようとしても同じ。
=======================================================================================*/

/**
 * 固定済みの職種 ID（未固定なら空文字）
 * 控えが無くても、すでに正しい ID で公開されている投稿（このプラグインより前に公開したもの）は固定済みとして扱う。
 */
function tetote_jobs_locked_id($post_id)
{
  $locked = (string) get_post_meta($post_id, TETOTE_JOBS_LOCKED_ID_KEY, true);
  if ('' !== $locked) {
    return $locked;
  }
  $post = get_post($post_id);
  return $post && 'publish' === $post->post_status && tetote_jobs_is_valid_id($post->post_name) ? $post->post_name : '';
}

function tetote_jobs_keep_published_slug($data, $postarr)
{
  if (TETOTE_JOBS_POST_TYPE !== $data['post_type'] || empty($postarr['ID']) || 'trash' === $data['post_status']) {
    return $data;
  }
  $locked = tetote_jobs_locked_id((int) $postarr['ID']);
  if ('' !== $locked && $data['post_name'] !== $locked) {
    $data['post_name'] = $locked;
  }
  return $data;
}
add_filter('wp_insert_post_data', 'tetote_jobs_keep_published_slug', 10, 2);

/** 正しい形式の ID で公開されたら、その ID を控える（一度控えたら上書きしない） */
function tetote_jobs_lock_id_on_publish($post_id, $post)
{
  if ('publish' !== $post->post_status || '' !== (string) get_post_meta($post_id, TETOTE_JOBS_LOCKED_ID_KEY, true) || !tetote_jobs_is_valid_id($post->post_name)) {
    return;
  }
  update_post_meta($post_id, TETOTE_JOBS_LOCKED_ID_KEY, $post->post_name);
}
add_action('save_post_' . TETOTE_JOBS_POST_TYPE, 'tetote_jobs_lock_id_on_publish', 20, 2);

/*=======================================================================================
  管理画面の一覧: 募集状態と職種 ID の列
=======================================================================================*/
add_filter('manage_' . TETOTE_JOBS_POST_TYPE . '_posts_columns', function ($columns) {
  $result = [];
  foreach ($columns as $key => $label) {
    $result[$key] = $label;
    if ('title' === $key) {
      $result['tetote_job_status'] = '募集状態';
      $result['tetote_job_id'] = '職種 ID';
    }
  }
  return $result;
});

add_action(
  'manage_' . TETOTE_JOBS_POST_TYPE . '_posts_custom_column',
  function ($column, $post_id) {
    if ('tetote_job_status' === $column) {
      echo tetote_jobs_is_open($post_id) ? '募集中' : '<strong>募集終了</strong>';
    }
    if ('tetote_job_id' === $column) {
      $id = get_post_field('post_name', $post_id);
      echo '<code>' . esc_html($id) . '</code>';
      if (!tetote_jobs_is_valid_id($id)) {
        echo '<br><span style="color:#b32d2e;">形式が合わないため一覧に表示されません</span>';
      }
    }
  },
  10,
  2,
);

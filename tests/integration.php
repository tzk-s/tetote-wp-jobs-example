<?php
/**
 * WordPress 上で動かす結合テスト（テスト用のフレームワークは使わない最小構成）
 *
 * WordPress Playground の Blueprint（tests/blueprint.test.json）から、プラグインを有効化した状態で実行する。
 * 結果は標準出力（と /tetote-test-output/result.txt）に書き、失敗が 1 件でもあれば例外を投げて Blueprint を失敗させる（npm test が失敗で終わる）。
 * 確認すること:
 *   - 保存時: nonce・権限の確認、項目ごとのサニタイズ、定義外のキーの無視、募集中フラグ
 *   - 職種 ID: 公開後は変更できないこと（下書きに戻しても同じ。公開前は変えられること、形式外の ID は公開後も直せること、ゴミ箱へ移して戻せること）
 *   - 取得: 募集中を先頭に並べること、形式の合わない ID を一覧に出さないこと
 *   - 表示: 値のエスケープ、募集終了の案内
 *   - 募集一覧ブロック: 設定値の検証（件数の範囲・職種 ID の形式）、表示する職種の選び方、該当なし・募集終了の表示、編集画面用 API の権限
 *   - 採用デモページ: 再実行してもページが増えず、編集した内容を上書きしないこと
 */

require_once '/wordpress/wp-load.php';

$results = [];
$check = function ($name, $condition) use (&$results) {
  $results[] = [($condition ? 'PASS' : 'FAIL'), $name];
};

//  テスト用のユーザーを作る（管理者と購読者）
$admin_id = wp_insert_user(['user_login' => 'test-editor-' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'administrator']);
$subscriber_id = wp_insert_user(['user_login' => 'test-subscriber-' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
wp_set_current_user($admin_id);

global $wpdb;

$create = function ($slug, $title, $open, $status = 'publish', $order = 0) {
  $post_id = wp_insert_post([
    'post_type' => TETOTE_JOBS_POST_TYPE,
    'post_status' => $status,
    'post_title' => $title,
    'post_name' => $slug,
    'menu_order' => $order,
  ]);
  update_post_meta($post_id, TETOTE_JOBS_OPEN_KEY, $open ? '1' : '0');
  return $post_id;
};

/* ---------- 送信値のサニタイズ（DB を使わない） ---------- */
$values = tetote_jobs_values_from_request([
  'tetote_job_open' => '1',
  'tetote_job_fields' => [
    '_tetote_job_salary' => "<script>alert(1)</script>年収\n500万円",
    '_tetote_job_duties' => "1 行目\n<b>2 行目</b>",
    '_tetote_job_unknown' => 'saved?',
    '_tetote_job_location' => str_repeat('あ', 1200),
  ],
]);
$check('1 行の項目からタグと改行を除く', '年収 500万円' === $values['_tetote_job_salary']);
$check('複数行の項目は改行を残してタグを除く', "1 行目\n2 行目" === $values['_tetote_job_duties']);
$check('定義にないキーは保存対象にしない', !array_key_exists('_tetote_job_unknown', $values));
$check('文字数の上限で切る', TETOTE_JOBS_MAX_LENGTH === mb_strlen($values['_tetote_job_location']));
$check('チェックボックスが送られなければ募集終了', '0' === tetote_jobs_values_from_request([])[TETOTE_JOBS_OPEN_KEY]);
$check('募集中フラグは 1 以外を募集終了として扱う', '0' === tetote_jobs_sanitize_open('yes'));

/* ---------- 保存してよいリクエストか ---------- */
$post_id = $create('test-save', '保存のテスト', true);
$check('nonce が無ければ保存しない', false === tetote_jobs_can_save($post_id, []));
$check('nonce が違えば保存しない', false === tetote_jobs_can_save($post_id, [TETOTE_JOBS_NONCE_NAME => 'invalid']));
$check(
  '管理者が正しい nonce で送れば保存する',
  true === tetote_jobs_can_save($post_id, [TETOTE_JOBS_NONCE_NAME => wp_create_nonce(TETOTE_JOBS_NONCE_ACTION)]),
);
wp_set_current_user($subscriber_id);
$check(
  '編集権限のないユーザーは、自分の nonce があっても保存しない',
  false === tetote_jobs_can_save($post_id, [TETOTE_JOBS_NONCE_NAME => wp_create_nonce(TETOTE_JOBS_NONCE_ACTION)]),
);
wp_set_current_user($admin_id);

/* ---------- 保存の流れ（save_post から） ---------- */
$_POST = [
  TETOTE_JOBS_NONCE_NAME => wp_create_nonce(TETOTE_JOBS_NONCE_ACTION),
  'tetote_job_fields' => ['_tetote_job_summary' => '<i>紹介文</i>'],
  //  tetote_job_open を送らない = 募集中のチェックを外した
];
wp_update_post(['ID' => $post_id, 'post_title' => '保存のテスト（更新）']);
$_POST = [];
$check('チェックを外して保存すると募集終了になる', false === tetote_jobs_is_open($post_id));
$check('保存時に紹介文のタグを除く', '紹介文' === get_post_meta($post_id, '_tetote_job_summary', true));

$_POST = [TETOTE_JOBS_NONCE_NAME => 'invalid', 'tetote_job_open' => '1'];
wp_update_post(['ID' => $post_id, 'post_title' => '保存のテスト（不正な nonce）']);
$_POST = [];
$check('nonce が違う送信では募集状態を変えない', false === tetote_jobs_is_open($post_id));

/* ---------- 職種 ID の固定 ---------- */
wp_update_post(['ID' => $post_id, 'post_name' => 'renamed']);
$check('公開後は職種 ID を変更できない', 'test-save' === get_post_field('post_name', $post_id));

$draft_id = $create('draft-before', '下書きのテスト', true, 'draft');
wp_update_post(['ID' => $draft_id, 'post_name' => 'draft-after']);
$check('下書きのうちは職種 ID を変更できる', 'draft-after' === get_post_field('post_name', $draft_id));

$reopened_id = $create('reopen-before', '下書きに戻すテスト', true);
wp_update_post(['ID' => $reopened_id, 'post_status' => 'draft']);
wp_update_post(['ID' => $reopened_id, 'post_name' => 'reopen-after']);
$check('一度公開した職種は、下書きに戻しても職種 ID を変更できない', 'reopen-before' === get_post_field('post_name', $reopened_id));

$legacy_id = $create('legacy-id', '控えの無い旧投稿のテスト', true);
//  ID を控える仕組みより前に公開された投稿を再現する
delete_post_meta($legacy_id, TETOTE_JOBS_LOCKED_ID_KEY);
wp_update_post(['ID' => $legacy_id, 'post_status' => 'draft']);
wp_update_post(['ID' => $legacy_id, 'post_name' => 'legacy-renamed']);
$check('控えの無い旧投稿も、下書きに戻した後に職種 ID を変更できない', 'legacy-id' === get_post_field('post_name', $legacy_id));

$fixed_id = $create('fix-me', '形式外の ID で公開したテスト', true);
delete_post_meta($fixed_id, TETOTE_JOBS_LOCKED_ID_KEY);
//  日本語のタイトルから自動で作られたスラッグで公開された状態を再現する
$wpdb->update($wpdb->posts, ['post_name' => '%e5%96%b6%e6%a5%ad'], ['ID' => $fixed_id]);
clean_post_cache($fixed_id);
wp_update_post(['ID' => $fixed_id, 'post_name' => 'fixed-id']);
$check('形式外の ID で公開した職種は、公開したまま正しい ID へ直せる', 'fixed-id' === get_post_field('post_name', $fixed_id));
wp_update_post(['ID' => $fixed_id, 'post_name' => 'fixed-again']);
$check('正しい ID へ直した後は、その ID に固定される', 'fixed-id' === get_post_field('post_name', $fixed_id));

$trashed_id = $create('trash-restore', 'ゴミ箱から戻すテスト', true);
wp_trash_post($trashed_id);
wp_untrash_post($trashed_id);
$check('ゴミ箱から戻すと元の職種 ID に戻る', 'trash-restore' === get_post_field('post_name', $trashed_id));

$check('公開済みの職種もゴミ箱へ移せる', false !== wp_trash_post($post_id) && 'trash' === get_post_status($post_id));

/* ---------- 取得と並び ---------- */
$create('zz-closed', '終了した職種', false, 'publish', 1);
$create('zz-open', '<script>alert(1)</script>募集中の職種', true, 'publish', 2);
$invalid_id = $create('zz-invalid', '形式外の ID', true, 'publish', 3);
//  日本語のタイトルから自動で作られたスラッグを再現する（パーマリンクを直さずに公開した場合）
$wpdb->update($wpdb->posts, ['post_name' => '%e5%96%b6%e6%a5%ad'], ['ID' => $invalid_id]);
clean_post_cache($invalid_id);

$ids = array_column(tetote_jobs_get_jobs(), 'id');
$check('募集中の職種が募集終了より先に並ぶ', array_search('zz-open', $ids, true) < array_search('zz-closed', $ids, true));
$check('形式の合わない職種 ID は一覧に出さない', !in_array('%e5%96%b6%e6%a5%ad', $ids, true));
$check(
  '並べ替えは元の順序を保つ',
  ['a', 'c', 'b'] === array_column(tetote_jobs_sort_jobs([['id' => 'a', 'open' => true], ['id' => 'b', 'open' => false], ['id' => 'c', 'open' => true]]), 'id'),
);

/* ---------- 表示 ---------- */
$list = tetote_jobs_render_list();
$check('一覧で職種名をエスケープする', false === strpos($list, '<script>') && false !== strpos($list, '&lt;script&gt;'));
$check('一覧で募集終了を文字で示す', false !== strpos($list, '募集終了'));

$closed = tetote_jobs_get_job(get_page_by_path('zz-closed', OBJECT, TETOTE_JOBS_POST_TYPE));
$before = tetote_jobs_render_detail_before($closed);
$check('募集終了の詳細に案内と一覧へのリンクを出す', false !== strpos($before, 'この職種の募集は終了しました') && false !== strpos($before, '<a href='));

$closed['rows'][0]['value'] = "<img src=x onerror=alert(1)>\n2 行目";
$after = tetote_jobs_render_detail_after($closed);
$check('複数行の値をエスケープしてから改行を <br> にする', false !== strpos($after, '&lt;img src=x onerror=alert(1)&gt;<br>'));

/* ---------- 募集一覧ブロック: 入力値の検証と選び方（DB を使わない） ---------- */
$n = tetote_jobs_block_normalize_attributes(['jobId' => '', 'count' => 99]);
$check('ブロック: 件数は 20 を上限にする', 20 === $n['count'] && '' === $n['job_id'] && $n['valid']);
$check('ブロック: 件数は 1 を下限にする', 1 === tetote_jobs_block_normalize_attributes(['count' => -5])['count']);
$check('ブロック: 数値でない件数は初期値の 3 にする', 3 === tetote_jobs_block_normalize_attributes(['count' => 'abc'])['count']);
$check('ブロック: 設定が無ければすべての職種・3 件', ['job_id' => '', 'count' => 3, 'valid' => true] === tetote_jobs_block_normalize_attributes(null));
$check('ブロック: 形式外の職種 ID は該当なしとして扱う', false === tetote_jobs_block_normalize_attributes(['jobId' => '<script>'])['valid']);
$check('ブロック: 文字列でない職種 ID はすべての職種として扱う', '' === tetote_jobs_block_normalize_attributes(['jobId' => ['a']])['job_id']);

$sample = [['id' => 'a', 'open' => true], ['id' => 'b', 'open' => true], ['id' => 'c', 'open' => false]];
$pick = fn($attrs) => array_column(tetote_jobs_block_select_jobs($sample, tetote_jobs_block_normalize_attributes($attrs)), 'id');
$check('ブロック: すべての職種は先頭から件数分', ['a', 'b'] === $pick(['count' => 2]));
$check('ブロック: 1 職種を選ぶと件数は使わない', ['c'] === $pick(['jobId' => 'c', 'count' => 1]));
$check('ブロック: 選んだ職種が無ければ空（該当なし）', [] === $pick(['jobId' => 'zzz']));
$check('ブロック: 形式外の職種 ID は空（すべての職種にしない）', [] === $pick(['jobId' => 'A B']));

/* ---------- 募集一覧ブロック: 登録・表示・編集画面用の API ---------- */
$block_supported = is_wp_version_compatible('6.6');
$registered = WP_Block_Type_Registry::get_instance()->is_registered('tetote-jobs/jobs-list');
$check('ブロック: WordPress 6.6 以上でだけ登録する（6.5 はショートコードのみ）', $block_supported === $registered);

$render = fn($attrs) => tetote_jobs_render_block($attrs);
$html = $render(['jobId' => 'zz-closed']);
$check('ブロック: 募集終了の職種は一覧と同じく「募集終了」と示す', false !== strpos($html, '募集終了') && false !== strpos($html, '<h3 class="tetote-jobs__name">'));
$check('ブロック: 該当なしは一覧と同じ文言', false !== strpos($render(['jobId' => 'no-such-job']), '現在、掲載している募集はありません。'));
$check('ブロック: ショートコードの職種名は h2 のまま', false !== strpos(tetote_jobs_render_list(), '<h2 class="tetote-jobs__name">'));
$check('ブロック: 職種名をエスケープする', false === strpos($render([]) . $render(['count' => 20]), '<script>'));

$draft_id = $create('block-draft', '下書きの職種', true, 'draft');
wp_set_current_user($subscriber_id);
$denied = rest_get_server()->dispatch(new WP_REST_Request('GET', '/tetote-jobs/v1/block-jobs'));
$check('ブロック API: 投稿を編集できないユーザーは読めない', in_array($denied->get_status(), [401, 403], true));
wp_set_current_user($admin_id);
$data = rest_get_server()->dispatch(new WP_REST_Request('GET', '/tetote-jobs/v1/block-jobs'))->get_data();
$check('ブロック API: 公開中の職種は公開側と同じ並び', array_column($data['published'], 'id') === array_column(tetote_jobs_get_jobs(), 'id'));
$check('ブロック API: 下書きの職種を状態つきで返す', in_array(['id' => 'block-draft', 'name' => '下書きの職種', 'open' => true, 'status' => 'draft'], $data['unpublished'], true));

/* ---------- 採用デモページ（demo/seed.php） ---------- */
require_once TETOTE_JOBS_DIR . 'demo/seed.php';
$first = tetote_jobs_demo_seed_recruit_page();
if ($block_supported) {
  $page = get_post($first);
  $check('デモページ: 標準ブロックと募集一覧ブロックで作る', $page && has_block('core/image', $page) && has_block('core/heading', $page) && has_block('core/buttons', $page) && has_block('tetote-jobs/jobs-list', $page));
  wp_update_post(['ID' => $first, 'post_content' => $page->post_content . "\n<!-- wp:paragraph --><p>編集した文章</p><!-- /wp:paragraph -->"]);
  $second = tetote_jobs_demo_seed_recruit_page();
  $check('デモページ: 再実行してもページを増やさず、編集を上書きしない', $first === $second && false !== strpos(get_post($second)->post_content, '編集した文章') && 1 === count(get_posts(['post_type' => 'page', 'name' => 'recruit-demo', 'post_status' => 'any'])));
  $check('デモページ: 画像は 1 回だけメディアに入れる', 1 === count(get_posts(['post_type' => 'attachment', 'meta_key' => '_tetote_jobs_demo_image', 'post_status' => 'inherit', 'numberposts' => -1])));
} else {
  $check('デモページ: ブロックを使えない環境では作らない', 0 === $first && !get_page_by_path('recruit-demo'));
}

/* ---------- 結果 ---------- */
$failed = count(array_filter($results, fn($r) => 'FAIL' === $r[0]));
$output = implode("\n", array_map(fn($r) => $r[0] . '  ' . $r[1], $results));
$output .= sprintf("\n\n%d passed, %d failed\n", count($results) - $failed, $failed);
echo $output;
//  run-blueprint は標準出力を表示しないため、出力先がマウントされていればファイルにも書く（package.json の test）
if (is_dir('/tetote-test-output')) {
  file_put_contents('/tetote-test-output/result.txt', $output);
}
if ($failed > 0) {
  throw new RuntimeException("tetote-jobs: {$failed} test(s) failed\n" . $output);
}

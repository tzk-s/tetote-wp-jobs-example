<?php
/**
 * 公開側の表示: 募集一覧（ショートコード）と詳細（本文の前後に追加）
 *
 * テーマのテンプレートを差し替えず、どのテーマでも同じ内容が出るようにしている。
 *   一覧 … 固定ページに [tetote_jobs] を置く
 *   詳細 … /jobs/<職種 ID>/ の本文の前に募集状態、後ろに募集要項を足す
 *
 * 【募集終了の扱い】
 *   一覧から消さず、後ろへ回して「募集終了」と明示する。詳細ページも 404 にしない。
 *   外部に残ったリンクから来た人に、終了したことと一覧への道を示すため。
 *
 * 【エスケープ】出力する値はすべて、出力する場所に合わせてエスケープする。
 *   テキスト → esc_html / 属性 → esc_attr / URL → esc_url / 複数行 → esc_html のあと nl2br
 */

if (!defined('ABSPATH')) {
  exit();
}

add_action('wp_enqueue_scripts', function () {
  if (is_singular(TETOTE_JOBS_POST_TYPE) || (is_singular() && has_shortcode((string) get_post_field('post_content'), 'tetote_jobs'))) {
    wp_enqueue_style('tetote-jobs', TETOTE_JOBS_URL . 'assets/tetote-jobs.css', [], TETOTE_JOBS_VERSION);
  }
});

/** 募集終了の札。色だけに頼らず文字で状態を伝える */
function tetote_jobs_status_badge($job)
{
  return $job['open'] ? '' : ' <span class="tetote-jobs__status">募集終了</span>';
}

/** 複数行の値: エスケープしてから改行を <br> にする（順番を逆にすると <br> もエスケープされる／タグが通る） */
function tetote_jobs_multiline($value)
{
  return nl2br(esc_html($value), false);
}

/*=======================================================================================
  一覧: [tetote_jobs]
=======================================================================================*/
function tetote_jobs_render_list()
{
  $jobs = tetote_jobs_get_jobs();
  if (!$jobs) {
    return '<p class="tetote-jobs__empty">現在、掲載している募集はありません。</p>';
  }

  $html = '<ul class="tetote-jobs">';
  foreach ($jobs as $job) {
    $html .= '<li class="tetote-jobs__item' . ($job['open'] ? '' : ' is-closed') . '">';
    $html .= '<h2 class="tetote-jobs__name">' . esc_html($job['name']) . tetote_jobs_status_badge($job) . '</h2>';
    if ('' !== $job['summary']) {
      $html .= '<p class="tetote-jobs__summary">' . esc_html($job['summary']) . '</p>';
    }
    if (!$job['open']) {
      $html .= '<p class="tetote-jobs__closed-note">この職種の募集は終了しました。</p>';
    }
    //  同じ文言のリンクが並ぶため、見えない職種名を前に足して読み上げで区別できるようにする
    $html .= sprintf(
      '<p class="tetote-jobs__more"><a href="%1$s"><span class="screen-reader-text">%2$sの</span>詳細を見る</a></p>',
      esc_url($job['url']),
      esc_html($job['name']),
    );
    $html .= '</li>';
  }
  return $html . '</ul>';
}
add_shortcode('tetote_jobs', 'tetote_jobs_render_list');

/*=======================================================================================
  詳細: 本文の前に募集状態、後ろに募集要項
=======================================================================================*/
function tetote_jobs_render_detail_before($job)
{
  if ($job['open']) {
    return '<p class="tetote-job__status is-open">募集中</p>';
  }
  return sprintf(
    '<div class="tetote-job__closed" role="note"><p><strong>この職種の募集は終了しました。</strong>現在募集中の職種は<a href="%s">募集一覧</a>からご確認いただけます。</p></div>',
    esc_url(tetote_jobs_list_url()),
  );
}

function tetote_jobs_render_detail_after($job)
{
  $html = '<h2 class="tetote-job__heading">募集要項</h2><dl class="tetote-job__rows">';
  $html .= '<dt>職種名</dt><dd>' . esc_html($job['name']) . '</dd>';
  $html .= '<dt>募集状態</dt><dd>' . ($job['open'] ? '募集中' : '募集終了') . '</dd>';
  foreach ($job['rows'] as $row) {
    if ('' === $row['value']) {
      continue;
    }
    $value = 'textarea' === $row['type'] ? tetote_jobs_multiline($row['value']) : esc_html($row['value']);
    $html .= '<dt>' . esc_html($row['label']) . '</dt><dd>' . $value . '</dd>';
  }
  $html .= '</dl>';
  $html .= sprintf('<p class="tetote-job__back"><a href="%s">募集一覧へ戻る</a></p>', esc_url(tetote_jobs_list_url()));
  return $html;
}

add_filter('the_content', function ($content) {
  //  詳細ページのメインの本文だけに足す（一覧ブロックや抜粋、他の投稿の本文には足さない）
  if (!is_singular(TETOTE_JOBS_POST_TYPE) || !in_the_loop() || !is_main_query() || get_the_ID() !== get_queried_object_id()) {
    return $content;
  }
  $job = tetote_jobs_get_job(get_the_ID());
  return tetote_jobs_render_detail_before($job) . $content . tetote_jobs_render_detail_after($job);
});


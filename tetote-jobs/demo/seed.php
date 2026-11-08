<?php
/**
 * デモ用の架空データを入れる（WordPress Playground の Blueprint と Docker の docker/setup.sh から実行する）
 *
 * プラグイン本体からは読み込まない。実在の求人ではない。
 * 職種名と項目は、tetote-application-example の架空データ（public/data/jobs.json）と同じにしている。
 * 何度実行しても同じ職種 ID の投稿は増えない。
 */

if (!defined('ABSPATH')) {
  exit();
}

function tetote_jobs_demo_jobs()
{
  $common = [
    '_tetote_job_employment' => '正社員（試用期間 3 か月）',
    '_tetote_job_location' => '本社（架空）',
    '_tetote_job_salary' => 'デモ用のため記載していません',
    '_tetote_job_holidays' => '土日祝日、年末年始',
  ];
  return [
    [
      'id' => 'consultant',
      'name' => 'コンサルタント',
      'open' => true,
      'order' => 1,
      'content' => '企業の業務課題を整理し、解決策の設計から導入・定着までを支援する仕事です。（デモ用の架空の文章です）',
      'meta' => $common + [
        '_tetote_job_summary' => '企業の業務課題を整理し、解決策の設計から導入・定着までを支援します。',
        '_tetote_job_duties' => "業務課題のヒアリングと分析\n解決策の設計と導入までの進行管理",
        '_tetote_job_requirements' => '社会人経験 2 年以上（業種は問いません）',
        '_tetote_job_hours' => '9:00〜18:00（フレックスタイム制）',
        '_tetote_job_selection' => '書類選考 → 面接（2 回）→ 内定',
      ],
    ],
    [
      'id' => 'sales',
      'name' => 'ソリューション営業',
      'open' => true,
      'order' => 2,
      'content' => 'お客様の課題を聞き取り、サービスと技術を組み合わせた提案を行う仕事です。（デモ用の架空の文章です）',
      'meta' => $common + [
        '_tetote_job_summary' => 'お客様の課題を聞き取り、サービスと技術を組み合わせた提案を行います。',
        '_tetote_job_duties' => '既存のお客様への提案と導入後のフォロー',
        '_tetote_job_requirements' => '法人営業の経験がある方',
        '_tetote_job_hours' => '9:00〜18:00',
        '_tetote_job_selection' => '書類選考 → 面接（2 回）→ 内定',
      ],
    ],
    [
      'id' => 'engineer',
      'name' => 'システムエンジニア',
      'open' => false,
      'order' => 3,
      'content' => '社内外のシステムの設計・開発・運用を担当する仕事です。（デモ用の架空の文章です）',
      'meta' => $common + [
        '_tetote_job_summary' => '社内外のシステムの設計・開発・運用を担当します。',
        '_tetote_job_duties' => '業務システムの設計・開発・運用',
        '_tetote_job_requirements' => 'Web アプリケーションの開発経験がある方',
        '_tetote_job_location' => '本社（架空）／リモート勤務可',
        '_tetote_job_hours' => '9:00〜18:00（フレックスタイム制）',
        '_tetote_job_selection' => '書類選考 → 技術面接 → 最終面接 → 内定',
      ],
    ],
  ];
}

/** @return array{list_page_id: int, job_ids: array<string, int>, recruit_page_id: int} */
function tetote_jobs_demo_seed()
{
  $job_ids = [];
  foreach (tetote_jobs_demo_jobs() as $job) {
    $existing = get_page_by_path($job['id'], OBJECT, TETOTE_JOBS_POST_TYPE);
    if ($existing) {
      $job_ids[$job['id']] = $existing->ID;
      continue;
    }
    $post_id = wp_insert_post(
      [
        'post_type' => TETOTE_JOBS_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $job['name'],
        'post_name' => $job['id'],
        'post_content' => $job['content'],
        'menu_order' => $job['order'],
      ],
      true,
    );
    if (is_wp_error($post_id)) {
      continue;
    }
    update_post_meta($post_id, TETOTE_JOBS_OPEN_KEY, $job['open'] ? '1' : '0');
    foreach ($job['meta'] as $key => $value) {
      update_post_meta($post_id, $key, $value);
    }
    $job_ids[$job['id']] = $post_id;
  }

  $page = get_page_by_path('jobs-list');
  $list_page_id = $page
    ? $page->ID
    : (int) wp_insert_post([
      'post_type' => 'page',
      'post_status' => 'publish',
      'post_title' => '募集一覧',
      'post_name' => 'jobs-list',
      'post_content' => '<!-- wp:shortcode -->[tetote_jobs]<!-- /wp:shortcode -->',
    ]);

  return ['list_page_id' => $list_page_id, 'job_ids' => $job_ids, 'recruit_page_id' => tetote_jobs_demo_seed_recruit_page()];
}

/**
 * 採用デモページ（/recruit-demo/）を作る。標準ブロック（画像・見出し・段落・ボタン）と募集一覧ブロックで構成する
 * 同じスラッグのページがあれば何もしない（編集した内容を上書きしない。ページを増やさない）。
 * 募集一覧ブロックを使えない環境（WordPress 6.5）では作らない。
 */
function tetote_jobs_demo_seed_recruit_page()
{
  if (!function_exists('tetote_jobs_block_is_supported') || !tetote_jobs_block_is_supported()) {
    return 0;
  }
  $page = get_page_by_path('recruit-demo');
  if ($page) {
    return $page->ID;
  }

  $image_id = tetote_jobs_demo_import_image();
  $image = '';
  if ($image_id) {
    $image = sprintf(
      "<!-- wp:image {\"id\":%1\$d,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"%2\$s\" alt=\"%3\$s\" class=\"wp-image-%1\$d\"/></figure>\n<!-- /wp:image -->\n\n",
      $image_id,
      esc_url(wp_get_attachment_image_url($image_id, 'large')),
      esc_attr('3 人の人物を図形で表したイラスト'),
    );
  }

  $content =
    $image .
    "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">一緒に働く仲間を募集しています</h2>\n<!-- /wp:heading -->\n\n" .
    "<!-- wp:paragraph -->\n<p>TETOTE は、お客様の業務に寄り添い、仕組みづくりから定着までを一緒に進める会社です。（デモ用の架空の企業・文章です）</p>\n<!-- /wp:paragraph -->\n\n" .
    "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">募集中の職種</h2>\n<!-- /wp:heading -->\n\n" .
    "<!-- wp:tetote-jobs/jobs-list {\"count\":2} /-->\n\n" .
    "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/jobs-list/\">すべての募集を見る</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->";

  return (int) wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => '採用情報（デモ）',
    'post_name' => 'recruit-demo',
    'post_content' => $content,
  ]);
}

/** デモ用の画像（demo/images/recruit-hero.png。図形だけで描いた自作の画像）をメディアに入れる。入れ済みならその ID を返す */
function tetote_jobs_demo_import_image()
{
  $existing = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'meta_key' => '_tetote_jobs_demo_image',
    'meta_value' => 'recruit-hero',
    'fields' => 'ids',
    'numberposts' => 1,
  ]);
  if ($existing) {
    return (int) $existing[0];
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  //  media_handle_sideload は一時ファイルを移動するので、プラグインの画像をコピーしてから渡す
  $tmp = wp_tempnam('recruit-hero.png');
  if (!$tmp || !copy(TETOTE_JOBS_DIR . 'demo/images/recruit-hero.png', $tmp)) {
    return 0;
  }
  $id = media_handle_sideload(['name' => 'recruit-hero.png', 'tmp_name' => $tmp], 0, '採用デモの画像（自作の図形イラスト）', ['post_name' => 'recruit-hero']);
  if (is_wp_error($id)) {
    @unlink($tmp);
    return 0;
  }
  update_post_meta($id, '_tetote_jobs_demo_image', 'recruit-hero');
  update_post_meta($id, '_wp_attachment_image_alt', '3 人の人物を図形で表したイラスト');
  return (int) $id;
}

/**
 * WordPress を入れた直後に入っているサンプルの投稿・固定ページを消す（デモで紛らわしくならないように）
 * 初期状態のままのもの（スラッグが既定値）だけを対象にする。
 */
function tetote_jobs_demo_remove_defaults()
{
  foreach ([['hello-world', 'post'], ['sample-page', 'page']] as [$slug, $type]) {
    $post = get_page_by_path($slug, OBJECT, $type);
    if ($post) {
      wp_delete_post($post->ID, true);
    }
  }
}

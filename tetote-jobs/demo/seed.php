<?php
/**
 * デモ用の架空データを入れる（WordPress Playground の Blueprint から 1 回だけ実行する）
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

/** @return array{list_page_id: int, job_ids: array<string, int>} */
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

  return ['list_page_id' => $list_page_id, 'job_ids' => $job_ids];
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

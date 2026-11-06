<?php
/**
 * 募集要項の項目の定義
 *
 * 管理画面の入力欄・保存時のサニタイズ・公開側の表示は、すべてこの配列から作る。
 * 項目を足すときはここに 1 行足すだけでよく、入力欄と表示の対応がずれない。
 *
 * 項目は、更新担当者が求人票から写せる最小限にしている（業務内容〜選考フロー）。
 * 仕事の詳しい紹介は本文（エディター）に書く。
 */

if (!defined('ABSPATH')) {
  exit();
}

/** 募集中かどうかを持つメタキー（'1' = 募集中 / '0' = 募集終了） */
const TETOTE_JOBS_OPEN_KEY = '_tetote_job_open';

/** 職種 ID（= 投稿のスラッグ）の形式。URL と、応募フォームに渡す値を兼ねる */
/** 初めて公開したときの職種 ID を控えるメタキー（以後はこの値に固定する） */
const TETOTE_JOBS_LOCKED_ID_KEY = '_tetote_job_locked_id';

const TETOTE_JOBS_ID_PATTERN = '/^[a-z0-9-]{1,64}$/';

/** 1 項目あたりの文字数の上限（貼り付けミスで極端に長い値が保存されないように） */
const TETOTE_JOBS_MAX_LENGTH = 1000;

/**
 * @return array<string, array{label: string, type: 'text'|'textarea', description: string}>
 *   キーはメタキー（先頭の _ で「カスタムフィールド」欄に出さない）
 */
function tetote_jobs_fields()
{
  return [
    '_tetote_job_summary' => [
      'label' => '一覧に出す紹介文',
      'type' => 'text',
      'description' => '募集一覧で職種名の下に出す 1 文です。',
    ],
    '_tetote_job_duties' => [
      'label' => '業務内容',
      'type' => 'textarea',
      'description' => '改行はそのまま表示されます。',
    ],
    '_tetote_job_requirements' => [
      'label' => '応募条件',
      'type' => 'textarea',
      'description' => '必須の経験・資格など。',
    ],
    '_tetote_job_employment' => [
      'label' => '雇用形態',
      'type' => 'text',
      'description' => '例：正社員（試用期間 3 か月）',
    ],
    '_tetote_job_location' => [
      'label' => '勤務地',
      'type' => 'text',
      'description' => '',
    ],
    '_tetote_job_hours' => [
      'label' => '勤務時間',
      'type' => 'text',
      'description' => '例：9:00〜18:00（フレックスタイム制）',
    ],
    '_tetote_job_salary' => [
      'label' => '給与',
      'type' => 'text',
      'description' => '',
    ],
    '_tetote_job_holidays' => [
      'label' => '休日',
      'type' => 'text',
      'description' => '',
    ],
    '_tetote_job_selection' => [
      'label' => '選考フロー',
      'type' => 'textarea',
      'description' => '例：書類選考 → 面接（2 回）→ 内定',
    ],
  ];
}

/**
 * 1 項目の値をそろえる（保存時に使う）
 * 1 行の項目は改行やタグを除き、複数行の項目は改行だけ残す。どちらも上限で切る。
 */
function tetote_jobs_sanitize_field($value, $type)
{
  $value = is_string($value) ? $value : '';
  $value = 'textarea' === $type ? sanitize_textarea_field($value) : sanitize_text_field($value);
  return mb_substr($value, 0, TETOTE_JOBS_MAX_LENGTH);
}

/** 募集中フラグの値をそろえる。'1' 以外はすべて募集終了として扱う */
function tetote_jobs_sanitize_open($value)
{
  return '1' === (string) $value ? '1' : '0';
}

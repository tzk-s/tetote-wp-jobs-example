<?php
/**
 * 募集一覧ブロックの公開側の表示
 *
 * 表示の中身は includes/block.php の tetote_jobs_render_block() にまとめている（ショートコードと同じ HTML を使う）。
 * このファイルはビルドで build/jobs-list/ にコピーされ、block.json の "render" から読み込まれる。
 *
 * @var array $attributes ブロックの設定値（保存された値。ここでは信用せず、関数の中で検証する）
 */

if (!defined('ABSPATH')) {
  exit();
}

echo tetote_jobs_render_block($attributes); // phpcs:ignore WordPress.Security.EscapeOutput -- 関数の中で値ごとにエスケープ済み

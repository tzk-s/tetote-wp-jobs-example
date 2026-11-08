/**
 * 募集一覧ブロックの登録（編集画面だけで読み込まれる）
 *
 * 公開側の HTML は PHP（render.php）が出すので、save は null を返す。
 * そのため、表示を直しても投稿に保存済みの HTML と食い違わない（「ブロックの修復」が出ない）。
 */
import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import type { JobsListAttributes } from './types';

registerBlockType(metadata as BlockConfiguration<JobsListAttributes>, {
  edit: Edit,
  save: () => null,
});

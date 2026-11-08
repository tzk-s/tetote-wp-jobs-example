/**
 * 募集一覧ブロックの編集画面（React / TypeScript）
 *
 * 【このファイルが受け持つこと】
 *   - 右側の設定欄: 「すべての職種」か「特定の 1 職種」を選ぶ。すべての職種のときだけ件数を設定できる
 *   - 選択中の職種が削除・非公開になっていたら、編集画面で伝え、「すべての職種」へ戻すボタンを出す
 *   - 中央のプレビュー: 公開側と同じ PHP の表示（ServerSideRender）をそのまま見せる
 *
 * 【受け持たないこと】
 *   - 公開側の HTML・入力値の検証（PHP の includes/block.php）。編集画面の値は信用せず、PHP でもう一度確かめる
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { Button, Notice, PanelBody, RangeControl, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

import type { BlockJob, BlockJobsResponse, JobsListAttributes } from './types';

/** PHP 側（TETOTE_JOBS_BLOCK_MAX_COUNT）と同じ上限 */
const MAX_COUNT = 20;

const STATUS_LABELS: Record<string, string> = {
  draft: '下書き',
  pending: 'レビュー待ち',
  private: '非公開',
  future: '予約投稿',
};

type LoadState = { kind: 'loading' } | { kind: 'error' } | { kind: 'ready'; data: BlockJobsResponse };

/** 職種の一覧を REST API から読む（編集できるユーザーだけが読める） */
function useBlockJobs(): LoadState {
  const [state, setState] = useState<LoadState>({ kind: 'loading' });
  useEffect(() => {
    let active = true;
    apiFetch<BlockJobsResponse>({ path: '/tetote-jobs/v1/block-jobs' })
      .then((data) => active && setState({ kind: 'ready', data }))
      .catch(() => active && setState({ kind: 'error' }));
    //  ブロックを外したあとに結果が届いても state を変えない
    return () => {
      active = false;
    };
  }, []);
  return state;
}

/** 選択中の職種が公開側に出せない理由（出せるなら null） */
function describeMissing(jobId: string, data: BlockJobsResponse): string | null {
  if (!jobId || data.published.some((job) => job.id === jobId)) {
    return null;
  }
  const unpublished = data.unpublished.find((job) => job.id === jobId);
  if (unpublished) {
    const label = STATUS_LABELS[unpublished.status] ?? unpublished.status;
    return `選択中の職種「${unpublished.name}」は公開されていません（${label}）。公開側には「現在、掲載している募集はありません。」と表示されます。`;
  }
  return `選択中の職種（ID: ${jobId}）が見つかりません。削除された可能性があります。公開側には「現在、掲載している募集はありません。」と表示されます。`;
}

function jobLabel(job: BlockJob): string {
  return job.open ? job.name : `${job.name}（募集終了）`;
}

export default function Edit({ attributes, setAttributes }: BlockEditProps<JobsListAttributes>) {
  const { jobId, count } = attributes;
  const jobs = useBlockJobs();
  const blockProps = useBlockProps();

  const missing = jobs.kind === 'ready' ? describeMissing(jobId, jobs.data) : null;

  //  選択肢: 「すべての職種」＋公開中の職種。選択中の職種が出せない状態なら、それも選択肢に残して状態がわかるようにする
  const options = [{ label: 'すべての職種', value: '' }];
  if (jobs.kind === 'ready') {
    options.push(...jobs.data.published.map((job) => ({ label: jobLabel(job), value: job.id })));
    if (missing) {
      options.push({ label: `（表示できない職種: ${jobId}）`, value: jobId });
    }
  }

  return (
    <>
      <InspectorControls>
        <PanelBody title="表示する職種">
          {jobs.kind === 'loading' && <Spinner />}
          {jobs.kind === 'error' && (
            <Notice status="error" isDismissible={false}>
              職種の一覧を読み込めませんでした。ページを再読み込みしてください。
            </Notice>
          )}
          {jobs.kind === 'ready' && (
            <SelectControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label="職種"
              value={jobId}
              options={options}
              onChange={(value: string) => setAttributes({ jobId: value })}
              help="「すべての職種」は募集中を先頭に並べます。1 職種を選ぶと、その職種だけを表示します。"
            />
          )}
          {/* 件数は「すべての職種」のときだけ使う。1 職種を選んでいる間は隠すが、値は残すので戻したときに元の件数になる */}
          {jobId === '' && (
            <RangeControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label="表示件数"
              value={count}
              min={1}
              max={MAX_COUNT}
              onChange={(value?: number) => setAttributes({ count: value ?? 3 })}
              help="募集中 → 募集終了の順で、先頭から表示します。"
            />
          )}
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        {missing && (
          <Notice status="warning" isDismissible={false}>
            <p>{missing}</p>
            <Button variant="secondary" onClick={() => setAttributes({ jobId: '' })}>
              すべての職種に戻す
            </Button>
          </Notice>
        )}
        <ServerSideRender block="tetote-jobs/jobs-list" attributes={attributes} />
      </div>
    </>
  );
}

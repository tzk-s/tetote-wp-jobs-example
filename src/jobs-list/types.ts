/** ブロックの設定値（block.json の attributes と同じ形） */
// interface ではなく type にする（@wordpress/blocks の型が Record<string, unknown> を求めるため）
export type JobsListAttributes = {
  /** 表示する職種の ID（投稿のスラッグ）。空文字は「すべての職種」 */
  jobId: string;
  /** 「すべての職種」のときに表示する件数。1 職種を選んでいる間も値は残す */
  count: number;
};

/** 編集画面用の REST API（/tetote-jobs/v1/block-jobs）が返す 1 職種 */
export interface BlockJob {
  id: string;
  name: string;
  open: boolean;
  /** WordPress の投稿の状態（publish / draft / pending / private / future） */
  status: string;
}

export interface BlockJobsResponse {
  /** 公開中で一覧に出せる職種（公開側と同じ並び） */
  published: BlockJob[];
  /** 公開されていない職種（下書き・非公開など）。選択中の職種の状態を伝えるために使う */
  unpublished: BlockJob[];
}

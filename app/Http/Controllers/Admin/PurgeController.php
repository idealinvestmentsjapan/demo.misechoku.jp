<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminOperationLogService;
use App\Services\DocumentReviewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 書類削除候補のバッチ運用管理。
 *
 * ワークフロー：
 *  1. 取得 — 対象書類の実ファイルを ZIP で一括ダウンロード
 *  2. NAS移動 — 運営が自社 NAS へ移動した旨を記録
 *  3. 削除 — サーバから対象書類の実ファイル・DB レコードを完全削除
 *
 * 状態は admin_operation_logs の以下 action を最新順で読み取って判定：
 *  - purge.batch.download
 *  - purge.batch.nas_moved
 *  - purge.batch.execute
 */
class PurgeController extends Controller
{
    private const ACTION_DOWNLOAD    = 'purge.batch.download';
    private const ACTION_NAS_MOVED   = 'purge.batch.nas_moved';
    private const ACTION_EXECUTE     = 'purge.batch.execute';
    private const LOG_TABLE          = 'admin_operation_logs';

    public function __construct(
        private readonly DocumentReviewService $documentReviewService,
        private readonly AdminOperationLogService $opLog,
    ) {
    }

    public function index(): View
    {
        $candidates = $this->documentReviewService->getPurgeCandidateDocuments();
        $castDocs = $candidates['cast_docs'];
        $shopDocs = $candidates['shop_docs'];
        $castCount = $castDocs->count();
        $shopCount = $shopDocs->count();
        $totalCount = $castCount + $shopCount;

        $state = $this->currentBatchState();

        return view('admin.purge.index', [
            'castCount'        => $castCount,
            'shopCount'        => $shopCount,
            'totalCount'       => $totalCount,
            'castDocs'         => $castDocs,
            'shopDocs'         => $shopDocs,
            'retentionPolicy'  => $this->documentReviewService->getRetentionPolicy(),
            'currentStep'      => $state['step'],
            'lastDownload'     => $state['download'],
            'lastNasMoved'     => $state['nas_moved'],
            'lastExecute'      => $state['execute'],
        ]);
    }

    /**
     * 対象書類の実ファイルを ZIP で一括ダウンロード。
     * ZIP のファイル名パターン：
     *  - cast_{cast_id}_doc{doc_id}_{front|back}.{ext}
     *  - shop_{shop_id}_doc{doc_id}.{ext}
     * さらに MANIFEST.txt にキャスト名／店舗名・書類種別・理由を書き出す。
     */
    public function download(): BinaryFileResponse|RedirectResponse
    {
        $candidates = $this->documentReviewService->getPurgeCandidateDocuments();
        $castDocs = $candidates['cast_docs'];
        $shopDocs = $candidates['shop_docs'];
        $total = $castDocs->count() + $shopDocs->count();

        if ($total === 0) {
            return redirect()->route('admin.purge.index')
                ->with('error', '削除候補がないため、ダウンロードするファイルはありません。');
        }

        if (!class_exists(\ZipArchive::class)) {
            return redirect()->route('admin.purge.index')
                ->with('error', 'サーバの PHP に ZipArchive が組み込まれていません。管理者へ連絡してください。');
        }

        $manifest = [];
        $manifest[] = 'ミセチョク — 書類削除候補バッチ MANIFEST';
        $manifest[] = '生成日時: ' . now()->format('Y-m-d H:i:s');
        $manifest[] = str_repeat('-', 60);

        $tempPath = tempnam(sys_get_temp_dir(), 'purge_batch_');
        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            return redirect()->route('admin.purge.index')
                ->with('error', 'ZIP ファイルを作成できませんでした。');
        }

        $addedFiles = 0;
        $skippedFiles = 0;

        // キャスト本人確認書類（front / back）
        foreach ($castDocs as $doc) {
            foreach (['image_path_front' => 'front', 'image_path_back' => 'back'] as $col => $side) {
                $path = $doc->{$col} ?? null;
                if (empty($path)) continue;

                $relative = preg_replace('#^private/#', '', (string) $path);
                if (!Storage::disk('private')->exists($relative)) {
                    $skippedFiles++;
                    continue;
                }

                $contents = Storage::disk('private')->get($relative);
                $ext = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'bin';
                $zipName = sprintf('cast/%s_doc%d_%s.%s', $doc->cast_id, $doc->id, $side, $ext);
                $zip->addFromString($zipName, (string) $contents);
                $addedFiles++;
            }
            $displayName = $doc->nickname ?: $doc->profile_name ?: $doc->cast_id;
            $reason = $this->documentReviewService->labelForPurgeReason((int) $doc->status);
            $manifest[] = sprintf('[CAST] %s (id=%s doc_id=%d) — %s', $displayName, $doc->cast_id, $doc->id, $reason);
        }

        // 店舗許可証
        foreach ($shopDocs as $doc) {
            $path = $doc->image_path ?? null;
            if (!empty($path)) {
                $relative = preg_replace('#^private/#', '', (string) $path);
                if (Storage::disk('private')->exists($relative)) {
                    $contents = Storage::disk('private')->get($relative);
                    $ext = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'bin';
                    $zipName = sprintf('shop/%s_doc%d.%s', $doc->shop_id, $doc->id, $ext);
                    $zip->addFromString($zipName, (string) $contents);
                    $addedFiles++;
                } else {
                    $skippedFiles++;
                }
            }
            $displayName = $doc->shop_name ?: $doc->shop_id;
            $reason = $this->documentReviewService->labelForPurgeReason((int) $doc->status);
            $manifest[] = sprintf('[SHOP] %s (id=%s doc_id=%d) — %s', $displayName, $doc->shop_id, $doc->id, $reason);
        }

        $manifest[] = str_repeat('-', 60);
        $manifest[] = sprintf('総候補件数: %d 件 / ZIPへ追加: %d / スキップ（ファイル欠損）: %d', $total, $addedFiles, $skippedFiles);
        $zip->addFromString('MANIFEST.txt', implode("\n", $manifest));
        $zip->close();

        $filename = sprintf('purge_batch_%s.zip', now()->format('Ymd_His'));

        $this->opLog->record(
            self::ACTION_DOWNLOAD,
            'document_purge_batch',
            null,
            sprintf('削除候補ZIPをダウンロード（%d件、ファイル%d件、スキップ%d件）', $total, $addedFiles, $skippedFiles),
            ['cast_count' => $castDocs->count(), 'shop_count' => $shopDocs->count(), 'files' => $addedFiles, 'skipped' => $skippedFiles]
        );

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * 自社 NAS への移動完了を記録。
     */
    public function markNasMoved(Request $request): RedirectResponse
    {
        $note = trim((string) $request->input('note', ''));
        $state = $this->currentBatchState();

        if ($state['download'] === null) {
            return redirect()->route('admin.purge.index')
                ->with('error', 'まず「取得（ZIPダウンロード）」を実施してから、NAS移動完了を記録してください。');
        }

        $this->opLog->record(
            self::ACTION_NAS_MOVED,
            'document_purge_batch',
            null,
            'NAS への移動完了を記録' . ($note !== '' ? '（メモ: ' . mb_substr($note, 0, 100) . '）' : ''),
            ['note' => $note]
        );

        return redirect()->route('admin.purge.index')
            ->with('status', 'NAS移動完了を記録しました。「サーバから削除」に進めます。');
    }

    /**
     * 削除候補の全書類をサーバから完全削除。
     */
    public function execute(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_nas_moved' => 'required|accepted',
            'confirm_irreversible' => 'required|accepted',
        ], [
            'confirm_nas_moved.required' => 'NAS への移動が完了していることの確認にチェックを入れてください。',
            'confirm_irreversible.required' => '削除が復元できないことの確認にチェックを入れてください。',
        ]);

        $state = $this->currentBatchState();
        if ($state['nas_moved'] === null || ($state['download'] !== null && $state['nas_moved'] < $state['download'])) {
            return redirect()->route('admin.purge.index')
                ->with('error', 'ダウンロード → NAS移動 → 削除 の順で実施してください。NAS移動の記録が見つかりません。');
        }

        $result = $this->documentReviewService->purgeAllCandidates();
        $castDeleted = $result['cast_deleted'];
        $shopDeleted = $result['shop_deleted'];
        $failed = $result['failed'];
        $totalDeleted = $castDeleted + $shopDeleted;

        $this->opLog->record(
            self::ACTION_EXECUTE,
            'document_purge_batch',
            null,
            sprintf('サーバから削除実行（キャスト%d件・店舗%d件・失敗%d件）', $castDeleted, $shopDeleted, $failed),
            $result
        );

        $msg = sprintf('バッチ削除を完了しました。キャスト %d 件・店舗 %d 件を削除。', $castDeleted, $shopDeleted);
        if ($failed > 0) {
            $msg .= sprintf('（失敗 %d 件はログを確認してください）', $failed);
        }

        return redirect()->route('admin.purge.index')->with('status', $msg);
    }

    /**
     * 最新の各アクションログを読み、現在のステップを判定する。
     *
     * @return array{step: int, download: ?Carbon, nas_moved: ?Carbon, execute: ?Carbon}
     */
    private function currentBatchState(): array
    {
        $default = ['step' => 1, 'download' => null, 'nas_moved' => null, 'execute' => null];
        if (!Schema::hasTable(self::LOG_TABLE)) {
            return $default;
        }

        $latestOf = function (string $action): ?Carbon {
            $row = DB::table(self::LOG_TABLE)
                ->where('action', $action)
                ->orderByDesc('id')
                ->first(['created_at']);
            return $row && $row->created_at ? Carbon::parse($row->created_at) : null;
        };

        $download = $latestOf(self::ACTION_DOWNLOAD);
        $nasMoved = $latestOf(self::ACTION_NAS_MOVED);
        $execute  = $latestOf(self::ACTION_EXECUTE);

        // 実行が最新なら、次のバッチのステップ1へリセット
        $step = 1;
        if ($download !== null && ($execute === null || $download > $execute)) {
            $step = 2; // NAS移動待ち
            if ($nasMoved !== null && $nasMoved > $download) {
                $step = 3; // 削除待ち
            }
        }

        return [
            'step'      => $step,
            'download'  => $download,
            'nas_moved' => $nasMoved,
            'execute'   => $execute,
        ];
    }
}

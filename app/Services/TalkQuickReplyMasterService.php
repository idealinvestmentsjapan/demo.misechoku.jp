<?php

namespace App\Services;

use App\Support\TalkQuickReplyCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 運営管理画面 (/admin/talk-quick-replies) 用のマスタサービス。
 *
 * talk_quick_reply_templates テーブルの読み書きを担当し、
 * トークルーム側 (TalkQuickReplyCatalog) と同じデータ源を共有する。
 */
class TalkQuickReplyMasterService
{
    private const TABLE = 'talk_quick_reply_templates';

    public const OWNER_TYPES = ['cast', 'shop'];

    /**
     * 表示する (owner_type × status_code) の順序と表示ラベル。
     *
     * @return array<int, array{owner_type:string, status_code:string, owner_label:string, status_label:string}>
     */
    public const GROUPS = [
        ['owner_type' => 'cast', 'status_code' => 'chatting_outgoing_first', 'owner_label' => 'キャスト → 店舗', 'status_label' => 'やり取り中（こちらから初回）'],
        ['owner_type' => 'cast', 'status_code' => 'chatting_incoming_first', 'owner_label' => 'キャスト → 店舗', 'status_label' => 'やり取り中（相手からの初回に返信）'],
        ['owner_type' => 'cast', 'status_code' => 'chatting_ongoing',        'owner_label' => 'キャスト → 店舗', 'status_label' => 'やり取り中（お互い1通以上）'],
        ['owner_type' => 'cast', 'status_code' => 'interview_pending',       'owner_label' => 'キャスト → 店舗', 'status_label' => '面談日調整中'],
        ['owner_type' => 'cast', 'status_code' => 'interview_fixed',         'owner_label' => 'キャスト → 店舗', 'status_label' => '面談日確定済み'],
        ['owner_type' => 'cast', 'status_code' => 'hired',                   'owner_label' => 'キャスト → 店舗', 'status_label' => '採用'],
        ['owner_type' => 'cast', 'status_code' => 'rejected',                'owner_label' => 'キャスト → 店舗', 'status_label' => '不採用・お断り'],
        ['owner_type' => 'shop', 'status_code' => 'chatting_outgoing_first', 'owner_label' => '店舗 → キャスト', 'status_label' => 'やり取り中（こちらから初回）'],
        ['owner_type' => 'shop', 'status_code' => 'chatting_incoming_first', 'owner_label' => '店舗 → キャスト', 'status_label' => 'やり取り中（相手からの初回に返信）'],
        ['owner_type' => 'shop', 'status_code' => 'chatting_ongoing',        'owner_label' => '店舗 → キャスト', 'status_label' => 'やり取り中（お互い1通以上）'],
        ['owner_type' => 'shop', 'status_code' => 'interview_pending',       'owner_label' => '店舗 → キャスト', 'status_label' => '面談日調整中'],
        ['owner_type' => 'shop', 'status_code' => 'interview_fixed',         'owner_label' => '店舗 → キャスト', 'status_label' => '面談日確定済み'],
        ['owner_type' => 'shop', 'status_code' => 'hired',                   'owner_label' => '店舗 → キャスト', 'status_label' => '採用'],
        ['owner_type' => 'shop', 'status_code' => 'rejected',                'owner_label' => '店舗 → キャスト', 'status_label' => '不採用・お断り'],
    ];

    /** 選択肢用カテゴリ（既存の色分け・並びに準拠）。 */
    public const CATEGORIES = [
        'intro'    => '挨拶・自己紹介',
        'thanks'   => 'お礼',
        'schedule' => '日程調整',
        'question' => '質問・確認',
        'status'   => '状況報告',
        'help'     => 'ヘルプ依頼',
    ];

    public function __construct(private readonly TalkQuickReplyCatalog $catalog)
    {
    }

    /**
     * 管理画面用にグループ単位の行を返す。DB 未整備・空グループは既定値で埋める。
     *
     * @return array<int, array{owner_type:string, status_code:string, owner_label:string, status_label:string, rows: array<int, array{id:?int, category:string, body:string, sort_order:int, is_active:bool, is_default:bool}>}>
     */
    public function getGroupedForAdmin(): array
    {
        $dbRows = $this->loadAllRowsByGroup();

        return array_map(function (array $group) use ($dbRows) {
            $key = $group['owner_type'] . '|' . $group['status_code'];
            $rows = $dbRows[$key] ?? [];

            if (empty($rows)) {
                $defaults = $this->catalog->defaultFor($group['owner_type'], $group['status_code']);
                $rows = [];
                foreach ($defaults as $index => $entry) {
                    $rows[] = [
                        'id'         => null,
                        'category'   => (string) ($entry['category'] ?? ''),
                        'body'       => (string) ($entry['body'] ?? ''),
                        'sort_order' => $index + 1,
                        'is_active'  => true,
                        'is_default' => true,
                    ];
                }
            }

            return $group + ['rows' => $rows];
        }, self::GROUPS);
    }

    /**
     * @return array<string, array<int, array{id:int, category:string, body:string, sort_order:int, is_active:bool, is_default:bool}>>
     */
    private function loadAllRowsByGroup(): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->orderBy('owner_type')
            ->orderBy('status_code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'owner_type', 'status_code', 'category', 'body', 'sort_order', 'is_active']);

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->owner_type . '|' . $row->status_code;
            $grouped[$key][] = [
                'id'         => (int) $row->id,
                'category'   => (string) ($row->category ?? ''),
                'body'       => (string) ($row->body ?? ''),
                'sort_order' => (int) $row->sort_order,
                'is_active'  => (bool) $row->is_active,
                'is_default' => false,
            ];
        }

        return $grouped;
    }

    /**
     * 管理画面からの一括保存。
     *
     * $inputs 形式:
     *   [
     *     '<owner_type>|<status_code>' => [
     *        ['id' => int|null, 'category' => string, 'body' => string, 'delete' => bool],
     *        ...
     *     ]
     *   ]
     * 順序が sort_order になる。id が空の行は新規、delete=true の既存行は物理削除。
     */
    public function saveAll(array $inputs): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $validKeys = collect(self::GROUPS)
            ->map(fn (array $g) => $g['owner_type'] . '|' . $g['status_code'])
            ->all();

        DB::transaction(function () use ($inputs, $validKeys) {
            foreach ($validKeys as $groupKey) {
                [$ownerType, $statusCode] = explode('|', $groupKey);
                $rows = $inputs[$groupKey] ?? [];

                $existingIds = DB::table(self::TABLE)
                    ->where('owner_type', $ownerType)
                    ->where('status_code', $statusCode)
                    ->pluck('id')
                    ->all();
                $keptIds = [];
                $sortOrder = 0;

                foreach ($rows as $row) {
                    $body = trim((string) ($row['body'] ?? ''));
                    if ($body === '') {
                        continue;
                    }
                    if (!empty($row['delete'])) {
                        continue;
                    }
                    $category = (string) ($row['category'] ?? '');
                    if (!array_key_exists($category, self::CATEGORIES)) {
                        $category = '';
                    }
                    $sortOrder++;

                    $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
                    if ($id !== null && in_array($id, $existingIds, true)) {
                        DB::table(self::TABLE)
                            ->where('id', $id)
                            ->update([
                                'category'   => $category,
                                'body'       => $body,
                                'sort_order' => $sortOrder,
                                'is_active'  => 1,
                                'updated_at' => now(),
                            ]);
                        $keptIds[] = $id;
                    } else {
                        DB::table(self::TABLE)->insert([
                            'owner_type'  => $ownerType,
                            'status_code' => $statusCode,
                            'category'    => $category,
                            'body'        => $body,
                            'sort_order'  => $sortOrder,
                            'is_active'   => 1,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }
                }

                // 明示削除 or フォームから消えた行は物理削除 (履歴保持はしない: マスタ性質)
                $removedIds = array_diff($existingIds, $keptIds);
                if (!empty($removedIds)) {
                    DB::table(self::TABLE)->whereIn('id', $removedIds)->delete();
                }
            }
        });
    }

    /**
     * 指定グループを既定値へ戻す (DB 行を全削除)。
     * TalkQuickReplyCatalog がフォールバックで DEFAULT_TEMPLATES を返す挙動に頼る。
     */
    public function resetGroupToDefault(string $ownerType, string $statusCode): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }
        DB::table(self::TABLE)
            ->where('owner_type', $ownerType)
            ->where('status_code', $statusCode)
            ->delete();
    }
}

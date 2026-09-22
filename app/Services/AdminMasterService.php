<?php

namespace App\Services;

use App\Models\Master\CastTag;
use App\Models\Master\ColumnCategory;
use App\Models\Master\Industry;
use App\Models\Master\NgWord;
use App\Models\Master\ShopTag;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 運営マスタ管理サービス.
 *
 * - cast_tags / shop_tags への統合に追従
 *   - cast_tags  : category = looks / personality
 *   - shop_tags  : target = shop (atmosphere / facility)
 *                  target = job  (work_style / welcome / benefit)
 */
class AdminMasterService
{
    /**
     * @return array<string, array{label: string, target?: string, category: string}>
     */
    private const CAST_TAG_CATEGORIES = [
        'cast_looks' => [
            'label'    => 'ルックス',
            'category' => 'looks',
        ],
        'cast_personality' => [
            'label'    => 'パーソナリティ',
            'category' => 'personality',
        ],
    ];

    /**
     * @return array<string, array{label: string, target: string, category: string}>
     */
    private const SHOP_TAG_CATEGORIES = [
        'shop_atmosphere' => [
            'label'    => '店内の雰囲気・客層',
            'target'   => 'shop',
            'category' => 'atmosphere',
        ],
        'shop_facility' => [
            'label'    => '設備・アクセス',
            'target'   => 'shop',
            'category' => 'facility',
        ],
        'job_work_style' => [
            'label'    => '働き方・給与',
            'target'   => 'job',
            'category' => 'work_style',
        ],
        'job_welcome' => [
            'label'    => '歓迎条件',
            'target'   => 'job',
            'category' => 'welcome',
        ],
        'job_benefit' => [
            'label'    => '待遇・サポート',
            'target'   => 'job',
            'category' => 'benefit',
        ],
    ];

    public function getMasterIndexData(?string $selectedCatalogKey = null, ?int $editingRecordId = null, string $selectedSort = 'created_desc'): array
    {
        try {
            $selectedSort = in_array($selectedSort, ['created_desc', 'name_asc'], true) ? $selectedSort : 'created_desc';
            $catalogs = collect($this->catalogDefinitions())
                ->map(function (array $catalog) {
                    $catalog['count'] = $this->countCatalogRecords($catalog);

                    return $catalog;
                });
            $ngWords = $this->fetchNgWords();
            $profileCatalogKeys = collect([
                'industries',
                'cast_tags',
                'shop_profile_tags',
            ]);
            $recruitCatalogKeys = collect([
                'shop_job_tags',
            ]);
            $selectedCatalog = $selectedCatalogKey
                ? $catalogs->firstWhere('key', $selectedCatalogKey)
                : $catalogs->first();

            if ($selectedCatalog) {
                $selectedCatalog['records'] = $this->fetchCatalogRecords($selectedCatalog, $selectedSort);
                $selectedCatalog['editing_record'] = $editingRecordId
                    ? $selectedCatalog['records']->firstWhere('id', $editingRecordId)
                    : null;
            }

            return [
                'summary' => [
                    'catalog_count' => $catalogs->count(),
                    'record_count' => $catalogs->sum('count'),
                    'profile_master_count' => $catalogs
                        ->whereIn('key', $profileCatalogKeys)
                        ->sum('count'),
                    'recruit_master_count' => $catalogs
                        ->whereIn('key', $recruitCatalogKeys)
                        ->sum('count'),
                    'ng_word_count' => $ngWords->count(),
                ],
                'catalogs' => $catalogs,
                'selectedCatalog' => $selectedCatalog,
                'selectedSort' => $selectedSort,
                'ngWords' => $ngWords,
                'error' => null,
            ];
        } catch (QueryException) {
            return [
                'summary' => [
                    'catalog_count' => 0,
                    'record_count' => 0,
                    'profile_master_count' => 0,
                    'recruit_master_count' => 0,
                    'ng_word_count' => 0,
                ],
                'catalogs' => collect(),
                'selectedCatalog' => null,
                'selectedSort' => $selectedSort,
                'ngWords' => collect(),
                'error' => 'データベースに接続できないため、マスタ設定を読み込めませんでした。',
            ];
        }
    }

    public function getNgWordData(?int $editingId = null): array
    {
        try {
            $words = $this->getAllNgWords();
            $editingWord = $editingId
                ? $words->firstWhere('id', $editingId)
                : null;

            return [
                'words' => $words,
                'editingWord' => $editingWord,
                'error' => null,
            ];
        } catch (QueryException) {
            return [
                'words' => collect(),
                'editingWord' => null,
                'error' => 'データベースに接続できないため、NGワードを読み込めませんでした。',
            ];
        }
    }

    public function getCatalogDefinition(string $key): ?array
    {
        return collect($this->catalogDefinitions())
            ->firstWhere('key', $key);
    }

    public function createCatalogRecord(string $key, array $data): void
    {
        $catalog = $this->getCatalogDefinition($key);

        if (!$catalog) {
            return;
        }

        $payload = [];
        foreach ($catalog['fields'] as $field) {
            $payload[$field['column']] = $data[$field['input']] ?? null;
        }

        if (!empty($catalog['fixed_attributes'])) {
            foreach ($catalog['fixed_attributes'] as $col => $val) {
                $payload[$col] = $val;
            }
        }

        if (!empty($catalog['uses_del_flg'])) {
            $payload['del_flg'] = 0;
        }
        if (!empty($catalog['uses_is_active'])) {
            $payload['is_active'] = 1;
        }
        if (!empty($catalog['uses_sort_order'])) {
            $max = (int) DB::table($catalog['table'])->max('sort_order');
            $payload['sort_order'] = $max + 1;
        }

        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        DB::table($catalog['table'])->insert($payload);
    }

    public function getCatalogRecord(string $key, int $recordId): ?object
    {
        $catalog = $this->getCatalogDefinition($key);

        if (!$catalog || !Schema::hasTable($catalog['table'])) {
            return null;
        }

        $query = DB::table($catalog['table'])->where('id', $recordId);
        if (!empty($catalog['fixed_attributes'])) {
            foreach ($catalog['fixed_attributes'] as $col => $val) {
                $query->where($col, $val);
            }
        }

        return $query->first();
    }

    public function updateCatalogRecord(string $key, int $recordId, array $data): void
    {
        $catalog = $this->getCatalogDefinition($key);

        if (!$catalog) {
            return;
        }

        $payload = [];
        foreach ($catalog['fields'] as $field) {
            $payload[$field['column']] = $data[$field['input']] ?? null;
        }

        $payload['updated_at'] = now();

        DB::table($catalog['table'])
            ->where('id', $recordId)
            ->update($payload);
    }

    /**
     * ドラッグ＆ドロップの並び順どおりに sort_order を 1 から振り直す。
     *
     * @param  int[]  $orderedIds  画面上の並び順の ID 配列
     */
    public function reorderCatalogRecords(string $key, array $orderedIds): void
    {
        $catalog = $this->getCatalogDefinition($key);
        if (!$catalog || !Schema::hasTable($catalog['table']) || !Schema::hasColumn($catalog['table'], 'sort_order')) {
            return;
        }

        DB::transaction(function () use ($catalog, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                DB::table($catalog['table'])
                    ->where('id', $id)
                    ->update([
                        'sort_order' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * NG ワードを新規追加。
     */
    public function createNgWord(string $word): void
    {
        if (!Schema::hasTable('ng_words')) {
            return;
        }
        DB::table('ng_words')->insert([
            'word' => $word,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * NG ワードの内容と有効フラグを更新。
     */
    public function updateNgWord(int $id, string $word, bool $isActive): void
    {
        if (!Schema::hasTable('ng_words')) {
            return;
        }
        DB::table('ng_words')
            ->where('id', $id)
            ->update([
                'word' => $word,
                'is_active' => $isActive ? 1 : 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * NG ワードを削除（論理削除：is_active = 0）。
     */
    public function deleteNgWord(int $id): void
    {
        if (!Schema::hasTable('ng_words')) {
            return;
        }
        DB::table('ng_words')
            ->where('id', $id)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);
    }

    public function getNgWord(int $id): ?object
    {
        if (!Schema::hasTable('ng_words')) {
            return null;
        }
        return DB::table('ng_words')->where('id', $id)->first();
    }

    /**
     * NG ワード（無効含めて全件）を返す。管理画面で削除済みも編集可能にする用途。
     */
    public function getAllNgWords(): Collection
    {
        if (!Schema::hasTable('ng_words')) {
            return collect();
        }
        return DB::table('ng_words')
            ->orderBy('id')
            ->get(['id', 'word', 'is_active', 'created_at', 'updated_at']);
    }

    /**
     * マスタレコードの論理削除
     *
     * - del_flg カラムがあれば del_flg = 1
     * - なければ is_active カラムがあれば is_active = 0
     * - どちらもなければ物理削除
     */
    public function deleteCatalogRecord(string $key, int $recordId): void
    {
        $catalog = $this->getCatalogDefinition($key);

        if (!$catalog) {
            return;
        }

        if (!empty($catalog['model'])) {
            /** @var class-string<\App\Models\Master\BaseMaster> $modelClass */
            $modelClass = $catalog['model'];
            $record = $modelClass::query()->find($recordId);
            if ($record) {
                $record->logicalDelete();
            }

            return;
        }

        $update = [];
        if (Schema::hasColumn($catalog['table'], 'del_flg')) {
            $update['del_flg'] = 1;
        } elseif (Schema::hasColumn($catalog['table'], 'is_active')) {
            $update['is_active'] = 0;
        }
        if (!empty($update)) {
            $update['updated_at'] = now();
            DB::table($catalog['table'])
                ->where('id', $recordId)
                ->update($update);
        }
    }

    /**
     * キャストプロフィール画面で使うマスタ.
     *
     * @return array{industries: \Illuminate\Support\Collection, looks: \Illuminate\Support\Collection, personalities: \Illuminate\Support\Collection}
     */
    public function getCastProfileMasters(): array
    {
        return [
            'industries'    => Industry::query()->active()->orderBy('id')->get(['id', 'name']),
            'looks'         => $this->fetchCastTags('looks'),
            'personalities' => $this->fetchCastTags('personality'),
        ];
    }

    /**
     * 店舗プロフィール画面で使うマスタ.
     *
     * @return array{industries: \Illuminate\Support\Collection, atmosphere: \Illuminate\Support\Collection, facility: \Illuminate\Support\Collection}
     */
    public function getShopProfileMasters(): array
    {
        return [
            'industries' => Industry::query()->active()->orderBy('id')->get(['id', 'name']),
            'atmosphere' => $this->fetchShopTags('shop', 'atmosphere'),
            'facility'   => $this->fetchShopTags('shop', 'facility'),
        ];
    }

    /**
     * 求人票画面で使うマスタ.
     *
     * @return array{work_style: \Illuminate\Support\Collection, welcome: \Illuminate\Support\Collection, benefit: \Illuminate\Support\Collection}
     */
    public function getRecruitmentMasters(): array
    {
        return [
            'work_style' => $this->fetchShopTags('job', 'work_style'),
            'welcome'    => $this->fetchShopTags('job', 'welcome'),
            'benefit'    => $this->fetchShopTags('job', 'benefit'),
        ];
    }

    private function fetchCastTags(string $category): Collection
    {
        if (!Schema::hasTable('cast_tags')) {
            return collect();
        }

        return CastTag::query()
            ->active()
            ->category($category)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    private function fetchShopTags(string $target, string $category): Collection
    {
        if (!Schema::hasTable('shop_tags')) {
            return collect();
        }

        return ShopTag::query()
            ->active()
            ->target($target)
            ->category($category)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    private function catalogDefinitions(): array
    {
        return [
            [
                'key' => 'industries',
                'table' => 'industries',
                'model' => Industry::class,
                'title' => '業種マスタ',
                'description' => 'キャバクラ、ラウンジ、ガールズバー など',
                'group' => 'プロフィール',
                'fields' => [
                    ['input' => 'name', 'column' => 'name', 'label' => '業種名', 'placeholder' => '例: キャバクラ', 'max' => 100],
                ],
            ],
            [
                'key' => 'column_categories',
                'table' => 'column_categories',
                'model' => ColumnCategory::class,
                'title' => 'コラムカテゴリマスタ',
                'description' => 'キャバクラ情報、面接対策 など',
                'group' => 'コンテンツ',
                'fields' => [
                    ['input' => 'name', 'column' => 'name', 'label' => 'カテゴリ名', 'placeholder' => '例: 面接対策', 'max' => 255],
                    ['input' => 'directory', 'column' => 'directory', 'label' => 'ディレクトリ', 'placeholder' => '例: interview', 'max' => 255],
                ],
                'uses_del_flg' => true,
            ],
            ...$this->castTagCatalogDefinitions(),
            ...$this->shopTagCatalogDefinitions(),
        ];
    }

    /**
     * cast_tags（looks / personality）をカテゴリごとに 1 件のカタログとして公開する.
     *
     * @return array<int, array<string, mixed>>
     */
    private function castTagCatalogDefinitions(): array
    {
        $defs = [];
        foreach (self::CAST_TAG_CATEGORIES as $key => $meta) {
            $defs[] = [
                'key' => $key,
                'table' => 'cast_tags',
                'model' => CastTag::class,
                'title' => 'キャストタグ：' . $meta['label'],
                'description' => 'cast_tags (category = ' . $meta['category'] . ')',
                'group' => 'プロフィール',
                'fields' => [
                    ['input' => 'name', 'column' => 'name', 'label' => 'タグ名', 'placeholder' => '例: スレンダー', 'max' => 100],
                ],
                'uses_del_flg' => true,
                'uses_sort_order' => true,
                'fixed_attributes' => [
                    'category' => $meta['category'],
                ],
            ];
        }

        return $defs;
    }

    /**
     * shop_tags を target × category 単位の 5 つのカタログとして公開する.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shopTagCatalogDefinitions(): array
    {
        $defs = [];
        foreach (self::SHOP_TAG_CATEGORIES as $key => $meta) {
            $group = $meta['target'] === 'shop' ? '店舗プロフィール' : '求人票';
            $defs[] = [
                'key' => $key,
                'table' => 'shop_tags',
                'model' => ShopTag::class,
                'title' => '店舗タグ：' . $meta['label'] . '（' . ($meta['target'] === 'shop' ? '店舗' : '求人') . '用）',
                'description' => 'shop_tags (target = ' . $meta['target'] . ', category = ' . $meta['category'] . ')',
                'group' => $group,
                'fields' => [
                    ['input' => 'name', 'column' => 'name', 'label' => 'タグ名', 'placeholder' => '例: アットホーム', 'max' => 100],
                ],
                'uses_del_flg' => true,
                'uses_sort_order' => true,
                'fixed_attributes' => [
                    'target'   => $meta['target'],
                    'category' => $meta['category'],
                ],
            ];
        }

        return $defs;
    }

    private function countCatalogRecords(array $catalog): int
    {
        if (empty($catalog['model']) || !Schema::hasTable($catalog['table'])) {
            return 0;
        }

        /** @var class-string<\App\Models\Master\BaseMaster> $modelClass */
        $modelClass = $catalog['model'];
        $query = $modelClass::query()->active();

        if (!empty($catalog['fixed_attributes'])) {
            foreach ($catalog['fixed_attributes'] as $col => $val) {
                $query->where($col, $val);
            }
        }

        return (int) $query->count();
    }

    private function fetchCatalogRecords(array $catalog, string $sort = 'created_desc'): Collection
    {
        if (empty($catalog['model']) || !Schema::hasTable($catalog['table'])) {
            return collect();
        }

        /** @var class-string<\App\Models\Master\BaseMaster> $modelClass */
        $modelClass = $catalog['model'];
        $query = $modelClass::query()
            ->active()
            ->select('id', 'name', 'created_at');

        if (!empty($catalog['fixed_attributes'])) {
            foreach ($catalog['fixed_attributes'] as $col => $val) {
                $query->where($col, $val);
            }
        }

        if (Schema::hasColumn($catalog['table'], 'directory')) {
            $query->addSelect('directory');
        }

        if (Schema::hasColumn($catalog['table'], 'sort_order')) {
            $query->addSelect('sort_order');
        }

        if (Schema::hasColumn($catalog['table'], 'del_flg')) {
            $query->addSelect(DB::raw('CASE WHEN del_flg = 0 THEN 1 ELSE 0 END as is_active'));
        } elseif (Schema::hasColumn($catalog['table'], 'is_active')) {
            $query->addSelect('is_active');
        }

        if ($sort === 'name_asc') {
            return $query
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        }

        if (Schema::hasColumn($catalog['table'], 'sort_order')) {
            return $query
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function fetchNgWords(): Collection
    {
        return NgWord::query()
            ->active()
            ->orderBy('word')
            ->orderByDesc('id')
            ->get(['id', 'word', 'is_active', 'created_at']);
    }
}

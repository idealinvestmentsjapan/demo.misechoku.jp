<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewPortalService
{
    public function getShopReviewPageData(string $shopId): array
    {
        $selectCols = [
            'reviews.id',
            'reviews.cast_id',
            'reviews.contents',
            'reviews.eva',
            'reviews.created_at',
            DB::raw($this->reviewReleaseExpression() . ' as `release`'),
            DB::raw($this->reviewAnonymousExpression() . ' as `anonymous`'),
            'cast_profiles.nickname',
            'cast_profiles.name',
        ];
        // 店舗返信カラムがあれば SELECT に追加（2026-08-02 追加）
        if (Schema::hasColumn('reviews', 'reply_body')) {
            $selectCols[] = 'reviews.reply_body';
            $selectCols[] = 'reviews.reply_at';
        }

        $reviewRows = DB::table('reviews')
            ->leftJoin('cast_profiles', 'reviews.cast_id', '=', 'cast_profiles.cast_id')
            ->where('reviews.shop_id', $shopId)
            ->orderByDesc('reviews.id')
            ->select($selectCols)
            ->get();

        $reviewIds = $reviewRows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $detailMap = $this->loadReviewDetails($reviewIds);

        $reviews = $reviewRows->map(function ($row) use ($detailMap) {
            $castId = (string) $row->cast_id;
            $displayName = trim((string) ($row->nickname ?: $row->name ?: $castId));

            return [
                'id' => (int) $row->id,
                'cast_id' => $castId,
                'user_name' => $displayName !== '' ? $displayName : $castId,
                'user_img' => $this->resolveCastAvatar($castId),
                'anonymous' => (int) ($row->anonymous ?? 0),
                'avg_score' => (float) ($row->eva ?? 0),
                'text' => (string) ($row->contents ?? ''),
                'release' => (int) ($row->release ?? 1),
                'details' => $detailMap[(int) $row->id] ?? [],
                'created_at_label' => !empty($row->created_at) ? date('Y-m-d H:i', strtotime((string) $row->created_at)) : null,
                // 店舗からの返信（reply_body / reply_at）— 未対応スキーマの場合は null
                'reply_body' => property_exists($row, 'reply_body') ? (string) ($row->reply_body ?? '') : '',
                'reply_at_label' => (property_exists($row, 'reply_at') && !empty($row->reply_at))
                    ? date('Y-m-d H:i', strtotime((string) $row->reply_at))
                    : null,
            ];
        })->all();

        return [
            'shopData' => [
                'review_avg' => round($reviewRows->avg(fn ($row) => (float) ($row->eva ?? 0)) ?: 0, 1),
                'review_count' => count($reviews),
            ],
            'reviews' => $reviews,
            'supports_release' => Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'release'),
        ];
    }

    public function updateShopReviewStatus(string $shopId, int $reviewId, int $release): bool
    {
        if (!(Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'release'))) {
            return false;
        }

        return DB::table('reviews')
            ->where('id', $reviewId)
            ->where('shop_id', $shopId)
            ->update(['release' => $release]) > 0;
    }

    public function getShopReviewNotifications(string $shopId, int $limit = 10): array
    {
        return DB::table('reviews')
            ->leftJoin('cast_profiles', 'reviews.cast_id', '=', 'cast_profiles.cast_id')
            ->where('reviews.shop_id', $shopId)
            ->orderByDesc('reviews.id')
            ->limit($limit)
            ->get([
                'reviews.id',
                'reviews.cast_id',
                'reviews.created_at',
                'cast_profiles.nickname',
                'cast_profiles.name',
            ])
            ->map(function ($row) {
                $name = trim((string) ($row->nickname ?: $row->name ?: $row->cast_id));
                $label = $name !== '' ? $name : (string) $row->cast_id;

                return [
                    'title' => $label . ' さんがレビューを投稿しました',
                    'body' => !empty($row->created_at)
                        ? date('Y-m-d H:i', strtotime((string) $row->created_at))
                        : 'レビューが投稿されました',
                    'url' => route('shop.mypage.review.index') . '#review-' . (int) $row->id,
                ];
            })
            ->all();
    }

    /**
     * @param array<int, int> $reviewIds
     * @return array<int, array<int, array{name:string, score:float}>>
     */
    private function loadReviewDetails(array $reviewIds): array
    {
        if (empty($reviewIds)) {
            return [];
        }

        if (!Schema::hasTable('review_details') || !Schema::hasTable('review_contents')) {
            return [];
        }

        $contentColumn = $this->reviewContentColumn();
        $hasReviewContentId = Schema::hasColumn('review_details', 'review_content_id');
        $hasVal = Schema::hasColumn('review_details', 'val');
        if (!$hasReviewContentId && !$hasVal) {
            return [];
        }

        $labelSql = 'review_contents.' . $contentColumn;
        $joinColumnExpr = $this->reviewDetailJoinColumnExpression($hasReviewContentId, $hasVal);
        if ($joinColumnExpr === null) {
            return [];
        }

        $rows = DB::table('review_details')
            ->join('review_contents', DB::raw($joinColumnExpr), '=', 'review_contents.id')
            ->whereIn('review_details.review_id', $reviewIds)
            ->orderBy('review_details.review_id')
            ->when(
                Schema::hasColumn('review_contents', 'sort_order'),
                fn ($query) => $query->orderBy('review_contents.sort_order')
            )
            ->orderBy('review_contents.id')
            ->get([
                'review_details.review_id',
                DB::raw($labelSql . ' as question_label'),
                'review_details.score',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $rid = (int) $row->review_id;
            $label = trim((string) ($row->question_label ?? ''));
            if (!isset($out[$rid])) {
                $out[$rid] = [];
            }
            $out[$rid][] = [
                'name' => $label,
                'content' => $label,
                'score' => (float) ($row->score ?? 0),
            ];
        }

        return $out;
    }

    private function resolveCastAvatar(string $castId): string
    {
        $path = DB::table('cast_images')
            ->where('cast_id', $castId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->value('image_path');

        return $this->assetPathForStored($path) ?: asset('assets/images/common/user-default.svg');
    }

    private function assetPathForStored(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'public/')) {
            return asset('storage/' . ltrim(substr($path, 7), '/'));
        }

        if (str_starts_with($path, 'uploads/')) {
            return asset($path);
        }

        return asset(ltrim($path, '/'));
    }

    private function reviewContentColumn(): string
    {
        return Schema::hasTable('review_contents') && Schema::hasColumn('review_contents', 'content')
            ? 'content'
            : 'name';
    }

    private function reviewDetailContentColumn(): string
    {
        if (Schema::hasTable('review_details') && Schema::hasColumn('review_details', 'val')) {
            return 'val';
        }

        return 'review_content_id';
    }

    private function reviewDetailJoinColumnExpression(bool $hasReviewContentId, bool $hasVal): ?string
    {
        if ($hasReviewContentId && $hasVal) {
            // 互換対応: 新旧どちらの列に設問IDが入っていても参照できるようにする
            return 'COALESCE(review_details.review_content_id, review_details.val)';
        }

        if ($hasReviewContentId) {
            return 'review_details.review_content_id';
        }

        if ($hasVal) {
            return 'review_details.val';
        }

        return null;
    }

    private function reviewReleaseExpression(): string
    {
        return Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'release')
            ? 'reviews.release'
            : '1';
    }

    private function reviewAnonymousExpression(): string
    {
        return Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'is_anonymous')
            ? 'reviews.is_anonymous'
            : '0';
    }
}

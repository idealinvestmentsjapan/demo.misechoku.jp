<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 検索機能の基底コントローラー
 */
abstract class SearchController extends Controller
{
    protected function applySavedSearchDefaults(Request $request, array $saved, array $mapping): void
    {
        if ($request->boolean('filters_applied')) {
            return;
        }
        foreach ($mapping as $queryKey => $savedKey) {
            if (!$request->query->has($queryKey) && isset($saved[$savedKey])) {
                $request->query->set($queryKey, $saved[$savedKey]);
            }
        }
    }

    /**
     * 共通のインデックス表示ロジック
     */
    protected function renderIndex(array $data)
    {
        $items = $data['items'] ?? [];
        $data['resultCount'] = count($items);
        $data['items'] = $this->paginateResults($items, request());

        return view('common.search.index', array_merge([
            'pageId' => 'search',
        ], $data));
    }

    protected function paginateResults(array $items, Request $request): LengthAwarePaginator
    {
        $total = count($items);
        $perPage = 20;
        $page = max(1, min((int) $request->query('page', 1), max(1, (int) ceil($total / $perPage))));

        return new LengthAwarePaginator(array_slice($items, ($page - 1) * $perPage, $perPage), $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->except('page'),
        ]);
    }

    /**
     * SEARCH のキープタブ用データ（キープリスト + おすすめ）。
     *
     * @return array<string, mixed>
     */
    protected function buildKeepPaneData(): array
    {
        $isCastPortal = request()->is('cast/*');
        $keeps = app(\App\Services\KeepListService::class);
        $recommendation = app(\App\Services\RecommendationService::class);

        if ($isCastPortal) {
            $castId = Auth::guard('member')->check() ? (string) Auth::guard('member')->id() : '';
            return [
                'keepItems' => $castId !== '' ? $keeps->keptShopsForCast($castId) : [],
                'keepProfileRoute' => 'cast.shopprofile.show',
                'recommendItems' => $castId !== '' ? $recommendation->recommendShopsForCast($castId, 6) : [],
                'recommendType' => 'shop',
                'recommendLogic' => \App\Services\RecommendationService::castRecommendLogicLines(),
                'recommendDetailRoute' => 'cast.shopprofile.show',
            ];
        }

        $shopId = Auth::guard('shop')->check() ? (string) (Auth::guard('shop')->user()->shop_id ?? '') : '';
        return [
            'keepItems' => $shopId !== '' ? $keeps->keptCastsForShop($shopId) : [],
            'keepProfileRoute' => 'shop.castprofileview.show',
            'recommendItems' => $shopId !== '' ? $recommendation->recommendCastsForShop($shopId, 6) : [],
            'recommendType' => 'cast',
            'recommendLogic' => \App\Services\RecommendationService::shopRecommendLogicLines(),
            'recommendDetailRoute' => 'shop.castprofileview.show',
        ];
    }

    /**
     * 検索結果アイテム配列に is_keeping を付与する。
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  string  $itemType  'shop' or 'cast'
     * @return array<int, array<string, mixed>>
     */
    protected function attachFavoriteStates(array $items, string $itemType): array
    {
        if ($items === []) {
            return $items;
        }

        $senderType = null;
        $myCastId = null;
        $myShopId = null;
        if (Auth::guard('shop')->check()) {
            $senderType = Favorite::SENDER_SHOP;
            $myShopId = (string) (Auth::guard('shop')->user()->shop_id ?? '');
        } elseif (Auth::guard('member')->check()) {
            $senderType = Favorite::SENDER_CAST;
            $myCastId = (string) (Auth::guard('member')->user()->id ?? '');
        } else {
            // 未ログインは全部 false で返す
            foreach ($items as &$it) {
                $it['is_keeping'] = false;
            }
            unset($it);
            return $items;
        }

        $ids = array_values(array_filter(array_map(fn ($it) => (string) ($it['id'] ?? ''), $items)));
        if ($ids === []) {
            foreach ($items as &$it) {
                $it['is_keeping'] = false;
            }
            unset($it);
            return $items;
        }

        $favoriteColumn = $itemType === 'shop' ? 'shop_id' : 'cast_id';

        // 自分の KEEP の取得（個別 is_keeping 用）
        $rows = DB::table('favorites')
            ->select($favoriteColumn . ' as target_id')
            ->where('sender_type', $senderType)
            ->where('action_type', Favorite::ACTION_KEEP)
            ->when($senderType === Favorite::SENDER_CAST && $myCastId !== '', fn ($q) => $q->where('cast_id', $myCastId))
            ->when($senderType === Favorite::SENDER_SHOP && $myShopId !== '', fn ($q) => $q->where('shop_id', $myShopId))
            ->whereIn($favoriteColumn, $ids)
            ->get();

        $keepSet = [];
        foreach ($rows as $r) {
            $keepSet[(string) $r->target_id] = true;
        }

        foreach ($items as &$it) {
            $id = (string) ($it['id'] ?? '');
            $it['is_keeping'] = isset($keepSet[$id]);
        }
        unset($it);

        return $items;
    }

    /**
     * ひらがな/カタカナ、全角/半角、英字大小の揺れを吸収する。
     */
    protected function normalizeSearchText(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = mb_convert_kana($value, 'asKV', 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace_callback('/[\x{30A1}-\x{30F6}]/u', function (array $matches) {
            return mb_chr(mb_ord($matches[0], 'UTF-8') - 0x60, 'UTF-8');
        }, $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}

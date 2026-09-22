<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * スワイプ画面などからの「キープ」をトグルする簡易API
     *
     * action: keep
     * item_type: cast / shop
     * item_id: 対象のID（キャストID or 店舗ID）
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:keep',
            'item_type' => 'required|string|in:cast,shop',
            'item_id' => 'required|string|max:20',
        ]);

        $action = $validated['action'];
        $itemType = $validated['item_type'];
        $itemId = $validated['item_id'];

        // 認証中の主体（店舗 or キャスト）を判定
        $castId = null;
        $shopId = null;
        $senderType = null;

        if (Auth::guard('shop')->check()) {
            // 店舗ポータル：manager 経由で shop_id がぶら下がっている想定
            $shopUser = Auth::guard('shop')->user();
            $shopId = (string) ($shopUser->shop_id ?? '');
            $senderType = Favorite::SENDER_SHOP;
            if ($itemType === 'cast') {
                $castId = $itemId;
            }
        } elseif (Auth::guard('member')->check()) {
            // キャストポータル：guard member の ID が cast_id
            $castUser = Auth::guard('member')->user();
            $castId = (string) ($castUser->id ?? '');
            $senderType = Favorite::SENDER_CAST;
            if ($itemType === 'shop') {
                $shopId = $itemId;
            }
        } else {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validCombination = ($senderType === Favorite::SENDER_SHOP && $itemType === 'cast')
            || ($senderType === Favorite::SENDER_CAST && $itemType === 'shop');
        if (!$validCombination || empty($castId) || empty($shopId)) {
            return response()->json(['error' => 'Invalid target combination'], 422);
        }

        $targetExists = $itemType === 'cast'
            ? DB::table('casts')->where('id', $castId)->exists()
            : DB::table('shops')->where('id', $shopId)->exists();
        if (!$targetExists) {
            return response()->json(['error' => 'Target not found'], 422);
        }

        // 自分が同じ向きで打った既存レコード
        $existing = DB::table('favorites')
            ->where('action_type', Favorite::ACTION_KEEP)
            ->where('sender_type', $senderType)
            ->when(!empty($castId), fn ($q) => $q->where('cast_id', $castId))
            ->when(!empty($shopId), fn ($q) => $q->where('shop_id', $shopId))
            ->first();

        // 純粋なトグル：既存行あり → 削除（取り消し）／ 既存行なし → 追加
        if ($existing) {
            DB::table('favorites')->where('id', $existing->id)->delete();
            $isActive = false;
        } else {
            DB::table('favorites')->insert([
                'cast_id' => $castId,
                'shop_id' => $shopId,
                'action_type' => Favorite::ACTION_KEEP,
                'sender_type' => $senderType,
                'created_at' => now(),
            ]);
            $isActive = true;
        }

        return response()->json([
            'ok' => true,
            'action' => $action,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'is_active' => $isActive,
        ]);
    }
}

<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class TalkDeliveryService
{
    /**
     * 通信再試行では同じ送信IDの結果を返す。本文が同じでも別の送信IDなら別送信。
     * Plesk単一ホストのfileストアを使い、既定のDBキャッシュに依存させない。
     */
    public function once(string $actor, string $requestId, string $content, Closure $send): array
    {
        $key = 'talk-delivery:' . hash('sha256', $actor . ':' . $requestId);
        $fingerprint = hash('sha256', $content);
        $cache = Cache::store('file');

        try {
            return $cache->lock($key . ':lock', 60)->block(5, function () use ($cache, $key, $fingerprint, $send) {
                $saved = $cache->get($key);
                if (is_array($saved)) {
                    abort_unless(hash_equals($saved['fingerprint'], $fingerprint), 409, '送信内容が変わっています。画面を再読み込みしてください。');
                    return ['data' => $saved['data'], 'created' => false];
                }

                $data = $send();
                $cache->put($key, ['fingerprint' => $fingerprint, 'data' => $data], now()->addHours(48));

                return ['data' => $data, 'created' => true];
            });
        } catch (LockTimeoutException $exception) {
            abort(409, '同じメッセージを送信中です。少し待ってから再試行してください。');
        }
    }
}

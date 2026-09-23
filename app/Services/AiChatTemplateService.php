<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「AIに見せかけたテンプレ駆動チャット」のロジック。
 *
 * 自由文の入力からインテント（エリア / 業種 / 時給帯 / 雰囲気 / 未経験OK / ノルマ等）を抽出し、
 * 候補店舗を絞り込んでテンプレ返答と一緒に返す。
 *
 * 真の LLM 呼び出しはしない。返答は事前に用意した日本語の口語テンプレ群から動的選択。
 */
class AiChatTemplateService
{
    /**
     * 公開エリアの代表的な区・市区町村名（東京中心。命名は ph 認識用）
     *
     * @var array<int, string>
     */
    private const KNOWN_AREAS = [
        '六本木', '銀座', '新宿', '渋谷', '池袋', '上野', '秋葉原', '神田', '日本橋',
        '中央区', '港区', '千代田区', '新宿区', '渋谷区', '豊島区', '台東区', '品川区',
        '横浜', '川崎', '大阪', '梅田', '難波', '心斎橋', '京都', '名古屋', '錦', '栄',
        '福岡', '中洲', '天神', '札幌', 'すすきの', '仙台', '国分町',
    ];

    /**
     * 業種名 → industries.name へのマッピング用の表現バリエーション。
     *
     * @var array<string, string>
     */
    private const INDUSTRY_ALIASES = [
        'キャバ'       => 'キャバクラ',
        'キャバクラ'   => 'キャバクラ',
        'クラブ'       => 'クラブ',
        'ナイトクラブ' => 'クラブ',
        'ラウンジ'     => 'ラウンジ',
        'ガルバ'       => 'ガールズバー',
        'ガールズバー' => 'ガールズバー',
        'コンカフェ'   => 'コンカフェ',
        'スナック'     => 'スナック',
        '朝キャバ'     => '朝キャバ',
        '昼キャバ'     => '昼キャバ',
    ];

    /**
     * インテント抽出 → 候補店舗取得 → テンプレ返答を返す。
     *
     * @param  string $userMessage  ユーザー入力（自由文）
     * @param  array<int, array<string,mixed>> $history  会話履歴（未使用だが将来用）
     * @return array{reply: string, recommendations: array<int, array<string,mixed>>, quick_replies: array<int, string>}
     */
    public function respond(string $userMessage, array $history = []): array
    {
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            return $this->fallbackGreeting();
        }

        $grounded = $this->buildGroundedContext($userMessage);

        // 完全一致でも段階的緩和でも 1 件も無い場合（DB がほぼ空、等）は
        // 素の「おすすめ」で必ずカードを出す。空カード状態は基本的に発生させない方針。
        if ($grounded['recommendations'] === []) {
            return [
                'reply' => 'ちょうどピッタリのお店はまだ見つからなかったから、'
                    . 'いま人気で近そうなお店を先におすすめするね✨',
                'recommendations' => [],
                'quick_replies'   => [
                    '未経験OKのお店を探す',
                    '六本木エリアで探す',
                    '時給4500円以上で探す',
                    'ノルマ無しのお店を探す',
                ],
            ];
        }

        return [
            'reply'           => $this->pickReplyTemplate(
                $grounded['intent'],
                count($grounded['recommendations']),
                $grounded['relaxed'] ?? [],
                (int) ($grounded['exact_count'] ?? 0),
            ),
            'recommendations' => $grounded['recommendations'],
            'quick_replies'   => $this->pickQuickReplies($grounded['intent']),
        ];
    }

    /**
     * ユーザー発話から intent と候補店舗を作り、外部（LLM 連携等）から利用できるように返す。
     *
     * `$limit` 件を最低保証ラインとして扱う：条件どんぴしゃが `$limit` 件未満なら
     * 段階的に条件を緩めて近い候補を「補充」し、最終的に必ず `$limit` 件
     * を返す（DB の登録店舗がそもそも足りない場合を除く）。
     * どの条件を緩めたかは `relaxed`、どんぴしゃの件数は `exact_count`、
     * 各候補が完全一致か補充かは recommendation の `matched` で報告する。
     *
     * @return array{
     *   intent: array<string,mixed>,
     *   recommendations: array<int, array<string,mixed>>,
     *   relaxed: array<int, string>,
     *   exact_count: int
     * }
     */
    public function buildGroundedContext(string $userMessage, int $limit = 3): array
    {
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            return ['intent' => $this->extractIntent(''), 'recommendations' => [], 'relaxed' => [], 'exact_count' => 0];
        }
        $limit = max(1, $limit);
        $intent = $this->extractIntent($userMessage);

        // 1) Strict match first.
        $shops = $this->pickShops($intent, $limit);
        $exactCount = count($shops);
        $seen = array_map(fn ($s) => (string) $s->id, $shops);
        $relaxed = [];

        // 2) Top up with progressively relaxed filters until we reach $limit.
        //    Drop the least important condition first
        //    (atmosphere -> reward floor -> wage floor -> industry -> area).
        if (count($shops) < $limit) {
            $relaxSteps = [
                'atmosphere'  => ['atmosphere' => null],
                'reward_min'  => ['reward_min' => 0],
                'wage'        => ['wage_min' => 0, 'high_wage' => false],
                'industry'    => ['industry' => null],
                'area'        => ['area' => null],
            ];
            $working = $intent;
            foreach ($relaxSteps as $label => $override) {
                // Skip conditions the user never specified (not a real relaxation).
                $wasActive = $this->isFilterActive($working, $label);
                $working = array_merge($working, $override);
                if (!$wasActive) {
                    continue;
                }
                $relaxed[] = $label;
                foreach ($this->pickShops($working, $limit * 2) as $row) {
                    if (in_array((string) $row->id, $seen, true)) {
                        continue;
                    }
                    $shops[] = $row;
                    $seen[] = (string) $row->id;
                    if (count($shops) >= $limit) {
                        break;
                    }
                }
                if (count($shops) >= $limit) {
                    break;
                }
            }
        }

        // 3) Still short: fill from the unconditional pool (newest first).
        $countBeforeFallback = count($shops);
        if (count($shops) < $limit) {
            foreach ($this->pickFallbackShops($limit * 2) as $row) {
                if (in_array((string) $row->id, $seen, true)) {
                    continue;
                }
                $shops[] = $row;
                $seen[] = (string) $row->id;
                if (count($shops) >= $limit) {
                    break;
                }
            }
            // Nothing matched at all and only the fallback produced shops.
            if ($exactCount === 0 && $countBeforeFallback === 0 && $shops !== []) {
                $relaxed = ['all_filters'];
            }
        }

        return [
            'intent'          => $intent,
            'recommendations' => array_values(array_map(
                fn ($i, $s) => $this->toRecommendation($s, $intent, $i < $exactCount),
                array_keys($shops),
                $shops,
            )),
            'relaxed'         => $relaxed,
            'exact_count'     => $exactCount,
        ];
    }

    /**
     * intent の特定フィルタが「実際に指定されているか」を判定。
     */
    private function isFilterActive(array $intent, string $label): bool
    {
        return match ($label) {
            'atmosphere' => !empty($intent['atmosphere']),
            'reward_min' => (int) ($intent['reward_min'] ?? 0) > 0,
            'wage'       => ((int) ($intent['wage_min'] ?? 0) > 0) || !empty($intent['high_wage']),
            'industry'   => !empty($intent['industry']),
            'area'       => !empty($intent['area']),
            default      => false,
        };
    }


    /**
     * intent からクイックリプライ候補を返す（外部からも利用できるように公開）。
     *
     * @param array<string,mixed> $intent
     * @return array<int, string>
     */
    public function buildQuickReplies(array $intent): array
    {
        return $this->pickQuickReplies($intent);
    }

    // =================================================================
    // インテント抽出
    // =================================================================

    /**
     * @return array{
     *   area: ?string,
     *   industry: ?string,
     *   wage_min: int,
     *   reward_min: int,
     *   no_experience: bool,
     *   no_norma: bool,
     *   high_wage: bool,
     *   near_station: bool,
     *   atmosphere: ?string
     * }
     */
    private function extractIntent(string $msg): array
    {
        $intent = [
            'area'          => null,
            'industry'      => null,
            'wage_min'      => 0,
            'reward_min'    => 0,
            'no_experience' => false,
            'no_norma'      => false,
            'high_wage'     => false,
            'near_station'  => false,
            'atmosphere'    => null,
        ];

        // エリア
        foreach (self::KNOWN_AREAS as $area) {
            if (mb_strpos($msg, $area) !== false) {
                $intent['area'] = $area;
                break;
            }
        }

        // 業種
        foreach (self::INDUSTRY_ALIASES as $alias => $canonical) {
            if (mb_strpos($msg, $alias) !== false) {
                $intent['industry'] = $canonical;
                break;
            }
        }

        // 時給（"4500円以上" / "時給5千" / "時給4000" など）
        if (preg_match('/時給[^0-9]{0,4}([1-9]\d{2,4})/u', $msg, $m)) {
            $intent['wage_min'] = (int) $m[1];
        } elseif (preg_match('/(\d{4,5})円以上/u', $msg, $m)) {
            $intent['wage_min'] = (int) $m[1];
        } elseif (preg_match('/([1-9])千円?/u', $msg, $m)) {
            $intent['wage_min'] = ((int) $m[1]) * 1000;
        }

        // 採用報酬
        if (preg_match('/(?:報酬|お祝い金|入店祝金|ボーナス)[^0-9]{0,4}([1-9]\d{2,4})/u', $msg, $m)) {
            $intent['reward_min'] = (int) $m[1] * 100;
        }

        // 質感ワード
        if (preg_match('/(未経験|初心者|初めて|はじめて|初出勤)/u', $msg)) {
            $intent['no_experience'] = true;
        }
        if (preg_match('/(ノルマ).{0,5}(緩|なし|無し|ない|無)/u', $msg) || mb_strpos($msg, 'ノルマ無') !== false) {
            $intent['no_norma'] = true;
        }
        if (preg_match('/(高時給|稼げ|高収入|稼ぎたい)/u', $msg)) {
            $intent['high_wage'] = true;
        }
        if (preg_match('/(駅近|駅前|徒歩|駅から)/u', $msg)) {
            $intent['near_station'] = true;
        }
        if (preg_match('/(落ち着|静か|大人|シック|アット)/u', $msg)) {
            $intent['atmosphere'] = 'calm';
        } elseif (preg_match('/(賑や|盛り上が|わいわい|パーティ|楽し|明るい)/u', $msg)) {
            $intent['atmosphere'] = 'lively';
        }

        return $intent;
    }

    // =================================================================
    // 候補店舗の選定
    // =================================================================

    /**
     * 全条件で 0 件だったときの最終保険。全店舗から新着順に返す。
     *
     * @return array<int, object>
     */
    private function pickFallbackShops(int $limit): array
    {
        return $this->pickShops([
            'area'          => null,
            'industry'      => null,
            'wage_min'      => 0,
            'reward_min'    => 0,
            'no_experience' => false,
            'no_norma'      => false,
            'high_wage'     => false,
            'near_station'  => false,
            'atmosphere'    => null,
        ], $limit);
    }

    /**
     * 条件を緩めて拾ったときの前置き文。緩めたラベルに応じてトーンを変える。
     *
     * @param array<int, string> $relaxed
     */
    private function buildRelaxedOpener(array $relaxed): string
    {
        // 全部外した（fallback）→ もっと控えめに謝る
        if (in_array('all_filters', $relaxed, true)) {
            return $this->randomPick([
                'うーん、その条件どんぴしゃのお店はまだないみたい🥺 いま登録があるお店から、近そうなのを先に紹介するね✨',
                'ちょうどぴったりが見つからなかったから、いま人気で近そうなお店を並べてみたよ💎',
                '条件フルマッチのお店はまだなんだけど、雰囲気が近そうなところをおすすめするね☕',
            ]);
        }

        // どの条件を緩めたかで一言添える
        $labelMap = [
            'area'       => 'エリア',
            'industry'   => '業種',
            'wage'       => '時給の条件',
            'reward_min' => '採用報酬の条件',
            'atmosphere' => '雰囲気の条件',
        ];
        $labels = array_values(array_filter(array_map(fn ($k) => $labelMap[$k] ?? null, $relaxed)));
        $labelText = $labels === [] ? '条件' : implode('・', $labels);

        return $this->randomPick([
            'その条件どんぴしゃのお店はまだ無かったから、' . $labelText . 'を少し広げて近い候補をピックアップしたよ✨',
            'ぴったりのお店は見つからなかったんだけど、' . $labelText . 'を緩めたら近いお店があったよ💎',
            '完全一致のお店はまだ登録がないみたい🥺 ' . $labelText . 'を少しだけ広げて近そうなのを出すね✨',
        ]);
    }

    /**
     * @param array<string,mixed> $intent
     * @return array<int, object>
     */
    private function pickShops(array $intent, int $limit): array
    {
        $q = DB::table('shops')
            ->join('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
            ->leftJoin('shop_jobs', 'shops.id', '=', 'shop_jobs.shop_id')
            ->select(
                'shops.id',
                'shop_profiles.shop_name',
                'shop_profiles.pref',
                'shop_profiles.city',
                'shop_profiles.industry_id',
                DB::raw("(SELECT si.image_path FROM shop_images si WHERE si.shop_id = shops.id ORDER BY si.is_main DESC, si.id LIMIT 1) as main_image_path"),
            );

        // 候補に含めるカラムを動的に追加
        if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
            $q->addSelect('shop_jobs.regular_hourly_wage');
        }
        if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
            $q->addSelect('shop_jobs.hourly_wage_regular');
        }
        if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
            $q->addSelect('shop_jobs.bonus_reward');
        }
        if (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
            $q->addSelect('shop_jobs.noruma_reward');
        }

        // エリア
        if (!empty($intent['area'])) {
            $area = (string) $intent['area'];
            $q->where(function ($w) use ($area) {
                $w->where('shop_profiles.pref', 'like', '%' . $area . '%')
                  ->orWhere('shop_profiles.city', 'like', '%' . $area . '%');
            });
        }

        // 業種
        if (!empty($intent['industry'])) {
            $industryId = DB::table('industries')->where('name', $intent['industry'])->where('del_flg', 0)->value('id');
            if ($industryId) {
                $q->where('shop_profiles.industry_id', $industryId);
            }
        }

        // 時給下限
        if ($intent['wage_min'] > 0) {
            $cols = [];
            if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
                $cols[] = 'shop_jobs.regular_hourly_wage';
            }
            if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
                $cols[] = 'shop_jobs.hourly_wage_regular';
            }
            if ($cols !== []) {
                $q->whereRaw('COALESCE(' . implode(', ', $cols) . ', 0) >= ?', [$intent['wage_min']]);
            }
        }

        // 採用報酬下限
        if ($intent['reward_min'] > 0) {
            $cols = [];
            if (Schema::hasColumn('shop_jobs', 'bonus_reward')) {
                $cols[] = 'shop_jobs.bonus_reward';
            }
            if (Schema::hasColumn('shop_jobs', 'noruma_reward')) {
                $cols[] = 'shop_jobs.noruma_reward';
            }
            if ($cols !== []) {
                $q->whereRaw('COALESCE(' . implode(', ', $cols) . ', 0) >= ?', [$intent['reward_min']]);
            }
        }

        // 高時給フラグ（明示的な金額指定がなければ目安として 4500 円）
        if ($intent['high_wage'] && $intent['wage_min'] === 0) {
            $cols = [];
            if (Schema::hasColumn('shop_jobs', 'regular_hourly_wage')) {
                $cols[] = 'shop_jobs.regular_hourly_wage';
            }
            if (Schema::hasColumn('shop_jobs', 'hourly_wage_regular')) {
                $cols[] = 'shop_jobs.hourly_wage_regular';
            }
            if ($cols !== []) {
                $q->whereRaw('COALESCE(' . implode(', ', $cols) . ', 0) >= 4500');
            }
        }

        // 1店舗 1 求人想定なので shops.id でユニーク化（safety）
        $q->groupBy('shops.id');

        // 高時給/採用報酬を希望していれば、それらで降順優先。それ以外は新着順。
        if ($intent['high_wage'] || $intent['wage_min'] > 0) {
            $cols = array_filter([
                Schema::hasColumn('shop_jobs', 'regular_hourly_wage') ? 'shop_jobs.regular_hourly_wage' : null,
                Schema::hasColumn('shop_jobs', 'hourly_wage_regular') ? 'shop_jobs.hourly_wage_regular' : null,
            ]);
            if ($cols !== []) {
                $q->orderByRaw('COALESCE(' . implode(', ', $cols) . ', 0) DESC');
            }
        }
        $q->orderByDesc('shops.created_at')->orderByDesc('shops.id');

        $rows = $q->limit($limit)->get();
        return $rows->all();
    }

    /**
     * @param array<string,mixed> $intent
     * @return array<string, mixed>
     */
    private function toRecommendation(object $row, array $intent, bool $matched = true): array
    {
        $wage = (int) ($row->regular_hourly_wage ?? $row->hourly_wage_regular ?? 0);
        $reward = (int) ($row->bonus_reward ?? $row->noruma_reward ?? 0);

        return [
            'id'         => $row->id,
            'name'       => (string) ($row->shop_name ?? 'ショップ'),
            'pref'       => (string) ($row->pref ?? ''),
            'city'       => (string) ($row->city ?? ''),
            'image'      => $this->shopImageUrl($row->main_image_path ?? null),
            'wage'       => $wage,
            'reward'     => $reward,
            'reason'     => $this->reasonText($row, $intent),
            'url'        => url('/cast/shopprofiles/' . $row->id),
            'matched'    => $matched,
        ];
    }

    /**
     * 候補1件ごとの「選ばれた理由」テキスト（テンプレに値を埋め込んで会話風にする）
     *
     * @param array<string,mixed> $intent
     */
    private function reasonText(object $row, array $intent): string
    {
        $bits = [];
        if (!empty($intent['area']) && (mb_strpos((string) ($row->pref ?? ''), (string) $intent['area']) !== false
            || mb_strpos((string) ($row->city ?? ''), (string) $intent['area']) !== false)) {
            $bits[] = $intent['area'] . 'エリアど真ん中';
        }
        if ($intent['high_wage'] || $intent['wage_min'] > 0) {
            $wage = (int) ($row->regular_hourly_wage ?? $row->hourly_wage_regular ?? 0);
            if ($wage > 0) {
                $bits[] = '時給 ' . number_format($wage) . ' 円〜';
            }
        }
        if ($intent['no_experience']) {
            $bits[] = '未経験さん歓迎の雰囲気';
        }
        if ($intent['no_norma']) {
            $bits[] = 'ノルマ緩めで安心';
        }
        if ($intent['atmosphere'] === 'calm') {
            $bits[] = '落ち着いた接客スタイル';
        }
        if ($intent['atmosphere'] === 'lively') {
            $bits[] = '元気な接客が映えるお店';
        }

        if ($bits === []) {
            $bits[] = '今あなたの希望と相性が良さそう';
        }

        return implode(' / ', $bits);
    }

    // =================================================================
    // テンプレ
    // =================================================================

    /**
     * @param array<string,mixed> $intent
     * @param array<int, string>  $relaxed  緩和した条件ラベル（'area','industry','wage','reward_min','atmosphere','all_filters'）
     * @param int                 $exactCount  条件どんぴしゃで見つかった件数（残りは補充）
     */
    private function pickReplyTemplate(array $intent, int $count, array $relaxed = [], int $exactCount = 0): string
    {
        // 条件を緩めて拾ったときは、冒頭で「ちょうどのお店はまだない」と正直に伝える
        if ($relaxed !== [] && $exactCount > 0) {
            // Partial fill: some strict matches exist, the rest are near matches.
            $opener = $this->randomPick([
                "条件ぴったりのお店は{$exactCount}件見つかったよ！近い雰囲気のお店も一緒に並べるね✨",
                "どんぴしゃは{$exactCount}件！ほかにも良さそうな近い候補を足しておいたよ💎",
            ]);
        } elseif ($relaxed !== []) {
            $opener = $this->buildRelaxedOpener($relaxed);
        } else {
            $opener = $this->randomPick([
                'ちょっと考えるね…',
                'なるほど〜！',
                'うんうん、わかった！',
                'いい感じの希望だね✨',
            ]);
        }
        $closing = $this->randomPick([
            'どれか気になるところある？',
            '気になるお店があったら「詳しく」って言ってね💎',
            'もっと条件を絞りたい時は遠慮なく教えて〜！',
            'この中だとどれが好み？',
        ]);

        $core = '';
        if (!empty($intent['industry']) && !empty($intent['area'])) {
            $core = "{$intent['area']}エリアの{$intent['industry']}から、{$count}件ピックアップしてみたよ✨";
        } elseif (!empty($intent['area'])) {
            $core = "{$intent['area']}エリアでこの{$count}件、おすすめできそう💎";
        } elseif (!empty($intent['industry'])) {
            $core = "{$intent['industry']}のお店から{$count}件、雰囲気が良さそうなのを選んだよ☕";
        } elseif ($intent['wage_min'] > 0) {
            $core = "時給 {$intent['wage_min']} 円以上のお店から{$count}件、ピックアップしたよ💰";
        } elseif ($intent['high_wage']) {
            $core = "稼ぎたい派には、まずはこの{$count}件かな〜💎";
        } elseif ($intent['no_experience']) {
            $core = "未経験さんでも安心して入れそうなお店、{$count}件選んだよ🌸";
        } elseif ($intent['no_norma']) {
            $core = "ノルマ緩めでマイペースに働けそうなお店を{$count}件！";
        } elseif ($intent['atmosphere'] === 'calm') {
            $core = "落ち着いた雰囲気のお店、{$count}件いい感じのを選んできたよ🌙";
        } elseif ($intent['atmosphere'] === 'lively') {
            $core = "賑やかで盛り上がる系のお店、{$count}件見つけてきた🎉";
        } else {
            $core = "今のヒントから、{$count}件選んでみたよ✨";
        }

        return $opener . ' ' . $core . "\n\n" . $closing;
    }

    /**
     * @return array<int, string>
     */
    private function fallbackGreeting(): array
    {
        return [
            'reply' => "こんにちは✨ あなたにピッタリのお店、AIが一緒に探すよ！\n例えば「六本木で時給高いお店」「未経験OKでノルマ緩いところ」みたいに教えてくれると見つけやすいよ💎",
            'recommendations' => [],
            'quick_replies' => [
                '六本木で時給高いお店',
                '未経験OKのお店',
                'ノルマ無しで働きたい',
                '銀座のクラブを見る',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $intent
     * @return array<int, string>
     */
    private function pickQuickReplies(array $intent): array
    {
        $bag = [];
        if (empty($intent['area'])) {
            $bag[] = '銀座エリアで絞りたい';
            $bag[] = '六本木で見たい';
        } else {
            $bag[] = '別のエリアも見たい';
        }
        if ($intent['wage_min'] === 0 && !$intent['high_wage']) {
            $bag[] = '時給高い順で見たい';
        }
        if (!$intent['no_experience']) {
            $bag[] = '未経験OKに絞って';
        }
        if (!$intent['no_norma']) {
            $bag[] = 'ノルマ無しで探して';
        }
        $bag[] = '採用報酬が高いお店は?';

        return array_slice($bag, 0, 4);
    }

    // =================================================================
    // 補助
    // =================================================================

    private function shopImageUrl(?string $path): string
    {
        if (empty($path)) {
            return asset('assets/images/common/no-image.png');
        }
        if (str_starts_with($path, 'uploads/')) {
            return asset($path);
        }
        if (str_starts_with($path, 'public/')) {
            return asset('storage/' . substr($path, 7));
        }
        return asset(ltrim($path, '/'));
    }

    /**
     * @param array<int, string> $candidates
     */
    private function randomPick(array $candidates): string
    {
        if ($candidates === []) {
            return '';
        }
        return $candidates[array_rand($candidates)];
    }
}

<?php

namespace App\Services;

/**
 * AI コンシェルジュのオーケストレータ。
 *
 * 「候補店舗プールは決定論的（AiChatTemplateService・最低件数を保証）」+
 * 「プール内からの選定・おすすめ理由・会話文生成は LLM（LlmChatService）」
 * というハイブリッド構成。
 *
 * LLM はプールに実在する店舗 id しか選べないため、hallucination で
 * 存在しないお店を紹介するリスクは無い。JSON 選定に失敗した場合は
 * プール先頭（条件一致順）の決定論的な選定 + 平文返答へ、LLM 自体が
 * 無効／失敗ならテンプレ返答へ自動フォールバックする。
 */
class AiConciergeService
{
    /** 決定論的に用意する候補プールの件数（LLM はこの中からだけ選ぶ） */
    private const CANDIDATE_POOL_SIZE = 8;

    /** 推薦カードの最低表示件数（DB の登録が足りない場合を除き必ず満たす） */
    private const MIN_RECOMMENDATIONS = 3;

    /** LLM 選定時の最大表示件数 */
    private const MAX_RECOMMENDATIONS = 5;
    /**
     * コンシェルジュのシステムプロンプト。
     * 口調・出力形式・禁止事項をここで固定する。
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
あなたはミセチョク（水商売・夜職向けのマッチングアプリ）に常駐する
「AI コンシェルジュ」です。求職者（キャスト）の相談を受けて、
おすすめのお店を紹介します。

# キャラクター
- 一人称は「私」または名前なし。夜職の先輩のような、親身で明るい口調。
- 語尾はカジュアル。文末に絵文字（✨💎🌸💰🌙🎉☕🥺 など）を1文につき1つまで
  自然に添える。過剰にしない。
- 敬語は避け、フレンドリーな「〜だよ」「〜だね」「〜してみて」を基本にする。
- 相手を励ますトーンを保つ。「未経験でも大丈夫」「一緒に探そう」など。

# タスク
- ユーザーの希望（エリア／業種／時給／未経験可／ノルマの有無／雰囲気 など）を
  読み取り、それに合う候補店舗を提示する。
- 候補店舗のリストは開発者から「### 候補店舗（この中からのみ紹介できる）」として
  与えられる。それ以外のお店の名前を出してはいけない。
- 開発者からの補足に「条件を緩めて出した」旨が明記されているときは、
  必ず冒頭で「ちょうどぴったりのお店はまだ無かったから、○○を広げて近いところを出したよ」
  という正直で前向きなトーンを添えること（強要）。
- 候補が本当に 0 件のときは、条件を緩める提案をする（「エリアを変える？」等）。
- 会話履歴を踏まえて、繰り返しの挨拶にならないよう自然に続ける。

# 出力
- Markdown や見出しは使わない。日本語の話し言葉のみ。
- 100〜200 文字程度に収める。カード（お店の詳細）は別途 UI で表示されるため、
  お店の値段や住所を長々と再掲する必要はない。
- 応答の末尾に「気になるお店ある？」「他の条件も試してみる？」など、
  次のアクションを促す軽い一言を添える。
- 候補が複数あるときは各店の魅力を "選ばれた理由" を軸に、1〜2文で並列に触れる。
- 絶対に電話番号・住所の詳細・年齢確認要件・法令に関わる助言は出さない。
- 医療・法律・税務の具体的助言は避け、必要なら「専門家に相談」と促す。
PROMPT;

    /**
     * 店舗選定モードのシステムプロンプト。
     * 候補プールから 3〜5 件を選ばせ、返答文 + 選定理由を JSON で受け取る。
     */
    private const SELECTION_PROMPT = <<<'PROMPT'
あなたはミセチョク（水商売・夜職向けのマッチングアプリ）に常駐する
「AI コンシェルジュ」です。求職者（キャスト）の希望と会話履歴を踏まえ、
開発者から渡される候補店舗リストの中から相談者に合うお店を選んで紹介します。

# 厳守事項
- 候補リストにある id のお店だけを選ぶ。リストに無い店名・id を絶対に出さない。
- 3件以上 5件以内で選ぶ。候補が 3 件しか無ければ 3 件すべて選ぶ。
- 出力は JSON オブジェクトのみ。前後に説明文・コードブロックを付けない。

# 出力形式
{"reply": "紹介文", "picks": [{"id": "候補のid", "reason": "選んだ理由"}]}
- reply: 100〜200字。日本語の話し言葉のみ（Markdown・見出し禁止）。
  夜職の先輩のような親身で明るいカジュアルな口調（「〜だよ」「〜だね」）。
  絵文字（✨💎🌸💰🌙 など）は1文につき1つまで。
  末尾に「気になるお店ある？」など次のアクションを促す一言を添える。
  開発者補足に「どんぴしゃが無い／少ない」とあれば、冒頭で
  「ぴったりはまだ少ないから近い候補も出したよ」と正直に伝える。
- reason: 30字以内。相談者の希望や接客タイプに紐づけて書く。
- 電話番号・住所の詳細・法令に関わる助言・医療/法律/税務の具体的助言は書かない。
PROMPT;

    /**
     * 接客タイプ診断（4文字コード）の各軸の意味。
     * LLM が「私のタイプに合うお店」等の相談に答えられるようコンテキストに添える。
     */
    private const PERSONALITY_AXES = [
        'L' => 'リード型（会話を主導して場を盛り上げる）',
        'F' => 'フォロワー型（聞き役でお客様のペースに合わせる）',
        'C' => '恋人型（女性らしさ・疑似恋愛が武器）',
        'P' => 'パートナー型（知性と対等な会話が武器）',
        'I' => '懐型（人懐っこく一気に距離を詰める）',
        'O' => '領域型（プロの距離感・ミステリアスさを保つ）',
        'H' => 'ハンター型（短期集中で大きな結果を出す）',
        'R' => 'リレーション型（マメな連絡で関係をじっくり育てる）',
    ];

    public function __construct(
        private readonly AiChatTemplateService $template,
        private readonly LlmChatService $llm,
    ) {
    }

    /**
     * ユーザ発話に返答する。
     *
     * @param  string $userMessage
     * @param  array<int, array{role:string, content:string}> $history
     * @param  ?string $personalityType 登録済みの接客タイプ診断（例: LCIR）
     * @return array{reply: string, recommendations: array<int, array<string,mixed>>, quick_replies: array<int, string>, source: 'llm'|'template'}
     */
    public function respond(string $userMessage, array $history = [], ?string $personalityType = null): array
    {
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            $g = $this->template->respond('');
            $g['source'] = 'template';
            return $g;
        }

        // 1) 常に決定論的に intent と店舗候補プールを作る（LLM の hallucination 回避）。
        //    プールは条件一致順（どんぴしゃ → 段階緩和の補充）で最低件数が保証される。
        $grounded = $this->template->buildGroundedContext($userMessage, self::CANDIDATE_POOL_SIZE);
        $intent = $grounded['intent'];
        $pool   = $grounded['recommendations'];
        $relaxed = $grounded['relaxed'] ?? [];

        // 決定論的なデフォルト選定（プール先頭 = 条件一致度の高い順）
        $recs = array_slice($pool, 0, self::MIN_RECOMMENDATIONS);

        if ($this->llm->isEnabled()) {
            // 2) 本命: LLM がプールから 3〜5 件を選定し、返答文と理由も生成（JSON）
            $picked = $this->selectWithLlm($userMessage, $history, $intent, $pool, $personalityType, $relaxed);
            if ($picked !== null) {
                return [
                    'reply'           => $picked['reply'],
                    'recommendations' => $picked['recommendations'],
                    'quick_replies'   => $this->template->buildQuickReplies($intent),
                    'source'          => 'llm',
                ];
            }

            // 3) JSON 選定に失敗 → デフォルト選定のまま平文の返答だけ LLM に生成させる
            $llmReply = $this->llm->chat(
                self::SYSTEM_PROMPT,
                $this->buildMessages($userMessage, $history, $intent, $recs, $personalityType, $relaxed),
            );
            if ($llmReply !== null && $llmReply !== '') {
                return [
                    'reply'           => $this->sanitizeLlmReply($llmReply),
                    'recommendations' => $recs,
                    'quick_replies'   => $this->template->buildQuickReplies($intent),
                    'source'          => 'llm',
                ];
            }
        }

        // 4) LLM 不使用／失敗 → テンプレ（こちらも最低件数は保証される）
        $t = $this->template->respond($userMessage, $history);
        $t['source'] = 'template';
        return $t;
    }

    /**
     * LLM に候補プールから店舗を選定させ、返答文・選定理由を JSON で受け取る。
     * 失敗（無効な JSON・プール外 id のみ等）は null を返して呼び出し側でフォールバック。
     *
     * @param  array<int, array{role:string, content:string}> $history
     * @param  array<string,mixed> $intent
     * @param  array<int, array<string,mixed>> $pool
     * @param  array<int, string> $relaxed
     * @return array{reply: string, recommendations: array<int, array<string,mixed>>}|null
     */
    private function selectWithLlm(string $userMessage, array $history, array $intent, array $pool, ?string $personalityType, array $relaxed): ?array
    {
        if ($pool === []) {
            return null;
        }

        $messages = $this->normalizeHistory($history);
        $last = end($messages);
        if ($last === false || ($last['role'] ?? '') !== 'user' || ($last['content'] ?? '') !== $userMessage) {
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        }
        $lastIdx = count($messages) - 1;
        $messages[$lastIdx]['content'] .= "\n\n---\n"
            . $this->formatShopsContext($intent, $pool, $personalityType, $relaxed, true);

        $raw = $this->llm->chat(self::SELECTION_PROMPT, $messages, [
            'response_format' => ['type' => 'json_object'],
            'temperature'     => 0.6,
            'max_tokens'      => 700,
        ]);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $data = $this->decodeJsonReply($raw);
        if (!is_array($data)) {
            return null;
        }
        $reply = trim((string) ($data['reply'] ?? ''));
        if ($reply === '') {
            return null;
        }

        $byId = [];
        foreach ($pool as $rec) {
            $byId[(string) ($rec['id'] ?? '')] = $rec;
        }

        $selected = [];
        $used = [];
        $picks = is_array($data['picks'] ?? null) ? $data['picks'] : [];
        foreach ($picks as $pick) {
            if (!is_array($pick)) {
                continue;
            }
            $id = (string) ($pick['id'] ?? '');
            if ($id === '' || !isset($byId[$id]) || isset($used[$id])) {
                continue;
            }
            $rec = $byId[$id];
            $reason = trim((string) ($pick['reason'] ?? ''));
            if ($reason !== '') {
                $reason = preg_replace('/\s+/u', ' ', $reason) ?? $reason;
                $rec['reason'] = mb_substr($reason, 0, 60);
            }
            $selected[] = $rec;
            $used[$id] = true;
            if (count($selected) >= self::MAX_RECOMMENDATIONS) {
                break;
            }
        }

        // 最低件数を保証：不足分はプール順（条件一致度の高い順）で補充する
        foreach ($pool as $rec) {
            if (count($selected) >= self::MIN_RECOMMENDATIONS) {
                break;
            }
            $id = (string) ($rec['id'] ?? '');
            if (isset($used[$id])) {
                continue;
            }
            $selected[] = $rec;
            $used[$id] = true;
        }

        if ($selected === []) {
            return null;
        }

        return [
            'reply'           => $this->sanitizeLlmReply($reply),
            'recommendations' => array_values($selected),
        ];
    }

    /**
     * LLM の JSON 返答をパースする。コードフェンスや前後の文が混じっても救済する。
     *
     * @return array<string,mixed>|null
     */
    private function decodeJsonReply(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?? $raw;

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * LLM に渡す messages を組む。履歴は role=user/assistant の平文で送る。
     *
     * @param  array<int, array{role:string, content:string}> $history
     * @param  array<string,mixed> $intent
     * @param  array<int, array<string,mixed>> $recs
     * @return array<int, array{role:string, content:string}>
     */
    private function buildMessages(string $userMessage, array $history, array $intent, array $recs, ?string $personalityType = null, array $relaxed = []): array
    {
        $out = $this->normalizeHistory($history);

        // 直近のユーザ入力（重複を避けるため、末尾が同じなら追加しない）
        $lastUserPushed = end($out);
        if ($lastUserPushed === false || ($lastUserPushed['role'] ?? '') !== 'user'
            || ($lastUserPushed['content'] ?? '') !== $userMessage) {
            $out[] = ['role' => 'user', 'content' => $userMessage];
        }

        // 候補店舗を "developer / system 補足" として最後の user メッセージに
        // インジェクション形式で添える（履歴に残さない）
        $context = $this->formatShopsContext($intent, $recs, $personalityType, $relaxed);
        if ($context !== '') {
            // 末尾の user メッセージにコンテキストを追加
            $lastIdx = count($out) - 1;
            $out[$lastIdx]['content'] = $out[$lastIdx]['content']
                . "\n\n---\n" . $context;
        }

        return $out;
    }

    /**
     * 会話履歴を user/assistant のみに正規化する。
     *
     * @param  array<int, array{role:string, content:string}> $history
     * @return array<int, array{role:string, content:string}>
     */
    private function normalizeHistory(array $history): array
    {
        $out = [];
        foreach ($history as $h) {
            $role = (string) ($h['role'] ?? '');
            $role = match ($role) {
                'ai', 'assistant' => 'assistant',
                'user'            => 'user',
                default           => null,
            };
            if ($role === null) continue;
            $content = trim((string) ($h['content'] ?? ''));
            if ($content === '') continue;
            $out[] = ['role' => $role, 'content' => $content];
        }
        return $out;
    }

    /**
     * @param  array<string,mixed> $intent
     * @param  array<int, array<string,mixed>> $recs
     * @param  bool $forSelection  true = 店舗選定モード（id を明示し JSON 出力を指示）
     */
    private function formatShopsContext(array $intent, array $recs, ?string $personalityType = null, array $relaxed = [], bool $forSelection = false): string
    {
        $lines = [];
        $lines[] = '（開発者からの補足。ユーザには見えない）';
        $lines[] = '解析された希望条件: ' . $this->intentSummary($intent);
        if ($personalityType !== null && $personalityType !== '') {
            $axes = array_filter(array_map(
                fn (string $c) => self::PERSONALITY_AXES[$c] ?? null,
                str_split($personalityType),
            ));
            $lines[] = '相談者の接客タイプ診断: ' . $personalityType
                . '（' . implode(' / ', $axes) . '）'
                . '。「私のタイプに合うお店」等と聞かれたらこの特性を踏まえて候補の魅力を語ること。';
        }

        // どんぴしゃ／補充の内訳を LLM に伝える（返答トーンの調整用）
        $total = count($recs);
        $matchedCount = count(array_filter($recs, fn ($r) => !empty($r['matched'])));

        if ($total > 0 && $matchedCount > 0 && $matchedCount < $total) {
            $lines[] = '重要: 条件どんぴしゃのお店は ' . $matchedCount . ' 件のみで、'
                . '残りは条件を少し緩めた近い候補です。'
                . '「ぴったりは' . $matchedCount . '件、ほかに近い候補も出したよ」のニュアンスを自然に添えてください。';
        } elseif ($matchedCount === 0 && $relaxed !== []) {
            if (in_array('all_filters', $relaxed, true)) {
                $lines[] = '重要: 条件どんぴしゃのお店は登録がなかったため、'
                    . '全条件を外して人気順・新着順で近いお店を出しています。'
                    . '返答の冒頭で「ちょうどぴったりのお店はまだないから、いま近そうなお店を先に紹介するね」等の'
                    . '正直で前向きなトーンを必ず入れてください。';
            } else {
                $labelMap = [
                    'area'       => 'エリア',
                    'industry'   => '業種',
                    'wage'       => '時給の条件',
                    'reward_min' => '採用報酬の条件',
                    'atmosphere' => '雰囲気の条件',
                ];
                $labels = array_values(array_filter(array_map(fn ($k) => $labelMap[$k] ?? null, $relaxed)));
                $labelText = $labels === [] ? '条件' : implode('・', $labels);
                $lines[] = '重要: 条件どんぴしゃのお店が無かったため、【' . $labelText . '】を少し緩めて近い候補を出しています。'
                    . '返答の冒頭で「ぴったりのお店はまだないから、' . $labelText . 'を少し広げて近いところを出したよ」等、'
                    . '正直に伝える一言を必ず入れてください。';
            }
        }

        $headline = $forSelection ? '### 候補店舗（この中からのみ選べる）' : '### 候補店舗（この中からのみ紹介できる）';
        if (empty($recs)) {
            $lines[] = $headline;
            $lines[] = '（該当なし。「まだピッタリが無いから、また条件を教えてね」と前向きに促してください）';
        } else {
            $lines[] = $headline;
            foreach ($recs as $i => $r) {
                $n = $i + 1;
                $area = trim(($r['pref'] ?? '') . ' ' . ($r['city'] ?? ''));
                $wage = !empty($r['wage'])
                    ? '時給 ' . number_format((int) $r['wage']) . '円〜'
                    : '時給情報なし';
                $reward = !empty($r['reward'])
                    ? '入店報酬 ' . number_format((int) $r['reward']) . '円'
                    : '';
                $reason = trim((string) ($r['reason'] ?? ''));
                $tag = array_key_exists('matched', $r)
                    ? (!empty($r['matched']) ? '条件ぴったり' : '近い候補')
                    : '';
                $bits = array_filter([$area, $wage, $reward, $reason, $tag]);
                $idPart = $forSelection ? '[id: ' . (string) ($r['id'] ?? '') . '] ' : '';
                $lines[] = "{$n}. {$idPart}{$r['name']} — " . implode(' / ', $bits);
            }
        }
        $lines[] = '';
        $lines[] = $forSelection
            ? '上記の候補から相談者に最も合うお店を 3〜5 件選び、指定の JSON 形式のみで出力してください。'
            : '上記の候補を踏まえて、自然な口調で 100〜200 字の返答を1つだけ生成してください。';
        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $intent
     */
    private function intentSummary(array $intent): string
    {
        $bits = [];
        if (!empty($intent['area']))       $bits[] = 'エリア=' . $intent['area'];
        if (!empty($intent['industry']))   $bits[] = '業種='   . $intent['industry'];
        if (!empty($intent['wage_min']))   $bits[] = '時給下限=' . $intent['wage_min'];
        if (!empty($intent['reward_min'])) $bits[] = '報酬下限=' . $intent['reward_min'];
        if (!empty($intent['no_experience'])) $bits[] = '未経験OK希望';
        if (!empty($intent['no_norma']))      $bits[] = 'ノルマ緩め希望';
        if (!empty($intent['high_wage']))     $bits[] = '高時給希望';
        if (!empty($intent['near_station']))  $bits[] = '駅近希望';
        if (!empty($intent['atmosphere']))    $bits[] = '雰囲気=' . $intent['atmosphere'];
        return $bits === [] ? '（明確な指定なし）' : implode(', ', $bits);
    }

    /**
     * LLM の生返答から余計な要素（コードブロック、冗長な自己紹介、Markdown 見出し）を軽く除去。
     */
    private function sanitizeLlmReply(string $reply): string
    {
        // コードブロック除去
        $reply = preg_replace('/```[\s\S]*?```/', '', $reply) ?? $reply;
        // 見出し記号除去
        $reply = preg_replace('/^#+\s*/m', '', $reply) ?? $reply;
        // 連続空行を 1 行に
        $reply = preg_replace("/\n{3,}/", "\n\n", $reply) ?? $reply;
        // 長すぎる返答は 400 字で切る（保険）
        if (mb_strlen($reply) > 400) {
            $reply = mb_substr($reply, 0, 400) . '…';
        }
        return trim($reply);
    }
}

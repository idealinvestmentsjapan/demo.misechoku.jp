<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * トークのクイック定型文カタログ。
 *
 * 運営が /admin/talk-quick-replies から編集した内容を `talk_quick_reply_templates`
 * テーブルから読み込み、テーブルが未整備な環境 (テスト/初期構築直後) では
 * DEFAULT_TEMPLATES ハードコード配列にフォールバックする。DEFAULT_TEMPLATES は
 * mock_demo.sql の初期シードと同じ内容を保持するので、DB とコードが同じ既定値を
 * 共有する。運営が本番で編集した内容はこのカタログを介してトークルームに反映される。
 *
 * ステータスコードは TalkController のプライベート定数と同じ値を維持する。
 */
final class TalkQuickReplyCatalog
{
    public const STATUS_CHATTING          = 1;
    public const STATUS_INTERVIEW_PENDING = 2;
    public const STATUS_INTERVIEW_FIXED   = 3;
    public const STATUS_HIRED             = 4;
    public const STATUS_REJECTED          = 5;
    public const STATUS_HIRED_FULLTIME    = 6;
    public const STATUS_REJECTED_TRIAL    = 7;

    private const TABLE = 'talk_quick_reply_templates';

    /**
     * 指定ステータス x 役割の定型文候補を返す。DB を優先し、無ければ既定へフォールバック。
     *
     * $talkTopic は初回応募時の求人種別コンテキスト（'new_hire' / 'help'）。
     * 「やり取り中」ステータスでのみ利用され、コンテキストに合ったテンプレを優先。
     *
     * @return array<int, array{category:string, body:string}>
     */
    public function forStatus(bool $isCastPortal, int $status, ?string $talkTopic = null): array
    {
        $ownerType = $isCastPortal ? 'cast' : 'shop';
        $statusKey = self::statusKey($status);
        $topicKey = self::topicKey($ownerType, $statusKey, $talkTopic);

        if ($topicKey !== $statusKey) {
            $fromDb = $this->loadFromDatabase($ownerType, $topicKey);
            if ($fromDb !== null) {
                return $fromDb;
            }
            $defaults = $this->defaultFor($ownerType, $topicKey);
            if (!empty($defaults)) {
                return $defaults;
            }
        }

        $fromDb = $this->loadFromDatabase($ownerType, $statusKey);
        if ($fromDb !== null) {
            return $fromDb;
        }

        return $this->defaultFor($ownerType, $statusKey);
    }

    /**
     * 定型文編集画面などで「すべての状況の候補文」を並べて返す。
     *
     * @return array<int, array{status_code:int, status_key:string, status_label:string, items: array<int, array{category:string, body:string}>}>
     */
    public function allByStatus(bool $isCastPortal, ?string $talkTopic = null): array
    {
        $groups = [
            ['code' => self::STATUS_CHATTING,          'label' => 'やり取り中（初回・雑談）'],
            ['code' => self::STATUS_INTERVIEW_PENDING, 'label' => '面談日調整中'],
            ['code' => self::STATUS_INTERVIEW_FIXED,   'label' => '面談日確定済み'],
            ['code' => self::STATUS_HIRED,             'label' => '採用'],
            ['code' => self::STATUS_REJECTED,          'label' => '不採用・お断り'],
        ];

        return array_values(array_filter(array_map(function (array $g) use ($isCastPortal, $talkTopic) {
            $topicForStatus = $g['code'] === self::STATUS_CHATTING ? $talkTopic : null;
            $items = $this->forStatus($isCastPortal, $g['code'], $topicForStatus);
            if (empty($items)) {
                return null;
            }
            return [
                'status_code'  => $g['code'],
                'status_key'   => self::statusKey($g['code']),
                'status_label' => self::statusLabelWithTopic($g['label'], $topicForStatus),
                'items'        => $items,
            ];
        }, $groups)));
    }

    /**
     * 求人種別コンテキストに応じた統合キー。'chatting' 以外はそのまま返す。
     */
    private static function topicKey(string $ownerType, string $statusKey, ?string $talkTopic): string
    {
        if ($statusKey !== 'chatting') {
            return $statusKey;
        }
        return match ($talkTopic) {
            'new_hire' => 'chatting_new_hire',
            'help'     => 'chatting_help',
            default    => $statusKey,
        };
    }

    /**
     * トピック（応募種別）が確定しているときは「やり取り中」のラベルに補記を足す。
     */
    private static function statusLabelWithTopic(string $baseLabel, ?string $talkTopic): string
    {
        return match ($talkTopic) {
            'new_hire' => 'やり取り中（新規採用）',
            'help'     => 'やり取り中（ヘルプ）',
            default    => $baseLabel,
        };
    }

    /**
     * 内部ステータスコード → フロント側で照合する文字列キー。
     * TalkController::applicationStatusCode() と同じマッピングを提供する。
     */
    public static function statusKey(int $status): string
    {
        return match ($status) {
            self::STATUS_INTERVIEW_PENDING => 'interview_pending',
            self::STATUS_INTERVIEW_FIXED   => 'interview_fixed',
            self::STATUS_HIRED,
            self::STATUS_HIRED_FULLTIME    => 'hired',
            self::STATUS_REJECTED,
            self::STATUS_REJECTED_TRIAL    => 'rejected',
            default                        => 'chatting',
        };
    }

    /**
     * ハードコードの既定値を返す (管理画面のリセット用途など)。
     *
     * @return array<int, array{category:string, body:string}>
     */
    public function defaultFor(string $ownerType, string $statusKey): array
    {
        return self::DEFAULT_TEMPLATES[$ownerType][$statusKey] ?? [];
    }

    /**
     * @return array<int, array{category:string, body:string}>|null
     */
    private function loadFromDatabase(string $ownerType, string $statusKey): ?array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return null;
        }

        $rows = DB::table(self::TABLE)
            ->where('owner_type', $ownerType)
            ->where('status_code', $statusKey)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['category', 'body']);

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->map(fn ($row) => [
            'category' => (string) ($row->category ?? ''),
            'body'     => (string) ($row->body ?? ''),
        ])->all();
    }

    /**
     * ハードコード既定値。mock_demo.sql の初期シードと同一。
     * SPEC.md §2.3-2.4 のフロー (応募→面談調整→採用→勤務完了報告→ボーナス請求→振込) に沿って配置。
     *
     * @var array<string, array<string, array<int, array{category:string, body:string}>>>
     */
    private const DEFAULT_TEMPLATES = [
        'cast' => [
            'chatting' => [
                ['category' => 'intro',    'body' => 'はじめまして。求人を拝見してご連絡いたしました。ぜひ詳しくお伺いできますと幸いです。'],
                ['category' => 'intro',    'body' => 'プロフィールをご覧いただきありがとうございます。前向きに検討したく、ご連絡いたしました。'],
                ['category' => 'question', 'body' => 'お店の雰囲気やお客様層について教えてください。'],
                ['category' => 'question', 'body' => '時給・バック率・体入時の条件について詳しく知りたいです。'],
                ['category' => 'question', 'body' => '出勤可能なシフトや最低出勤本数はどれくらいでしょうか？'],
                ['category' => 'question', 'body' => '未経験ですが、安心して働ける環境でしょうか？'],
                ['category' => 'schedule', 'body' => 'ぜひ一度、体入または面談をお願いしたいです。ご都合はいかがでしょうか？'],
            ],
            // 新規採用（体入からのスタート）応募直後に見せる定型文
            'chatting_new_hire' => [
                ['category' => 'intro',    'body' => 'はじめまして。新規採用の求人を拝見してご連絡いたしました。まずは体入からご相談させてください。'],
                ['category' => 'intro',    'body' => 'プロフィールを拝見し、ぜひ体入からお願いしたくご連絡しました。前向きに検討しております。'],
                ['category' => 'question', 'body' => '体入時の時給・バック率・保証などの条件を詳しく教えてください。'],
                ['category' => 'question', 'body' => '体入で出勤可能な曜日・時間帯はどのあたりでしょうか？'],
                ['category' => 'question', 'body' => 'お店の雰囲気・お客様層・在籍キャストさんの傾向を教えてください。'],
                ['category' => 'question', 'body' => '未経験（もしくは経験少なめ）ですが、大丈夫でしょうか？'],
                ['category' => 'schedule', 'body' => 'ぜひ一度、体入または面談をお願いしたいです。ご都合の良い日時を教えていただけますか？'],
            ],
            // ヘルプ応募（単発ピンチヒッター）直後に見せる定型文
            'chatting_help' => [
                ['category' => 'help',     'body' => 'ヘルプの求人を拝見しました。稼働可能ですのでご検討いただけますか？'],
                ['category' => 'help',     'body' => '本日◯時〜◯時までヘルプ入れます。よろしければお願いいたします。'],
                ['category' => 'question', 'body' => 'ヘルプ時給・保証・ラウンドの本数目安を教えていただけますか？'],
                ['category' => 'question', 'body' => 'ヘルプ当日の集合時間・場所・持ち物を教えてください。'],
                ['category' => 'question', 'body' => 'ドレスコード（衣装／私服／ドレス貸出可否）はいかがでしょうか？'],
                ['category' => 'schedule', 'body' => '直近で入れる日時をお知らせします：◯月◯日 ◯時〜◯時。ご調整可能でしょうか？'],
                ['category' => 'status',   'body' => '本日ヘルプ、当日入りが可能です。まだ枠が空いていれば入らせてください。'],
            ],
            'interview_pending' => [
                ['category' => 'thanks',   'body' => '面談候補日をお送りいただきありがとうございます。確認してすぐご返信いたします。'],
                ['category' => 'schedule', 'body' => 'ご提示いただいた第一希望の日程で問題ございません。当日よろしくお願いいたします。'],
                ['category' => 'schedule', 'body' => '申し訳ございません、いただいた日程は都合が合わないため、別日をご提案いただけますでしょうか。'],
                ['category' => 'question', 'body' => '面談はどれくらいのお時間を予定していますか？'],
                ['category' => 'question', 'body' => '面談は対面／オンラインどちらをご希望でしょうか？'],
                ['category' => 'question', 'body' => '当日の持ち物や服装の指定があれば事前に教えてください。'],
            ],
            'interview_fixed' => [
                ['category' => 'thanks',   'body' => '面談当日、よろしくお願いいたします！'],
                ['category' => 'question', 'body' => '当日の持ち物・服装の指定があれば教えてください。'],
                ['category' => 'question', 'body' => '店舗までの詳しいアクセスを教えていただけますでしょうか。'],
                ['category' => 'question', 'body' => '当日はどなたをお訪ねすればよいですか？'],
                ['category' => 'status',   'body' => '大変申し訳ございません、少し遅れそうです。到着次第ご連絡いたします。'],
                ['category' => 'status',   'body' => '到着いたしました。入口はどちらでしょうか？'],
                ['category' => 'schedule', 'body' => '大変恐縮ですが、体調不良のため面談日程を再調整させていただけますでしょうか。'],
            ],
            'hired' => [
                ['category' => 'thanks',   'body' => 'この度は採用いただきありがとうございます！精一杯頑張ります。'],
                ['category' => 'schedule', 'body' => '初出勤日についてご相談させてください。'],
                ['category' => 'question', 'body' => '初出勤当日の集合時間・持ち物・服装を教えてください。'],
                ['category' => 'question', 'body' => '入店時の手続きで必要な書類はありますか？'],
                ['category' => 'status',   'body' => '本日勤務完了いたしました。ありがとうございました。'],
                ['category' => 'schedule', 'body' => 'ぜひ本入店で継続させていただきたいです。ご検討いただけますでしょうか。'],
                ['category' => 'schedule', 'body' => 'ボーナス条件を達成いたしましたので、ご確認とご承認をお願いいたします。'],
                ['category' => 'thanks',   'body' => 'ご入金の確認が取れました。この度はありがとうございました。'],
            ],
            'rejected' => [
                ['category' => 'thanks', 'body' => 'この度はご連絡いただきありがとうございました。'],
                ['category' => 'thanks', 'body' => 'またご縁がありましたら、ぜひよろしくお願いいたします。'],
            ],
        ],
        'shop' => [
            'chatting' => [
                ['category' => 'thanks',   'body' => 'この度はご応募（お問い合わせ）ありがとうございます。当店にご興味を持っていただき嬉しく思います。'],
                ['category' => 'intro',    'body' => 'ご返信ありがとうございます。ご不明な点があればお気軽にご質問くださいませ。'],
                ['category' => 'question', 'body' => '差し支えなければ、勤務開始のご希望時期や週の出勤可能日数を教えていただけますか？'],
                ['category' => 'question', 'body' => 'これまでのご経験や現在の在籍状況について教えていただけますでしょうか。'],
                ['category' => 'schedule', 'body' => 'ぜひ一度、面談または体入にお越しいただきたく思います。候補日をお送りしましょうか？'],
                ['category' => 'intro',    'body' => 'プロフィール拝見しました。ぜひ一度お話しできれば嬉しいです。'],
                ['category' => 'help',     'body' => '「今すぐ入れる」宣言を拝見しました。本日◯時から◯時まで、ヘルプでお願いできませんか？'],
                ['category' => 'help',     'body' => '急遽ピンチヒッターを探しております。ご対応可能でしたら折り返しお願いいたします！'],
            ],
            // 新規採用求人からの応募を受けた店舗側の最初の返信
            'chatting_new_hire' => [
                ['category' => 'thanks',   'body' => '新規採用のご応募ありがとうございます！当店にご興味を持っていただき嬉しく思います。'],
                ['category' => 'intro',    'body' => 'まずは体入からのスタートを想定しております。ご都合の良い候補日を伺えますでしょうか？'],
                ['category' => 'question', 'body' => 'これまでの在籍経験・お店のジャンル（キャバ／ラウンジ／クラブ 等）を教えていただけますか？'],
                ['category' => 'question', 'body' => '週の出勤可能日数・勤務開始のご希望時期を教えてください。'],
                ['category' => 'question', 'body' => '体入時の希望時給・お客様層のご要望など、ご希望があればお伺いします。'],
                ['category' => 'schedule', 'body' => 'では体入日を決めましょう。候補日を3つほどお送りしますね。'],
            ],
            // ヘルプ応募を受けた店舗側の最初の返信
            'chatting_help' => [
                ['category' => 'thanks',   'body' => 'ヘルプのお問い合わせありがとうございます！助かります。'],
                ['category' => 'question', 'body' => 'ご希望の入り時間帯・可能な時間数を教えていただけますか？'],
                ['category' => 'question', 'body' => 'ヘルプ経験や、経験のあるお店のジャンルを教えてください。'],
                ['category' => 'help',     'body' => '本日◯時〜◯時のヘルプを想定しております。可能でしたらこの時間でお願いできますでしょうか？'],
                ['category' => 'help',     'body' => 'ヘルプ時給と本数目安をお伝えします。ご確認のうえご返信お願いいたします。'],
                ['category' => 'schedule', 'body' => 'ではこの日時で確定でお願いします。集合場所と持ち物を追ってお送りします。'],
            ],
            'interview_pending' => [
                ['category' => 'schedule', 'body' => '面談の候補日をお送りしました。ご都合はいかがでしょうか？'],
                ['category' => 'schedule', 'body' => 'ご都合の良い日程があれば追加でお気軽にお知らせください。'],
                ['category' => 'schedule', 'body' => '日程が合わない場合は改めて候補日をお送りいたします。'],
                ['category' => 'question', 'body' => '面談は対面／オンラインどちらをご希望ですか？'],
                ['category' => 'question', 'body' => '当日の所要時間は30分〜1時間程度を予定しております。'],
                ['category' => 'question', 'body' => 'ご不明点があればお気軽にご質問ください。'],
            ],
            'interview_fixed' => [
                ['category' => 'status',   'body' => '面談当日、お待ちしております！'],
                ['category' => 'status',   'body' => 'お気をつけてお越しくださいませ。'],
                ['category' => 'status',   'body' => '当日は私服でお越しいただいて大丈夫です。'],
                ['category' => 'status',   'body' => '到着されましたらこのトークでお知らせください。'],
                ['category' => 'question', 'body' => '当日は身分証（顔写真付き）と印鑑をお持ちください。'],
                ['category' => 'question', 'body' => '店舗までのアクセス情報をお送りします。ご不明な点があればご連絡ください。'],
                ['category' => 'schedule', 'body' => '大変申し訳ございません、店舗都合により日程の再調整をお願いできますでしょうか。'],
            ],
            'hired' => [
                ['category' => 'thanks',   'body' => 'この度は採用となりました！おめでとうございます。これからよろしくお願いいたします。'],
                ['category' => 'schedule', 'body' => '初出勤日について改めてご案内いたします。ご都合の良い日程を教えてください。'],
                ['category' => 'question', 'body' => '当日の集合時間・持ち物・服装のご案内です。ご確認をお願いします。'],
                ['category' => 'status',   'body' => '本日はお疲れさまでした！ありがとうございました。'],
                ['category' => 'thanks',   'body' => 'ボーナス達成条件の確認が取れました。承認処理を進めさせていただきます。'],
                ['category' => 'status',   'body' => 'ご請求内容を確認しました。承認いたしましたので、運営からの請求書発行をお待ちください。'],
                ['category' => 'schedule', 'body' => 'ぜひ本入店で継続をご検討いただけますと嬉しいです。'],
                ['category' => 'status',   'body' => 'ご不明点があればいつでもご連絡ください。'],
            ],
            'rejected' => [
                ['category' => 'thanks', 'body' => 'この度はご応募いただきありがとうございました。'],
                ['category' => 'thanks', 'body' => 'またのご縁がありましたら、ぜひよろしくお願いいたします。'],
            ],
        ],
    ];
}

<?php

namespace App\Http\Controllers\Common;

use App\Http\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Services\MessageTemplateService;
use App\Services\ShopJobApplicationJobSnapshotService;
use App\Support\ShopJobApplicationView;
use App\Support\TalkQuickReplyCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TalkController extends Controller
{
    use ResolvesActor;

    private const MESSAGE_TYPE_TEXT = 1;
    private const MESSAGE_TYPE_INTERVIEW_OFFER = 2;
    private const MESSAGE_TYPE_INTERVIEW_CONFIRMED = 3;
    private const MESSAGE_TYPE_HIRED = 4;
    private const MESSAGE_TYPE_REJECTED = 5;
    private const MESSAGE_TYPE_IMAGE = 6;
    private const MESSAGE_TYPE_INTERVIEW_CANCEL_REQUEST = 7;
    private const APPLICATION_STATUS_CHATTING = 1;
    private const APPLICATION_STATUS_INTERVIEW_PENDING = 2;
    private const APPLICATION_STATUS_INTERVIEW_FIXED = 3;
    private const APPLICATION_STATUS_HIRED = 4;
    private const APPLICATION_STATUS_REJECTED = 5;
    private const APPLICATION_STATUS_HIRED_FULLTIME = 6;
    private const APPLICATION_STATUS_REJECTED_TRIAL = 7;

    public function __construct(
        private readonly MessageTemplateService $messageTemplateService,
        private readonly ShopJobApplicationJobSnapshotService $shopJobApplicationJobSnapshotService,
        private readonly \App\Services\NotificationService $notificationService,
        private readonly TalkQuickReplyCatalog $quickReplyCatalog,
        private readonly \App\Services\NgWordDetector $ngWordDetector,
    ) {
    }

    /**
     * メッセージ一覧
     */
    public function index()
    {
        $isCastPortal = request()->is('cast/*');

        if ($isCastPortal) {
            $profileRoute = 'cast.shopprofile.show';
        } else {
            $profileRoute = 'shop.castprofileview.show';
        }

        $conversations = $this->buildTalkList($isCastPortal);
        if ($isCastPortal) {
            $requestTalks = $conversations
                ->filter(fn ($talk) => ($talk['unread_count'] ?? 0) > 0 && ($talk['reply_count'] ?? 0) === 0)
                ->values()
                ->all();
            $ongoingTalks = $conversations
                ->reject(fn ($talk) => ($talk['unread_count'] ?? 0) > 0 && ($talk['reply_count'] ?? 0) === 0)
                ->values()
                ->all();
        } else {
            $pastStatusList = [
                self::APPLICATION_STATUS_REJECTED,
                self::APPLICATION_STATUS_HIRED_FULLTIME,
                self::APPLICATION_STATUS_REJECTED_TRIAL,
            ];
            $requestTalks = $conversations
                ->filter(fn ($talk) => in_array((int) ($talk['application_status'] ?? 0), $pastStatusList, true))
                ->values()
                ->all();
            $ongoingTalks = $conversations
                ->reject(fn ($talk) => in_array((int) ($talk['application_status'] ?? 0), $pastStatusList, true))
                ->values()
                ->all();
        }

        // 店舗側：本日のスカウト（新規トーク開始）残り送信可能数を上部に表示する
        $scoutQuota = null;
        if (!$isCastPortal) {
            try {
                $scoutQuota = app(\App\Services\PlanSubscriptionService::class)
                    ->checkScoutQuota((string) $this->currentShopId());
            } catch (\Throwable $e) {
                $scoutQuota = null;
            }
        }

        return view('common.talk.index', compact('ongoingTalks', 'requestTalks', 'profileRoute', 'scoutQuota'));
    }

    /**
     * トークルーム
     */
    public function room($id)
    {
        $isCastPortal = request()->is('cast/*');
        $currentId = $isCastPortal ? $this->currentCastId() : $this->currentShopId();
        $partnerId = (string) $id;
        $partner = $this->resolvePartner($partnerId, $isCastPortal);
        abort_unless($partner, 404);
        $castId = $isCastPortal ? $currentId : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $currentId;

        // IDOR 対策：ログインユーザと相手との関係性が無ければ参照不可。
        // 許可条件のいずれかを満たす必要がある：
        //   (a) 既にメッセージ履歴がある
        //   (b) 既に応募／面談（shop_job_applications）の関係がある
        //   (c) お気に入り／いいね等（favorites）がある
        //   (d) URL に ?initiate=1 を伴って遷移してきた（公開導線：求人/プロフィール画面の "メッセージを送る" ボタン）
        if (!$this->canAccessTalkRoom($castId, $shopId, request()->boolean('initiate'))) {
            abort(403, 'このトークルームを表示する権限がありません。');
        }
        $blockState = $this->getBlockState($castId, $shopId, $isCastPortal);
        $currentApplicationStatus = $this->getCurrentApplicationStatus($castId, $shopId);
        $selectedTalkJobKind = $this->getSelectedTalkJobKind($castId, $shopId);
        $applicationForReview = $isCastPortal && in_array($currentApplicationStatus, [
            self::APPLICATION_STATUS_HIRED,
            self::APPLICATION_STATUS_HIRED_FULLTIME,
        ], true)
            ? $this->findApplicationForTalk($castId, $shopId)
            : null;

        $rawMessages = DB::table('messages')
            ->where($isCastPortal ? 'cast_id' : 'shop_id', $currentId)
            ->where($isCastPortal ? 'shop_id' : 'cast_id', $partnerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $messages = $this->mapRoomMessages($rawMessages, $isCastPortal);
        $initialTalkTopic = $this->normalizeTalkTopic((string) request()->query('talk_topic', ''));
        $initialTalkJobKind = $this->normalizeTalkJobKind((string) request()->query('job_kind', ''));

        DB::table('messages')
            ->where($isCastPortal ? 'cast_id' : 'shop_id', $currentId)
            ->where($isCastPortal ? 'shop_id' : 'cast_id', $partnerId)
            ->where('sender_type', $isCastPortal ? 2 : 1)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'updated_at' => now(),
            ]);

        return view('common.talk.room', [
            'partnerName' => $partner['name'],
            'partnerAvatar' => $partner['avatar'],
            'messages' => $messages,
            'partnerId' => $partnerId,
            'actionUrl' => $isCastPortal ? route('cast.talk.action') : route('shop.talk.action'),
            'blockUrl' => $isCastPortal ? route('cast.talk.block') : route('shop.talk.block'),
            'blockState' => $blockState,
            'canSend' => !$blockState['is_blocked'],
            // 入力欄下のクイック定型文パネル：やりとりの進行状況に応じた候補を優先表示
            'quickReplySuggestions' => $this->buildQuickReplySuggestions($isCastPortal, $currentApplicationStatus),
            'allQuickReplySuggestions' => $this->buildAllQuickReplySuggestionsByStatus($isCastPortal),
            'currentStatusCode' => $this->applicationStatusCode($currentApplicationStatus),
            'currentStatusLabel' => $this->statusLabel(
                $this->applicationStatusCode($currentApplicationStatus),
                $selectedTalkJobKind,
                $currentApplicationStatus
            ),
            'canOfferInterview' => !$isCastPortal
                && !$blockState['is_blocked']
                && $currentApplicationStatus === self::APPLICATION_STATUS_CHATTING
                && $selectedTalkJobKind !== null,
            'canConfirmInterview' => $isCastPortal
                && !$blockState['is_blocked']
                && $currentApplicationStatus === self::APPLICATION_STATUS_INTERVIEW_PENDING,
            'canSelectResult' => !$isCastPortal
                && !$blockState['is_blocked']
                && $currentApplicationStatus === self::APPLICATION_STATUS_INTERVIEW_FIXED,
            'canCancelStatus' => !$isCastPortal
                && !$blockState['is_blocked']
                && in_array($currentApplicationStatus, [
                    self::APPLICATION_STATUS_INTERVIEW_PENDING,
                    self::APPLICATION_STATUS_INTERVIEW_FIXED,
                ], true),
            'resultMessageTemplates' => !$isCastPortal
                ? [
                    'hired' => $this->messageTemplateService->getTemplates('talk_hired'),
                    'rejected' => $this->messageTemplateService->getTemplates('talk_rejected'),
                ]
                : [],
            'reviewApplicationId' => $applicationForReview ? (int) $applicationForReview->id : null,
            'initialTalkTopic' => $initialTalkTopic,
            'initialTalkJobKind' => $initialTalkJobKind,
            'hasMessages' => $messages->isNotEmpty(),
            'selectedTalkJobKind' => $selectedTalkJobKind,
            'canSelectTalkJobKind' => !$blockState['is_blocked']
                && in_array($currentApplicationStatus, [
                    self::APPLICATION_STATUS_CHATTING,
                    self::APPLICATION_STATUS_INTERVIEW_PENDING,
                ], true),
            'canRequestFulltime' => $isCastPortal
                && !$blockState['is_blocked']
                && $currentApplicationStatus === self::APPLICATION_STATUS_HIRED
                && $selectedTalkJobKind === 'trial',
            'quickTemplates' => $this->messageTemplateService->getQuickTemplateSlots(
                $isCastPortal ? 'cast' : 'shop',
                $isCastPortal ? $this->currentCastId() : $this->currentShopId()
            ),
            'ngWordPayload' => $this->ngWordPayloadForView(),
        ]);
    }

    /**
     * メッセージ送信 (Shops/TalkController より統合)
     */
    public function store(Request $request)
    {
        $isCastPortal = request()->is('cast/*');
        $request->validate([
            'partner_id' => ['required', 'string'],
            'message' => ['required', 'string', 'max:5000'],
            'client_request_id' => ['nullable', 'uuid'],
            'talk_topic' => ['nullable', 'string', 'in:new_hire,help,other'],
            'talk_job_kind' => ['nullable', 'string', 'in:fulltime,trial,help'],
        ]);

        $partnerId = (string) $request->input('partner_id');
        abort_unless($this->resolvePartner($partnerId, $isCastPortal), 404);
        $this->abortIfBlocked($partnerId, $isCastPortal);

        // IDOR 対策：トークルーム表示と同じ条件を送信時にも適用。
        // 初回会話は initiate=1 を許可（送信後はメッセージ履歴が残るため以後のアクセスは自動的に通る）。
        $checkCastId = $isCastPortal ? (string) $this->currentCastId() : $partnerId;
        $checkShopId = $isCastPortal ? $partnerId : (string) $this->currentShopId();
        if (!$this->canAccessTalkRoom($checkCastId, $checkShopId, (bool) $request->boolean('initiate'))) {
            abort(403, 'このトークルームへの送信権限がありません。');
        }

        $content = trim((string) $request->input('message'));
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/\n{2,}/', "\n", $content);
        abort_if($content === '', 422, 'メッセージを入力してください。');

        // スカウト上限：店舗→やりとりの無いキャストへの新規送信は1日 無料5件 / Premium30件まで。
        // 既存キャストとのやりとり（履歴がある相手）は無制限。
        if (!$isCastPortal) {
            $planService = app(\App\Services\PlanSubscriptionService::class);
            if ($planService->isScout((string) $checkShopId, (string) $checkCastId)) {
                $quota = $planService->checkScoutQuota((string) $checkShopId);
                if (!$quota['allowed']) {
                    $msg = $quota['is_premium']
                        ? "本日のスカウト送信上限（{$quota['limit']}件）に達しました。明日以降に再度お試しください。"
                        : "本日のスカウト送信上限（{$quota['limit']}件）に達しました。Premiumプランなら1日" . \App\Services\PlanSubscriptionService::SCOUT_LIMIT_PREMIUM . '件まで送信できます。';
                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'scout_limit_reached' => true,
                        'upgrade_url' => route('subscription'),
                    ], 429);
                }
            }
        }

        // NGワード検査（電話・SNSハンドル・URL・連絡先誘導など）
        if ($ngHit = $this->detectNgWord($content)) {
            return response()->json([
                'success' => false,
                'message' => '使用できない表現が含まれています：「' . $ngHit . '」',
                'ng_word' => $ngHit,
            ], 422);
        }

        $messageType = self::MESSAGE_TYPE_TEXT;
        $storedContent = $content;

        $payload = [
            'cast_id' => $isCastPortal ? $this->currentCastId() : $partnerId,
            'shop_id' => $isCastPortal ? $partnerId : $this->currentShopId(),
            'sender_type' => $this->mySenderType($isCastPortal),
            'type' => $messageType,
            'content' => $storedContent,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $delivery = app(\App\Services\TalkDeliveryService::class)->once(
            $payload['cast_id'] . ':' . $payload['shop_id'] . ':' . $payload['sender_type'],
            (string) ($request->input('client_request_id') ?: \Illuminate\Support\Str::uuid()),
            $content,
            function () use ($isCastPortal, $partnerId, $request, $payload, $messageType, $content) {
                if ($isCastPortal) {
                    $this->ensureApplicationForTalkStart(
                        (string) $this->currentCastId(),
                        (string) $partnerId,
                        $this->normalizeTalkTopic((string) $request->input('talk_topic', '')),
                        $this->normalizeTalkJobKind((string) $request->input('talk_job_kind', ''))
                    );
                }
                $message = \App\Models\Message::create($payload);

                return [
                    'message_id' => $message->id,
                    'message_type' => $messageType,
                    'content' => $content,
                    'time' => Carbon::now()->format('H:i'),
                ];
            }
        );
        if ($delivery['created']) {
            try {
                $this->notifyConversationPartner(
                    castId: (string) $payload['cast_id'],
                    shopId: (string) $payload['shop_id'],
                    isCastPortal: $isCastPortal,
                    title: '新着メッセージ',
                    body: $isCastPortal ? 'キャストからメッセージが届きました。' : '店舗からメッセージが届きました。内容を確認してください。',
                    url: $isCastPortal ? url('/shop/talk/room/' . $payload['cast_id']) : url('/cast/talk/room/' . $payload['shop_id'])
                );
            } catch (\Throwable $exception) {
                // メッセージの保存成功を通知障害で失敗扱いにしない。
                report($exception);
            }
        }

        return response()->json([
            'success' => true,
            'message' => '送信しました',
            'data' => $delivery['data'],
        ]);
    }

    /**
     * メッセージ削除（送信から10分以内の自分のテキストメッセージのみ）
     */
    public function destroy(Request $request)
    {
        $isCastPortal = request()->is('cast/*');
        $request->validate([
            'partner_id' => ['required', 'string'],
            'message_id' => ['required', 'integer', 'min:1'],
        ]);

        $partnerId = (string) $request->input('partner_id');
        $messageId = (int) $request->input('message_id');
        abort_unless($this->resolvePartner($partnerId, $isCastPortal), 404);

        $currentId = $isCastPortal ? $this->currentCastId() : $this->currentShopId();
        $mySenderType = $this->mySenderType($isCastPortal);
        $castId = $isCastPortal ? $currentId : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $currentId;

        $message = DB::table('messages')
            ->where('id', $messageId)
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->first();

        abort_unless($message, 404);
        abort_if((int) $message->sender_type !== $mySenderType, 403, '自分のメッセージのみ削除できます。');
        abort_if((int) $message->type !== self::MESSAGE_TYPE_TEXT, 403, 'テキストメッセージのみ削除できます。');

        $createdAt = Carbon::parse($message->created_at);
        $limit = Carbon::now()->subMinutes(10);
        abort_if($createdAt->lt($limit), 422, '送信から10分を過ぎたメッセージは削除できません。');

        DB::table('messages')->where('id', $messageId)->delete();

        return response()->json(['success' => true, 'message' => '削除しました']);
    }

    public function action(Request $request)
    {
        $isCastPortal = request()->is('cast/*');
        $request->validate([
            'partner_id' => ['required', 'string'],
            'action_type' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Support\TalkActionRegistry::allTypes())],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string'],
            'offer_token' => ['nullable', 'string'],
            'selected_option' => ['nullable', 'string'],
            'message' => ['nullable', 'string', 'max:5000'],
            'hired_regular_hourly_wage' => ['nullable', 'string', 'max:32'],
            'employment_kind' => ['nullable', 'string', 'in:fulltime,trial,help'],
            'job_kind' => ['nullable', 'string', 'in:fulltime,trial,help'],
        ]);

        $partnerId = (string) $request->input('partner_id');
        abort_unless($this->resolvePartner($partnerId, $isCastPortal), 404);
        $this->abortIfBlocked($partnerId, $isCastPortal);

        $actionType = $request->input('action_type');
        // Delegate side-authorization to TalkActionRegistry (single source of truth).
        abort_unless(\App\Support\TalkActionRegistry::isAllowed($actionType, $isCastPortal), 403);
        $castId = $isCastPortal ? $this->currentCastId() : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $this->currentShopId();
        $currentApplicationStatus = $this->getCurrentApplicationStatus($castId, $shopId);
        if ($actionType === 'fulltime_request') {
            abort_if(!$isCastPortal, 403);
            abort_if(
                !($currentApplicationStatus === self::APPLICATION_STATUS_HIRED && $this->getSelectedTalkJobKind($castId, $shopId) === 'trial'),
                422,
                '本入店リクエストは体験採用後のみ送信できます。'
            );
        }
        $bonusMeta = in_array($actionType, ['interview_offer', 'interview_confirm'], true)
            ? $this->buildJobBonusMetaForConversation($castId, $shopId)
            : null;

        if ($actionType === 'interview_offer') {
            abort_if(
                $this->getSelectedTalkJobKind($castId, $shopId) === null,
                422,
                '面談候補日を送る前に求人種別（体験入店／本入店／ヘルプ）を選択してください。'
            );
            abort_if(
                !in_array($currentApplicationStatus, [
                    self::APPLICATION_STATUS_CHATTING,
                    self::APPLICATION_STATUS_INTERVIEW_PENDING,
                ], true),
                422,
                '面談候補日は「やり取り中」または「面談日調整中」のときのみ送信できます。'
            );
        }

        if ($actionType === 'interview_cancel_request') {
            abort_if(
                $currentApplicationStatus !== self::APPLICATION_STATUS_INTERVIEW_FIXED,
                422,
                '面談日確定時のみキャンセル依頼を送信できます。'
            );
        }

        if ($actionType === 'interview_cancel_accept') {
            abort_if(
                $currentApplicationStatus !== self::APPLICATION_STATUS_INTERVIEW_FIXED,
                422,
                '面談日確定時のみ承諾できます。'
            );
        }

        if ($actionType === 'set_job_kind') {
            abort_if(
                !in_array($currentApplicationStatus, [
                    self::APPLICATION_STATUS_CHATTING,
                    self::APPLICATION_STATUS_INTERVIEW_PENDING,
                ], true),
                422,
                '面談日確定後は求人種別を変更できません。'
            );
            $jobKind = $this->normalizeTalkJobKind((string) $request->input('job_kind', ''));
            if ($jobKind === null) {
                abort(422, '求人種別を選択してください。');
            }
            $this->setConversationJobKind($castId, $shopId, $jobKind);

            return response()->json(['success' => true]);
        }

        if ($actionType === 'interview_confirm') {
            abort_if(
                $currentApplicationStatus !== self::APPLICATION_STATUS_INTERVIEW_PENDING,
                422,
                '面談日は「面談日調整中」の候補に対してのみ確定できます。'
            );
        }

        if ($actionType === 'work_complete_report') {
            abort_if(!$isCastPortal, 403);
            $talkKind = $this->getSelectedTalkJobKind($castId, $shopId);
            abort_if(!in_array($talkKind, ['trial', 'help'], true), 422, '本入店では勤務完了報告は利用できません。');
        }

        if ($actionType === 'bonus_achievement_report') {
            abort_if(!$isCastPortal, 403);
            $talkKind = $this->getSelectedTalkJobKind($castId, $shopId);
            abort_if($talkKind !== 'fulltime', 422, 'ボーナス達成報告は本入店でのみ利用できます。');
        }

        if (in_array($actionType, ['hired', 'rejected'], true)) {
            abort_if(
                $currentApplicationStatus !== self::APPLICATION_STATUS_INTERVIEW_FIXED,
                422,
                '採用／不採用は面談日決定後にのみ選択できます。'
            );
        }

        if ($actionType === 'cancel_status') {
            abort_if(
                !in_array($currentApplicationStatus, [
                    self::APPLICATION_STATUS_INTERVIEW_PENDING,
                    self::APPLICATION_STATUS_INTERVIEW_FIXED,
                ], true),
                422,
                'キャンセルできるのは面談日調整中または面談日決定のステータスのみです。'
            );
        }

        $hiredWageNormalized = null;
        $selectedEmploymentKind = null;
        $internalRejectionReason = null;
        if (in_array($actionType, ['hired', 'rejected'], true)) {
            $selectedEmploymentKind = $this->normalizeEmploymentKind((string) $request->input('employment_kind', ''));
            if ($selectedEmploymentKind === null) {
                abort(422, '採用区分を選択してください。');
            }
            if ($actionType === 'hired') {
                $hiredWageNormalized = ShopJobApplicationView::normalizeWageDigits(
                    $request->input('hired_regular_hourly_wage') !== null
                        ? (string) $request->input('hired_regular_hourly_wage')
                        : null
                );
                if ($hiredWageNormalized === null) {
                    abort(422, '採用時給（確定）を入力してください。');
                }
            } else {
                $internalRejectionReason = trim((string) $request->input('message', ''));
            }
        }

        [$messageType, $content] = match ($actionType) {
            'interview_offer' => [
                self::MESSAGE_TYPE_INTERVIEW_OFFER,
                json_encode([
                    'offer_token' => (string) Str::uuid(),
                    'options' => collect($request->input('options', []))
                        ->map(fn ($option) => trim((string) $option))
                        ->filter()
                        ->values()
                        ->all(),
                    'bonus_meta' => $bonusMeta,
                ], JSON_UNESCAPED_UNICODE),
            ],
            'interview_confirm' => [
                self::MESSAGE_TYPE_INTERVIEW_CONFIRMED,
                json_encode([
                    'offer_token' => (string) $request->input('offer_token'),
                    'selected_option' => trim((string) $request->input('selected_option')),
                    'bonus_meta' => $bonusMeta,
                ], JSON_UNESCAPED_UNICODE),
            ],
            'interview_cancel_request' => [
                self::MESSAGE_TYPE_INTERVIEW_CANCEL_REQUEST,
                json_encode([
                    'requested_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE),
            ],
            'interview_cancel_accept' => [
                self::MESSAGE_TYPE_TEXT,
                '【自動送信】面談キャンセルを承諾しました。やり取り中に戻します。',
            ],
            'hired' => [
                self::MESSAGE_TYPE_HIRED,
                $this->buildHiredMessageForCast(
                    $this->ensureAutoPrefix(
                        (string) $this->resolveResultMessage((string) $actionType, (string) $request->input('message'))
                    ),
                    (string) $hiredWageNormalized,
                    (string) $selectedEmploymentKind
                ),
            ],
            'rejected' => [
                self::MESSAGE_TYPE_REJECTED,
                $this->ensureAutoPrefix(
                    $this->resolveResultMessage((string) $actionType, '')
                ),
            ],
            'cancel_status' => [
                self::MESSAGE_TYPE_TEXT,
                '【自動送信】面談ステータスをキャンセルし、やり取り中に戻しました。必要に応じて面談候補日を再設定してください。',
            ],
            'fulltime_request' => [
                self::MESSAGE_TYPE_TEXT,
                '【自動送信】本入店を希望します。ご確認をお願いします。',
            ],
            'work_complete_report' => [
                self::MESSAGE_TYPE_TEXT,
                '【自動送信】勤務完了報告を送信しました。ご確認をお願いします。',
            ],
            'bonus_achievement_report' => [
                self::MESSAGE_TYPE_TEXT,
                '【自動送信】ボーナス達成報告を送信しました。内容確認後に承認をお願いします。',
            ],
        };

        if ($actionType === 'interview_offer') {
            abort_if(empty(json_decode($content, true)['options'] ?? []), 422);
            $parsedOptions = collect(json_decode($content, true)['options'] ?? [])
                ->map(function ($option) {
                    try {
                        return Carbon::parse((string) $option);
                    } catch (\Throwable $e) {
                        return null;
                    }
                })
                ->filter();
            abort_if($parsedOptions->isEmpty(), 422, '面談候補日を1件以上入力してください。');
            $now = Carbon::now();
            $max = $now->copy()->addMonthsNoOverflow(2);
            $hasOutOfRange = $parsedOptions->contains(function (Carbon $dt) use ($now, $max) {
                return $dt->lt($now) || $dt->gt($max);
            });
            abort_if($hasOutOfRange, 422, '面談候補日は現在日時〜2か月後まで指定できます。');
            if ($currentApplicationStatus === self::APPLICATION_STATUS_INTERVIEW_PENDING) {
                $this->invalidateInterviewOffers($castId, $shopId);
            }
        }

        if ($actionType === 'interview_confirm') {
            $payload = json_decode($content, true) ?: [];
            abort_if(empty($payload['offer_token']) || empty($payload['selected_option']), 422);
        }

        DB::table('messages')->insert([
            'cast_id' => $isCastPortal ? $this->currentCastId() : $partnerId,
            'shop_id' => $isCastPortal ? $partnerId : $this->currentShopId(),
            'sender_type' => $this->mySenderType($isCastPortal),
            'type' => $messageType,
            'content' => $content,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncApplicationStatusFromTalkAction(
            $partnerId,
            $isCastPortal,
            $actionType,
            $content,
            $hiredWageNormalized,
            $selectedEmploymentKind,
            $internalRejectionReason
        );
        if ($selectedEmploymentKind !== null) {
            $this->syncApplicationEmploymentKindFromTalkAction($partnerId, $isCastPortal, $selectedEmploymentKind, $actionType);
        }
        $this->notifyTalkAction(
            castId: (string) ($isCastPortal ? $this->currentCastId() : $partnerId),
            shopId: (string) ($isCastPortal ? $partnerId : $this->currentShopId()),
            isCastPortal: $isCastPortal,
            actionType: (string) $actionType,
            content: (string) $content
        );
        if ($actionType === 'work_complete_report') {
            $this->notifyOperationTransferInstruction($castId, $shopId, 'work_complete');
        }
        if ($actionType === 'bonus_achievement_report') {
            $this->notifyOperationTransferInstruction($castId, $shopId, 'bonus_achievement');
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function toggleBlock(Request $request)
    {
        $isCastPortal = request()->is('cast/*');
        $request->validate([
            'partner_id' => ['required', 'string'],
        ]);

        $partnerId = (string) $request->input('partner_id');
        abort_unless($this->resolvePartner($partnerId, $isCastPortal), 404);

        $castId = $isCastPortal ? $this->currentCastId() : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $this->currentShopId();
        $actor = $isCastPortal ? 'cast' : 'shop';
        $block = DB::table('talk_blocks')
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->first();

        if ($block) {
            abort_if($block->blocked_by !== $actor, 403);

            DB::table('talk_blocks')
                ->where('id', $block->id)
                ->delete();

            return redirect()
                ->back()
                ->with('message', 'ブロックを解除しました。');
        }

        DB::table('talk_blocks')->insert([
            'cast_id' => $castId,
            'shop_id' => $shopId,
            'blocked_by' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('message', 'この相手をブロックしました。');
    }

    private function buildTalkList(bool $isCastPortal)
    {
        $currentId = $isCastPortal ? $this->currentCastId() : $this->currentShopId();
        $mySenderType = $isCastPortal ? 1 : 2;
        $partnerColumn = $isCastPortal ? 'shop_id' : 'cast_id';

        $rows = DB::table('messages')
            ->where($isCastPortal ? 'cast_id' : 'shop_id', $currentId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $rows
            ->groupBy($partnerColumn)
            ->map(function ($messages, $partnerId) use ($isCastPortal, $mySenderType, $currentId) {
                $latest = $messages->first();
                $partner = $this->resolvePartner((string) $partnerId, $isCastPortal);
                if (!$partner) {
                    return null;
                }

                $latestAt = Carbon::parse($latest->created_at);
                $statusCode = $this->resolveConversationStatusCode($messages);
                $applicationStatus = $this->getCurrentApplicationStatus(
                    $isCastPortal ? $currentId : (string) $partnerId,
                    $isCastPortal ? (string) $partnerId : $currentId
                );
                $statusCode = $this->applicationStatusCode($applicationStatus);
                $blockState = $this->getBlockState(
                    $isCastPortal ? $currentId : (string) $partnerId,
                    $isCastPortal ? (string) $partnerId : $currentId,
                    $isCastPortal
                );
                // 採用種別（体験／本入店／ヘルプ）— ステータスラベルに反映
                $jobKindForLabel = $this->getSelectedTalkJobKind(
                    $isCastPortal ? $currentId : (string) $partnerId,
                    $isCastPortal ? (string) $partnerId : $currentId
                );

                return [
                    'partner_id' => (string) $partnerId,
                    'profile_id' => $isCastPortal ? $this->toNumericShopId((string) $partnerId) : (string) $partnerId,
                    'name' => $partner['name'],
                    'age' => $partner['age'],
                    'location' => $partner['location'],
                    'avatar' => $partner['avatar'],
                    'last_message' => $this->formatTalkPreview($latest, $mySenderType),
                    'last_time' => $this->formatTalkTime($latestAt),
                    'sort_key' => $latestAt,
                    'unread_count' => $messages
                        ->where('sender_type', '!=', $mySenderType)
                        ->where('is_read', false)
                        ->count(),
                    'fulltime_request_unread_count' => (!$isCastPortal)
                        ? $messages
                            ->where('sender_type', '!=', $mySenderType)
                            ->where('is_read', false)
                            ->where('type', self::MESSAGE_TYPE_TEXT)
                            ->where('content', '本入店を希望します。ご確認をお願いします。')
                            ->count()
                        : 0,
                    'reply_count' => $messages
                        ->where('sender_type', $mySenderType)
                        ->count(),
                    'last_message_by_me' => (int) $latest->sender_type === $mySenderType,
                    'is_read' => (bool) $latest->is_read,
                    'status_code' => $statusCode,
                    'application_status' => $applicationStatus,
                    'status_label' => $blockState['is_blocked']
                        ? ($blockState['blocked_by_me'] ? 'ブロック中' : '相手がブロック中')
                        : $this->statusLabel($statusCode, $jobKindForLabel, $applicationStatus),
                    'talk_job_kind' => $jobKindForLabel,
                    'has_fulltime_request_badge' => (!$isCastPortal)
                        && $messages
                            ->where('sender_type', '!=', $mySenderType)
                            ->where('is_read', false)
                            ->where('type', self::MESSAGE_TYPE_TEXT)
                            ->where('content', '本入店を希望します。ご確認をお願いします。')
                            ->count() > 0,
                    'pinned' => false,
                ];
            })
            ->filter()
            ->sortByDesc('sort_key')
            ->values();
    }

    private function mapRoomMessages($messages, bool $isCastPortal)
    {
        $confirmedByToken = $messages
            ->filter(fn ($message) => (int) $message->type === self::MESSAGE_TYPE_INTERVIEW_CONFIRMED)
            ->mapWithKeys(function ($message) {
                $meta = $this->decodeMessageMeta($message);
                return empty($meta['offer_token']) ? [] : [$meta['offer_token'] => ($meta['selected_option'] ?? null)];
            });

        $now = Carbon::now();
        $deleteLimit = $now->copy()->subMinutes(10);

        return $messages->map(function ($message) use ($isCastPortal, $confirmedByToken, $deleteLimit) {
            $meta = $this->decodeMessageMeta($message);
            $type = (int) $message->type;
            $offerToken = $meta['offer_token'] ?? null;
            $isMine = (int) $message->sender_type === $this->mySenderType($isCastPortal);
            $createdAt = Carbon::parse($message->created_at);
            $canDelete = $isMine && $type === self::MESSAGE_TYPE_TEXT && $createdAt->gte($deleteLimit);

            return (object) [
                'id' => (int) $message->id,
                'type' => $type,
                'content' => $type === self::MESSAGE_TYPE_TEXT || $type === self::MESSAGE_TYPE_HIRED || $type === self::MESSAGE_TYPE_REJECTED
                    ? $message->content
                    : ($type === self::MESSAGE_TYPE_IMAGE ? (string) ($meta['caption'] ?? '') : ($meta['selected_option'] ?? '')),
                'is_mine' => $isMine,
                'created_at' => $createdAt,
                'can_delete' => $canDelete,
                'interview_options' => $type === self::MESSAGE_TYPE_INTERVIEW_OFFER ? ($meta['options'] ?? []) : [],
                'offer_token' => $offerToken,
                'selected_option' => $offerToken ? ($confirmedByToken[$offerToken] ?? null) : ($meta['selected_option'] ?? null),
                'image_url' => $type === self::MESSAGE_TYPE_IMAGE ? $this->assetPathForStored($meta['image_path'] ?? null) : null,
                'is_invalidated' => $type === self::MESSAGE_TYPE_INTERVIEW_OFFER ? !empty($meta['invalidated']) : false,
            ];
        });
    }

    private function conversationMessages(string $partnerId, bool $isCastPortal)
    {
        return DB::table('messages')
            ->where($isCastPortal ? 'cast_id' : 'shop_id', $isCastPortal ? $this->currentCastId() : $this->currentShopId())
            ->where($isCastPortal ? 'shop_id' : 'cast_id', $partnerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * トークルームに対する参照権限（IDOR 対策）。
     *
     * @param  string $castId       cast_id（or partner_id）
     * @param  string $shopId       shop_id（or partner_id）
     * @param  bool   $hasInitiate  初回会話を許可する明示的な遷移か（?initiate=1）
     */
    private function canAccessTalkRoom(string $castId, string $shopId, bool $hasInitiate): bool
    {
        // (a) メッセージ履歴
        $hasMessages = DB::table('messages')
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->exists();
        if ($hasMessages) {
            return true;
        }

        // (b) 応募／面談関係
        if (Schema::hasTable('shop_job_applications') && Schema::hasTable('shop_jobs')) {
            $hasApplication = DB::table('shop_job_applications')
                ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
                ->where('shop_job_applications.cast_id', $castId)
                ->where('shop_jobs.shop_id', $shopId)
                ->exists();
            if ($hasApplication) {
                return true;
            }
        }

        // (c) お気に入り（いずれかの方向の保存／いいね）
        if (Schema::hasTable('favorites')) {
            $hasFavorite = DB::table('favorites')
                ->where('cast_id', $castId)
                ->where('shop_id', $shopId)
                ->exists();
            if ($hasFavorite) {
                return true;
            }
        }

        // (d) 公開導線からの初回会話（求人画面の「メッセージを送る」ボタンなど）
        // 遷移後にメッセージが送信されれば (a) が成立するため、以後のアクセスは許可される。
        return $hasInitiate;
    }

    private function resolvePartner(string $partnerId, bool $isCastPortal): ?array
    {
        if ($isCastPortal) {
            $row = DB::table('shops')
                ->leftJoin('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')
                ->where('shops.id', $partnerId)
                ->select(
                    'shops.id',
                    'shop_profiles.shop_name',
                    'shop_profiles.pref',
                    'shop_profiles.city',
                    DB::raw("(SELECT si.image_path FROM shop_images si WHERE si.shop_id = shops.id ORDER BY si.is_main DESC, si.main_order IS NULL, si.main_order, si.id LIMIT 1) as main_image_path")
                )
                ->first();

            if (!$row) {
                return null;
            }

            return [
                'name' => $row->shop_name ?: 'お店',
                'age' => null,
                'location' => trim(implode('', array_filter([$row->pref ?? null, $row->city ?? null]))),
                'avatar' => $this->resolveShopAvatar((string) $row->id, $row->main_image_path ?? null),
            ];
        }

        $row = DB::table('casts')
            ->leftJoin('cast_profiles', 'casts.id', '=', 'cast_profiles.cast_id')
            ->where('casts.id', $partnerId)
            ->select(
                'casts.id',
                'cast_profiles.nickname',
                'cast_profiles.name',
                'cast_profiles.pref',
                'cast_profiles.city',
                'cast_profiles.birthday'
            )
            ->first();

        if (!$row) {
            return null;
        }

        $mainImagePath = DB::table('cast_images')
            ->where('cast_id', $partnerId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->value('image_path');

        return [
            'name' => $row->nickname ?: ($row->name ?: 'キャスト'),
            'age' => !empty($row->birthday) ? Carbon::parse($row->birthday)->age : null,
            'location' => trim(implode('', array_filter([$row->pref ?? null, $row->city ?? null]))),
            'avatar' => $this->assetPathForStored($mainImagePath),
        ];
    }

    private function currentCastId(): string
    {
        return (string) auth()->guard('member')->id();
    }

    private function currentShopId(): string
    {
        return (string) auth()->guard('shop')->user()->shop_id;
    }

    private function mySenderType(bool $isCastPortal): int
    {
        return $isCastPortal ? 1 : 2;
    }

    private function decodeMessageMeta($message): array
    {
        $decoded = json_decode((string) $message->content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function formatTalkPreview($message, int $mySenderType): string
    {
        $type = (int) $message->type;
        $isMine = (int) $message->sender_type === $mySenderType;
        $meta = $this->decodeMessageMeta($message);

        return match ($type) {
            self::MESSAGE_TYPE_INTERVIEW_OFFER => $isMine
                ? '面談候補日を送りました'
                : '面談候補日が届いています',
            self::MESSAGE_TYPE_INTERVIEW_CONFIRMED => empty($meta['selected_option'])
                ? '面談日が確定しました'
                : '面談日が確定しました: ' . Carbon::parse($meta['selected_option'])->format('Y年n月j日 H:i'),
            self::MESSAGE_TYPE_HIRED => $isMine
                ? '採用メッセージを送りました'
                : '採用メッセージが届いています',
            self::MESSAGE_TYPE_REJECTED => $isMine
                ? '不採用メッセージを送りました'
                : '不採用メッセージが届いています',
            self::MESSAGE_TYPE_IMAGE => $isMine ? '画像を送りました' : '画像が届いています',
            self::MESSAGE_TYPE_INTERVIEW_CANCEL_REQUEST => $isMine
                ? '面談キャンセル依頼を送りました'
                : '面談キャンセル依頼が届いています',
            default => Str::limit(preg_replace('/\s+/u', ' ', trim((string) $message->content)), 60, '...'),
        };
    }

    private function resolveConversationStatusCode($messages): string
    {
        $latestAction = $this->latestConversationAction($messages);

        if (!$latestAction) {
            return 'chatting';
        }

        return match ((int) $latestAction->type) {
            self::MESSAGE_TYPE_INTERVIEW_OFFER => 'interview_pending',
            self::MESSAGE_TYPE_INTERVIEW_CONFIRMED => 'interview_fixed',
            self::MESSAGE_TYPE_HIRED => 'hired',
            self::MESSAGE_TYPE_REJECTED => 'rejected',
            default => 'chatting',
        };
    }

    private function latestConversationAction($messages)
    {
        return $messages
            ->filter(fn ($message) => in_array((int) $message->type, [
                self::MESSAGE_TYPE_INTERVIEW_OFFER,
                self::MESSAGE_TYPE_INTERVIEW_CONFIRMED,
                self::MESSAGE_TYPE_HIRED,
                self::MESSAGE_TYPE_REJECTED,
            ], true))
            ->sortByDesc(fn ($message) => sprintf(
                '%s-%010d',
                Carbon::parse($message->created_at)->format('YmdHis'),
                (int) ($message->id ?? 0)
            ))
            ->first();
    }

    /**
     * トークステータスのラベル。採用／不採用は求人種別と合わせて
     * 「体験採用 / 本採用 / ヘルプ採用 / 体験不採用 / 本入店不採用 / ヘルプ不採用」を返す。
     */
    private function statusLabel(string $code, ?string $jobKind = null, int $applicationStatus = 0): string
    {
        if ($code === 'hired') {
            // HIRED_FULLTIME は本採用確定（体験から本採用に変わった案件）
            if ($applicationStatus === self::APPLICATION_STATUS_HIRED_FULLTIME) {
                return '本採用';
            }
            return match ($jobKind) {
                'fulltime' => '本採用',
                'trial' => '体験採用',
                'help' => 'ヘルプ採用',
                default => '採用',
            };
        }
        if ($code === 'rejected') {
            // REJECTED_TRIAL = 体験後に本入店を見送り
            if ($applicationStatus === self::APPLICATION_STATUS_REJECTED_TRIAL) {
                return '本入店不採用';
            }
            return match ($jobKind) {
                'fulltime' => '本入店不採用',
                'trial' => '体験不採用',
                'help' => 'ヘルプ不採用',
                default => '不採用',
            };
        }
        return match ($code) {
            'interview_pending' => '面談調整中',
            'interview_fixed' => '面談日決定',
            default => 'やり取り中',
        };
    }

    private function getBlockState(string $castId, string $shopId, bool $isCastPortal): array
    {
        $block = DB::table('talk_blocks')
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->first();
        $actor = $isCastPortal ? 'cast' : 'shop';

        return [
            'is_blocked' => (bool) $block,
            'blocked_by' => $block->blocked_by ?? null,
            'blocked_by_me' => $block ? $block->blocked_by === $actor : false,
            'blocked_by_other' => $block ? $block->blocked_by !== $actor : false,
        ];
    }

    private function abortIfBlocked(string $partnerId, bool $isCastPortal): void
    {
        $castId = $isCastPortal ? $this->currentCastId() : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $this->currentShopId();
        $blockState = $this->getBlockState($castId, $shopId, $isCastPortal);

        abort_if($blockState['is_blocked'], 403, 'このトークはブロックされています。');
    }

    private function formatTalkTime(Carbon $dateTime): string
    {
        if ($dateTime->isToday()) {
            return $dateTime->format('H:i');
        }

        if ($dateTime->isYesterday()) {
            return '昨日';
        }

        return $dateTime->format('Y/m/d');
    }

    // assetPathForStored() is now provided by the ResolvesActor trait.

    private function resolveShopAvatar(string $shopId, ?string $fallbackPath = null): string
    {
        $imagePath = DB::table('shop_images')
            ->where('shop_id', $shopId)
            ->orderByRaw('is_main DESC')
            ->orderByRaw('main_order IS NULL')
            ->orderBy('main_order')
            ->orderBy('id')
            ->value('image_path');

        return $this->assetPathForStored($imagePath ?: $fallbackPath);
    }

    private function toNumericShopId(string $shopId): int
    {
        return (int) ltrim(str_starts_with($shopId, 's') ? substr($shopId, 1) : $shopId, '0');
    }

    /**
     * クイック定型文パネル用：やりとりの進行状況（application status）に応じた
     * 定型文候補を返す。先頭ほど優先度が高い。
     *
     * @return array<int, string>
     */
    /**
     * 定型文編集画面で表示する「ステータスごとの全定型文」。
     * 現在ステータスに関係なく、すべての状況の候補文をセクションで返す。
     *
     * @return array<int, array{status_code:int, status_label:string, items: array<int,string>}>
     */
    /**
     * 全ステータスのクイック返信候補（定型文編集画面等で全メニュー表示するとき用）。
     * 実データは TalkQuickReplyCatalog に委譲。ここは薄い adapter。
     */
    private function buildAllQuickReplySuggestionsByStatus(bool $isCastPortal): array
    {
        return $this->quickReplyCatalog->allByStatus($isCastPortal);
    }

    /**
     * 現在ステータスに応じたクイック返信候補。
     * 実データは TalkQuickReplyCatalog に委譲。ここは薄い adapter。
     */
    private function buildQuickReplySuggestions(bool $isCastPortal, int $status): array
    {
        return $this->quickReplyCatalog->forStatus($isCastPortal, $status);
    }

    private function getCurrentApplicationStatus(string $castId, string $shopId): int
    {
        $application = $this->findApplicationForTalk($castId, $shopId);

        return (int) ($application->status ?? self::APPLICATION_STATUS_CHATTING);
    }

    /**
     * NGワード検出。最初にヒットした語を返す。なければ null。
     * 連絡先誘導（電話・メール・URL・SNSハンドル）と、ng_words テーブルの双方を検査。
     */
    // Delegate to the injected service (extracted 2026-08-02).
    // Kept as a thin wrapper so existing callers ($this->detectNgWord(...)) don't need to change.
    private function detectNgWord(string $text): ?string
    {
        return $this->ngWordDetector->detect($text);
    }

    /**
     * 画面側 NG ワードチェック用の素材（正規表現リスト＋語句）。
     */
    private function ngWordPayloadForView(): array
    {
        $words = [];
        try {
            $words = DB::table('ng_words')
                ->where('is_active', 1)
                ->pluck('word')
                ->filter()
                ->map(fn ($w) => (string) $w)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $words = [];
        }
        return [
            'patterns' => [
                ['re' => '\\d{2,4}-\\d{2,4}-\\d{4}', 'flags' => '', 'label' => '電話番号'],
                ['re' => '(080|090|070|050)\\d{8}', 'flags' => '', 'label' => '携帯番号'],
                ['re' => '[\\w.+-]+@[\\w-]+\\.[\\w.-]+', 'flags' => '', 'label' => 'メールアドレス'],
                ['re' => 'https?:\\/\\/\\S+', 'flags' => 'i', 'label' => 'URL'],
                ['re' => '(line|ﾗｲﾝ|ライン)\\s*(id|ID|アイディー)?[:：]?\\s*[A-Za-z0-9._-]{3,}', 'flags' => 'i', 'label' => 'LINE ID'],
                ['re' => '@[A-Za-z0-9_.]{3,}', 'flags' => '', 'label' => 'SNSアカウント'],
            ],
            'words' => $words,
        ];
    }

    private function applicationStatusCode(int $status): string
    {
        return match ($status) {
            self::APPLICATION_STATUS_INTERVIEW_PENDING => 'interview_pending',
            self::APPLICATION_STATUS_INTERVIEW_FIXED => 'interview_fixed',
            self::APPLICATION_STATUS_HIRED => 'hired',
            self::APPLICATION_STATUS_REJECTED => 'rejected',
            self::APPLICATION_STATUS_HIRED_FULLTIME => 'hired',
            self::APPLICATION_STATUS_REJECTED_TRIAL => 'rejected',
            default => 'chatting',
        };
    }

    private function syncApplicationStatusFromTalkAction(
        string $partnerId,
        bool $isCastPortal,
        string $actionType,
        string $content,
        ?string $hiredRegularHourlyWage = null,
        ?string $selectedEmploymentKind = null,
        ?string $internalRejectionReason = null,
    ): void {
        $castId = $isCastPortal ? $this->currentCastId() : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $this->currentShopId();
        $application = $this->resolveOrCreateApplicationForTalk($castId, $shopId);

        if (!$application) {
            return;
        }

        $updates = ['updated_at' => now()];
        $meta = $content !== '' ? (json_decode($content, true) ?: []) : [];

        if ($actionType === 'interview_offer') {
            $updates['status'] = self::APPLICATION_STATUS_INTERVIEW_PENDING;
        } elseif ($actionType === 'interview_confirm') {
            $updates['status'] = self::APPLICATION_STATUS_INTERVIEW_FIXED;
            $updates['result_date'] = !empty($meta['selected_option'])
                ? Carbon::parse($meta['selected_option'])->toDateString()
                : $application->result_date;
        } elseif ($actionType === 'hired') {
            $updates['status'] = self::APPLICATION_STATUS_HIRED;
            $updates['reason_rejection'] = null;
            $hiredBonus = $this->resolveHiredBonusForApplicationUpdate($application, $selectedEmploymentKind);
            if ($hiredBonus !== null) {
                $updates['hired_bonus_amount'] = $hiredBonus['bonus_amount'];
                if (Schema::hasColumn('shop_job_applications', 'hired_bonus_condition')) {
                    $updates['hired_bonus_condition'] = $hiredBonus['bonus_condition'];
                }
            }
            if (Schema::hasColumn('shop_job_applications', 'hired_regular_hourly_wage') && $hiredRegularHourlyWage !== null) {
                $updates['hired_regular_hourly_wage'] = $hiredRegularHourlyWage;
            } elseif (Schema::hasColumn('shop_job_applications', 'hourly_wage_regular') && $hiredRegularHourlyWage !== null) {
                $updates['hourly_wage_regular'] = $hiredRegularHourlyWage;
            }
        } elseif ($actionType === 'rejected') {
            $updates['status'] = self::APPLICATION_STATUS_REJECTED;
            $updates['reason_rejection'] = trim((string) $internalRejectionReason);
        } elseif ($actionType === 'cancel_status') {
            $updates['status'] = self::APPLICATION_STATUS_CHATTING;
            $updates['result_date'] = null;
            $updates['reason_rejection'] = null;
        } elseif ($actionType === 'interview_cancel_accept') {
            $updates['status'] = self::APPLICATION_STATUS_CHATTING;
            $updates['result_date'] = null;
        } elseif ($actionType === 'work_complete_report') {
            $updates['status'] = self::APPLICATION_STATUS_HIRED;
        }

        DB::table('shop_job_applications')
            ->where('id', $application->id)
            ->update($updates);
    }

    private function findApplicationForTalk(string $castId, string $shopId): ?object
    {
        return DB::table('shop_job_applications')
            ->join('shop_jobs', 'shop_job_applications.shop_job_id', '=', 'shop_jobs.id')
            ->where('shop_job_applications.cast_id', $castId)
            ->where('shop_jobs.shop_id', $shopId)
            ->orderByDesc('shop_job_applications.id')
            ->select('shop_job_applications.*')
            ->first();
    }

    private function resolveOrCreateApplicationForTalk(string $castId, string $shopId): ?object
    {
        $application = $this->findApplicationForTalk($castId, $shopId);

        if ($application) {
            return $application;
        }

        $shopJob = DB::table('shop_jobs')
            ->where('shop_id', $shopId)
            ->orderByDesc('id')
            ->first();

        if (!$shopJob) {
            return null;
        }

        $row = [
            'cast_id' => $castId,
            'shop_job_id' => $shopJob->id,
            'status' => 1,
            'result_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $row = array_merge($row, $this->shopJobApplicationJobSnapshotService->snapshotColumnsForApplication($shopJob));

        $applicationId = DB::table('shop_job_applications')->insertGetId($row);

        return DB::table('shop_job_applications')->where('id', $applicationId)->first();
    }

    private function ensureApplicationForTalkStart(
        string $castId,
        string $shopId,
        ?string $talkTopic,
        ?string $talkJobKind,
    ): void {
        if ($talkTopic === 'other') {
            return;
        }
        if ($this->findApplicationForTalk($castId, $shopId)) {
            return;
        }
        $targetJobType = match ($talkJobKind) {
            'trial' => 2,
            'help' => 3,
            default => 1,
        };
        $created = $this->createApplicationForTalk($castId, $shopId, $targetJobType);
        if ($created && in_array($talkTopic, ['new_hire', 'help'], true)) {
            $autoMessage = $talkTopic === 'help'
                ? '【自動送信】ヘルプ求人から応募がありました。'
                : '【自動送信】新規採用求人から応募がありました。';
            DB::table('messages')->insert([
                'cast_id' => $castId,
                'shop_id' => $shopId,
                'sender_type' => $this->mySenderType(true),
                'type' => self::MESSAGE_TYPE_TEXT,
                'content' => $autoMessage,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createApplicationForTalk(string $castId, string $shopId, int $preferredJobType): ?object
    {
        $shopJobId = $this->resolveShopJobIdByType($shopId, $preferredJobType);
        if ($shopJobId === null) {
            $shopJobId = $this->resolveShopJobIdByType($shopId, 1);
        }
        if ($shopJobId === null) {
            return null;
        }
        $shopJob = DB::table('shop_jobs')->where('id', $shopJobId)->first();
        if (!$shopJob) {
            return null;
        }

        $row = [
            'cast_id' => $castId,
            'shop_job_id' => $shopJob->id,
            'status' => 1,
            'result_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('shop_job_applications', 'talk_job_kind')) {
            $row['talk_job_kind'] = match ($preferredJobType) {
                2 => 'trial',
                3 => 'help',
                default => 'fulltime',
            };
        }
        $row = array_merge($row, $this->shopJobApplicationJobSnapshotService->snapshotColumnsForApplication($shopJob));
        $applicationId = DB::table('shop_job_applications')->insertGetId($row);

        return DB::table('shop_job_applications')->where('id', $applicationId)->first();
    }

    private function resolveShopJobIdByType(string $shopId, int $jobType): ?int
    {
        $query = DB::table('shop_jobs')->where('shop_id', $shopId);
        if (Schema::hasColumn('shop_jobs', 'job_type')) {
            $query->where('job_type', $jobType);
        }
        $id = $query->orderByDesc('id')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * トーク相手との会話コンテキストから、求人票ベースのボーナス条件メタ情報を取得する
     * （ボーナス金額・勤務日数・勤務時間・フリーテキスト条件）。
     *
     * 面談候補日送信／面談日確定メッセージに埋め込み、後続のチェックリスト機能でも再利用する想定。
     */
    private function buildJobBonusMetaForConversation(string $castId, string $shopId): ?array
    {
        $application = $this->resolveOrCreateApplicationForTalk($castId, $shopId);
        if (!$application) {
            return null;
        }

        $job = DB::table('shop_jobs')->where('id', $application->shop_job_id)->first();
        if (!$job) {
            return null;
        }

        $meta = [];
        if (!empty($job->noruma_cond)) {
            $decoded = json_decode($job->noruma_cond, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        if (property_exists($application, 'applied_norma_day') && $application->applied_norma_day !== null && $application->applied_norma_day !== '') {
            $meta['working_days'] = (string) (int) $application->applied_norma_day;
        }
        if (property_exists($application, 'applied_norma_hours') && $application->applied_norma_hours !== null && $application->applied_norma_hours !== '') {
            $meta['working_hours'] = (string) (int) $application->applied_norma_hours;
        }

        $extraCondition = ShopJobApplicationView::bonusConditionAtApplication($application);
        if ($extraCondition === '' && property_exists($job, 'bonus_condition') && $job->bonus_condition !== null && $job->bonus_condition !== '') {
            $extraCondition = trim((string) $job->bonus_condition);
        }
        if ($extraCondition === '' && isset($meta['bonus_condition'])) {
            $extraCondition = trim((string) $meta['bonus_condition']);
        }

        $bonusAmount = 0;
        if (property_exists($application, 'applied_bonus_reward') && $application->applied_bonus_reward !== null) {
            $bonusAmount = (int) $application->applied_bonus_reward;
        } else {
            $bonusAmount = (int) ($job->bonus_reward ?? $job->noruma_reward ?? $job->hourly_wage_regular ?? 0);
        }

        return [
            'bonus_amount' => $bonusAmount,
            'working_days' => isset($meta['working_days']) ? (string) $meta['working_days'] : '',
            'working_hours' => isset($meta['working_hours']) ? (string) $meta['working_hours'] : '',
            'extra_condition' => $extraCondition,
        ];
    }

    private function resolveResultMessage(string $actionType, string $customMessage): string
    {
        $message = trim($customMessage);
        if ($actionType !== 'rejected' && $message !== '') {
            return $message;
        }

        return match ($actionType) {
            'hired' => $this->messageTemplateService->getDefaultBody('talk_hired'),
            'rejected' => $this->messageTemplateService->getDefaultBody('talk_rejected'),
            default => '',
        };
    }

    private function buildHiredMessageForCast(string $baseMessage, string $hourlyWage, string $employmentKind): string
    {
        $kindLabel = match ($employmentKind) {
            'trial' => '体験入店',
            'help' => 'ヘルプ',
            default => '本入店',
        };
        return trim($baseMessage) . "\n\n" .
            '【確定情報】' . "\n" .
            '採用区分: ' . $kindLabel . "\n" .
            '時給: ¥' . number_format((int) $hourlyWage);
    }

    private function ensureAutoPrefix(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '【自動送信】';
        }
        if (Str::startsWith($trimmed, '【自動送信】')) {
            return $trimmed;
        }

        return '【自動送信】' . $trimmed;
    }

    /**
     * 採用時点のボーナス金・達成条件を求人から取得して返す（レガシー行・列向け）
     */
    private function snapshotHiredBonusForApplication(int $shopJobId): ?array
    {
        $job = DB::table('shop_jobs')->where('id', $shopJobId)->first();
        if (!$job) {
            return null;
        }

        $bonusAmount = 0;
        if (property_exists($job, 'bonus_reward') && $job->bonus_reward !== null) {
            $bonusAmount = (int) $job->bonus_reward;
        } elseif (property_exists($job, 'noruma_reward') && $job->noruma_reward !== null) {
            $bonusAmount = (int) $job->noruma_reward;
        } elseif (!property_exists($job, 'bonus_reward')
            && !property_exists($job, 'noruma_reward')
            && property_exists($job, 'hourly_wage_regular')
            && $job->hourly_wage_regular !== null) {
            $bonusAmount = (int) $job->hourly_wage_regular;
        }

        $bonusCondition = '';
        if (property_exists($job, 'bonus_condition') && $job->bonus_condition !== null && $job->bonus_condition !== '') {
            $bonusCondition = trim((string) $job->bonus_condition);
        } elseif (!empty($job->noruma_cond)) {
            $meta = json_decode((string) $job->noruma_cond, true);
            $bonusCondition = trim((string) ($meta['bonus_condition'] ?? ''));
        }

        return [
            'bonus_amount' => $bonusAmount,
            'bonus_condition' => $bonusCondition,
        ];
    }

    /**
     * @return array{bonus_amount: int, bonus_condition: string}|null
     */
    private function resolveHiredBonusForApplicationUpdate(object $application, ?string $selectedEmploymentKind = null): ?array
    {
        if (!Schema::hasColumn('shop_job_applications', 'hired_bonus_amount')) {
            return null;
        }

        $resolvedKind = $selectedEmploymentKind;
        if ($resolvedKind === null && Schema::hasColumn('shop_job_applications', 'talk_job_kind')) {
            $candidate = trim((string) ($application->talk_job_kind ?? ''));
            if (in_array($candidate, ['fulltime', 'trial', 'help'], true)) {
                $resolvedKind = $candidate;
            }
        }
        if ($resolvedKind === null) {
            $resolvedKind = 'fulltime';
        }

        // ヘルプ採用はボーナス対象外。保存値を明示的に0/空に揃える。
        if ($resolvedKind === 'help') {
            return [
                'bonus_amount' => 0,
                'bonus_condition' => '',
            ];
        }

        $shopId = DB::table('shop_jobs')
            ->where('id', (int) $application->shop_job_id)
            ->value('shop_id');
        if (!$shopId) {
            $fromJob = $this->snapshotHiredBonusForApplication((int) $application->shop_job_id);
            return [
                'bonus_amount' => (int) ($fromJob['bonus_amount'] ?? 0),
                'bonus_condition' => (string) ($fromJob['bonus_condition'] ?? ''),
            ];
        }

        $targetJobType = match ($resolvedKind) {
            'trial' => 2,
            default => 1,
        };
        $targetShopJobId = $this->resolveShopJobIdByType((string) $shopId, $targetJobType)
            ?? (int) $application->shop_job_id;
        $fromJob = $this->snapshotHiredBonusForApplication((int) $targetShopJobId);

        $amount = 0;
        if ($fromJob !== null) {
            $amount = (int) $fromJob['bonus_amount'];
        }

        $condition = '';
        if ($fromJob !== null) {
            $condition = (string) $fromJob['bonus_condition'];
        }

        return [
            'bonus_amount' => $amount,
            'bonus_condition' => $condition,
        ];
    }

    private function syncApplicationEmploymentKindFromTalkAction(
        string $partnerId,
        bool $isCastPortal,
        string $employmentKind,
        string $actionType,
    ): void {
        $castId = $isCastPortal ? $this->currentCastId() : $partnerId;
        $shopId = $isCastPortal ? $partnerId : $this->currentShopId();
        $application = $this->resolveOrCreateApplicationForTalk($castId, $shopId);
        if (!$application) {
            return;
        }

        $targetJobType = match ($employmentKind) {
            'trial' => 2,
            'help' => 3,
            default => 1,
        };
        $targetShopJobId = $this->resolveShopJobIdByType($shopId, $targetJobType);
        if ($targetShopJobId === null && Schema::hasColumn('shop_jobs', 'job_type')) {
            return;
        }

        $status = (int) $application->status;
        if ($actionType === 'hired') {
            $status = $employmentKind === 'fulltime' ? 6 : self::APPLICATION_STATUS_HIRED;
        } elseif ($actionType === 'rejected') {
            $status = $employmentKind === 'trial' ? 7 : self::APPLICATION_STATUS_REJECTED;
        }

        $updates = [
            'status' => $status,
            'updated_at' => now(),
        ];
        if ($targetShopJobId !== null) {
            $updates['shop_job_id'] = $targetShopJobId;
        }
        if (Schema::hasColumn('shop_job_applications', 'talk_job_kind')) {
            $updates['talk_job_kind'] = $employmentKind;
        }

        DB::table('shop_job_applications')
            ->where('id', $application->id)
            ->update($updates);
    }

    private function getSelectedTalkJobKind(string $castId, string $shopId): ?string
    {
        $application = $this->findApplicationForTalk($castId, $shopId);
        if (!$application) {
            return null;
        }
        if (Schema::hasColumn('shop_job_applications', 'talk_job_kind')) {
            $kind = trim((string) ($application->talk_job_kind ?? ''));
            if (in_array($kind, ['fulltime', 'trial', 'help'], true)) {
                return $kind;
            }
        }
        if (Schema::hasColumn('shop_jobs', 'job_type')) {
            $jobType = DB::table('shop_jobs')->where('id', $application->shop_job_id)->value('job_type');
            $jt = $jobType !== null ? (int) $jobType : 1;
            return match ($jt) {
                2 => 'trial',
                3 => 'help',
                default => 'fulltime',
            };
        }
        return null;
    }

    private function setConversationJobKind(string $castId, string $shopId, string $jobKind): void
    {
        $targetJobType = match ($jobKind) {
            'trial' => 2,
            'help' => 3,
            default => 1,
        };
        $targetShopJobId = $this->resolveShopJobIdByType($shopId, $targetJobType);
        if ($targetShopJobId === null) {
            abort(422, '選択した求人種別の求人票が見つかりません。');
        }

        $application = $this->findApplicationForTalk($castId, $shopId);
        if (!$application) {
            $this->createApplicationForTalk($castId, $shopId, $targetJobType);
            return;
        }

        $updates = ['updated_at' => now()];
        if ($targetShopJobId !== null) {
            $updates['shop_job_id'] = $targetShopJobId;
        }
        if (Schema::hasColumn('shop_job_applications', 'talk_job_kind')) {
            $updates['talk_job_kind'] = $jobKind;
        }

        DB::table('shop_job_applications')
            ->where('id', $application->id)
            ->update($updates);
    }

    private function normalizeTalkTopic(string $value): ?string
    {
        $v = trim($value);
        return in_array($v, ['new_hire', 'help', 'other'], true) ? $v : null;
    }

    private function normalizeTalkJobKind(string $value): ?string
    {
        $v = trim($value);
        return in_array($v, ['fulltime', 'trial', 'help'], true) ? $v : null;
    }

    private function normalizeEmploymentKind(string $value): ?string
    {
        $v = trim($value);
        return in_array($v, ['fulltime', 'trial', 'help'], true) ? $v : null;
    }

    private function notifyTalkAction(
        string $castId,
        string $shopId,
        bool $isCastPortal,
        string $actionType,
        string $content
    ): void {
        $meta = json_decode($content, true);
        $meta = is_array($meta) ? $meta : [];
        $title = 'トーク更新';
        $body = 'トーク内容が更新されました。';

        if ($actionType === 'interview_offer') {
            $title = '面談候補日が届きました';
            $body = '候補日時を確認して、都合のよい日程を選択してください。';
        } elseif ($actionType === 'interview_confirm') {
            $selected = isset($meta['selected_option']) && $meta['selected_option'] !== ''
                ? Carbon::parse((string) $meta['selected_option'])->format('Y/m/d H:i')
                : null;
            $title = $isCastPortal ? '面談日時が確定しました' : '面談日が確定しました';
            $body = $selected ? ('確定日時: ' . $selected) : 'トーク画面で確定内容をご確認ください。';
        } elseif ($actionType === 'hired') {
            $title = '選考結果: 採用';
            $body = '店舗から採用連絡が届いています。';
        } elseif ($actionType === 'rejected') {
            $title = '選考結果: 不採用';
            $body = '店舗から結果連絡が届いています。';
        } elseif ($actionType === 'cancel_status') {
            $title = '面談ステータスが変更されました';
            $body = $isCastPortal
                ? '面談日程がキャンセルされました。再提案をお願いします。'
                : '面談日程が再調整になりました。トークをご確認ください。';
        } elseif ($actionType === 'fulltime_request') {
            $title = '本入店リクエスト';
            $body = 'キャストから本入店リクエストが届きました。';
        } elseif ($actionType === 'work_complete_report') {
            $title = '勤務完了報告';
            $body = 'キャストから勤務完了報告が届きました。';
        } elseif ($actionType === 'bonus_achievement_report') {
            $title = 'ボーナス達成報告';
            $body = 'キャストからボーナス達成報告が届きました。承認をご確認ください。';
        } elseif ($actionType === 'interview_cancel_request') {
            $title = '面談キャンセル依頼';
            $body = '店舗から面談キャンセル依頼が届きました。承諾するとやり取り中に戻ります。';
        } elseif ($actionType === 'interview_cancel_accept') {
            $title = '面談キャンセル承諾';
            $body = 'キャストが面談キャンセルを承諾しました。';
        }

        // Map talk actions to distinct notification types so that
        // per-category user preferences (talk vs selection) can gate delivery.
        $type = match ($actionType) {
            'interview_offer' => 'talk.interview_offer',
            'interview_confirm' => 'talk.interview_confirmed',
            'hired' => 'talk.hired',
            'rejected' => 'talk.rejected',
            default => 'talk.status_changed',
        };

        $this->notifyConversationPartner(
            castId: $castId,
            shopId: $shopId,
            isCastPortal: $isCastPortal,
            title: $title,
            body: $body,
            url: $isCastPortal ? url('/shop/talk/room/' . $castId) : url('/cast/talk/room/' . $shopId),
            type: $type
        );
    }

    private function notifyConversationPartner(
        string $castId,
        string $shopId,
        bool $isCastPortal,
        string $title,
        string $body,
        string $url,
        string $type = 'talk.message_received'
    ): void {
        // Channel gating (push/LINE x category) is handled inside NotificationService;
        // the in-app inbox record is always created.
        try {
            if ($isCastPortal) {
                // キャスト→店舗：店舗マネージャー全員に通知
                $managerIds = DB::table('shop_managers')
                    ->where('shop_id', $shopId)
                    ->pluck('id');
                foreach ($managerIds as $managerId) {
                    $this->notificationService->createForShopManager(
                        (string) $managerId,
                        $type,
                        $title,
                        $body,
                        $url,
                        ['cast_id' => $castId, 'shop_id' => $shopId]
                    );
                }
                return;
            }

            // 店舗→キャスト
            $this->notificationService->createForCast(
                $castId,
                $type,
                $title,
                $body,
                $url,
                ['cast_id' => $castId, 'shop_id' => $shopId]
            );
        } catch (\Throwable $e) {
            Log::warning('Talk notify failed: ' . $e->getMessage());
        }
    }

    private function notifyOperationTransferInstruction(string $castId, string $shopId, string $flowType): void
    {
        $application = $this->findApplicationForTalk($castId, $shopId);
        if (!$application) {
            return;
        }

        if ($flowType === 'work_complete') {
            $hourly = 0;
            if (property_exists($application, 'hired_regular_hourly_wage') && $application->hired_regular_hourly_wage !== null) {
                $hourly = (int) $application->hired_regular_hourly_wage;
            } elseif (property_exists($application, 'applied_regular_hourly_wage') && $application->applied_regular_hourly_wage !== null) {
                $hourly = (int) $application->applied_regular_hourly_wage;
            }
            $amount = (int) floor($hourly * 0.23);
            $title = '運営への振込指示';
            $body = '勤務完了報告を受領しました。指示額: ¥' . number_format($amount);
            $this->notifyConversationPartner($castId, $shopId, true, $title, $body, url('/shop/talk/room/' . $castId));
            return;
        }

        $bonus = 0;
        if (property_exists($application, 'hired_bonus_amount') && $application->hired_bonus_amount !== null) {
            $bonus = (int) $application->hired_bonus_amount;
        } elseif (property_exists($application, 'applied_bonus_reward') && $application->applied_bonus_reward !== null) {
            $bonus = (int) $application->applied_bonus_reward;
        }
        $amount = (int) floor($bonus * 1.23);
        $title = '運営への振込指示';
        $body = 'ボーナス達成報告を受領しました。指示額: ¥' . number_format($amount);
        $this->notifyConversationPartner($castId, $shopId, true, $title, $body, url('/shop/talk/room/' . $castId));
    }

    private function invalidateInterviewOffers(string $castId, string $shopId): void
    {
        $targets = DB::table('messages')
            ->where('cast_id', $castId)
            ->where('shop_id', $shopId)
            ->where('type', self::MESSAGE_TYPE_INTERVIEW_OFFER)
            ->orderByDesc('id')
            ->get();

        foreach ($targets as $row) {
            $meta = json_decode((string) $row->content, true);
            if (!is_array($meta)) {
                continue;
            }
            if (!empty($meta['invalidated'])) {
                continue;
            }
            $meta['invalidated'] = true;
            $meta['invalidated_at'] = now()->toDateTimeString();
            DB::table('messages')
                ->where('id', $row->id)
                ->update([
                    'content' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }
}

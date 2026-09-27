<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 店舗の有料プラン契約（Premium）。
 * 振込 + 運営の目視確認で有効化する運用のため、
 * 「入金待ち → 有効」のステータス遷移を管理画面から行う。
 */
class ShopPlanSubscription extends Model
{
    public const STATUS_PENDING_PAYMENT = 1; // 契約済み・入金待ち
    public const STATUS_ACTIVE          = 2; // 入金確認済み・Premium有効
    public const STATUS_EXPIRED         = 3; // 期間満了
    public const STATUS_CANCELED        = 4; // キャンセル（入金前）

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY  = 'yearly';

    public const PLAN_PREMIUM = 'premium';

    protected $table = 'shop_plan_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'status' => 'integer',
        'invoice_issued_at' => 'datetime',
        'payment_due_date' => 'date',
        'shop_payment_reported_at' => 'datetime',
        'paid_confirmed_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function statusLabel(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PENDING_PAYMENT => '入金待ち',
            self::STATUS_ACTIVE => '有効',
            self::STATUS_EXPIRED => '期間満了',
            self::STATUS_CANCELED => 'キャンセル',
            default => '不明',
        };
    }

    public function cycleLabel(): string
    {
        return $this->billing_cycle === self::CYCLE_YEARLY ? '年払い' : '月払い';
    }
}

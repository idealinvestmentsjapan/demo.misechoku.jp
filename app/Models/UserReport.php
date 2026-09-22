<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ユーザー間の通報レコード。キャスト・店舗の相互報告を管理者キューに送る。
 */
class UserReport extends Model
{
    // Legacy int constants (backwards compat).
    // Prefer App\Enums\UserReportStatus in new code:
    //   UserReportStatus::from($report->status)->label()
    public const STATUS_PENDING   = 0;
    public const STATUS_IN_REVIEW = 1;
    public const STATUS_RESOLVED  = 2;
    public const STATUS_DISMISSED = 3;

    /**
     * Convenience accessor returning the strongly-typed status enum
     * (or null if the row has an unexpected value).
     */
    public function statusEnum(): ?\App\Enums\UserReportStatus
    {
        return \App\Enums\UserReportStatus::tryFrom((int) $this->status);
    }

    /** 通報理由コード（フロント側の enum に一致させる） */
    public const REASONS = [
        'harassment'    => 'ハラスメント／脅迫',
        'contact_info'  => '連絡先誘導（LINE ID・電話番号等）',
        'inappropriate' => '不適切な発言・画像',
        'fake'          => 'なりすまし・虚偽情報',
        'other'         => 'その他',
    ];

    protected $table = 'user_reports';

    protected $fillable = [
        'reporter_type',
        'reporter_id',
        'target_type',
        'target_id',
        'reason',
        'detail',
        'context_type',
        'context_message_id',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'status' => 'integer',
    ];

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function statusLabel(): string
    {
        return match ((int) $this->status) {
            self::STATUS_IN_REVIEW => '対応中',
            self::STATUS_RESOLVED  => '完了',
            self::STATUS_DISMISSED => '却下',
            default                => '未対応',
        };
    }
}

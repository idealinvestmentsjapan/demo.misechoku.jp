<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportInquiryController extends Controller
{
    public function index(Request $request): View
    {
        // 未対応（要対応）のみ、発生日が古い順（＝経過日数が長い順）で表示。
        // 対応済みや対応中を含む一覧・フリー検索は運用上不要のため撤去済み。
        $inquiries = SupportInquiry::query()
            ->where('status', SupportInquiry::STATUS_NEW)
            ->orderBy('created_at', 'asc')
            ->paginate(30);

        $pendingCount = SupportInquiry::query()
            ->where('status', SupportInquiry::STATUS_NEW)
            ->count();

        return view('admin.support-inquiries.index', compact('inquiries', 'pendingCount'));
    }

    public function show(SupportInquiry $inquiry): View
    {
        return view('admin.support-inquiries.show', compact('inquiry'));
    }

    public function updateStatus(Request $request, SupportInquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportInquiry::STATUS_LABELS))],
        ]);

        $update = ['status' => $validated['status']];

        // 「対応中」「完了」へ最初に遷移した瞬間に responded_at を記録（一次応対した時刻）
        if (in_array($validated['status'], [SupportInquiry::STATUS_IN_PROGRESS, SupportInquiry::STATUS_RESOLVED], true)
            && $inquiry->responded_at === null) {
            $update['responded_at'] = now();
        }

        // 担当を未割当なら自分（admin）で埋める
        if (empty($inquiry->assigned_admin_id)) {
            $update['assigned_admin_id'] = (string) (Auth::guard('admin')->id() ?? '');
        }

        $inquiry->update($update);

        return redirect()
            ->route('admin.support-inquiries.show', $inquiry->id)
            ->with('status', '対応ステータスを更新しました。');
    }

    public function updateNote(Request $request, SupportInquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $inquiry->update(['admin_note' => $validated['admin_note']]);

        return redirect()
            ->route('admin.support-inquiries.show', $inquiry->id)
            ->with('status', 'メモを保存しました。');
    }
}

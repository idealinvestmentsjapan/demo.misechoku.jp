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
        $status = $request->query('status', SupportInquiry::STATUS_NEW);
        $validStatuses = array_keys(SupportInquiry::STATUS_LABELS);

        $q = SupportInquiry::query();
        if (in_array($status, $validStatuses, true)) {
            $q->where('status', $status);
            // 新着は古い順（経過日数の長い順）、対応中・完了は新しい順
            $q->orderBy('created_at', $status === SupportInquiry::STATUS_NEW ? 'asc' : 'desc');
        } elseif ($status === 'all') {
            $q->orderByDesc('created_at');
        } else {
            $status = SupportInquiry::STATUS_NEW;
            $q->where('status', $status)->orderBy('created_at', 'asc');
        }

        $inquiries = $q->paginate(30)->withQueryString();

        $counts = [
            'all'         => SupportInquiry::count(),
            'new'         => SupportInquiry::where('status', SupportInquiry::STATUS_NEW)->count(),
            'in_progress' => SupportInquiry::where('status', SupportInquiry::STATUS_IN_PROGRESS)->count(),
            'resolved'    => SupportInquiry::where('status', SupportInquiry::STATUS_RESOLVED)->count(),
        ];

        return view('admin.support-inquiries.index', compact('inquiries', 'counts', 'status'));
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

        return $this->redirectAfterUpdate($request, $inquiry, '対応ステータスを更新しました。');
    }

    public function updateNote(Request $request, SupportInquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $inquiry->update(['admin_note' => $validated['admin_note']]);

        return $this->redirectAfterUpdate($request, $inquiry, 'メモを保存しました。');
    }

    /**
     * 更新後のリダイレクト先を決定。redirect_to=index なら一覧に戻る。
     */
    private function redirectAfterUpdate(Request $request, SupportInquiry $inquiry, string $message): RedirectResponse
    {
        if ($request->input('redirect_to') === 'index') {
            $returnTab = $request->input('return_tab', SupportInquiry::STATUS_NEW);
            return redirect()
                ->route('admin.support-inquiries.index', ['status' => $returnTab])
                ->with('status', $message);
        }

        return redirect()
            ->route('admin.support-inquiries.show', $inquiry->id)
            ->with('status', $message);
    }
}

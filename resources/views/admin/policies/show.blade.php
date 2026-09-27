@extends('layouts.admin')

@php
    use App\Support\MarkdownRenderer;
    $isEditing = $isEditing ?? false;
    $oldChapters = $document->isAbout()
        ? []
        : ($isEditing
            ? (old('chapters', $document->chapters->map(fn ($c) => ['title' => $c->title, 'body' => $c->body])->all()))
            : []);
    if ($isEditing && ! $document->isAbout() && (! is_array($oldChapters) || count($oldChapters) === 0)) {
        $oldChapters = [['title' => '', 'body' => '']];
    }
    $oldMeta = old('meta', $document->meta ?? []);
@endphp

@section('title', $document->title . ' - 規約管理')
@section('admin_page_title', $document->title)

@push('admin-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin-policy-page.css') }}">
@endpush

@section('content')
    <div class="admin-page policy-doc-admin">
        @php
            $policyTabs = [
                'about'   => ['label' => '運営協会',         'icon' => 'fa-landmark'],
                'terms'   => ['label' => '利用規約',         'icon' => 'fa-file-contract'],
                'privacy' => ['label' => 'プライバシーポリシー', 'icon' => 'fa-user-shield'],
            ];
            $currentKey = (string) $document->key;
        @endphp
        <nav class="policy-switcher" aria-label="規約切替">
            @foreach ($policyTabs as $key => $meta)
                <a href="{{ route('admin.policies.show', ['key' => $key]) }}"
                   class="policy-switcher__tab {{ $currentKey === $key ? 'is-active' : '' }}">
                    <i class="fas {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="policy-doc-admin__toolbar">
            <div>
                @include('admin.parts.page-title', ['eyebrow' => 'POLICY', 'title' => $document->title])
                <div class="u-mt-4">
                    <span class="policy-doc-admin__key-badge">キー: {{ $document->key }}</span>
                </div>
            </div>
            <div class="policy-doc-admin__actions">
                @if ($isEditing)
                    <a href="{{ route('admin.policies.show', ['key' => $document->key]) }}" class="btn-policy-ghost">
                        <i class="fas fa-times"></i> キャンセル
                    </a>
                    <button type="submit" form="policy-edit-form" class="btn-policy-primary">
                        <i class="fas fa-save"></i> 保存する
                    </button>
                @else
                    <a href="{{ route('admin.policies.show', ['key' => $document->key, 'edit' => 1]) }}" class="btn-policy-primary">
                        <i class="fas fa-pen"></i> 編集する
                    </a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="admin-alert admin-alert-success" style="margin-bottom:1rem;">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="admin-alert admin-alert-error" style="margin-bottom:1rem;">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 更新履歴（開閉） --}}
        <div class="policy-acc" id="policy-acc-history">
            <button type="button" class="policy-acc__head" data-policy-acc="policy-acc-history" aria-expanded="false">
                <span class="policy-acc__head-left">
                    <span class="policy-acc__icon"><i class="fas fa-clock-rotate-left"></i></span>
                    <span class="policy-acc__title">更新履歴</span>
                </span>
                <i class="fas fa-chevron-down policy-acc__chev" aria-hidden="true"></i>
            </button>
            <div class="policy-acc__body">
                <div style="padding:1rem 1.1rem 1.25rem;">
                    @if ($document->revisions->isEmpty())
                        <p class="admin-note" style="margin:0;">まだ更新履歴はありません。</p>
                    @else
                        <div class="table-wrapper" style="border:none;box-shadow:none;background:transparent;">
                            <table class="admin-table" style="min-width:100%;">
                                <thead>
                                    <tr>
                                        <th>日時</th>
                                        <th>操作</th>
                                        <th>更新者</th>
                                        <th>メモ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($document->revisions as $rev)
                                        <tr>
                                            <td>{{ optional($rev->created_at)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $rev->action_label }}</td>
                                            <td>{{ $rev->updated_by_name ?: '-' }}</td>
                                            <td style="white-space:normal;">{{ $rev->summary ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($isEditing)
            <form id="policy-edit-form" method="post" action="{{ route('admin.policies.update', ['key' => $document->key]) }}">
                @csrf
                @method('PUT')
        @endif

        {{-- 規約本文（開閉・ライト背景） --}}
        <div class="policy-light is-open" id="policy-acc-body">
            <button type="button" class="policy-light__head" data-policy-acc="policy-acc-body" aria-expanded="true">
                <span class="policy-light__head-title">
                    <i class="fas fa-file-lines"></i>
                    規約本文
                </span>
                <span style="display:flex;align-items:center;gap:0.75rem;">
                    @unless ($document->isAbout())
                        <span class="policy-acc__meta">全 {{ $isEditing ? count($oldChapters) : $document->chapters->count() }} 章</span>
                    @endunless
                    <i class="fas fa-chevron-down policy-light__chev" aria-hidden="true"></i>
                </span>
            </button>
            <div class="policy-light__body">
                @if ($isEditing)
                    <label class="policy-field-label">ページタイトル</label>
                    <input type="text" name="title" class="policy-field-input" style="margin-bottom:1.1rem;"
                        value="{{ old('title', $document->title) }}" required maxlength="200">

                    <label class="policy-field-label">リード見出し（任意）</label>
                    <input type="text" name="lead_title" class="policy-field-input" style="margin-bottom:1rem;"
                        value="{{ old('lead_title', $document->lead_title) }}" maxlength="200"
                        placeholder="例: GREETING / 理事長 挨拶">
                @endif

                @if ($document->lead_title && ! $isEditing)
                    <p class="policy-field-label" style="margin-bottom:0.35rem;">{{ $document->lead_title }}</p>
                @endif

                @if ($document->lead_body || $isEditing)
                    @if ($isEditing)
                        <label class="policy-field-label">リード本文（Markdown）</label>
                        <textarea name="lead_body" class="policy-field-textarea" rows="8" style="margin-bottom:0;"
                            placeholder="Markdown で入力">{{ old('lead_body', $document->lead_body) }}</textarea>
                    @else
                        <div class="policy-md-light">
                            {!! MarkdownRenderer::toHtml($document->lead_body ?? '') !!}
                        </div>
                    @endif
                @endif

                @if ($document->isAbout() && is_array($document->meta))
                    <div class="policy-preamble-sep"></div>
                    <p class="policy-field-label" style="margin-bottom:0.75rem;">協会概要</p>
                    <div class="policy-meta-grid">
                        @foreach ($metaSchema as $row)
                            @php
                                $entry = $isEditing ? ($oldMeta[$row['key']] ?? null) : ($document->meta[$row['key']] ?? null);
                                $value = is_array($entry) ? ($entry['value'] ?? '') : '';
                                $label = is_array($entry) ? ($entry['label'] ?? $row['label']) : $row['label'];
                            @endphp
                            <div class="policy-meta-row">
                                @if ($isEditing)
                                    <label class="policy-field-label" style="text-transform:none;letter-spacing:0;color:#5c4d43;">{{ $row['label'] }}</label>
                                    <input type="hidden" name="meta[{{ $row['key'] }}][label]" value="{{ $label }}">
                                    <textarea name="meta[{{ $row['key'] }}][value]" class="policy-field-textarea" rows="2">{{ $value }}</textarea>
                                @else
                                    <div class="policy-field-label" style="text-transform:none;letter-spacing:0.06em;color:#5c4d43;margin-bottom:0.35rem;">{{ $label }}</div>
                                    @if ($value !== '')
                                        <div class="policy-md-light">{!! MarkdownRenderer::toHtml($value) !!}</div>
                                    @else
                                        <span style="color:#a89a8c;font-size:0.88rem;">-</span>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @unless ($document->isAbout())
                    @if (($document->lead_body || $document->lead_title || $isEditing) || $document->chapters->isNotEmpty() || $isEditing)
                        <div class="policy-preamble-sep"></div>
                    @endif

                    @if ($isEditing)
                        <div id="chapters-list">
                            @foreach ($oldChapters as $i => $ch)
                                <div class="policy-chapter-edit policy-chapter-row">
                                    <div class="policy-chapter-edit__row">
                                        <input type="text" name="chapters[{{ $i }}][title]" class="policy-field-input"
                                            value="{{ is_array($ch) ? ($ch['title'] ?? '') : '' }}" maxlength="200" placeholder="章タイトル">
                                        <button type="button" class="policy-chapter-remove policy-btn-icon" title="削除">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <label class="policy-field-label">本文（Markdown）</label>
                                    <textarea name="chapters[{{ $i }}][body]" class="policy-field-textarea" rows="6" placeholder="本文">{{ is_array($ch) ? ($ch['body'] ?? '') : '' }}</textarea>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="policy-add-chapter" id="add-chapter-btn">
                            <i class="fas fa-plus"></i> 新しい章を追加
                        </button>
                        <template id="chapter-template">
                            <div class="policy-chapter-edit policy-chapter-row">
                                <div class="policy-chapter-edit__row">
                                    <input type="text" name="chapters[__INDEX__][title]" class="policy-field-input" maxlength="200" placeholder="章タイトル">
                                    <button type="button" class="policy-chapter-remove policy-btn-icon" title="削除">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <label class="policy-field-label">本文（Markdown）</label>
                                <textarea name="chapters[__INDEX__][body]" class="policy-field-textarea" rows="6" placeholder="本文"></textarea>
                            </div>
                        </template>
                    @else
                        @forelse ($document->chapters as $chapter)
                            <article class="policy-chapter-read">
                                <h3 class="policy-chapter-read__title">{{ $chapter->title }}</h3>
                                <div class="policy-md-light">
                                    {!! MarkdownRenderer::toHtml($chapter->body) !!}
                                </div>
                            </article>
                        @empty
                            <p style="margin:0;color:#5c4d43;font-size:0.88rem;">章がまだありません。「編集する」から追加できます。</p>
                        @endforelse
                    @endif
                @endunless

                @if ($isEditing)
                    <div class="policy-save-footer">
                        <p class="policy-save-note">
                            保存すると更新者・日時が履歴に記録されます。保存するには「ロックを解除して更新する」にチェックを入れてください。
                        </p>
                        <label class="policy-field-label" style="text-transform:none;color:#241117;margin-bottom:0.5rem;">更新者名（必須）</label>
                        <input type="text" name="updater_name" class="policy-field-input" style="max-width:22rem;margin-bottom:0.75rem;"
                            value="{{ old('updater_name', optional(auth()->guard('admin')->user())->name) }}"
                            required maxlength="120" placeholder="例: 管理者 太郎">
                        <label class="policy-field-label" style="text-transform:none;color:#241117;margin-bottom:0.5rem;">更新内容メモ（任意）</label>
                        <textarea name="change_summary" class="policy-field-textarea" rows="2" style="margin-bottom:0.85rem;max-width:36rem;"
                            placeholder="変更内容の概要">{{ old('change_summary') }}</textarea>
                        <label style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.84rem;color:#3a2a22;cursor:pointer;">
                            <input type="checkbox" name="confirm_unlock" value="1" style="margin-top:0.2rem;" {{ old('confirm_unlock') ? 'checked' : '' }}>
                            <span><strong>ロックを解除して更新する</strong>（チェックなしでは保存できません）</span>
                        </label>
                    </div>
                @endif
            </div>
        </div>

        @if ($isEditing)
            </form>
        @endif
    </div>
@endsection

@push('admin-scripts')
<script>
    (function () {
        document.querySelectorAll('[data-policy-acc]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-policy-acc');
                var root = id ? document.getElementById(id) : null;
                if (!root) return;
                root.classList.toggle('is-open');
                var open = root.classList.contains('is-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });

        var listEl = document.getElementById('chapters-list');
        var addBtn = document.getElementById('add-chapter-btn');
        var tmplEl = document.getElementById('chapter-template');
        if (listEl && addBtn && tmplEl) {
            function renumber() {
                listEl.querySelectorAll('.policy-chapter-row').forEach(function (row, idx) {
                    row.querySelectorAll('input,textarea').forEach(function (input) {
                        if (!input.name) return;
                        input.name = input.name.replace(/chapters\[(\d+|__INDEX__)\]/, 'chapters[' + idx + ']');
                    });
                });
            }
            addBtn.addEventListener('click', function () {
                var n = listEl.querySelectorAll('.policy-chapter-row').length;
                var html = tmplEl.innerHTML.replaceAll('__INDEX__', String(n));
                var wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                listEl.appendChild(wrap.firstChild);
                renumber();
            });
            listEl.addEventListener('click', function (e) {
                var del = e.target.closest('.policy-chapter-remove');
                if (!del) return;
                if (!confirm('この章を削除しますか？（保存するまで反映されません）')) return;
                var row = del.closest('.policy-chapter-row');
                if (row) row.remove();
                renumber();
            });
        }

        var form = document.getElementById('policy-edit-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var nameInput = form.querySelector('input[name="updater_name"]');
                var unlock = form.querySelector('input[name="confirm_unlock"]');
                if (!nameInput || nameInput.value.trim() === '') {
                    (window.appToast || window.alert)('更新者名を入力してください。', 'error');
                    e.preventDefault();
                    return;
                }
                if (!unlock || !unlock.checked) {
                    (window.appToast || window.alert)('「ロックを解除して更新する」にチェックしてください。', 'error');
                    e.preventDefault();
                    return;
                }
                if (!confirm('更新者: ' + nameInput.value + ' として保存します。よろしいですか？')) {
                    e.preventDefault();
                }
            });
        }
    })();
</script>
@endpush

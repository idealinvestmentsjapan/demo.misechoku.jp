@extends('layouts.app-v2')

@section('title', '営業デモの準備')

@section('content')
<section class="bg-base text-text-main min-h-screen px-4 py-8 sm:px-6" data-theme="amethyst" aria-label="営業デモの準備">
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="space-y-3">
            <p class="text-accent-text font-bold text-sm">ミセチョク · 営業担当者用</p>
            <h1 class="text-2xl sm:text-3xl font-bold">見せたい場面を、すぐ用意。</h1>
            <p class="text-base leading-relaxed text-text-sub">店舗情報や候補者を一人ずつ入力する必要はありません。セットと場面を選べば、実演に必要な状態をまとめて用意できます。</p>
        </header>

        @if (session('message'))
            <div role="status" class="p-4 rounded-panel bg-accent/10 border border-line-accent/40 text-accent-text text-base">{{ session('message') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="p-4 rounded-panel border border-line-accent/40 bg-surface-from text-base">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <x-ui.card class="p-5 sm:p-6">
            <h2 class="text-xl font-bold mb-3">1. 自分のセットを選ぶ</h2>
            <p class="text-base text-text-sub mb-4">担当者ごとに別の番号を使ってください。同じ番号を使う人の実演状態は共有されます。</p>
            <form method="GET" action="{{ route('demo.sales') }}" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-40">
                    <label for="demo-slot" class="block text-sm font-bold mb-2">営業セット</label>
                    <select id="demo-slot" name="slot" class="w-full min-h-12 rounded-panel border border-line-accent/40 bg-base text-text-main px-4 text-base">
                        @for ($n = 1; $n <= 20; $n++)<option value="{{ $n }}" @selected($n === $slot)>セット {{ $n }}</option>@endfor
                    </select>
                </div>
                <x-ui.button type="submit" variant="outline" size="lg">この番号にする</x-ui.button>
            </form>
        </x-ui.card>

        <section aria-labelledby="scene-title" class="space-y-4">
            <h2 id="scene-title" class="text-xl font-bold">2. 見せたい場面を用意する</h2>
            <p class="text-base text-text-sub">下のボタンを押すと、セット{{ $slot }}の実演用トーク・採用・ボーナスの状態が選んだ場面に戻ります。</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($scenarios as $key => $scene)
                    <x-ui.card class="p-5 flex flex-col gap-3">
                        <h3 class="text-lg font-bold">{{ $scene['label'] }}</h3>
                        <p class="text-base leading-relaxed text-text-sub flex-1">{{ $scene['description'] }}</p>
                        <form method="POST" action="{{ route('demo.sales.prepare') }}" data-sales-demo-form>
                            @csrf
                            <input type="hidden" name="slot" value="{{ $slot }}">
                            <input type="hidden" name="scene" value="{{ $key }}">
                            <x-ui.button type="submit" size="lg" class="w-full">この場面を用意する</x-ui.button>
                        </form>
                    </x-ui.card>
                @endforeach
            </div>
        </section>

        <x-ui.card class="p-5 sm:p-6 space-y-4">
            <h2 class="text-xl font-bold">3. 実演画面を開く</h2>
            <p class="text-base text-text-sub">自動で実演用アカウントに切り替わります。ほかの画面を見せるときは、ブラウザの「戻る」でこの準備画面に戻ってください。</p>
            @if ($ready && isset($scenarios[$ready]))<p class="text-accent-text font-bold">この端末で最後に用意した場面：{{ $scenarios[$ready]['label'] }}</p>@endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach (['store' => 'お店の紹介を見せる', 'search' => '候補者を探す', 'profile' => 'あかりのプロフィール', 'talk' => 'あかりとのトーク', 'management' => '採用・入金管理', 'cast-talk' => 'あかり側の受領画面'] as $screen => $label)
                    <form method="POST" action="{{ route('demo.sales.enter') }}">
                        @csrf
                        <input type="hidden" name="slot" value="{{ $slot }}">
                        <input type="hidden" name="screen" value="{{ $screen }}">
                        <x-ui.button type="submit" variant="outline" size="lg" class="w-full">{{ $label }}</x-ui.button>
                    </form>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card class="p-5 sm:p-6 space-y-3">
            <h2 class="text-xl font-bold">これで用意できるもの</h2>
            <ul class="list-disc pl-5 space-y-2 text-base leading-relaxed">
                <li>店舗紹介・実演用画像・店長メッセージ・求人条件</li>
                <li>候補者3人のプロフィール・希望条件・ひとこと・性格タグ</li>
                <li>KEEP済みの候補者と、返信済みのトーク</li>
                <li>あかりの面談、採用後の請求、受領までの進捗（選んだ場面に応じて作成）</li>
            </ul>
            <p class="text-sm text-text-sub leading-relaxed">人物・お店・金額・審査・送金の進捗はすべて架空です。画像は実演用イラストです。データを用意する処理では、メール・LINE・プッシュ通知の送信や実際の振込は行いません。料金表と訪問先の登録情報は、別途用意してください。</p>
        </x-ui.card>
        <a class="inline-flex items-center min-h-12 text-accent-text underline" href="{{ route('login.demo') }}">デモログインへ戻る</a>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-sales-demo-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
        document.querySelectorAll('[data-sales-demo-form] button').forEach(function (button) { button.disabled = true; });
        const button = form.querySelector('button');
        button.textContent = '用意しています…';
    });
});
window.addEventListener('pageshow', function () {
    document.querySelectorAll('[data-sales-demo-form] button').forEach(function (button) {
        button.disabled = false;
        button.textContent = 'この場面を用意する';
    });
});
</script>
@endpush

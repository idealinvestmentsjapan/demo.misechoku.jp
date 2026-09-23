{{-- キャスト向けカードに表示する Tier チップ（DISCOVERY 用）
     Tier A（本日が候補日）→ 金色ピル + ⚡
     Tier B（オンライン中：直近ログイン & 位置あり）→ 緑ピル + ●

     引数（$tierChipItem として渡す配列）:
       - availability_active: bool                          Tier A 判定（本日が候補日）
       - availability_date_labels: list<string>|null        候補日ラベル（"本日"・"9/28" など）
       - is_online_now: bool                                Tier B 判定
       - distance_label: string|null                        "3.4km" など（あれば末尾に付与）

     チップは A/B いずれも該当しない場合、候補日があれば日付チップのみ表示 --}}
@php
    $tc = $tierChipItem ?? [];
    $tcActive   = !empty($tc['availability_active']);
    $tcOnline   = !empty($tc['is_online_now']);
    $tcDates    = array_values(array_filter((array) ($tc['availability_date_labels'] ?? [])));
    $tcDistance = (string) ($tc['distance_label'] ?? '');
    $tcDatesLabel = implode('・', array_slice($tcDates, 0, 3));
@endphp
@if($tcActive)
    <div class="tier-chip-row">
        <span class="tier-chip tier-chip--now">
            <i class="fas fa-bolt" aria-hidden="true"></i>
            本日入れる@if($tcDistance !== '')・{{ $tcDistance }}@endif
        </span>
    </div>
@elseif($tcDates !== [])
    <div class="tier-chip-row">
        <span class="tier-chip tier-chip--online">
            <i class="fas fa-calendar-days" aria-hidden="true"></i>
            {{ $tcDatesLabel }} 入れます
        </span>
    </div>
@elseif($tcOnline)
    <div class="tier-chip-row">
        <span class="tier-chip tier-chip--online">
            <i class="fas fa-circle" aria-hidden="true"></i>
            オンライン中@if($tcDistance !== '')・{{ $tcDistance }}@endif
        </span>
    </div>
@endif

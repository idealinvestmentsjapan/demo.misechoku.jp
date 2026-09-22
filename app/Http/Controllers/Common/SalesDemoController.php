<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Cast;
use App\Models\ShopManager;
use App\Services\SalesDemoService;
use App\Support\SalesDemoAccess;
use App\Support\SalesDemoFixture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class SalesDemoController extends Controller
{
    public function show(Request $request): View
    {
        $this->checkAccess($request);
        $request->validate(['slot' => ['nullable', 'integer', 'between:1,20']]);
        $slot = (int) $request->query('slot', $request->session()->get('sales_demo_slot', 1));

        return view('common.sales-demo', [
            'slot' => $slot, 'ids' => SalesDemoFixture::ids($slot), 'scenarios' => SalesDemoFixture::SCENARIOS,
            'ready' => $request->session()->get('sales_demo_ready.' . $slot),
        ]);
    }

    public function prepare(Request $request, SalesDemoService $service): RedirectResponse
    {
        $this->checkAccess($request);
        $data = $request->validate(['slot' => ['required', 'integer', 'between:1,20'],
            'scene' => ['required', 'in:' . implode(',', array_keys(SalesDemoFixture::SCENARIOS))]]);
        try {
            $service->prepare((int) $data['slot'], $data['scene']);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('demo.sales', ['slot' => $data['slot']])->withErrors([
                'prepare' => '準備できませんでした。別の担当者が準備中の場合は少し待ってください。続く場合は開発担当者へ「営業デモの準備エラー」とお伝えください。',
            ]);
        }
        $request->session()->put('sales_demo_slot', (int) $data['slot']);
        $request->session()->put('sales_demo_ready.' . $data['slot'], $data['scene']);

        return redirect()->route('demo.sales', ['slot' => $data['slot']])
            ->with('message', 'セット' . $data['slot'] . 'を「' . SalesDemoFixture::SCENARIOS[$data['scene']]['label'] . '」にしました。下のボタンから実演を始められます。');
    }

    public function enter(Request $request): RedirectResponse
    {
        $this->checkAccess($request);
        $data = $request->validate(['slot' => ['required', 'integer', 'between:1,20'],
            'screen' => ['required', 'in:search,profile,talk,management,store,cast-talk']]);
        $ids = SalesDemoFixture::ids((int) $data['slot']);
        $isCast = in_array($data['screen'], ['store', 'cast-talk'], true);
        $id = $isCast ? $ids['casts'][0] : $ids['manager'];
        $user = ($isCast ? Cast::query() : ShopManager::query())->where('email', SalesDemoFixture::email($id))->where('status', 1)->find($id);
        if (!$user || (!$isCast && $user->shop_id !== $ids['shop'])) {
            return redirect()->route('demo.sales', ['slot' => $data['slot']])->withErrors(['prepare' => '先に、見せたい場面の「この場面を用意する」を押してください。']);
        }
        foreach (['member', 'shop', 'admin'] as $guard) {
            auth()->guard($guard)->logout();
        }
        auth()->guard($isCast ? 'member' : 'shop')->login($user);
        $request->session()->regenerate();
        $request->session()->put('sales_demo_slot', (int) $data['slot']);
        $target = match ($data['screen']) {
            'profile' => route('shop.castprofileview.show', ['id' => $ids['casts'][0]]),
            'talk' => route('shop.talk.room', ['id' => $ids['casts'][0]]),
            'management' => route('shop.mypage.management'),
            'store' => route('cast.shopprofile.show', ['id' => $ids['shop']]),
            'cast-talk' => route('cast.talk.room', ['id' => $ids['shop']]),
            default => route('shop.search.index', ['keyword' => sprintf('デモ%02d・', $data['slot'])]),
        };
        return redirect()->to($target)->with('message', '営業セット' . $data['slot'] . 'の実演用アカウントで開いています。');
    }

    private function checkAccess(Request $request): void
    {
        abort_unless(SalesDemoAccess::enabled($request->getHost()), 404);
    }
}

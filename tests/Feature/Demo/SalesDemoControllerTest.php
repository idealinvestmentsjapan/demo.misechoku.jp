<?php

namespace Tests\Feature\Demo;

use App\Http\Middleware\InjectHeaderBadges;
use App\Services\CharacterGuideService;
use App\Services\SalesDemoService;
use App\Support\SalesDemoFixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/** Database access is explicitly forbidden; preparation is replaced by a mock. */
final class SalesDemoControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true, 'demo.sales_enabled' => true, 'app.url' => 'http://localhost',
            'session.driver' => 'array', 'cache.default' => 'array']);
        $this->withoutMiddleware(InjectHeaderBadges::class);
        DB::shouldReceive('connection')->never();
        $guide = Mockery::mock(CharacterGuideService::class);
        $guide->shouldReceive('getForRoute')->andReturn(['enabled' => false, 'message' => '']);
        $this->app->instance(CharacterGuideService::class, $guide);
    }

    public function test_page_contains_four_preparation_actions_and_bounded_slots(): void
    {
        $response = $this->get('/demo/sales?slot=2');
        $response->assertOk()->assertSee('営業セット')->assertSee('本人が受け取った場面');
        $this->assertSame(4, substr_count($response->getContent(), 'data-sales-demo-form>'));
        if ($previewPath = getenv('SALES_DEMO_PREVIEW_PATH')) {
            file_put_contents($previewPath, $response->getContent());
        }
    }

    public function test_disabled_flags_and_unknown_hosts_are_hidden_before_database_access(): void
    {
        config(['demo.sales_enabled' => false]);
        $this->get('/demo/sales')->assertNotFound();
        config(['demo.sales_enabled' => true]);
        $this->get('http://misechoku.jp/demo/sales')->assertNotFound();
    }

    public function test_invalid_inputs_do_not_call_the_preparation_service(): void
    {
        $service = Mockery::mock(SalesDemoService::class);
        $service->shouldNotReceive('prepare');
        $this->app->instance(SalesDemoService::class, $service);
        $this->postJson('/demo/sales/prepare', ['slot' => 21, 'scene' => 'start'])->assertUnprocessable();
        $this->postJson('/demo/sales/prepare', ['slot' => 1, 'scene' => 'erase'])->assertUnprocessable();
        $this->postJson('/demo/sales/enter', ['slot' => 1, 'screen' => 'admin'])->assertUnprocessable();
    }

    public function test_preparation_uses_the_requested_slot_and_returns_to_the_tablet_page(): void
    {
        $service = Mockery::mock(SalesDemoService::class);
        $service->shouldReceive('prepare')->once()->with(2, 'interview')
            ->andReturn(SalesDemoFixture::build(2, 'interview', CarbonImmutable::now()));
        $this->app->instance(SalesDemoService::class, $service);
        $this->post('/demo/sales/prepare', ['slot' => 2, 'scene' => 'interview'])
            ->assertRedirect('/demo/sales?slot=2')->assertSessionHas('sales_demo_slot', 2)
            ->assertSessionHas('sales_demo_ready.2', 'interview');
    }
}

<?php

namespace Tests\Feature\UiUx;

use App\Services\TalkDeliveryService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** メモリ上のキャッシュと送信コールバックを使用。DB・fileストアは操作しない。 */
class TalkDeliveryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Facade::setFacadeApplication(new Application(dirname(__DIR__, 3)));
        Date::swap(new DateFactory());
        Cache::swap(new class {
            private Repository $cache;
            public function __construct() { $this->cache = new Repository(new ArrayStore()); }
            public function store(string $name): Repository { return $this->cache; }
        });
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_retry_returns_original_result_without_sending_again(): void
    {
        $service = new TalkDeliveryService();
        $count = 0;
        $send = function () use (&$count) { return ['message_id' => ++$count]; };
        $first = $service->once('cast:shop:1', 'same-request', '本文', $send);
        $retry = $service->once('cast:shop:1', 'same-request', '本文', $send);
        $this->assertSame(1, $count);
        $this->assertSame($first['data'], $retry['data']);
        $this->assertTrue($first['created']);
        $this->assertFalse($retry['created']);
    }

    public function test_same_text_with_new_request_or_different_actor_is_not_dropped(): void
    {
        $service = new TalkDeliveryService();
        $count = 0;
        $send = function () use (&$count) { return ['message_id' => ++$count]; };
        $service->once('cast:shop:1', 'first', '同じ本文', $send);
        $service->once('cast:shop:1', 'second', '同じ本文', $send);
        $service->once('cast:other:1', 'first', '同じ本文', $send);
        $this->assertSame(3, $count);
    }

    public function test_request_id_cannot_be_reused_with_changed_content(): void
    {
        $service = new TalkDeliveryService();
        $service->once('cast:shop:1', 'first', '元の本文', fn () => ['message_id' => 1]);
        $this->expectException(HttpException::class);
        $service->once('cast:shop:1', 'first', '変更した本文', fn () => ['message_id' => 2]);
    }
}

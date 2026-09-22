<?php

namespace Tests\Feature\UiUx;

use App\Http\Controllers\Common\SearchController;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

class SearchNavigationTest extends TestCase
{
    private function controller(): SearchController
    {
        return new class extends SearchController {
            public function defaults(Request $request, array $saved): void
            {
                $this->applySavedSearchDefaults($request, $saved, ['age_min' => 'age_min']);
            }

            public function paginate(array $items, Request $request): LengthAwarePaginator
            {
                return $this->paginateResults($items, $request);
            }
        };
    }

    public function test_saved_defaults_do_not_override_explicit_values_or_cleared_filters(): void
    {
        $controller = $this->controller();
        $request = Request::create('/shop/search');
        $controller->defaults($request, ['age_min' => 20]);
        $this->assertSame(20, $request->query('age_min'));
        $request = Request::create('/shop/search?age_min=25');
        $controller->defaults($request, ['age_min' => 20]);
        $this->assertSame('25', $request->query('age_min'));
        $request = Request::create('/shop/search?filters_applied=1');
        $controller->defaults($request, ['age_min' => 20]);
        $this->assertNull($request->query('age_min'));
    }

    public function test_pagination_keeps_filters_and_returns_only_requested_twenty_items(): void
    {
        $result = $this->controller()->paginate(range(1, 45), Request::create('/cast/search/list?page=2&keyword=銀座&hourly_wage=5000'));
        $this->assertSame(range(21, 40), $result->items());
        $this->assertSame(45, $result->total());
        parse_str(parse_url($result->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('3', $query['page']);
        $this->assertSame('銀座', $query['keyword']);
        $this->assertSame('5000', $query['hourly_wage']);
    }

    public function test_out_of_range_page_is_clamped_even_for_empty_results(): void
    {
        $controller = $this->controller();
        $result = $controller->paginate(range(1, 23), Request::create('/shop/search?page=999'));
        $this->assertSame([21, 22, 23], $result->items());
        $this->assertSame(2, $result->currentPage());
        $empty = $controller->paginate([], Request::create('/shop/search?page=-2'));
        $this->assertSame(1, $empty->currentPage());
        $this->assertSame(0, $empty->total());
    }
}

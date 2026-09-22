<?php

namespace Tests\Feature\UiUx;

use App\Services\SearchFilterService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** DBを接続・作成せず、検索条件の判定だけを検証する。 */
class SearchFilterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_age_boundary_and_unknown_birthday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-13'));
        $service = new SearchFilterService();
        $filters = ['age_min' => 20, 'age_max' => 25];
        $this->assertTrue($service->matchesCast((object) ['birthday' => '2001-09-13'], $filters, [], []));
        $this->assertFalse($service->matchesCast((object) ['birthday' => '2000-09-13'], $filters, [], []));
        $this->assertFalse($service->matchesCast((object) ['birthday' => null], $filters, [], []));
    }

    public function test_tags_use_any_match_within_category_and_all_categories(): void
    {
        $service = new SearchFilterService();
        $filters = ['looks_tag_ids' => [1, 2], 'personality_tag_ids' => [3]];
        $this->assertTrue($service->matchesCast((object) [], $filters, ['looks' => [2], 'personality' => [3]], []));
        $this->assertFalse($service->matchesCast((object) [], $filters, ['looks' => [2], 'personality' => [4]], []));
    }

    public function test_availability_filters_apply_to_selected_period_and_frequency(): void
    {
        $service = new SearchFilterService();
        $filters = ['shift_frequency' => '週2回出勤', 'work_periods' => ['day', 'night']];
        $this->assertTrue($service->matchesAvailability($filters, ['shift_frequency' => '週2回出勤', 'work_periods' => ['night']]));
        $this->assertFalse($service->matchesAvailability($filters, ['shift_frequency' => '週1回出勤', 'work_periods' => ['night']]));
        $this->assertFalse($service->matchesAvailability($filters, []));
        $this->assertTrue($service->matchesAvailability([], []));
    }

    public function test_unknown_experience_is_not_mistaken_for_inexperience(): void
    {
        $service = new SearchFilterService();
        $this->assertFalse($service->matchesCast((object) ['exp' => null], ['night_work_exp' => 'none'], [], []));
        $this->assertTrue($service->matchesCast((object) ['exp' => 0], ['night_work_exp' => 'none'], [], []));
        $this->assertFalse($service->matchesCast((object) ['exp' => 1], ['night_work_exp' => 'none'], [], []));
    }
}

<?php

namespace Tests\Feature\Cast;

use App\Models\Cast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cast candidate-date availability declaration (up to 5 dates, 30 days ahead).
 */
class AvailabilityDeclarationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function cast_can_declare_candidate_dates(): void
    {
        $cast = $this->makeCast('c77770001');
        DB::table('cast_profiles')->insert(['cast_id' => 'c77770001', 'created_at' => now(), 'updated_at' => now()]);

        $dates = [
            now()->toDateString(),
            now()->addDays(2)->toDateString(),
            now()->addDays(5)->toDateString(),
        ];

        $this->actingAs($cast, 'member')
            ->postJson(route('cast.mypage.availability.declare'), ['dates' => $dates])
            ->assertOk()
            ->assertJson(['success' => true, 'dates' => $dates]);

        $stored = DB::table('availability_dates')
            ->where('owner_type', 'cast')
            ->where('owner_id', 'c77770001')
            ->orderBy('available_on')
            ->pluck('available_on')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();
        $this->assertSame($dates, $stored);
    }

    /** @test */
    public function declaring_again_replaces_the_whole_set(): void
    {
        $cast = $this->makeCast('c77770002');
        DB::table('cast_profiles')->insert(['cast_id' => 'c77770002', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($cast, 'member')
            ->postJson(route('cast.mypage.availability.declare'), ['dates' => [now()->toDateString()]])
            ->assertOk();

        $newDates = [now()->addDay()->toDateString(), now()->addDays(3)->toDateString()];
        $this->actingAs($cast, 'member')
            ->postJson(route('cast.mypage.availability.declare'), ['dates' => $newDates])
            ->assertOk();

        $stored = DB::table('availability_dates')
            ->where('owner_type', 'cast')
            ->where('owner_id', 'c77770002')
            ->orderBy('available_on')
            ->pluck('available_on')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();
        $this->assertSame($newDates, $stored);
    }

    /** @test */
    public function more_than_five_dates_are_rejected(): void
    {
        $cast = $this->makeCast('c77770003');
        DB::table('cast_profiles')->insert(['cast_id' => 'c77770003', 'created_at' => now(), 'updated_at' => now()]);

        $dates = collect(range(0, 5))->map(fn ($i) => now()->addDays($i)->toDateString())->all();

        $this->actingAs($cast, 'member')
            ->postJson(route('cast.mypage.availability.declare'), ['dates' => $dates])
            ->assertStatus(422);
    }

    /** @test */
    public function past_dates_are_rejected(): void
    {
        $cast = $this->makeCast('c77770004');
        DB::table('cast_profiles')->insert(['cast_id' => 'c77770004', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($cast, 'member')
            ->postJson(route('cast.mypage.availability.declare'), ['dates' => [now()->subDay()->toDateString()]])
            ->assertStatus(422);
    }

    /** @test */
    public function dates_beyond_30_days_are_rejected(): void
    {
        $cast = $this->makeCast('c77770005');
        DB::table('cast_profiles')->insert(['cast_id' => 'c77770005', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($cast, 'member')
            ->postJson(route('cast.mypage.availability.declare'), ['dates' => [now()->addDays(31)->toDateString()]])
            ->assertStatus(422);
    }

    /** @test */
    public function cast_can_clear_availability(): void
    {
        $cast = $this->makeCast('c77770006');
        DB::table('cast_profiles')->insert(['cast_id' => 'c77770006', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('availability_dates')->insert([
            'owner_type' => 'cast',
            'owner_id' => 'c77770006',
            'available_on' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($cast, 'member')
            ->deleteJson(route('cast.mypage.availability.clear'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, DB::table('availability_dates')
            ->where('owner_type', 'cast')
            ->where('owner_id', 'c77770006')
            ->count());
    }

    private function makeCast(string $id): Cast
    {
        DB::table('casts')->insert([
            'id' => $id,
            'email' => $id . '@test.example',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return Cast::findOrFail($id);
    }
}

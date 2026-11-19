<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\User;
use App\Services\DashboardStatistics;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlDashboardDistributionTest extends TestCase
{
    public function test_site_distribution_runs_on_mysql_843_with_only_full_group_by_and_counts_each_asset_once(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Ce contrôle en lecture seule nécessite une connexion MySQL.');
        }

        $server = $connection->selectOne('SELECT VERSION() AS version, @@SESSION.sql_mode AS sql_mode');

        $this->assertStringStartsWith('8.4.3', $server->version);
        $this->assertContains('ONLY_FULL_GROUP_BY', explode(',', $server->sql_mode));

        $viewer = new User;
        $viewer->role = UserRole::Viewer;

        $statistics = app(DashboardStatistics::class);
        $distribution = $statistics->forUser($viewer, [])['sitesChart'];
        $expected = [];

        Asset::query()
            ->with('location.parent.parent')
            ->chunkById(500, function ($assets) use (&$expected): void {
                foreach ($assets as $asset) {
                    $location = $asset->location;
                    $site = match (true) {
                        $location?->type?->isSite() => $location,
                        $location?->parent?->type?->isSite() => $location->parent,
                        $location?->parent?->type?->isBuilding() => $location->parent->parent,
                        default => null,
                    };
                    $key = $site ? (string) $site->getKey() : 'unlocated';
                    $expected[$key] = ($expected[$key] ?? 0) + 1;
                }
            });

        $actual = $distribution->mapWithKeys(fn (object $row): array => [
            $row->filter_value === null ? 'unlocated' : (string) $row->filter_value => (int) $row->total,
        ])->all();

        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
        $this->assertSame(Asset::query()->count(), (int) $distribution->sum('total'));

        $sample = Asset::query()->with(['category.parent', 'location.parent.parent'])->first();

        if ($sample) {
            $categoryId = $sample->category->parent_id ?? $sample->category_id;
            $location = $sample->location;
            $site = match (true) {
                $location?->type?->isSite() => $location,
                $location?->parent?->type?->isSite() => $location->parent,
                $location?->parent?->type?->isBuilding() => $location->parent->parent,
                default => null,
            };
            $filters = [
                'from' => $sample->created_at->toDateString(),
                'to' => $sample->created_at->toDateString(),
                'category_id' => $categoryId,
                'status' => $sample->status->value,
            ];

            if ($sample->category->parent_id !== null) {
                $filters['subcategory_id'] = $sample->category_id;
            }

            if ($site) {
                $filters['site_id'] = $site->getKey();
            }

            $filtered = $statistics->forUser($viewer, $filters);

            $this->assertSame(
                $filtered['metrics']['assets_total'],
                (int) $filtered['sitesChart']->sum('total'),
            );
        }
    }
}

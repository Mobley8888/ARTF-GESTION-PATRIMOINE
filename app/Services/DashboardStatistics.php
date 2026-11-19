<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetLocationHistory;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStatistics
{
    public function forUser(User $user, array $filters): array
    {
        $assetQuery = $this->filteredAssets($filters);
        $summary = (clone $assetQuery)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active', [AssetStatus::InService->value])
            ->first();

        $data = [
            'metrics' => [
                'assets_total' => (int) ($summary->total ?? 0),
                'assets_active' => (int) ($summary->active ?? 0),
                'assets_inactive' => (int) ($summary->total ?? 0) - (int) ($summary->active ?? 0),
                'assets_recent' => (clone $assetQuery)
                    ->where('assets.created_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'categoriesChart' => $this->categoryDistribution($filters),
            'sitesChart' => $this->siteDistribution($filters),
            'locationsChart' => $this->locationDistribution($filters),
            'statusesChart' => $this->statusDistribution($filters),
            'recentAssets' => (clone $assetQuery)
                ->with(['category.parent', 'location.parent.parent'])
                ->orderByDesc('assets.created_at')
                ->limit(6)
                ->get(),
            'statusLabels' => $this->statusLabels(),
            'conditionLabels' => $this->conditionLabels(),
        ];

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator, UserRole::AssetManager], true)) {
            $data += [
                'recentAssignments' => $this->recentAssignments($filters),
                'recentCompletedAssignments' => $this->recentCompletedAssignments($filters),
                'recentMovements' => $this->recentMovements($filters),
                'recentEdits' => $this->recentEdits($filters),
            ];
        }

        if ($user->role === UserRole::AssetManager) {
            $data['assignmentMetrics'] = $this->assignmentMetrics($assetQuery);
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator], true)) {
            $data['locationMetrics'] = $this->locationMetrics();
        }

        if ($user->role === UserRole::SuperAdmin) {
            $data['referenceMetrics'] = $this->referenceMetrics();
            $data['userMetrics'] = $this->userMetrics();
            $data['conditionsChart'] = $this->conditionDistribution($filters);
            $data['monthlyMetrics'] = $this->monthlyMetrics($filters);
        }

        return $data;
    }

    private function filteredAssets(array $filters): Builder
    {
        return $this->applyAssetFilters(Asset::query(), $filters);
    }

    private function applyAssetFilters(Builder $query, array $filters, bool $includeRegistrationPeriod = true): Builder
    {
        $query
            ->when($filters['site_id'] ?? null, fn (Builder $builder, string $siteId) => $builder->atSite($siteId))
            ->when($filters['category_id'] ?? null, function (Builder $builder, string $categoryId): void {
                $builder->where(function (Builder $categoryQuery) use ($categoryId): void {
                    $categoryQuery->where('assets.category_id', $categoryId)
                        ->orWhereHas('category', fn (Builder $category) => $category->where('parent_id', $categoryId));
                });
            })
            ->when(
                $filters['subcategory_id'] ?? null,
                fn (Builder $builder, string $subcategoryId) => $builder->where('assets.category_id', $subcategoryId),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $builder, string $status) => $builder->where('assets.status', $status),
            );

        if ($includeRegistrationPeriod && ($filters['from'] ?? null)) {
            $query->where('assets.created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if ($includeRegistrationPeriod && ($filters['to'] ?? null)) {
            $query->where('assets.created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query;
    }

    private function categoryDistribution(array $filters): Collection
    {
        return $this->filteredAssets($filters)
            ->join('categories as asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->leftJoin('categories as category_parents', 'asset_categories.parent_id', '=', 'category_parents.id')
            ->select([
                'asset_categories.id as filter_value',
                'asset_categories.name as label',
                'category_parents.name as parent_label',
            ])
            ->selectRaw('COUNT(assets.id) AS total')
            ->groupBy('asset_categories.id', 'asset_categories.name', 'category_parents.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function siteDistribution(array $filters): Collection
    {
        $siteId = "CASE
            WHEN asset_locations.type = 'site' THEN asset_locations.id
            WHEN parent_locations.type = 'site' THEN parent_locations.id
            ELSE root_locations.id
        END";
        $siteName = "CASE
            WHEN asset_locations.type = 'site' THEN asset_locations.name
            WHEN parent_locations.type = 'site' THEN parent_locations.name
            ELSE root_locations.name
        END";

        $assetsBySite = $this->filteredAssets($filters)
            ->leftJoin('locations as asset_locations', 'assets.location_id', '=', 'asset_locations.id')
            ->leftJoin('locations as parent_locations', 'asset_locations.parent_id', '=', 'parent_locations.id')
            ->leftJoin('locations as root_locations', 'parent_locations.parent_id', '=', 'root_locations.id')
            ->selectRaw("$siteId AS filter_value")
            ->selectRaw("COALESCE($siteName, 'Non localisé') AS label")
            ->addSelect('assets.id as asset_id');

        return DB::query()
            ->fromSub($assetsBySite->toBase(), 'assets_by_site')
            ->select('filter_value', 'label')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('filter_value', 'label')
            ->orderByDesc('total')
            ->get();
    }

    private function locationDistribution(array $filters): Collection
    {
        return $this->filteredAssets($filters)
            ->leftJoin('locations as asset_locations', 'assets.location_id', '=', 'asset_locations.id')
            ->selectRaw('asset_locations.id AS filter_value')
            ->selectRaw("COALESCE(asset_locations.name, 'Non localisé') AS label")
            ->selectRaw("COALESCE(asset_locations.code, '') AS detail")
            ->selectRaw('COUNT(assets.id) AS total')
            ->groupBy('asset_locations.id', 'asset_locations.name', 'asset_locations.code')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function statusDistribution(array $filters): Collection
    {
        return $this->filteredAssets($filters)
            ->select('assets.status as filter_value')
            ->selectRaw('COUNT(assets.id) AS total')
            ->groupBy('assets.status')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): object => (object) [
                'filter_value' => $row->filter_value,
                'label' => $this->statusLabels()[$row->filter_value] ?? $row->filter_value,
                'detail' => '',
                'total' => (int) $row->total,
            ]);
    }

    private function conditionDistribution(array $filters): Collection
    {
        return $this->filteredAssets($filters)
            ->whereNotNull('assets.condition')
            ->select('assets.condition as filter_value')
            ->selectRaw('COUNT(assets.id) AS total')
            ->groupBy('assets.condition')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): object => (object) [
                'filter_value' => $row->filter_value,
                'label' => $this->conditionLabels()[$row->filter_value] ?? $row->filter_value,
                'detail' => '',
                'total' => (int) $row->total,
            ]);
    }

    private function recentAssignments(array $filters): Collection
    {
        $query = AssetAssignment::query()
            ->with(['asset.category', 'agent', 'service', 'assigner'])
            ->whereHas('asset', fn (Builder $asset) => $this->applyAssetFilters($asset, $filters, false));

        $this->applyPeriod($query, 'assigned_at', $filters);

        return $query->orderByDesc('assigned_at')->limit(6)->get();
    }

    private function recentCompletedAssignments(array $filters): Collection
    {
        $query = AssetAssignment::query()
            ->with(['asset', 'agent', 'service', 'ender'])
            ->whereNotNull('ended_at')
            ->whereHas('asset', fn (Builder $asset) => $this->applyAssetFilters($asset, $filters, false));

        $this->applyPeriod($query, 'ended_at', $filters);

        return $query->orderByDesc('ended_at')->limit(6)->get();
    }

    private function recentMovements(array $filters): Collection
    {
        $query = AssetLocationHistory::query()
            ->with(['asset', 'fromLocation', 'toLocation', 'recorder'])
            ->whereHas('asset', fn (Builder $asset) => $this->applyAssetFilters($asset, $filters, false));

        $this->applyPeriod($query, 'moved_at', $filters);

        return $query->orderByDesc('moved_at')->limit(6)->get();
    }

    private function recentEdits(array $filters): Collection
    {
        $query = $this->applyAssetFilters(Asset::query(), $filters, false)
            ->whereNotNull('assets.updated_by')
            ->with('updater');

        $this->applyPeriod($query, 'assets.updated_at', $filters);

        return $query->orderByDesc('assets.updated_at')->limit(5)->get();
    }

    private function assignmentMetrics(Builder $assetQuery): array
    {
        return [
            'assets_assigned' => (clone $assetQuery)->whereHas(
                'assignments',
                fn (Builder $assignment) => $assignment->whereNull('ended_at'),
            )->count(),
            'assets_available' => (clone $assetQuery)
                ->where('assets.status', AssetStatus::InService->value)
                ->whereDoesntHave(
                    'assignments',
                    fn (Builder $assignment) => $assignment->whereNull('ended_at'),
                )
                ->count(),
        ];
    }

    private function applyPeriod(Builder $query, string $column, array $filters): void
    {
        if ($filters['from'] ?? null) {
            $query->where($column, '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if ($filters['to'] ?? null) {
            $query->where($column, '<=', Carbon::parse($filters['to'])->endOfDay());
        }
    }

    private function locationMetrics(): array
    {
        $row = Location::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->first();

        return [
            'locations_active' => (int) ($row->active ?? 0),
            'locations_inactive' => (int) ($row->total ?? 0) - (int) ($row->active ?? 0),
        ];
    }

    private function referenceMetrics(): array
    {
        $categories = Category::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END) AS primary_total')
            ->selectRaw('SUM(CASE WHEN parent_id IS NOT NULL THEN 1 ELSE 0 END) AS subcategory_total')
            ->first();
        $locationCounts = Location::query()
            ->whereIn('type', array_merge(LocationType::buildingValues(), LocationType::localValues(), [LocationType::Site->value]))
            ->select('type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'categories' => (int) ($categories->primary_total ?? 0),
            'subcategories' => (int) ($categories->subcategory_total ?? 0),
            'sites' => (int) $locationCounts->get(LocationType::Site->value, 0),
            'buildings' => (int) $locationCounts->get(LocationType::Building->value, 0)
                + (int) $locationCounts->get(LocationType::Batiment->value, 0),
            'locals' => (int) $locationCounts->get(LocationType::Room->value, 0)
                + (int) $locationCounts->get(LocationType::Local->value, 0),
        ];
    }

    private function userMetrics(): array
    {
        $users = User::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->first();

        return [
            'users_total' => (int) ($users->total ?? 0),
            'users_active' => (int) ($users->active ?? 0),
            'users_inactive' => (int) ($users->total ?? 0) - (int) ($users->active ?? 0),
        ];
    }

    private function monthlyMetrics(array $filters): Collection
    {
        $periodEnd = isset($filters['to'])
            ? Carbon::parse($filters['to'])->endOfMonth()
            : now()->endOfMonth();
        $periodStart = isset($filters['from'])
            ? Carbon::parse($filters['from'])->startOfMonth()
            : $periodEnd->copy()->subMonths(5)->startOfMonth();
        $queryStart = isset($filters['from'])
            ? Carbon::parse($filters['from'])->startOfDay()
            : $periodStart->copy()->startOfDay();
        $queryEnd = isset($filters['to'])
            ? Carbon::parse($filters['to'])->endOfDay()
            : now()->endOfDay();
        $assetCounts = $this->monthlyCounts(
            $this->applyAssetFilters(Asset::query(), $filters, false)
                ->whereBetween('assets.created_at', [$queryStart, $queryEnd]),
            'assets.created_at',
        );
        $assignmentCounts = $this->monthlyCounts(
            AssetAssignment::query()
                ->whereHas('asset', fn (Builder $asset) => $this->applyAssetFilters($asset, $filters, false))
                ->whereBetween('asset_assignments.assigned_at', [$queryStart, $queryEnd]),
            'asset_assignments.assigned_at',
        );
        $movementCounts = $this->monthlyCounts(
            AssetLocationHistory::query()
                ->whereHas('asset', fn (Builder $asset) => $this->applyAssetFilters($asset, $filters, false))
                ->whereBetween('asset_location_histories.moved_at', [$queryStart, $queryEnd]),
            'asset_location_histories.moved_at',
        );

        $months = collect();
        $cursor = $periodStart->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($periodEnd)) {
            $key = $cursor->format('Y-m');
            $months->push([
                'key' => $key,
                'label' => $cursor->format('m/Y'),
                'assets' => (int) $assetCounts->get($key, 0),
                'assignments' => (int) $assignmentCounts->get($key, 0),
                'movements' => (int) $movementCounts->get($key, 0),
            ]);
            $cursor->addMonth();
        }

        return $months;
    }

    private function monthlyCounts(Builder $query, string $column): Collection
    {
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', $column)",
            'mysql' => "DATE_FORMAT($column, '%Y-%m')",
            default => throw new \LogicException('Le tableau de bord prend en charge les connexions SQLite et MySQL.'),
        };

        return $query
            ->selectRaw("$monthExpression AS month")
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw($monthExpression)
            ->pluck('total', 'month');
    }

    private function statusLabels(): array
    {
        return [
            AssetStatus::InService->value => 'En service',
            AssetStatus::UnderMaintenance->value => 'En maintenance',
            AssetStatus::Retired->value => 'Retiré',
            AssetStatus::Disposed->value => 'Sorti du patrimoine',
            AssetStatus::Lost->value => 'Perdu',
        ];
    }

    private function conditionLabels(): array
    {
        return [
            AssetCondition::New->value => 'Neuf',
            AssetCondition::Good->value => 'Bon',
            AssetCondition::Fair->value => 'Moyen',
            AssetCondition::Poor->value => 'Mauvais',
            AssetCondition::Unserviceable->value => 'Hors service',
        ];
    }
}

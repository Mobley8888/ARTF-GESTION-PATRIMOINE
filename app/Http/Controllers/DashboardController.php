<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Models\Category;
use App\Models\Location;
use App\Services\DashboardStatistics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStatistics $statistics): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'before_or_equal:today'],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('type', LocationType::Site->value)
                    ->whereNull('parent_id'),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
            ],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
        ]);

        if (
            filled($filters['from'] ?? null)
            && filled($filters['to'] ?? null)
            && $filters['to'] < $filters['from']
        ) {
            throw ValidationException::withMessages([
                'to' => 'La date de fin doit être égale ou postérieure à la date de début.',
            ]);
        }

        if (filled($filters['subcategory_id'] ?? null)) {
            $belongsToSelectedCategory = filled($filters['category_id'] ?? null)
                && Category::query()
                    ->whereKey($filters['subcategory_id'])
                    ->where('parent_id', $filters['category_id'])
                    ->exists();

            if (! $belongsToSelectedCategory) {
                throw ValidationException::withMessages([
                    'subcategory_id' => 'La sous-catégorie doit appartenir à la catégorie principale sélectionnée.',
                ]);
            }
        }

        $categoryRoots = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        $selectedSubcategories = $categoryRoots
            ->firstWhere('id', (int) ($filters['category_id'] ?? 0))
            ?->children
            ?? collect();

        return view('admin.dashboard', [
            'role' => $request->user()->role,
            'filters' => $filters,
            'sites' => Location::query()
                ->where('type', LocationType::Site->value)
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'searchLocations' => Location::query()
                ->with('parent.parent')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'type', 'code', 'name', 'is_active']),
            'categoryRoots' => $categoryRoots,
            'subcategories' => $selectedSubcategories,
            ...$statistics->forUser($request->user(), $filters),
        ]);
    }
}

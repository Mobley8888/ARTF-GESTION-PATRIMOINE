<?php

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(LocationType::cases(), 'value'))],
            'active' => ['nullable', 'in:active,inactive'],
        ]);
        $filterType = isset($filters['type']) ? LocationType::from($filters['type'])->canonical() : null;
        $filters['type'] = $filterType?->value;

        $locations = Location::query()
            ->with('parent.parent')
            ->withCount([
                'children',
                'assets' => fn (Builder $query) => $query->withTrashed(),
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filterType, fn (Builder $query, LocationType $type) => $query->whereIn('type', $type->familyValues()))
            ->when($filters['active'] ?? null, fn (Builder $query, string $active) => $query->where('is_active', $active === 'active'))
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.references.locations.index', [
            'locations' => $locations,
            'filters' => $filters,
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.references.locations.create', [
            'location' => null,
            'parents' => $this->activeParents(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('references.locations.show', $location)
            ->with('status', 'Le lieu a été créé et activé.');
    }

    public function show(Location $location): View
    {
        $location->load('parent.parent')->loadCount([
            'children',
            'assets' => fn (Builder $query) => $query->withTrashed(),
            'sourceHistory',
            'destinationHistory',
        ]);

        return view('admin.references.locations.show', [
            'location' => $location,
        ]);
    }

    public function edit(Location $location): View
    {
        return view('admin.references.locations.edit', [
            'location' => $location,
            'parents' => $this->activeParents(),
            'typeOptions' => $this->typeOptions($location),
        ]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($location, $data): void {
            $target = Location::query()->lockForUpdate()->findOrFail($location->getKey());
            $newParentId = $data['parent_id'] ?? null;
            $newType = LocationType::from($data['type']);
            $hierarchyChanged = (string) $target->parent_id !== (string) $newParentId || $target->type !== $newType;

            if (
                $hierarchyChanged
                && (
                    $target->children()->exists()
                    || $target->assets()->withTrashed()->exists()
                    || $target->sourceHistory()->exists()
                    || $target->destinationHistory()->exists()
                )
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'La hiérarchie d’un lieu utilisé, historique ou ayant des enfants ne peut pas être modifiée.',
                ]);
            }

            $target->fill($data)->save();
        });

        return redirect()
            ->route('references.locations.show', $location)
            ->with('status', 'Le lieu a été mis à jour.');
    }

    public function toggleStatus(Location $location): RedirectResponse
    {
        DB::transaction(function () use ($location): void {
            $target = Location::query()->lockForUpdate()->findOrFail($location->getKey());

            if ($target->is_active) {
                if ($target->children()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'location' => 'Désactivez d’abord les lieux enfants actifs.',
                    ]);
                }

                if ($target->assets()->withTrashed()->exists()) {
                    throw ValidationException::withMessages([
                        'location' => 'Ce lieu est référencé par des biens. Déplacez-les avant de le désactiver.',
                    ]);
                }

                if ($target->sourceHistory()->exists() || $target->destinationHistory()->exists()) {
                    throw ValidationException::withMessages([
                        'location' => 'Ce lieu est référencé dans un historique de localisation et ne peut pas être désactivé.',
                    ]);
                }
            }

            $target->is_active = ! $target->is_active;
            $target->save();
        });

        return redirect()
            ->route('references.locations.show', $location)
            ->with('status', $location->fresh()->is_active ? 'Le lieu a été réactivé.' : 'Le lieu a été désactivé.');
    }

    private function activeParents(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $sites): void {
                    $sites->where('type', LocationType::Site->value)
                        ->whereNull('parent_id');
                })
                    ->orWhere(function (Builder $query): void {
                        $query->whereIn('type', LocationType::buildingValues())
                            ->whereHas('parent', fn (Builder $parent) => $parent
                                ->where('type', LocationType::Site->value)
                                ->whereNull('parent_id')
                                ->where('is_active', true));
                    });
            })
            ->with('parent')
            ->orderBy('name')
            ->get();
    }

    private function typeOptions(?Location $location = null): array
    {
        $options = [
            LocationType::Site->value => 'Site',
            LocationType::Batiment->value => 'Bâtiment',
            LocationType::Local->value => 'Local',
        ];

        if ($location && ! array_key_exists($location->type->value, $options)) {
            $options[$location->type->value] = $location->type->label();
        }

        return $options;
    }
}

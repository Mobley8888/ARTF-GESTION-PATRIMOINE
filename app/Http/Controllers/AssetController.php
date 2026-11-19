<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Services\AssetLocationService;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function __construct(private readonly AssetLocationService $locations, private readonly StockMovementService $stock) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('type', LocationType::Site->value)
                    ->whereNull('parent_id'),
            ],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'condition' => ['nullable', 'string', 'in:'.implode(',', array_column(AssetCondition::cases(), 'value'))],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(AssetStatus::cases(), 'value'))],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'usage_status' => ['nullable', 'in:in_service,broken,under_repair,retired,other'],
        ]);

        $assets = Asset::query()
            ->with(['category.parent', 'location.parent.parent'])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('manufacturer', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%")
                            ->orWhereHas('parent', fn (Builder $parent) => $parent->where('name', 'like', "%{$search}%")))
                        ->orWhereHas('location', fn (Builder $location) => $location->where('name', 'like', "%{$search}%")
                            ->orWhereHas('parent', fn (Builder $parent) => $parent->where('name', 'like', "%{$search}%")))
                        ->orWhereHas('activeAssignment', fn (Builder $assignment) => $assignment->whereHas('service', fn (Builder $service) => $service->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('agent.service', fn (Builder $service) => $service->where('name', 'like', "%{$search}%")));
                });
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $id) => $query->where('category_id', $id))
            ->when($filters['site_id'] ?? null, fn (Builder $query, string $id) => $query->atSite($id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, string $id) => $query->where('location_id', $id))
            ->when($filters['condition'] ?? null, fn (Builder $query, string $condition) => $query->where('condition', $condition))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['usage_status'] ?? null, fn (Builder $query, string $status) => $query->where('usage_status', $status))
            ->when($filters['service_id'] ?? null, fn (Builder $query, string $id) => $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment->where('service_id', $id)->orWhereHas('agent', fn (Builder $agent) => $agent->where('service_id', $id))))
            ->orderBy('asset_code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.assets.index', [
            'assets' => $assets,
            'filters' => $filters,
            ...$this->formOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.assets.create', [
            'asset' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $locationId = $data['location_id'] ?? null;
        unset($data['category_parent_id'], $data['subcategory_id'], $data['site_id'], $data['building_id'], $data['room_id']);
        $actor = $request->user();
        $data['location_id'] = null;
        $data['created_by'] = $actor->getKey();
        $data['updated_by'] = $actor->getKey();

        $asset = DB::transaction(function () use ($data, $locationId, $actor): Asset {
            $asset = Asset::query()->create($data);

            if ($locationId !== null) {
                $this->locations->move(
                    $asset,
                    Location::query()->findOrFail($locationId),
                    $actor,
                    'Localisation initiale à la création du bien',
                    registerStockMovement: false,
                );
            }

            $this->stock->registerAssetEntry($asset->refresh(), $actor);

            return $asset;
        });

        return redirect()
            ->route('assets.show', $asset)
            ->with('status', 'Le bien a été enregistré.');
    }

    public function show(Asset $asset): View
    {
        $asset->load([
            'category.parent',
            'location.parent.parent',
            'creator',
            'updater',
            'locationHistory.fromLocation.parent.parent',
            'locationHistory.toLocation.parent.parent',
            'locationHistory.recorder',
            'activeAssignment.agent.service',
            'activeAssignment.service',
            'activeAssignment.assigner',
            'assignments.agent.service',
            'assignments.service',
            'assignments.assigner',
            'assignments.ender',
            'documents.uploader',
            'maintenanceRequests.reporter',
            'maintenanceRequests.assignedAgent.service',
            'maintenanceRequests.intervention',
            'maintenanceSchedules.assignedAgent.service',
        ]);

        $maintenanceRequests = $asset->maintenanceRequests->filter(fn ($item): bool => auth()->user()->can('manage-maintenance')
            || $item->reported_by === auth()->id()
            || $item->assignedAgent?->user_id === auth()->id());
        $openMaintenance = $maintenanceRequests->first(fn ($item): bool => $item->status->isOpen());

        return view('admin.assets.show', [
            'asset' => $asset,
            'maintenanceRequests' => $maintenanceRequests,
            'openMaintenance' => $openMaintenance,
            'conditionLabels' => $this->conditionLabels(),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function edit(Asset $asset): View
    {
        return view('admin.assets.edit', [
            'asset' => $asset,
            ...$this->formOptions(),
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();
        $locationId = $data['location_id'] ?? null;
        unset($data['category_parent_id'], $data['subcategory_id'], $data['location_id'], $data['site_id'], $data['building_id'], $data['room_id']);

        $actor = $request->user();

        DB::transaction(function () use ($asset, $data, $locationId, $actor): void {
            $asset->fill($data);
            $asset->updated_by = $actor->getKey();
            $asset->save();

            if ((string) $asset->location_id !== (string) $locationId) {
                $destination = $locationId === null
                    ? null
                    : Location::query()->findOrFail($locationId);

                $this->locations->move(
                    $asset,
                    $destination,
                    $actor,
                    'Modification de la localisation depuis la fiche du bien',
                );
            }
        });

        return redirect()
            ->route('assets.show', $asset)
            ->with('status', 'Le bien a été mis à jour.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        DB::transaction(function () use ($request, $asset): void {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());

            if ($lockedAsset->assignments()->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Le bien est encore affecté. Enregistrez sa restitution avant de l’archiver.',
                ]);
            }

            $lockedAsset->updated_by = $request->user()->getKey();
            $lockedAsset->save();
            $lockedAsset->delete();
        });

        return redirect()
            ->route('assets.index')
            ->with('status', 'Le bien a été archivé. Son historique est conservé.');
    }

    public function uploadDocument(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:photo,invoice,delivery_note,reception_report,warranty,technical_sheet,other'],
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        foreach ($request->file('documents') as $file) {
            $path = $file->store('assets/'.$asset->getKey(), 'private');
            $asset->documents()->create([
                'uploaded_by' => $request->user()->getKey(),
                'type' => $validated['type'],
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'disk' => 'private',
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('status', 'Les documents ont été ajoutés à la fiche.');
    }

    public function downloadDocument(Request $request, Asset $asset, AssetDocument $document): StreamedResponse
    {
        abort_unless($document->asset_id === $asset->getKey(), 404);
        abort_unless($request->user()?->can('view-assets'), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, basename($document->original_name), [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteDocument(Request $request, Asset $asset, AssetDocument $document): RedirectResponse
    {
        abort_unless($document->asset_id === $asset->getKey(), 404);
        $document->delete();

        return back()->with('status', 'Le document a été retiré de la fiche. Le fichier est conservé pour préserver la traçabilité.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::query()
                ->where('is_active', true)
                ->where(function (Builder $query): void {
                    $query->whereNull('parent_id')
                        ->orWhereHas('parent', fn (Builder $parent) => $parent->where('is_active', true));
                })
                ->with('parent')
                ->orderBy('name')
                ->get(),
            'locations' => Location::query()
                ->where('is_active', true)
                ->with('parent.parent')
                ->orderBy('name')
                ->get(),
            'sites' => Location::query()
                ->where('type', LocationType::Site->value)
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(),
            'conditionLabels' => $this->conditionLabels(),
            'statusLabels' => $this->statusLabels(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetAssignmentRequest;
use App\Http\Requests\AssetLocationRequest;
use App\Http\Requests\AssetReturnRequest;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Location;
use App\Models\Service;
use App\Services\AssetAssignmentService;
use App\Services\AssetLocationService;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetAssignmentController extends Controller
{
    public function __construct(private readonly AssetAssignmentService $assignments, private readonly AssetLocationService $locations, private readonly StockMovementService $stock) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'movement_type' => ['nullable', 'in:assignment,transfer'],
        ]);

        $assignments = AssetAssignment::query()
            ->whereNull('ended_at')
            ->with(['asset', 'agent.service', 'service.direction', 'assigner'])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('asset', function (Builder $assetQuery) use ($search): void {
                    $assetQuery->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%");
                });
            })
            ->when($filters['agent_id'] ?? null, fn (Builder $query, string $id) => $query->where('agent_id', $id))
            ->when($filters['service_id'] ?? null, fn (Builder $query, string $id) => $query->where('service_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('assigned_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('assigned_at', '<=', $date))
            ->when($filters['location_id'] ?? null, fn (Builder $query, string $id) => $query->whereHas('asset', fn (Builder $asset) => $asset->where('location_id', $id)))
            ->when($filters['movement_type'] ?? null, fn (Builder $query, string $type) => $query->where('movement_type', $type))
            ->orderByDesc('assigned_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.assignments.index', [
            'assignments' => $assignments,
            'filters' => $filters,
            'agents' => Agent::query()->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function unassigned(): View
    {
        $assets = $this->stock->availableAssetsQuery()
            ->with(['category', 'location.parent.parent'])
            ->orderBy('asset_code')
            ->paginate(15);

        return view('admin.assignments.unassigned', compact('assets'));
    }

    public function create(Request $request): View
    {
        $selectedAssetId = $request->validate([
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
        ])['asset_id'] ?? null;

        return view('admin.assignments.create', [
            'assets' => $this->stock->availableAssetsQuery()
                ->orderBy('asset_code')
                ->get(),
            'selectedAssetId' => $selectedAssetId,
            ...$this->assigneeOptions(),
        ]);
    }

    public function store(AssetAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $asset = Asset::query()->findOrFail($data['asset_id']);

        $this->assignments->assign(
            $asset,
            isset($data['agent_id']) ? (int) $data['agent_id'] : null,
            isset($data['service_id']) ? (int) $data['service_id'] : null,
            $request->user(),
            $data['assigned_at'],
            $data['reason'],
            $data['observations'] ?? null,
        );

        return redirect()
            ->route('assignments.show', $asset)
            ->with('status', 'L’affectation a été enregistrée.');
    }

    public function show(Asset $asset): View
    {
        $asset->load([
            'category',
            'assignments.agent.service',
            'assignments.service.direction',
            'assignments.assigner',
            'assignments.ender',
            'locationHistory.fromLocation.parent.parent',
            'locationHistory.toLocation.parent.parent',
            'locationHistory.recorder',
        ]);

        return view('admin.assignments.show', [
            'asset' => $asset,
            'activeAssignment' => $asset->assignments->firstWhere('ended_at', null),
            'locations' => Location::query()->where('is_active', true)->with('parent.parent')->orderBy('name')->get(),
            ...$this->assigneeOptions(),
        ]);
    }

    public function transfer(AssetAssignmentRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();

        $this->assignments->transfer(
            $asset,
            isset($data['agent_id']) ? (int) $data['agent_id'] : null,
            isset($data['service_id']) ? (int) $data['service_id'] : null,
            $request->user(),
            $data['assigned_at'],
            $data['reason'],
            $data['observations'] ?? null,
        );

        return redirect()
            ->route('assignments.show', $asset)
            ->with('status', 'Le transfert a été enregistré.');
    }

    public function restitute(AssetReturnRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();

        $this->assignments->restitute(
            $asset,
            $request->user(),
            $data['returned_at'],
            $data['reason'],
            $data['received_by_name'] ?? null,
            $data['return_condition'] ?? null,
            $data['return_observations'] ?? null,
        );

        return redirect()
            ->route('assignments.show', $asset)
            ->with('status', 'La restitution a été enregistrée.');
    }

    public function movePhysical(AssetLocationRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();
        $this->locations->move(
            $asset,
            Location::query()->findOrFail($data['location_id']),
            $request->user(),
            $data['reason'],
            $data['moved_at'],
            $data['notes'] ?? null,
        );

        return redirect()->route('assignments.show', $asset)->with('status', 'Le déplacement physique a été enregistré.');
    }

    public function assignedToAgent(Agent $agent): View
    {
        $assignments = $agent->assetAssignments()
            ->whereNull('ended_at')
            ->with(['asset.category', 'assigner'])
            ->orderByDesc('assigned_at')
            ->paginate(15);

        return view('admin.assignments.assignee', [
            'title' => 'Biens affectés à '.$agent->first_name.' '.$agent->last_name,
            'subtitle' => 'Agent · '.$agent->employee_code,
            'assignments' => $assignments,
        ]);
    }

    public function assignedToService(Service $service): View
    {
        $assignments = $service->assetAssignments()
            ->whereNull('ended_at')
            ->with(['asset.category', 'assigner'])
            ->orderByDesc('assigned_at')
            ->paginate(15);

        return view('admin.assignments.assignee', [
            'title' => 'Biens affectés au service '.$service->name,
            'subtitle' => 'Service · '.$service->code,
            'assignments' => $assignments,
        ]);
    }

    private function assigneeOptions(): array
    {
        return [
            'agents' => Agent::query()
                ->where('is_active', true)
                ->with('service')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'services' => Service::query()
                ->where('is_active', true)
                ->with('direction')
                ->orderBy('name')
                ->get(),
        ];
    }
}

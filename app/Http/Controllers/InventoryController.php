<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCampaignStatus;
use App\Enums\InventoryItemStatus;
use App\Http\Requests\InventoryCampaignRequest;
use App\Http\Requests\InventoryCaptureRequest;
use App\Http\Requests\InventoryDiscoveryRequest;
use App\Http\Requests\InventoryTaskRequest;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\InventoryCampaign;
use App\Models\InventoryEvidence;
use App\Models\InventoryItem;
use App\Models\InventoryTask;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\InventoryCampaignService;
use App\Services\InventoryReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryCampaignService $campaigns,
        private readonly InventoryReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,in_progress,to_verify,validated,closed'],
        ]);

        $agentId = $this->restrictedAgentId($request->user());
        $campaignsQuery = InventoryCampaign::query()
            ->with('responsible')
            ->withCount($agentId ? [
                'scopes' => fn (Builder $query) => $query->whereHas('task', fn (Builder $task) => $task->where('agent_id', $agentId)),
                'tasks' => fn (Builder $query) => $query->where('agent_id', $agentId),
                'items' => fn (Builder $query) => $query->whereHas('task', fn (Builder $task) => $task->where('agent_id', $agentId)),
            ] : ['scopes', 'tasks', 'items'])
            ->when($agentId, fn (Builder $query, int $id) => $query->whereHas('tasks', fn (Builder $tasks) => $tasks->where('agent_id', $id)))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $q) => $q->where('reference', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at');
        $campaigns = (clone $campaignsQuery)->paginate(15)->withQueryString();

        return view('admin.inventories.index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
            'statusLabels' => $this->statusLabels(),
            'totals' => [
                'all' => (clone $campaignsQuery)->count(),
                'active' => (clone $campaignsQuery)->whereIn('status', [InventoryCampaignStatus::InProgress->value, InventoryCampaignStatus::ToVerify->value])->count(),
                'closed' => (clone $campaignsQuery)->where('status', InventoryCampaignStatus::Closed->value)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.inventories.create', [...$this->formOptions(), 'campaign' => null, 'scopesLocked' => false]);
    }

    public function edit(InventoryCampaign $campaign): View
    {
        abort_unless($campaign->status === InventoryCampaignStatus::Draft, 422);
        $campaign->load(['scopes.direction', 'scopes.service', 'scopes.location', 'tasks']);

        return view('admin.inventories.create', [
            ...$this->formOptions(),
            'campaign' => $campaign,
            'scopesLocked' => $campaign->tasks->isNotEmpty(),
        ]);
    }

    public function update(InventoryCampaignRequest $request, InventoryCampaign $campaign): RedirectResponse
    {
        $this->campaigns->updateDraft($campaign, $request->validated(), $request->user());

        return redirect()->route('inventories.show', $campaign)->with('status', 'Le brouillon de campagne a été mis à jour.');
    }

    public function preview(InventoryCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $count = $this->campaigns->previewCount($data);

        return back()->withInput()->with('inventory_preview_count', $count);
    }

    public function store(InventoryCampaignRequest $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($request->validated(), $request->user());

        return redirect()->route('inventories.show', $campaign)->with('status', 'La campagne brouillon a été créée. Attribuez chaque périmètre à un agent.');
    }

    public function show(InventoryCampaign $campaign): View
    {
        $agentId = $this->restrictedAgentId(request()->user());
        abort_unless(! $agentId || $campaign->tasks()->where('agent_id', $agentId)->exists(), 404);
        $campaign->load([
            'responsible', 'creator', 'validator', 'closer',
            'scopes' => fn (HasMany $query) => $query->when($agentId, fn (Builder $scopes) => $scopes->whereHas('task', fn (Builder $task) => $task->where('agent_id', $agentId))),
            'scopes.direction', 'scopes.service', 'scopes.location', 'scopes.task.agent.service',
            ...($agentId ? [] : ['reviews.reviewer']),
        ]);

        $itemQuery = $campaign->items()->when($agentId, fn (Builder $query, int $id) => $query->whereHas('task', fn (Builder $task) => $task->where('agent_id', $id)));
        $counts = (clone $itemQuery)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.inventories.show', [
            'campaign' => $campaign,
            'counts' => $counts,
            'itemsTotal' => $counts->sum(),
            'pendingCount' => (int) ($counts[InventoryItemStatus::Pending->value] ?? 0),
            'statusLabels' => $this->statusLabels(),
            'itemStatusLabels' => $this->itemStatusLabels(),
            'agents' => Agent::query()->where('is_active', true)->with(['user', 'service.direction'])->orderBy('last_name')->orderBy('first_name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'directions' => Direction::query()->where('is_active', true)->orderBy('name')->get(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'isRestrictedAgent' => (bool) $agentId,
            'taskCounts' => $campaign->tasks()->when($agentId, fn (Builder $query, int $id) => $query->where('agent_id', $id))->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function items(Request $request, InventoryCampaign $campaign): View
    {
        $agentId = $this->restrictedAgentId($request->user());
        abort_unless(! $agentId || $campaign->tasks()->where('agent_id', $agentId)->exists(), 404);
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,present_conform,present_anomaly,absent,moved,damaged,unidentified,discovered'],
            'q' => ['nullable', 'string', 'max:100'],
            'anomalies' => ['nullable', 'boolean'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'task' => ['nullable', 'integer', 'exists:inventory_tasks,id'],
        ]);

        $items = $campaign->items()
            ->with(['asset', 'task.agent', 'observedLocation', 'observedAgent', 'observedService', 'observer', 'evidence.uploader'])
            ->when($agentId, fn (Builder $query, int $id) => $query->whereHas('task', fn (Builder $task) => $task->where('agent_id', $id)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->boolean('anomalies'), fn (Builder $query) => $query->where(function (Builder $q): void {
                $q->whereNotNull('physical_anomaly')->orWhereNotNull('administrative_anomaly')->orWhereIn('status', ['present_anomaly', 'absent', 'moved', 'damaged', 'unidentified', 'discovered']);
            }))
            ->when($filters['direction_id'] ?? null, fn (Builder $query, string $id) => $query->where(fn (Builder $q) => $q->where('expected_snapshot->direction_id', $id)->orWhereHas('task.scope', fn (Builder $scope) => $scope->where('direction_id', $id))))
            ->when($filters['service_id'] ?? null, fn (Builder $query, string $id) => $query->where(fn (Builder $q) => $q->where('expected_snapshot->service_id', $id)->orWhereHas('task.scope', fn (Builder $scope) => $scope->where('service_id', $id))))
            ->when($filters['location_id'] ?? null, fn (Builder $query, string $id) => $query->where(fn (Builder $q) => $q->where('expected_snapshot->location_id', $id)->orWhere('observed_location_id', $id)->orWhereHas('task.scope', fn (Builder $scope) => $scope->where('location_id', $id))))
            ->when($filters['task'] ?? null, fn (Builder $query, string $id) => $query->where('task_id', $id))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $q) => $q->where('observed_asset_code', 'like', "%{$term}%")->orWhereHas('asset', fn (Builder $asset) => $asset->where('asset_code', 'like', "%{$term}%")->orWhere('designation', 'like', "%{$term}%"))))
            ->orderBy('status')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.inventories.items', [
            'campaign' => $campaign,
            'items' => $items,
            'filters' => $filters,
            'itemStatusLabels' => $this->itemStatusLabels(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'agents' => Agent::query()->where('is_active', true)->orderBy('last_name')->get(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'directions' => Direction::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function assignTask(InventoryTaskRequest $request, InventoryCampaign $campaign): RedirectResponse
    {
        $data = $request->validated();
        $scope = $campaign->scopes()->findOrFail($data['scope_id']);
        $this->campaigns->assignTask($campaign, $scope, (int) $data['agent_id'], $request->user(), $data['due_on'] ?? null);

        return back()->with('status', 'L’opération a été attribuée à l’agent.');
    }

    public function launch(Request $request, InventoryCampaign $campaign): RedirectResponse
    {
        $this->campaigns->launch($campaign, $request->user());

        return redirect()->route('inventories.show', $campaign)->with('status', 'La campagne est lancée. Les données théoriques ont été figées.');
    }

    public function capture(InventoryCaptureRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->campaigns->capture($item, $request->validated(), $request->user());

        return back()->with('status', 'Le constat a été enregistré sans modifier la fiche patrimoniale.');
    }

    public function discover(InventoryDiscoveryRequest $request, InventoryTask $task): RedirectResponse
    {
        $item = $this->campaigns->discover($task, $request->validated(), $request->user());

        return redirect()->route('inventories.items', $task->campaign_id)->with('status', 'Le bien découvert a été ajouté comme constat non rattaché au patrimoine.')->with('new_inventory_item', $item->getKey());
    }

    public function submitTask(Request $request, InventoryTask $task): RedirectResponse
    {
        $this->campaigns->submitTask($task, $request->user());

        return redirect()->route('inventories.show', $task->campaign_id)->with('status', 'L’opération a été soumise.');
    }

    public function uploadEvidence(Request $request, InventoryItem $item): RedirectResponse
    {
        $item->loadMissing('campaign', 'task');
        abort_unless($item->campaign->status === InventoryCampaignStatus::InProgress, 422);
        abort_unless($this->campaigns->canPerformTask($item->task, $request->user()), 403);
        $request->validate([
            'evidence' => ['required', 'array', 'min:1', 'max:10'],
            'evidence.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        foreach ($request->file('evidence') as $file) {
            $path = $file->store('inventories/'.$item->campaign_id.'/'.$item->getKey(), 'private');
            $item->evidence()->create([
                'uploaded_by' => $request->user()->getKey(),
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'disk' => 'private',
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('status', 'Les justificatifs ont été ajoutés.');
    }

    public function downloadEvidence(Request $request, InventoryEvidence $evidence): StreamedResponse
    {
        $evidence->loadMissing('item.task');
        abort_unless($this->canAccessTask($evidence->item?->task, $request->user()), 403);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download($evidence->path, basename($evidence->original_name), [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(Request $request, InventoryCampaign $campaign): RedirectResponse
    {
        Gate::authorize('verify-inventories');
        $data = $request->validate(['decision' => ['required', 'in:approved,returned'], 'observations' => ['nullable', 'string', 'max:5000']]);
        $this->campaigns->verify($campaign, $request->user(), $data['decision'] === 'approved', $data['observations'] ?? null);

        return back()->with('status', $data['decision'] === 'approved' ? 'La vérification est enregistrée.' : 'La campagne a été retournée en cours pour correction.');
    }

    public function validateCampaign(Request $request, InventoryCampaign $campaign): RedirectResponse
    {
        Gate::authorize('validate-inventories');
        $data = $request->validate(['observations' => ['nullable', 'string', 'max:5000']]);
        $this->campaigns->validateCampaign($campaign, $request->user(), $data['observations'] ?? null);

        return back()->with('status', 'La campagne a été validée.');
    }

    public function close(Request $request, InventoryCampaign $campaign): RedirectResponse
    {
        Gate::authorize('close-inventories');
        $data = $request->validate(['observations' => ['nullable', 'string', 'max:5000']]);
        $this->campaigns->close($campaign, $request->user(), $data['observations'] ?? null);

        return back()->with('status', 'La campagne est clôturée et protégée contre les modifications.');
    }

    public function spreadsheet(InventoryCampaign $campaign, Request $request): Response
    {
        abort_unless(! $this->restrictedAgentId($request->user()), 403);

        return $this->reports->spreadsheet($campaign, $this->reportFilters($request));
    }

    public function pdf(InventoryCampaign $campaign, Request $request): Response
    {
        abort_unless(! $this->restrictedAgentId($request->user()), 403);

        return $this->reports->pdf($campaign, $this->reportFilters($request));
    }

    private function reportFilters(Request $request): array
    {
        return $request->validate([
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable', 'in:pending,present_conform,present_anomaly,absent,moved,damaged,unidentified,discovered'],
            'anomalies' => ['nullable', 'boolean'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'directions' => Direction::query()->where('is_active', true)->orderBy('name')->get(),
            'services' => Service::query()->where('is_active', true)->with('direction')->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->with('parent.parent')->orderBy('name')->get(),
        ];
    }

    private function restrictedAgentId(?User $user): ?int
    {
        if (! $user || $user->can('manage-inventories') || $user->can('verify-inventories') || $user->can('validate-inventories') || $user->can('close-inventories')) {
            return null;
        }

        return $user->agent ? (int) $user->agent->getKey() : null;
    }

    private function canAccessTask(?InventoryTask $task, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $agentId = $this->restrictedAgentId($user);

        return ! $agentId || ($task && (int) $task->agent_id === $agentId);
    }

    private function statusLabels(): array
    {
        return [
            'draft' => 'Brouillon',
            'in_progress' => 'En cours',
            'to_verify' => 'À vérifier',
            'validated' => 'Validée',
            'closed' => 'Clôturée',
        ];
    }

    private function itemStatusLabels(): array
    {
        return [
            'pending' => 'À contrôler',
            'present_conform' => 'Présent et conforme',
            'present_anomaly' => 'Présent avec anomalie',
            'absent' => 'Absent',
            'moved' => 'Déplacé',
            'damaged' => 'Détérioré',
            'unidentified' => 'Non identifié',
            'discovered' => 'Découvert non enregistré',
        ];
    }
}

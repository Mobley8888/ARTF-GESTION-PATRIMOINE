<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceRequestStatus;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\Direction;
use App\Models\MaintenanceDocument;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Service;
use App\Models\User;
use App\Services\AdministrativeDocumentService;
use App\Services\MaintenanceWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceWorkflow $workflow, private readonly AdministrativeDocumentService $documents) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['corrective', 'preventive'])],
            'status' => ['nullable', Rule::in(array_column(MaintenanceRequestStatus::cases(), 'value'))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ]);
        $query = $this->visibleRequests(auth()->user())
            ->with(['asset.location.parent.parent', 'asset.activeAssignment.agent.service', 'asset.activeAssignment.service', 'assignedAgent.service', 'reporter', 'intervention'])
            ->when($filters['q'] ?? null, function (Builder $builder, string $term): void {
                $builder->where(fn (Builder $q) => $q->where('reference', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhereHas('asset', fn (Builder $asset) => $asset->where('asset_code', 'like', "%{$term}%")->orWhere('designation', 'like', "%{$term}%")));
            })
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('reported_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('reported_at', '<=', $date))
            ->when($filters['service_id'] ?? null, fn (Builder $q, string $id) => $q->whereHas('asset.activeAssignment', fn (Builder $assignment) => $assignment->where('service_id', $id)->orWhereHas('agent', fn (Builder $agent) => $agent->where('service_id', $id))))
            ->when($filters['direction_id'] ?? null, fn (Builder $q, string $id) => $q->where(fn (Builder $scope) => $scope->whereHas('asset.activeAssignment.agent.service', fn (Builder $service) => $service->where('direction_id', $id))
                ->orWhereHas('asset.activeAssignment.service', fn (Builder $service) => $service->where('direction_id', $id))));
        $items = $query->latest('reported_at')->paginate(15)->withQueryString();
        $visible = $this->visibleRequests(auth()->user());
        $canManage = auth()->user()->can('manage-maintenance');
        $stats = [
            'total' => (clone $visible)->count(),
            'pending' => (clone $visible)->whereIn('status', ['new', 'pending_validation'])->count(),
            'in_progress' => (clone $visible)->whereIn('status', ['planned', 'in_progress', 'suspended'])->count(),
            'completed' => (clone $visible)->where('status', 'completed')->count(),
            'broken' => $canManage ? Asset::query()->whereHas('maintenanceRequests', fn (Builder $q) => $q->where('type', 'corrective')->whereIn('status', ['new', 'pending_validation', 'approved', 'planned', 'in_progress', 'suspended', 'pending_closure']))->count() : null,
            'upcoming' => $canManage ? MaintenanceSchedule::query()->where('is_active', true)->whereBetween('next_due_on', [today(), today()->addDays(30)])->count() : null,
            'overdue' => $canManage ? MaintenanceSchedule::query()->where('is_active', true)->whereDate('next_due_on', '<', today())->count() : null,
            'corrective' => (clone $visible)->where('type', 'corrective')->count(),
            'preventive' => (clone $visible)->where('type', 'preventive')->count(),
            'costs' => auth()->user()->can('view-maintenance-costs') ? (float) MaintenanceIntervention::query()->where('status', 'completed')->selectRaw('COALESCE(SUM(labor_cost + parts_cost), 0) as total')->value('total') : null,
        ];

        return view('admin.maintenance.index', [
            'requests' => $items,
            'filters' => $filters,
            'stats' => $stats,
            'directions' => Direction::query()->orderBy('name')->get(),
            'services' => Service::query()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.maintenance.create-request', [
            'assets' => Asset::query()->orderBy('asset_code')->get(['id', 'asset_code', 'designation']),
            'selectedAsset' => $request->integer('asset_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);
        $maintenance = $this->workflow->report($data, $request->user());

        return to_route('maintenance.requests.show', $maintenance)->with('status', 'La panne a été enregistrée sous la référence '.$maintenance->reference.'.');
    }

    public function show(MaintenanceRequest $maintenanceRequest): View
    {
        $this->authorizeRequest($maintenanceRequest, auth()->user());
        $maintenanceRequest->load(['asset.location.parent.parent', 'asset.activeAssignment.agent.service', 'asset.activeAssignment.service', 'schedule', 'reporter', 'assignedAgent.service', 'validator', 'intervention.performer', 'intervention.creator', 'intervention.documents.uploader', 'histories.actor', 'documents.uploader']);
        $agents = Agent::query()->where('is_active', true)->with('service.direction')->orderBy('last_name')->orderBy('first_name')->get();

        return view('admin.maintenance.show-request', ['maintenanceRequest' => $maintenanceRequest, 'agents' => $agents]);
    }

    public function validateRequest(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'observation' => ['nullable', 'string', 'max:4000']]);
        $this->workflow->validateRequest($maintenanceRequest, $request->user(), $data['decision'] === 'approve', $data['observation'] ?? null);

        return back()->with('status', $data['decision'] === 'approve' ? 'Demande validée.' : 'Demande rejetée.');
    }

    public function assign(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', 'exists:agents,id', 'required_without:provider_name'],
            'provider_name' => ['nullable', 'string', 'max:180', 'required_without:agent_id'],
            'planned_at' => ['required', 'date'],
        ]);
        $agent = isset($data['agent_id']) ? Agent::query()->where('is_active', true)->findOrFail($data['agent_id']) : null;
        $this->workflow->assign($maintenanceRequest, $request->user(), $agent, $data['provider_name'] ?? null, $data['planned_at']);

        return back()->with('status', 'Responsable et intervention planifiée.');
    }

    public function start(Request $request, MaintenanceIntervention $intervention): RedirectResponse
    {
        $this->authorizeExecution($intervention, $request->user());
        $this->workflow->start($intervention, $request->user());

        return back()->with('status', 'Intervention démarrée.');
    }

    public function suspend(Request $request, MaintenanceIntervention $intervention): RedirectResponse
    {
        $this->authorizeExecution($intervention, $request->user());
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);
        $this->workflow->suspend($intervention, $request->user(), $data['reason']);

        return back()->with('status', 'Intervention suspendue avec motif historisé.');
    }

    public function finish(Request $request, MaintenanceIntervention $intervention): RedirectResponse
    {
        $this->authorizeExecution($intervention, $request->user());
        $data = $request->validate([
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'work_performed' => ['required', 'string', 'max:10000'],
            'parts_used' => ['nullable', 'string', 'max:10000'],
            'labor_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'parts_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'observations' => ['nullable', 'string', 'max:4000'],
            'report' => ['required', 'string', 'max:10000'],
        ]);
        $this->workflow->finish($intervention, $request->user(), $data);

        return back()->with('status', 'Compte rendu soumis à la validation de clôture.');
    }

    public function close(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate(['observation' => ['nullable', 'string', 'max:4000']]);
        $this->workflow->closeRequest($maintenanceRequest, $request->user(), $data['observation'] ?? null);

        return back()->with('status', 'Intervention contrôlée et demande clôturée. La situation patrimoniale du bien n’a pas été modifiée automatiquement.');
    }

    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);
        $this->workflow->cancel($maintenanceRequest, $request->user(), $data['reason']);

        return back()->with('status', 'Demande annulée et motif consigné dans l’historique.');
    }

    public function schedules(): View
    {
        $schedules = MaintenanceSchedule::query()->with(['asset', 'assignedAgent.service'])->orderBy('next_due_on')->paginate(20);

        return view('admin.maintenance.schedules', ['schedules' => $schedules]);
    }

    public function createSchedule(): View
    {
        return view('admin.maintenance.create-schedule', [
            'assets' => Asset::query()->orderBy('asset_code')->get(['id', 'asset_code', 'designation']),
            'agents' => Agent::query()->where('is_active', true)->with('service')->orderBy('last_name')->orderBy('first_name')->get(),
            'frequencies' => MaintenanceFrequency::cases(),
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'assigned_agent_id' => ['nullable', 'integer', Rule::exists('agents', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'frequency' => ['required', Rule::in(array_column(MaintenanceFrequency::cases(), 'value'))],
            'provider_name' => ['nullable', 'string', 'max:180'],
            'next_due_on' => ['required', 'date'],
        ]);
        $schedule = $this->workflow->createSchedule($data, $request->user());

        return to_route('maintenance.schedules')->with('status', 'Programme '.$schedule->reference.' créé.');
    }

    public function toggleSchedule(Request $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        $schedule = $this->workflow->toggleSchedule($schedule, $request->user());

        return back()->with('status', $schedule->is_active ? 'Programme réactivé.' : 'Programme mis en pause.');
    }

    public function createDueRequest(Request $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        $maintenance = $this->workflow->createDueRequest($schedule, $request->user());

        return to_route('maintenance.requests.show', $maintenance)->with('status', 'Échéance créée sous la référence '.$maintenance->reference.'.');
    }

    public function uploadDocument(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeRequest($maintenanceRequest, $request->user());
        abort_unless($request->user()->can('manage-maintenance') || $maintenanceRequest->reported_by === $request->user()->getKey() || $this->isAssignedAgent($maintenanceRequest, $request->user()), 403);
        $data = $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:20480']]);
        $file = $data['document'];
        $path = $file->store('maintenance/'.$maintenanceRequest->reference, 'local');
        $document = $maintenanceRequest->documents()->create([
            'uploaded_by' => $request->user()->getKey(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
        $maintenanceRequest->histories()->create(['actor_id' => $request->user()->getKey(), 'event_type' => 'document.uploaded', 'description' => 'Pièce justificative ajoutée : '.$document->original_name]);

        return back()->with('status', 'Justificatif enregistré dans le stockage privé.');
    }

    public function downloadDocument(MaintenanceDocument $document): StreamedResponse
    {
        $request = $document->request ?: $document->intervention?->request;
        abort_unless($request, 404);
        $this->authorizeRequest($request, auth()->user());
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function requestPdf(MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeRequest($maintenanceRequest, auth()->user());
        $maintenanceRequest->load(['asset.category', 'asset.location', 'assignedAgent.service', 'reporter', 'validator', 'intervention.performer']);

        return $this->documents->pdf('Fiche de demande de maintenance', $maintenanceRequest->reference, [
            'Demande' => ['Type' => $this->typeLabel($maintenanceRequest->type), 'Statut' => $maintenanceRequest->status->label(), 'Priorité' => $this->priorityLabel($maintenanceRequest->priority), 'Signalée le' => $maintenanceRequest->reported_at->format('d/m/Y H:i'), 'Déclarée par' => $maintenanceRequest->reporter?->name],
            'Bien concerné' => ['Référence' => $maintenanceRequest->asset->asset_code, 'Désignation' => $maintenanceRequest->asset->designation, 'Catégorie' => $maintenanceRequest->asset->category?->name, 'Localisation' => $maintenanceRequest->asset->location?->name, 'Situation patrimoniale' => $this->assetStatusLabel($maintenanceRequest->asset->status?->value)],
            'Description et suivi' => ['Objet' => $maintenanceRequest->title, 'Panne / entretien' => $maintenanceRequest->description, 'Diagnostic' => $maintenanceRequest->technical_diagnosis, 'Responsable' => $maintenanceRequest->assignedAgent?->last_name.' '.$maintenanceRequest->assignedAgent?->first_name, 'Prestataire' => $maintenanceRequest->provider_name, 'Date prévue' => $maintenanceRequest->planned_at?->format('d/m/Y H:i'), 'Compte rendu' => $maintenanceRequest->intervention?->report, 'Observations' => $maintenanceRequest->observations],
        ], 'demande-'.$maintenanceRequest->reference.'.pdf');
    }

    public function workOrderPdf(MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeRequest($maintenanceRequest, auth()->user());
        abort_unless($maintenanceRequest->status === MaintenanceRequestStatus::Planned, 404);
        $maintenanceRequest->load(['asset.category', 'asset.location', 'assignedAgent.service', 'reporter', 'assigner']);

        return $this->documents->pdf('Ordre de travail de maintenance', 'OT-'.$maintenanceRequest->reference, [
            'Ordre de travail' => ['Demande' => $maintenanceRequest->reference, 'Type' => $this->typeLabel($maintenanceRequest->type), 'Priorité' => $this->priorityLabel($maintenanceRequest->priority), 'Date de création' => $maintenanceRequest->reported_at->format('d/m/Y H:i'), 'Date prévue' => $maintenanceRequest->planned_at?->format('d/m/Y H:i'), 'Affecté par' => $maintenanceRequest->assigner?->name],
            'Bien concerné' => ['Référence' => $maintenanceRequest->asset->asset_code, 'Désignation' => $maintenanceRequest->asset->designation, 'Catégorie' => $maintenanceRequest->asset->category?->name, 'Localisation' => $maintenanceRequest->asset->location?->name],
            'Travaux demandés' => ['Objet' => $maintenanceRequest->title, 'Description' => $maintenanceRequest->description, 'Technicien' => $maintenanceRequest->assignedAgent?->last_name.' '.$maintenanceRequest->assignedAgent?->first_name, 'Prestataire' => $maintenanceRequest->provider_name, 'Diagnostic' => $maintenanceRequest->technical_diagnosis],
        ], 'ordre-travail-'.$maintenanceRequest->reference.'.pdf');
    }

    public function interventionPdf(MaintenanceIntervention $intervention)
    {
        $request = $intervention->request;
        abort_unless($request, 404);
        $this->authorizeRequest($request, auth()->user());
        $intervention->load(['request.asset', 'performer', 'creator']);

        return $this->documents->pdf('Fiche d’intervention de maintenance', $intervention->reference, [
            'Intervention' => ['Statut' => ucfirst(str_replace('_', ' ', $intervention->status)), 'Date prévue' => $intervention->planned_at?->format('d/m/Y H:i'), 'Début' => $intervention->started_at?->format('d/m/Y H:i'), 'Fin' => $intervention->completed_at?->format('d/m/Y H:i'), 'Technicien' => $intervention->performer?->last_name.' '.$intervention->performer?->first_name],
            'Bien concerné' => ['Référence' => $request->asset->asset_code, 'Désignation' => $request->asset->designation, 'Demande' => $request->reference],
            'Travaux' => ['Diagnostic' => $intervention->diagnosis, 'Opérations réalisées' => $intervention->work_performed, 'Pièces utilisées' => $intervention->parts_used, 'Coût main-d’œuvre' => $intervention->labor_cost, 'Coût pièces' => $intervention->parts_cost, 'Coût total' => $intervention->total_cost, 'Compte rendu' => $intervention->report, 'Observations' => $intervention->observations],
        ], 'intervention-'.$intervention->reference.'.pdf');
    }

    public function maintenanceReportPdf(MaintenanceIntervention $intervention)
    {
        $request = $intervention->request;
        abort_unless($request, 404);
        $this->authorizeRequest($request, auth()->user());
        abort_unless($intervention->status === 'completed', 404);
        $intervention->load(['request.asset.location', 'performer', 'creator']);

        return $this->documents->pdf('Rapport de maintenance', $intervention->reference, [
            'Intervention' => ['Référence' => $intervention->reference, 'Demande' => $request->reference, 'Date de début' => $intervention->started_at?->format('d/m/Y H:i'), 'Date de fin' => $intervention->completed_at?->format('d/m/Y H:i'), 'Technicien / prestataire' => $intervention->performer ? $intervention->performer->last_name.' '.$intervention->performer->first_name : $request->provider_name],
            'Bien concerné' => ['Référence' => $request->asset->asset_code, 'Désignation' => $request->asset->designation, 'Localisation enregistrée' => $request->asset->location?->name],
            'Compte rendu technique' => ['Demande initiale' => $request->description, 'Diagnostic' => $intervention->diagnosis, 'Opérations réalisées' => $intervention->work_performed, 'Pièces utilisées' => $intervention->parts_used, 'Compte rendu final' => $intervention->report, 'Observations' => $intervention->observations],
            'Coûts enregistrés' => ['Main-d’œuvre (FCFA)' => $intervention->labor_cost, 'Pièces (FCFA)' => $intervention->parts_cost, 'Total déclaré (FCFA)' => $intervention->total_cost],
        ], 'rapport-maintenance-'.$intervention->reference.'.pdf');
    }

    public function assetHistoryPdf(Asset $asset)
    {
        $this->authorize('view-maintenance');
        $asset->load(['maintenanceRequests.reporter', 'maintenanceRequests.intervention', 'maintenanceSchedules', 'maintenanceRequests.histories.actor']);
        abort_unless(auth()->user()->can('manage-maintenance') || $asset->maintenanceRequests->contains(fn (MaintenanceRequest $item) => $item->reported_by === auth()->id() || $this->isAssignedAgent($item, auth()->user())), 403);
        $rows = $asset->maintenanceRequests->map(fn (MaintenanceRequest $item) => $item->reference.' — '.$item->status->label().' — '.$item->reported_at->format('d/m/Y').' — '.$item->title)->implode("\n");

        return $this->documents->pdf('Historique de maintenance du bien', $asset->asset_code, [
            'Bien' => ['Référence' => $asset->asset_code, 'Désignation' => $asset->designation],
            'Demandes et interventions' => ['Historique' => $rows ?: 'Aucune demande de maintenance enregistrée.', 'Programmes préventifs' => $asset->maintenanceSchedules->map(fn (MaintenanceSchedule $schedule) => $schedule->reference.' — '.$schedule->title.' — prochaine échéance '.$schedule->next_due_on->format('d/m/Y'))->implode("\n") ?: 'Aucun programme préventif.'],
        ], 'historique-maintenance-'.$asset->asset_code.'.pdf');
    }

    public function costs(Request $request): View
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'direction_id' => ['nullable', 'integer', 'exists:directions,id'], 'service_id' => ['nullable', 'integer', 'exists:services,id'], 'type' => ['nullable', Rule::in(['corrective', 'preventive'])]]);
        $interventions = $this->costQuery($filters)->with(['request.asset.activeAssignment.agent.service', 'request.asset.activeAssignment.service', 'schedule.asset'])->latest('completed_at')->paginate(25)->withQueryString();
        $total = (clone $this->costQuery($filters))->selectRaw('COALESCE(SUM(labor_cost + parts_cost), 0) as total')->value('total');

        return view('admin.maintenance.costs', ['interventions' => $interventions, 'filters' => $filters, 'total' => $total, 'directions' => Direction::query()->orderBy('name')->get(), 'services' => Service::query()->orderBy('name')->get()]);
    }

    public function costsExcel(Request $request)
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'direction_id' => ['nullable', 'integer', 'exists:directions,id'], 'service_id' => ['nullable', 'integer', 'exists:services,id'], 'type' => ['nullable', Rule::in(['corrective', 'preventive'])]]);
        $rows = $this->costQuery($filters)->with(['request.asset', 'schedule.asset'])->get()->map(fn (MaintenanceIntervention $item) => [
            $item->reference,
            $item->request?->reference ?: $item->schedule?->reference,
            $item->request?->type === 'preventive' || $item->schedule_id ? 'Préventive' : 'Corrective',
            $item->request?->asset?->asset_code ?: $item->schedule?->asset?->asset_code,
            $item->request?->asset?->designation ?: $item->schedule?->asset?->designation,
            $item->completed_at?->format('d/m/Y'),
            $item->labor_cost,
            $item->parts_cost,
            $item->total_cost,
        ])->all();

        return $this->documents->spreadsheet('État récapitulatif des coûts de maintenance', ['Intervention', 'Demande / programme', 'Type', 'Référence du bien', 'Désignation', 'Date', 'Main-d’œuvre', 'Pièces', 'Total'], $rows, 'couts-maintenance.xls');
    }

    public function costsPdf(Request $request)
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'direction_id' => ['nullable', 'integer', 'exists:directions,id'], 'service_id' => ['nullable', 'integer', 'exists:services,id'], 'type' => ['nullable', Rule::in(['corrective', 'preventive'])]]);
        $items = $this->costQuery($filters)->with(['request.asset', 'schedule.asset'])->get();
        $rows = $items->map(fn (MaintenanceIntervention $item) => $item->reference.' | '.($item->request?->asset?->asset_code ?: $item->schedule?->asset?->asset_code).' | '.$item->completed_at?->format('d/m/Y').' | '.$item->total_cost)->implode("\n");

        return $this->documents->pdf('État récapitulatif des coûts de maintenance', 'COUTS-'.now()->format('Ymd'), ['Filtres' => ['Période' => ($filters['from'] ?? 'Toutes dates').' au '.($filters['to'] ?? 'Toutes dates')], 'Synthèse' => ['Nombre d’interventions clôturées' => $items->count(), 'Coût total' => number_format((float) $items->sum(fn (MaintenanceIntervention $item) => (float) $item->total_cost), 2, ',', ' ').' FCFA', 'Détail' => $rows ?: 'Aucun coût enregistré.']], 'couts-maintenance.pdf');
    }

    private function costQuery(array $filters): Builder
    {
        return MaintenanceIntervention::query()->where('status', 'completed')
            ->when($filters['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('completed_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('completed_at', '<=', $date))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $type === 'preventive' ? $q->where(fn (Builder $where) => $where->whereNotNull('schedule_id')->orWhereHas('request', fn (Builder $request) => $request->where('type', 'preventive'))) : $q->whereHas('request', fn (Builder $request) => $request->where('type', 'corrective')))
            ->when($filters['service_id'] ?? null, fn (Builder $q, string $id) => $q->whereHas('request.asset.activeAssignment', fn (Builder $assignment) => $assignment->where('service_id', $id)->orWhereHas('agent', fn (Builder $agent) => $agent->where('service_id', $id))))
            ->when($filters['direction_id'] ?? null, fn (Builder $q, string $id) => $q->where(fn (Builder $scope) => $scope->whereHas('request.asset.activeAssignment.agent.service', fn (Builder $service) => $service->where('direction_id', $id))
                ->orWhereHas('request.asset.activeAssignment.service', fn (Builder $service) => $service->where('direction_id', $id))));
    }

    private function visibleRequests(User $user): Builder
    {
        return MaintenanceRequest::query()->when(! $user->can('manage-maintenance'), fn (Builder $q) => $q->where(function (Builder $visible) use ($user): void {
            $visible->where('reported_by', $user->getKey())->orWhereHas('assignedAgent', fn (Builder $agent) => $agent->where('user_id', $user->getKey()));
        }));
    }

    private function authorizeRequest(MaintenanceRequest $request, User $user): void
    {
        abort_unless($user->can('manage-maintenance') || $request->reported_by === $user->getKey() || $this->isAssignedAgent($request, $user), 403);
    }

    private function authorizeExecution(MaintenanceIntervention $intervention, User $user): void
    {
        $this->authorizeRequest($intervention->request, $user);
        abort_unless($user->can('manage-maintenance') || $intervention->performer?->user_id === $user->getKey(), 403);
    }

    private function isAssignedAgent(MaintenanceRequest $request, User $user): bool
    {
        return $request->assignedAgent?->user_id === $user->getKey();
    }

    private function typeLabel(string $type): string
    {
        return $type === 'preventive' ? 'Préventive' : 'Corrective';
    }

    private function priorityLabel(string $priority): string
    {
        return ['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'urgent' => 'Urgente'][$priority] ?? $priority;
    }

    private function assetStatusLabel(?string $status): string
    {
        return ['in_service' => 'En service', 'under_maintenance' => 'En maintenance', 'retired' => 'Réformé', 'disposed' => 'Cédé', 'lost' => 'Perdu'][$status ?? ''] ?? 'Non renseigné';
    }
}

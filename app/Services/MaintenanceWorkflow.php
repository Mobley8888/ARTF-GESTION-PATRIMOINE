<?php

namespace App\Services;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceRequestStatus;
use App\Models\Agent;
use App\Models\Asset;
use App\Models\MaintenanceHistory;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceWorkflow
{
    public function __construct(private readonly StockMovementService $stock) {}

    public function report(array $data, User $actor): MaintenanceRequest
    {
        return DB::transaction(function () use ($data, $actor): MaintenanceRequest {
            $asset = Asset::query()->lockForUpdate()->findOrFail($data['asset_id']);
            $open = MaintenanceRequest::query()
                ->where('asset_id', $asset->getKey())
                ->where('type', 'corrective')
                ->whereIn('status', array_map(fn (MaintenanceRequestStatus $status): string => $status->value, array_filter(MaintenanceRequestStatus::cases(), fn (MaintenanceRequestStatus $status): bool => $status->isOpen())))
                ->exists();

            if ($open) {
                throw ValidationException::withMessages(['asset_id' => 'Une demande corrective non clôturée existe déjà pour ce bien.']);
            }

            $request = MaintenanceRequest::query()->create([
                ...$data,
                'reference' => $this->reference('DEM'),
                'type' => 'corrective',
                'status' => MaintenanceRequestStatus::New,
                'reported_by' => $actor->getKey(),
                'reported_at' => now(),
            ]);
            $this->record($actor, 'request.created', 'Création de la demande corrective.', $request, to: $request->status->value);

            return $request;
        });
    }

    public function createSchedule(array $data, User $actor): MaintenanceSchedule
    {
        return DB::transaction(function () use ($data, $actor): MaintenanceSchedule {
            $schedule = MaintenanceSchedule::query()->create([
                ...$data,
                'reference' => $this->reference('ENT'),
                'created_by' => $actor->getKey(),
                'is_active' => true,
            ]);
            $this->record($actor, 'schedule.created', 'Création du programme d’entretien.', schedule: $schedule);

            return $schedule;
        });
    }

    public function toggleSchedule(MaintenanceSchedule $schedule, User $actor): MaintenanceSchedule
    {
        return DB::transaction(function () use ($schedule, $actor): MaintenanceSchedule {
            $schedule = MaintenanceSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            $old = $schedule->is_active;
            $schedule->forceFill(['is_active' => ! $old])->save();
            $this->record($actor, 'schedule.status_changed', $schedule->is_active ? 'Programme préventif réactivé.' : 'Programme préventif mis en pause.', schedule: $schedule, changes: ['is_active' => ['old' => $old, 'new' => $schedule->is_active]]);

            return $schedule->refresh();
        });
    }

    public function createDueRequest(MaintenanceSchedule $schedule, User $actor): MaintenanceRequest
    {
        return DB::transaction(function () use ($schedule, $actor): MaintenanceRequest {
            $schedule = MaintenanceSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            if (! $schedule->is_active || $schedule->next_due_on->isFuture()) {
                throw ValidationException::withMessages(['schedule' => 'Ce programme n’est pas encore arrivé à échéance ou est inactif.']);
            }

            $open = MaintenanceRequest::query()->where('schedule_id', $schedule->getKey())
                ->whereIn('status', array_map(fn (MaintenanceRequestStatus $status): string => $status->value, array_filter(MaintenanceRequestStatus::cases(), fn (MaintenanceRequestStatus $status): bool => $status->isOpen())))
                ->exists();
            if ($open) {
                throw ValidationException::withMessages(['schedule' => 'Une opération est déjà ouverte pour cette échéance.']);
            }

            $request = MaintenanceRequest::query()->create([
                'asset_id' => $schedule->asset_id,
                'schedule_id' => $schedule->getKey(),
                'reported_by' => $actor->getKey(),
                'assigned_agent_id' => $schedule->assigned_agent_id,
                'reference' => $this->reference('PREV'),
                'type' => 'preventive',
                'title' => $schedule->title,
                'description' => $schedule->description ?: 'Entretien périodique prévu au programme '.$schedule->reference,
                'priority' => 'normal',
                'status' => MaintenanceRequestStatus::PendingValidation,
                'reported_at' => now(),
                'planned_at' => $schedule->next_due_on->copy()->startOfDay(),
                'provider_name' => $schedule->provider_name,
            ]);
            $this->record($actor, 'request.created', 'Demande préventive créée à partir du programme '.$schedule->reference.'.', $request, to: $request->status->value, schedule: $schedule);

            return $request;
        });
    }

    public function validateRequest(MaintenanceRequest $request, User $actor, bool $approved, ?string $observation = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($request, $actor, $approved, $observation): MaintenanceRequest {
            $request = MaintenanceRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if (! in_array($request->status, [MaintenanceRequestStatus::New, MaintenanceRequestStatus::PendingValidation], true)) {
                throw ValidationException::withMessages(['status' => 'Seule une demande nouvelle ou en attente de validation peut être contrôlée.']);
            }
            $old = $request->status->value;
            $request->forceFill([
                'status' => $approved ? MaintenanceRequestStatus::Approved : MaintenanceRequestStatus::Rejected,
                'validated_by' => $actor->getKey(),
                'validated_at' => now(),
                'observations' => $observation,
            ])->save();
            $this->record($actor, $approved ? 'request.approved' : 'request.rejected', $observation, $request, $old, $request->status->value);

            return $request->refresh();
        });
    }

    public function assign(MaintenanceRequest $request, User $actor, ?Agent $agent, ?string $provider, string $plannedAt): MaintenanceIntervention
    {
        return DB::transaction(function () use ($request, $actor, $agent, $provider, $plannedAt): MaintenanceIntervention {
            $request = MaintenanceRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($request->status !== MaintenanceRequestStatus::Approved) {
                throw ValidationException::withMessages(['status' => 'La demande doit être validée avant sa planification.']);
            }
            $old = $request->status->value;
            $previousAgent = $request->assignedAgent?->last_name.' '.$request->assignedAgent?->first_name;
            $request->forceFill([
                'assigned_agent_id' => $agent?->getKey(),
                'assigned_by' => $actor->getKey(),
                'provider_name' => $provider,
                'planned_at' => $plannedAt,
                'status' => MaintenanceRequestStatus::Planned,
            ])->save();

            $intervention = MaintenanceIntervention::query()->create([
                'request_id' => $request->getKey(),
                'schedule_id' => $request->schedule_id,
                'performed_by' => $agent?->getKey(),
                'created_by' => $actor->getKey(),
                'reference' => $this->reference('INT'),
                'status' => 'planned',
                'planned_at' => $plannedAt,
            ]);
            $this->record($actor, 'request.assigned', 'Responsable et date d’intervention enregistrés.', $request, $old, $request->status->value, $intervention, changes: [
                'responsable' => ['old' => trim((string) $previousAgent) ?: null, 'new' => $agent ? trim($agent->last_name.' '.$agent->first_name) : $provider],
                'date_prevue' => ['new' => $plannedAt],
            ]);

            return $intervention;
        });
    }

    public function start(MaintenanceIntervention $intervention, User $actor): MaintenanceIntervention
    {
        return DB::transaction(function () use ($intervention, $actor): MaintenanceIntervention {
            $intervention = MaintenanceIntervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            if (! in_array($intervention->status, ['planned', 'suspended'], true)) {
                throw ValidationException::withMessages(['status' => 'Cette intervention ne peut pas être démarrée dans son état actuel.']);
            }
            $request = $intervention->request;
            $old = $request?->status->value;
            $intervention->forceFill(['status' => 'in_progress', 'started_at' => $intervention->started_at ?: now()])->save();
            if ($request) {
                $request->forceFill(['status' => MaintenanceRequestStatus::InProgress])->save();
                if ($request->type === 'corrective') {
                    $asset = $request->asset;
                    $current = $asset->activeAssignment;
                    $asset->forceFill(['stock_state' => 'under_repair'])->save();
                    $this->stock->recordAssetMovement($asset, 'repair_dispatch', $actor, 'Sortie temporaire du bien pour réparation.', $asset->location_id, $asset->location_id, $current?->agent_id, $request->assigned_agent_id, $current?->service_id, null);
                }
            }
            $this->record($actor, 'intervention.started', 'Intervention démarrée.', $request, $old, $request?->status->value, $intervention, $intervention->schedule);

            return $intervention->refresh();
        });
    }

    public function suspend(MaintenanceIntervention $intervention, User $actor, ?string $reason): MaintenanceIntervention
    {
        return DB::transaction(function () use ($intervention, $actor, $reason): MaintenanceIntervention {
            $intervention = MaintenanceIntervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            if ($intervention->status !== 'in_progress') {
                throw ValidationException::withMessages(['status' => 'Seule une intervention en cours peut être suspendue.']);
            }
            $request = $intervention->request;
            $old = $request?->status->value;
            $intervention->forceFill(['status' => 'suspended', 'observations' => $reason])->save();
            $request?->forceFill(['status' => MaintenanceRequestStatus::Suspended])->save();
            $this->record($actor, 'intervention.suspended', $reason, $request, $old, $request?->status->value, $intervention, $intervention->schedule);

            return $intervention->refresh();
        });
    }

    public function finish(MaintenanceIntervention $intervention, User $actor, array $data): MaintenanceIntervention
    {
        return DB::transaction(function () use ($intervention, $actor, $data): MaintenanceIntervention {
            $intervention = MaintenanceIntervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            if (! in_array($intervention->status, ['in_progress', 'suspended'], true)) {
                throw ValidationException::withMessages(['status' => 'Seule une intervention en cours ou suspendue peut être remise au contrôle.']);
            }
            $request = $intervention->request;
            $old = $request?->status->value;
            $intervention->forceFill([...$data, 'status' => 'completed', 'completed_at' => now()])->save();
            $request?->forceFill(['status' => MaintenanceRequestStatus::PendingClosure, 'technical_diagnosis' => $data['diagnosis'] ?? null, 'resolution_notes' => $data['work_performed']])->save();
            $this->record($actor, 'intervention.reported', 'Compte rendu d’intervention soumis au contrôle.', $request, $old, $request?->status->value, $intervention, $intervention->schedule, changes: [
                'main_oeuvre' => $data['labor_cost'], 'pieces' => $data['parts_cost'], 'travaux' => $data['work_performed'],
            ]);

            return $intervention->refresh();
        });
    }

    public function closeRequest(MaintenanceRequest $request, User $actor, ?string $observation): MaintenanceRequest
    {
        return DB::transaction(function () use ($request, $actor, $observation): MaintenanceRequest {
            $request = MaintenanceRequest::query()->lockForUpdate()->with('intervention')->findOrFail($request->getKey());
            if ($request->status !== MaintenanceRequestStatus::PendingClosure || ! $request->intervention || $request->intervention->status !== 'completed') {
                throw ValidationException::withMessages(['status' => 'Un compte rendu terminé est requis avant la clôture administrative.']);
            }
            $old = $request->status->value;
            $request->forceFill(['status' => MaintenanceRequestStatus::Completed, 'completed_at' => now(), 'observations' => $observation ?: $request->observations])->save();
            $schedule = $request->schedule;
            if ($schedule) {
                $last = now()->startOfDay();
                $schedule->forceFill([
                    'last_completed_on' => $last->toDateString(),
                    'next_due_on' => MaintenanceFrequency::from($schedule->frequency)->next($last)->toDateString(),
                ])->save();
            }
            if ($request->type === 'corrective') {
                $asset = $request->asset;
                $asset->forceFill(['stock_state' => 'awaiting_inspection'])->save();
                $this->stock->recordAssetMovement($asset, 'repair_return', $actor, 'Retour de réparation enregistré; vérification de disponibilité requise.', $asset->location_id, $asset->location_id, $request->assigned_agent_id, null);
            }
            $this->record($actor, 'request.closed', 'Demande clôturée après contrôle du compte rendu.', $request, $old, $request->status->value, $request->intervention, $schedule);

            return $request->refresh();
        });
    }

    public function cancel(MaintenanceRequest $request, User $actor, ?string $reason): MaintenanceRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): MaintenanceRequest {
            $request = MaintenanceRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if (! $request->status->isOpen() || $request->status === MaintenanceRequestStatus::PendingClosure) {
                throw ValidationException::withMessages(['status' => 'Cette demande est déjà terminée ou en attente de clôture.']);
            }
            $old = $request->status->value;
            $request->forceFill(['status' => MaintenanceRequestStatus::Cancelled, 'observations' => $reason])->save();
            if ($request->intervention && in_array($request->intervention->status, ['planned', 'in_progress', 'suspended'], true)) {
                $wasDispatched = in_array($request->intervention->status, ['in_progress', 'suspended'], true);
                $request->intervention->forceFill(['status' => 'cancelled'])->save();
                if ($wasDispatched && $request->type === 'corrective') {
                    $asset = $request->asset;
                    $asset->forceFill(['stock_state' => 'awaiting_inspection'])->save();
                    $this->stock->recordAssetMovement($asset, 'repair_return', $actor, 'Opération de réparation annulée; vérification de disponibilité requise.', $asset->location_id, $asset->location_id, $request->assigned_agent_id, null);
                }
            }
            $this->record($actor, 'request.cancelled', $reason, $request, $old, $request->status->value, $request->intervention, $request->schedule);

            return $request->refresh();
        });
    }

    private function record(User $actor, string $event, ?string $description, ?MaintenanceRequest $request = null, ?string $from = null, ?string $to = null, ?MaintenanceIntervention $intervention = null, ?MaintenanceSchedule $schedule = null, array $changes = []): void
    {
        MaintenanceHistory::query()->create([
            'request_id' => $request?->getKey(),
            'schedule_id' => $schedule?->getKey() ?: $request?->schedule_id,
            'intervention_id' => $intervention?->getKey(),
            'actor_id' => $actor->getKey(),
            'event_type' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'description' => $description,
            'changes' => $changes ?: null,
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}

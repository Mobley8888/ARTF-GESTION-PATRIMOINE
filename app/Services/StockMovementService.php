<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Article;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function receive(Article $article, string $quantity, Location $location, User $actor, string $source, string $reason, string $receivedOn, ?string $documentReference = null, ?string $unitCost = null): StockMovement
    {
        return DB::transaction(function () use ($article, $quantity, $location, $actor, $source, $reason, $receivedOn, $documentReference, $unitCost): StockMovement {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $this->assertQuantityArticle($article);
            $this->assertLocation($location);
            $units = $this->toUnits($quantity);
            if ($units <= 0) {
                throw ValidationException::withMessages(['quantity' => 'La quantité reçue doit être supérieure à zéro.']);
            }
            $movement = $this->movement($actor, 'receipt', $reason, [
                'to_location_id' => $location->getKey(), 'source' => $source, 'document_reference' => $documentReference,
                'occurred_at' => $receivedOn.' 12:00:00',
            ]);
            $lot = $this->createLot($article, $location, $movement, $units, $receivedOn, $source, $documentReference, $unitCost);
            $movement->lines()->create(['article_id' => $article->getKey(), 'lot_id' => $lot->getKey(), 'to_location_id' => $location->getKey(), 'quantity' => $this->fromUnits($units), 'unit_cost' => $unitCost]);

            return $movement->load('lines.article', 'lines.lot', 'toLocation');
        }, attempts: 3);
    }

    public function issue(Article $article, string $quantity, Location $location, User $actor, string $reason, ?string $documentReference = null): StockMovement
    {
        return DB::transaction(function () use ($article, $quantity, $location, $actor, $reason, $documentReference): StockMovement {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $this->assertQuantityArticle($article);
            $this->assertLocation($location);
            $requested = $this->toUnits($quantity);
            if ($requested <= 0) {
                throw ValidationException::withMessages(['quantity' => 'La quantité à sortir doit être supérieure à zéro.']);
            }
            $allocations = $this->allocate($article, $location, $requested);
            $movement = $this->movement($actor, 'issue', $reason, ['from_location_id' => $location->getKey(), 'document_reference' => $documentReference]);
            foreach ($allocations as [$lot, $units]) {
                $lot->forceFill(['quantity_available' => $this->fromUnits($this->toUnits($lot->quantity_available) - $units)])->save();
                $movement->lines()->create(['article_id' => $article->getKey(), 'lot_id' => $lot->getKey(), 'from_location_id' => $location->getKey(), 'quantity' => $this->fromUnits($units), 'unit_cost' => $lot->unit_cost]);
            }

            return $movement->load('lines.article', 'lines.lot', 'fromLocation');
        }, attempts: 3);
    }

    public function transfer(Article $article, string $quantity, Location $from, Location $to, User $actor, string $reason, ?string $documentReference = null): StockMovement
    {
        return DB::transaction(function () use ($article, $quantity, $from, $to, $actor, $reason, $documentReference): StockMovement {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $this->assertQuantityArticle($article);
            $this->assertLocation($from);
            $this->assertLocation($to);
            if ($from->is($to)) {
                throw ValidationException::withMessages(['to_location_id' => 'La localisation de destination doit être différente.']);
            }
            $requested = $this->toUnits($quantity);
            if ($requested <= 0) {
                throw ValidationException::withMessages(['quantity' => 'La quantité à transférer doit être supérieure à zéro.']);
            }
            $allocations = $this->allocate($article, $from, $requested);
            $movement = $this->movement($actor, 'transfer', $reason, ['from_location_id' => $from->getKey(), 'to_location_id' => $to->getKey(), 'document_reference' => $documentReference]);
            foreach ($allocations as [$sourceLot, $units]) {
                $remaining = $this->toUnits($sourceLot->quantity_available) - $units;
                $sourceLot->forceFill(['quantity_available' => $this->fromUnits($remaining)])->save();
                $destinationLot = $this->createLot($article, $to, $movement, $units, $sourceLot->received_on->toDateString(), $sourceLot->source, $sourceLot->document_reference, $sourceLot->unit_cost);
                $movement->lines()->create([
                    'article_id' => $article->getKey(), 'lot_id' => $sourceLot->getKey(), 'destination_lot_id' => $destinationLot->getKey(),
                    'from_location_id' => $from->getKey(), 'to_location_id' => $to->getKey(), 'quantity' => $this->fromUnits($units), 'unit_cost' => $sourceLot->unit_cost,
                ]);
            }

            return $movement->load('lines.article', 'lines.lot', 'lines.destinationLot', 'fromLocation', 'toLocation');
        }, attempts: 3);
    }

    public function reconcile(Article $article, Location $location, string $observedQuantity, User $actor, string $reason): StockMovement
    {
        return DB::transaction(function () use ($article, $location, $observedQuantity, $actor, $reason): StockMovement {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $this->assertQuantityArticle($article);
            $this->assertLocation($location);
            $observed = $this->toUnits($observedQuantity);
            if ($observed < 0) {
                throw ValidationException::withMessages(['observed_quantity' => 'La quantité constatée ne peut pas être négative.']);
            }
            $lots = StockLot::query()->where('article_id', $article->getKey())->where('location_id', $location->getKey())->lockForUpdate()->get();
            $expected = $lots->sum(fn (StockLot $lot): int => $this->toUnits($lot->quantity_available));
            if ($observed === $expected) {
                throw ValidationException::withMessages(['observed_quantity' => 'Le stock théorique correspond déjà à la quantité constatée.']);
            }
            $movement = $this->movement($actor, 'adjustment', $reason, [
                'from_location_id' => $observed < $expected ? $location->getKey() : null,
                'to_location_id' => $observed > $expected ? $location->getKey() : null,
                'notes' => 'Rapprochement inventaire physique : théorique '.$this->fromUnits($expected).', constaté '.$this->fromUnits($observed).'.',
            ]);
            if ($observed > $expected) {
                $delta = $observed - $expected;
                $lot = $this->createLot($article, $location, $movement, $delta, today()->toDateString(), 'Régularisation inventaire physique', null, null);
                $movement->lines()->create(['article_id' => $article->getKey(), 'lot_id' => $lot->getKey(), 'to_location_id' => $location->getKey(), 'quantity' => $this->fromUnits($delta)]);
            } else {
                foreach ($this->allocate($article, $location, $expected - $observed) as [$lot, $units]) {
                    $lot->forceFill(['quantity_available' => $this->fromUnits($this->toUnits($lot->quantity_available) - $units)])->save();
                    $movement->lines()->create(['article_id' => $article->getKey(), 'lot_id' => $lot->getKey(), 'from_location_id' => $location->getKey(), 'quantity' => $this->fromUnits($units)]);
                }
            }

            return $movement->load('lines.article', 'lines.lot');
        }, attempts: 3);
    }

    public function recordAssetMovement(Asset $asset, string $type, User $actor, string $reason, ?int $fromLocationId = null, ?int $toLocationId = null, ?int $fromAgentId = null, ?int $toAgentId = null, ?int $fromServiceId = null, ?int $toServiceId = null, ?string $documentReference = null, ?string $occurredAt = null): StockMovement
    {
        $movement = $this->movement($actor, $type, $reason, [
            'from_location_id' => $fromLocationId, 'to_location_id' => $toLocationId,
            'from_agent_id' => $fromAgentId, 'to_agent_id' => $toAgentId,
            'from_service_id' => $fromServiceId, 'to_service_id' => $toServiceId,
            'document_reference' => $documentReference, 'occurred_at' => $occurredAt ?: now(),
        ]);
        $movement->lines()->create([
            'asset_id' => $asset->getKey(), 'quantity' => '1.000',
            'from_location_id' => $fromLocationId, 'to_location_id' => $toLocationId,
            'from_agent_id' => $fromAgentId, 'to_agent_id' => $toAgentId,
            'from_service_id' => $fromServiceId, 'to_service_id' => $toServiceId,
            'unit_cost' => $asset->acquisition_value,
        ]);

        return $movement;
    }

    public function registerAssetEntry(Asset $asset, User $actor): StockMovement
    {
        $state = match ($asset->usage_status) {
            'broken' => 'defective',
            'under_repair' => 'under_repair',
            'retired' => 'written_off',
            default => match ($asset->status) {
                AssetStatus::Retired, AssetStatus::Disposed => 'written_off',
                AssetStatus::Lost => 'lost',
                default => 'available',
            },
        };
        $asset->forceFill(['stock_state' => $state])->save();

        return $this->recordAssetMovement($asset, 'asset_entry', $actor, 'Entrée initiale du bien individualisé dans le patrimoine.', toLocationId: $asset->location_id, documentReference: $asset->invoice_reference ?: $asset->delivery_reference, occurredAt: $asset->acquired_on?->toDateString());
    }

    public function assertAssetAssignable(Asset $asset): void
    {
        if ($asset->stock_state !== null && $asset->stock_state !== 'available') {
            throw ValidationException::withMessages(['asset_id' => 'Ce bien n’est pas disponible : état de stock « '.$this->assetStateLabel($asset->stock_state).' ».']);
        }
        if ($asset->status !== AssetStatus::InService || ! in_array($asset->usage_status, [null, 'in_service'], true)) {
            throw ValidationException::withMessages(['asset_id' => 'Un bien hors service, défectueux ou en réparation ne peut pas être doté.']);
        }
        if ($asset->maintenanceRequests()->whereIn('status', ['new', 'pending_validation', 'approved', 'planned', 'in_progress', 'suspended', 'pending_closure'])->exists()) {
            throw ValidationException::withMessages(['asset_id' => 'Une demande de maintenance est ouverte pour ce bien. Vérifiez sa remise en service avant la dotation.']);
        }
    }

    public function availableAssetsQuery(): Builder
    {
        return Asset::query()->where('status', AssetStatus::InService->value)
            ->where(fn (Builder $usage) => $usage->whereNull('usage_status')->orWhere('usage_status', 'in_service'))
            ->where(fn (Builder $query) => $query->where('stock_state', 'available')->orWhere(function (Builder $legacy): void {
                $legacy->whereNull('stock_state')->where(fn (Builder $usage) => $usage->whereNull('usage_status')->orWhere('usage_status', 'in_service'));
            }))
            ->whereDoesntHave('assignments', fn (Builder $assignment) => $assignment->whereNull('ended_at'))
            ->whereDoesntHave('maintenanceRequests', fn (Builder $maintenance) => $maintenance->whereIn('status', ['new', 'pending_validation', 'approved', 'planned', 'in_progress', 'suspended', 'pending_closure']));
    }

    public function verifyAssetState(Asset $asset, string $state, User $actor, string $reason): Asset
    {
        return DB::transaction(function () use ($asset, $state, $actor, $reason): Asset {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());
            if ($asset->assignments()->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['asset' => 'Un bien affecté doit être restitué avant de modifier sa disponibilité.']);
            }
            if ($state === 'available') {
                $this->assertAssetAssignable($asset->setAttribute('stock_state', null));
            }
            $previous = $asset->stock_state ?: 'available_legacy';
            $asset->forceFill(['stock_state' => $state])->save();
            $type = $state === 'written_off' ? 'reform' : ($state === 'lost' ? 'loss' : 'adjustment');
            $movement = $this->recordAssetMovement($asset, $type, $actor, $reason, $asset->location_id, $asset->location_id, documentReference: null, occurredAt: now()->toDateTimeString());
            $movement->forceFill(['notes' => 'État de stock : '.$this->assetStateLabel($previous).' → '.$this->assetStateLabel($state)])->saveQuietly();

            return $asset->refresh();
        }, attempts: 3);
    }

    public function assetStateLabel(?string $state): string
    {
        return ['available' => 'Disponible', 'assigned' => 'Affecté', 'awaiting_inspection' => 'En attente de vérification', 'damaged' => 'Endommagé', 'defective' => 'Défectueux', 'under_repair' => 'En réparation', 'written_off' => 'Réformé', 'lost' => 'Perdu', 'available_legacy' => 'Disponibilité héritée à vérifier'][$state ?? ''] ?? 'Non disponible';
    }

    public function effectiveAssetState(Asset $asset): string
    {
        if ($asset->stock_state !== null) {
            return $asset->stock_state;
        }
        if ($asset->activeAssignment) {
            return 'assigned';
        }
        if (in_array($asset->status, [AssetStatus::Retired, AssetStatus::Disposed], true) || $asset->usage_status === 'retired') {
            return 'written_off';
        }
        if ($asset->status === AssetStatus::Lost || $asset->usage_status === 'lost') {
            return 'lost';
        }
        if ($asset->usage_status === 'under_repair') {
            return 'under_repair';
        }
        if ($asset->usage_status === 'broken') {
            return 'defective';
        }
        if ($asset->maintenanceRequests()->whereIn('status', ['in_progress', 'suspended'])->exists()) {
            return 'under_repair';
        }

        return 'available_legacy';
    }

    private function allocate(Article $article, Location $location, int $requested): array
    {
        $lots = StockLot::query()->where('article_id', $article->getKey())->where('location_id', $location->getKey())
            ->where('quantity_available', '>', 0)->orderBy('received_on')->orderBy('id')->lockForUpdate()->get();
        $available = $lots->sum(fn (StockLot $lot): int => $this->toUnits($lot->quantity_available));
        if ($available < $requested) {
            throw ValidationException::withMessages(['quantity' => 'Stock insuffisant à cette localisation. Disponible : '.$this->fromUnits($available).'.']);
        }
        $remaining = $requested;
        $allocations = [];
        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }
            $take = min($remaining, $this->toUnits($lot->quantity_available));
            $allocations[] = [$lot, $take];
            $remaining -= $take;
        }

        return $allocations;
    }

    private function createLot(Article $article, Location $location, StockMovement $movement, int $quantity, string $receivedOn, ?string $source, ?string $documentReference, ?string $unitCost): StockLot
    {
        return StockLot::query()->create([
            'article_id' => $article->getKey(), 'location_id' => $location->getKey(), 'source_movement_id' => $movement->getKey(),
            'reference' => $this->reference('LOT'), 'received_on' => $receivedOn,
            'quantity_received' => $this->fromUnits($quantity), 'quantity_available' => $this->fromUnits($quantity),
            'unit_cost' => $unitCost, 'source' => $source, 'document_reference' => $documentReference,
        ]);
    }

    private function movement(User $actor, string $type, string $reason, array $data = []): StockMovement
    {
        return StockMovement::query()->create([
            ...$data,
            'actor_id' => $actor->getKey(), 'validated_by' => $actor->getKey(),
            'reference' => $this->reference('MVT'), 'type' => $type, 'status' => 'validated',
            'occurred_at' => $data['occurred_at'] ?? now(), 'reason' => $reason,
        ]);
    }

    private function assertQuantityArticle(Article $article): void
    {
        if (! $article->is_active) {
            throw ValidationException::withMessages(['article_id' => 'Cet article est inactif.']);
        }
    }

    private function assertLocation(Location $location): void
    {
        if (! $location->is_active) {
            throw ValidationException::withMessages(['location_id' => 'La localisation doit être active.']);
        }
    }

    private function toUnits(string|int|float $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function fromUnits(int $units): string
    {
        return number_format($units / 1000, 3, '.', '');
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}

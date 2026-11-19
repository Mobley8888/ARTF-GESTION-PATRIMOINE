<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AdministrativeDocumentService;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockMovementService $stock, private readonly AdministrativeDocumentService $documents) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['receipt', 'issue', 'transfer', 'adjustment', 'asset_entry', 'asset_assignment', 'asset_transfer', 'asset_return', 'physical_transfer', 'repair_dispatch', 'repair_return', 'reform', 'loss'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $movementQuery = StockMovement::query()->with(['lines.article', 'lines.asset', 'lines.lot', 'actor', 'validator', 'fromLocation', 'toLocation'])
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '<=', $date))
            ->when($filters['location_id'] ?? null, fn (Builder $q, string $id) => $q->where(fn (Builder $where) => $where->where('from_location_id', $id)->orWhere('to_location_id', $id)))
            ->when($filters['category_id'] ?? null, fn (Builder $q, string $id) => $q->where(fn (Builder $where) => $where->whereHas('lines.article', fn (Builder $article) => $article->where('category_id', $id))
                ->orWhereHas('lines.asset.category', fn (Builder $category) => $category->whereKey($id))))
            ->when($filters['service_id'] ?? null, fn (Builder $q, string $id) => $q->where(fn (Builder $where) => $where->where('from_service_id', $id)->orWhere('to_service_id', $id)))
            ->when($filters['direction_id'] ?? null, fn (Builder $q, string $id) => $q->where(fn (Builder $where) => $where->whereHas('lines.fromAgent.service', fn (Builder $service) => $service->where('direction_id', $id))
                ->orWhereHas('lines.toAgent.service', fn (Builder $service) => $service->where('direction_id', $id))
                ->orWhereHas('lines.fromService', fn (Builder $service) => $service->where('direction_id', $id))
                ->orWhereHas('lines.toService', fn (Builder $service) => $service->where('direction_id', $id))));
        $movements = $movementQuery->latest('occurred_at')->paginate(20)->withQueryString();

        $allAssets = Asset::query();
        $stats = [
            'initial' => (float) StockMovement::query()->where('type', 'receipt')->where('status', 'validated')->join('stock_movement_lines', 'stock_movements.id', '=', 'stock_movement_lines.movement_id')->sum('stock_movement_lines.quantity'),
            'incoming' => (float) StockMovement::query()->whereIn('type', ['receipt', 'asset_entry', 'repair_return'])->where('status', 'validated')->join('stock_movement_lines', 'stock_movements.id', '=', 'stock_movement_lines.movement_id')->sum('stock_movement_lines.quantity'),
            'outgoing' => (float) StockMovement::query()->whereIn('type', ['issue', 'repair_dispatch', 'reform', 'loss'])->where('status', 'validated')->join('stock_movement_lines', 'stock_movements.id', '=', 'stock_movement_lines.movement_id')->sum('stock_movement_lines.quantity'),
            'available_quantity' => (float) \App\Models\StockLot::query()->sum('quantity_available'),
            'assigned_assets' => (clone $allAssets)->where('stock_state', 'assigned')->count(),
            'awaiting_inspection' => (clone $allAssets)->where('stock_state', 'awaiting_inspection')->count(),
            'damaged' => (clone $allAssets)->whereIn('stock_state', ['damaged', 'defective'])->count(),
            'under_repair' => (clone $allAssets)->where('stock_state', 'under_repair')->count(),
            'written_off' => (clone $allAssets)->whereIn('stock_state', ['written_off', 'lost'])->count(),
            'available_assets' => $this->stock->availableAssetsQuery()->count(),
        ];
        $articles = Article::query()->withAvailability()->with('category')->orderBy('name')->paginate(8, ['*'], 'articles_page');
        $assets = Asset::query()->with(['category', 'location', 'activeAssignment.agent', 'activeAssignment.service'])->orderBy('asset_code')->paginate(8, ['*'], 'assets_page');

        return view('admin.stock.index', [
            'filters' => $filters, 'stats' => $stats, 'movements' => $movements, 'articles' => $articles, 'assets' => $assets,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function articles(): View
    {
        return view('admin.stock.articles', [
            'articles' => Article::query()->withAvailability()->with('category')->orderBy('name')->paginate(25),
        ]);
    }

    public function createArticle(): View
    {
        return view('admin.stock.create-article', ['categories' => Category::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:60', 'unique:articles,sku'],
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:32'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999999999.999'],
        ]);
        $article = Article::query()->create([...$data, 'is_active' => true]);

        return to_route('stock.articles.show', $article)->with('status', 'Article de stock créé. Enregistrez une entrée pour initialiser sa quantité.');
    }

    public function showArticle(Article $article): View
    {
        $article->load(['category', 'lots.location', 'lots.sourceMovement.actor']);
        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();
        $movements = $article->movementLines()->with(['movement.actor', 'movement.fromLocation', 'movement.toLocation'])->latest()->paginate(25);

        return view('admin.stock.show-article', compact('article', 'locations', 'movements'));
    }

    public function receive(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999999.999', 'decimal:0,3'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'source' => ['required', 'string', 'max:180'], 'reason' => ['required', 'string', 'max:180'],
            'received_on' => ['required', 'date', 'before_or_equal:today'], 'document_reference' => ['nullable', 'string', 'max:120'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99', 'decimal:0,2'],
        ]);
        $movement = $this->stock->receive($article, $data['quantity'], Location::query()->findOrFail($data['location_id']), $request->user(), $data['source'], $data['reason'], $data['received_on'], $data['document_reference'] ?? null, $data['unit_cost'] ?? null);

        return to_route('stock.articles.show', $article)->with('status', 'Entrée stockée sous '.$movement->reference.'.');
    }

    public function issue(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999999.999', 'decimal:0,3'],
            'location_id' => ['required', 'integer', 'exists:locations,id'], 'reason' => ['required', 'string', 'max:180'],
            'document_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $movement = $this->stock->issue($article, $data['quantity'], Location::query()->findOrFail($data['location_id']), $request->user(), $data['reason'], $data['document_reference'] ?? null);

        return to_route('stock.articles.show', $article)->with('status', 'Sortie validée selon FIFO sous '.$movement->reference.'.');
    }

    public function transfer(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999999.999', 'decimal:0,3'],
            'from_location_id' => ['required', 'integer', 'exists:locations,id'], 'to_location_id' => ['required', 'integer', 'different:from_location_id', 'exists:locations,id'],
            'reason' => ['required', 'string', 'max:180'], 'document_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $movement = $this->stock->transfer($article, $data['quantity'], Location::query()->findOrFail($data['from_location_id']), Location::query()->findOrFail($data['to_location_id']), $request->user(), $data['reason'], $data['document_reference'] ?? null);

        return to_route('stock.articles.show', $article)->with('status', 'Transfert validé sous '.$movement->reference.'. Les lots et leur ordre FIFO sont conservés.');
    }

    public function reconcile(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'], 'observed_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999999.999', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:180'],
        ]);
        $movement = $this->stock->reconcile($article, Location::query()->findOrFail($data['location_id']), $data['observed_quantity'], $request->user(), $data['reason']);

        return to_route('stock.articles.show', $article)->with('status', 'Rapprochement enregistré par le mouvement '.$movement->reference.'.');
    }

    public function verifyAsset(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'stock_state' => ['required', Rule::in(['available', 'awaiting_inspection', 'damaged', 'defective', 'under_repair', 'written_off', 'lost'])],
            'reason' => ['required', 'string', 'max:180'],
        ]);
        $this->stock->verifyAssetState($asset, $data['stock_state'], $request->user(), $data['reason']);

        return back()->with('status', 'État de stock mis à jour et mouvement de régularisation historisé.');
    }

    public function movementPdf(StockMovement $movement)
    {
        $movement->load(['lines.article', 'lines.asset', 'lines.lot', 'actor', 'validator', 'fromLocation', 'toLocation']);
        $rows = $movement->lines->map(function ($line): string {
            $item = $line->asset ? $line->asset->asset_code.' — '.$line->asset->designation : $line->article?->sku.' — '.$line->article?->name;

            return $item.' | Qté '.$line->quantity.' | lot '.($line->lot?->reference ?? 'bien individualisé');
        })->implode("\n");

        return $this->documents->pdf($this->movementTitle($movement->type), $movement->reference, [
            'Mouvement' => ['Type' => $this->movementTitle($movement->type), 'Date' => $movement->occurred_at->format('d/m/Y H:i'), 'Statut' => 'Validé', 'Enregistré par' => $movement->actor?->name, 'Validé par' => $movement->validator?->name],
            'Origine et destination' => ['Localisation de départ' => $movement->fromLocation?->name, 'Localisation d’arrivée' => $movement->toLocation?->name, 'Référence justificative' => $movement->document_reference, 'Provenance' => $movement->source, 'Motif' => $movement->reason],
            'Lignes' => ['Biens / articles' => $rows ?: 'Aucune ligne enregistrée.', 'Observations' => $movement->notes],
        ], 'bon-stock-'.$movement->reference.'.pdf');
    }

    public function journalExcel(Request $request)
    {
        $filters = $request->validate(['type' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $movements = StockMovement::query()->with('lines.article', 'lines.asset')->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))->when($filters['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '>=', $date))->when($filters['to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '<=', $date))->orderBy('occurred_at')->get();
        $rows = $movements->flatMap(fn (StockMovement $movement) => $movement->lines->map(fn ($line) => [$movement->reference, $this->movementTitle($movement->type), $movement->occurred_at->format('d/m/Y H:i'), $line->asset?->asset_code ?: $line->article?->sku, $line->asset?->designation ?: $line->article?->name, $line->quantity, $movement->fromLocation?->name, $movement->toLocation?->name, $movement->actor?->name, $movement->validator?->name, $movement->document_reference, $movement->reason]))->all();

        return $this->documents->spreadsheet('Journal des mouvements de stock', ['Référence', 'Type', 'Date', 'Bien / SKU', 'Désignation', 'Quantité', 'Origine', 'Destination', 'Opérateur', 'Validateur', 'Justificatif', 'Motif'], $rows, 'journal-stock.xls');
    }

    private function movementTitle(string $type): string
    {
        return ['receipt' => 'Bon d’entrée en stock', 'issue' => 'Bon de sortie', 'transfer' => 'Bon de transfert', 'adjustment' => 'Bon de régularisation', 'asset_entry' => 'Entrée d’un bien individualisé', 'asset_assignment' => 'Bon de dotation / affectation', 'asset_transfer' => 'Transfert de bien individualisé', 'asset_return' => 'Bon de restitution', 'physical_transfer' => 'Déplacement physique', 'repair_dispatch' => 'Sortie pour réparation', 'repair_return' => 'Réintégration après réparation', 'reform' => 'Procès-verbal de réforme', 'loss' => 'Déclaration de perte'][$type] ?? $type;
    }
}

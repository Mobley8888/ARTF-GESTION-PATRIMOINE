@extends('admin.layout')

@section('title', 'Articles en stock')

@section('content')
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4"><div><a class="small" href="{{ route('stock.index') }}">← Stock patrimonial</a><h1 class="h3 mt-2">Articles gérés en quantité</h1></div>@can('manage-stock')<a class="btn btn-primary" href="{{ route('stock.articles.create') }}">Créer un article</a>@endcan</div>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <section class="card content-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Code / article</th><th>Catégorie</th><th>Quantité disponible</th><th>Seuil minimum</th><th>Lots</th></tr></thead><tbody>@forelse ($articles as $article)<tr><td><a href="{{ route('stock.articles.show', $article) }}" class="fw-semibold">{{ $article->sku }} · {{ $article->name }}</a><div class="small text-secondary">{{ $article->unit }}</div></td><td>{{ $article->category?->name ?? 'Non classé' }}</td><td>{{ number_format((float) ($article->quantity_available ?? 0), 3, ',', ' ') }} {{ $article->unit }}</td><td>{{ number_format((float) $article->minimum_stock, 3, ',', ' ') }} {{ $article->unit }}</td><td>{{ $article->lots_count ?? $article->lots()->count() }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-5">Aucun article enregistré.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $articles->links() }}</div></section>
@endsection

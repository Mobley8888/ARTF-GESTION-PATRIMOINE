@extends('admin.layout')

@section('title', $title.' — '.$reference)

@section('content')
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3 no-print">
        <a class="btn btn-outline-secondary" href="{{ url()->previous() }}">Retour</a>
        <div class="d-flex gap-2"><button class="btn btn-outline-primary" type="button" onclick="window.print()">Imprimer</button><a class="btn btn-primary" href="{{ $pdfUrl }}">Télécharger PDF</a></div>
    </div>

    <article class="admin-document bg-white mx-auto p-4 p-md-5">
        <header class="document-header d-flex justify-content-between align-items-start gap-3 pb-3 mb-4 border-bottom">
            <div class="document-logo border rounded d-flex align-items-center justify-content-center text-center">Emplacement<br>du logo ARTF</div>
            <div class="text-end"><div class="text-uppercase fw-bold">République du Congo</div><div class="fw-semibold">Agence de Régulation des Transferts de Fonds (ARTF)</div><div class="small text-secondary">Gestion du patrimoine</div></div>
        </header>
        <div class="d-flex justify-content-between gap-3 mb-4"><div><h1 class="h3 mb-1">{{ $title }}</h1><div class="text-secondary">Référence : {{ $reference }}</div></div><div class="text-end small">Édité le<br><strong>{{ $issuedAt->format('d/m/Y H:i') }}</strong></div></div>
        @foreach ($sections as $heading => $entries)
            <section class="mb-4"><h2 class="h6 text-uppercase border-bottom pb-2 mb-3">{{ $heading }}</h2><dl class="row mb-0">
                @foreach ($entries as $label => $value)
                    <dt class="col-sm-4 fw-semibold">{{ $label }}</dt><dd class="col-sm-8">@if (is_array($value))@forelse ($value as $line)<div>{{ $line }}</div>@empty<span class="text-secondary">Non renseigné</span>@endforelse @else{{ $value !== null && $value !== '' ? $value : 'Non renseigné' }}@endif</dd>
                @endforeach
            </dl></section>
        @endforeach
        <section class="document-signatures mt-5 pt-3 border-top"><div class="row text-center g-4"><div class="col-6"><strong>Établi par</strong><div class="signature-space"></div>Nom, fonction et signature</div><div class="col-6"><strong>Visa / bénéficiaire</strong><div class="signature-space"></div>Nom, fonction et signature</div></div><div class="mt-4"><strong>Observations</strong><div class="observation-space"></div></div></section>
        <footer class="document-footer small text-secondary border-top mt-4 pt-2 d-flex justify-content-between"><span>ARTF — Gestion du patrimoine</span><span>{{ $reference }} · {{ $issuedAt->format('d/m/Y') }}</span></footer>
    </article>
    <style>
        .admin-document{max-width:900px;min-height:1050px;box-shadow:0 0.125rem 0.5rem #0002;color:#1f2937}.document-logo{width:110px;height:76px;font-size:.75rem;color:#64748b}.signature-space{height:90px}.observation-space{height:55px;border-bottom:1px solid #adb5bd}
        @page{size:A4;margin:14mm}@media print{body{background:#fff!important}.sidebar,.topbar,main>footer,.no-print{display:none!important}.admin-document{max-width:none;min-height:0;box-shadow:none;padding:0!important}.py-4.py-lg-5{padding:0!important}main{width:100%!important}.document-signatures{break-inside:avoid}section{break-inside:avoid}}
    </style>
@endsection

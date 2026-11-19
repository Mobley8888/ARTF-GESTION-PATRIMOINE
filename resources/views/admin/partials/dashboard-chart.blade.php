<div class="{{ $columnClass }}">
    <section class="card content-card h-100">
        <div class="card-body p-4">
            <h2 class="h5 mb-1">{{ $title }}</h2>
            <p class="small text-secondary mb-3">Sélectionnez une valeur pour consulter les biens correspondants.</p>
            @php($maximum = max(1, (int) $rows->max('total')))
            @forelse ($rows as $row)
                @php($parameters = $filterKey !== null && data_get($row, 'filter_value') !== null ? [$filterKey => data_get($row, 'filter_value')] : [])
                @php($barWidth = max(4, (int) round($row->total * 100 / $maximum)))
                @if ($parameters)
                    <a class="dashboard-chart-link" href="{{ route('assets.index', $parameters) }}">
                @else
                    <div class="dashboard-chart-link dashboard-chart-link-static">
                @endif
                    <div class="d-flex align-items-start justify-content-between gap-3 small mb-1">
                        <span>
                            @if (data_get($row, 'parent_label'))
                                <span class="text-secondary">{{ data_get($row, 'parent_label') }} / </span>
                            @endif
                            {{ $row->label }}
                            @if (data_get($row, 'detail'))
                                <span class="text-secondary">· {{ data_get($row, 'detail') }}</span>
                            @endif
                        </span>
                        <span class="fw-semibold text-nowrap">{{ number_format($row->total, 0, ',', ' ') }}</span>
                    </div>
                    <div class="progress dashboard-chart-track" role="progressbar" aria-label="{{ $row->label }}" aria-valuenow="{{ $row->total }}" aria-valuemin="0" aria-valuemax="{{ $maximum }}">
                        <div class="progress-bar bg-{{ $color }}" style="width: {{ $barWidth }}%"></div>
                    </div>
                @if ($parameters)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <p class="text-secondary small mb-0">Aucune donnée pour les filtres choisis.</p>
            @endforelse
        </div>
    </section>
</div>

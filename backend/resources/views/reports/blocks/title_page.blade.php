<div class="report-header">
    @if (!empty($logoDataUri))
        <div class="report-logo-wrap">
            <img src="{{ $logoDataUri }}" alt="Логотип" class="report-logo">
        </div>
    @endif
    <h1>{{ $project->name }}</h1>
    <p class="meta">SEO Reports · {{ $template->name }}</p>
    <p class="meta">
        Период: {{ $job->period_start->format('d.m.Y') }} — {{ $job->period_end->format('d.m.Y') }}
    </p>
    @if ($job->compare_period_start && $job->compare_period_end)
        <p class="meta">
            Сравнение: {{ $job->compare_period_start->format('d.m.Y') }} — {{ $job->compare_period_end->format('d.m.Y') }}
        </p>
    @endif
    @if ($project->domain)
        <span class="badge">{{ $project->domain }}</span>
    @endif
</div>

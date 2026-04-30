<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $profile->full_name }} - {{ $job->title }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            background: #ffffff;
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        h1, h2, h3, p, ul { margin: 0; }
        .document { width: 100%; }
        .header { margin-bottom: 18px; }
        .name { font-size: 26px; font-weight: 700; letter-spacing: 0.02em; }
        .headline { margin-top: 4px; font-size: 13px; color: #374151; }
        .meta { margin-top: 8px; font-size: 11px; color: #4b5563; }
        .section { margin-top: 16px; }
        .section-title {
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #d1d5db;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .summary { white-space: pre-wrap; }
        .chip-list { margin-top: 4px; }
        .chip {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 3px 8px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            font-size: 11px;
            color: #1f2937;
        }
        ul.list {
            padding-left: 18px;
        }
        ul.list li {
            margin-bottom: 6px;
        }
        .muted {
            color: #6b7280;
        }
        .experience-item {
            margin-bottom: 10px;
        }
        .experience-title {
            font-weight: 700;
        }
        .links p {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <main class="document">
        <header class="header">
            <h1 class="name">{{ $profile->full_name }}</h1>
            <p class="headline">{{ $resume['headline'] }}</p>
            <p class="meta">
                Tailored for {{ $job->title }} at {{ $job->company_name }}
                @if($job->location)
                    | {{ $job->location }}
                @endif
                @if($match)
                    | Match Score: {{ $match->overall_score }}/100
                @endif
            </p>
        </header>

        <section class="section">
            <h2 class="section-title">Professional Summary</h2>
            <p class="summary">{{ $resume['professional_summary'] }}</p>
        </section>

        <section class="section">
            <h2 class="section-title">Core Skills</h2>
            <div class="chip-list">
                @foreach($resume['selected_skills'] as $skill)
                    <span class="chip">{{ $skill }}</span>
                @endforeach
            </div>
        </section>

        <section class="section">
            <h2 class="section-title">Relevant Experience</h2>
            <ul class="list">
                @foreach($resume['selected_experience_bullets'] as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        </section>

        <section class="section">
            <h2 class="section-title">Selected Projects</h2>
            <ul class="list">
                @forelse($resume['selected_projects'] as $project)
                    <li>{{ $project }}</li>
                @empty
                    <li class="muted">No projects were selected for this tailored draft.</li>
                @endforelse
            </ul>
        </section>

        @if(! empty($resume['ats_keywords']))
            <section class="section">
                <h2 class="section-title">ATS Keywords</h2>
                <div class="chip-list">
                    @foreach($resume['ats_keywords'] as $keyword)
                        <span class="chip">{{ $keyword }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="section">
            <h2 class="section-title">Experience Snapshot</h2>
            @forelse($profile->experiences->take(3) as $experience)
                <div class="experience-item">
                    <p class="experience-title">{{ $experience->title }} | {{ $experience->company }}</p>
                    @if($experience->description)
                        <p class="muted">{{ $experience->description }}</p>
                    @endif
                </div>
            @empty
                <p class="muted">No experience entries were available.</p>
            @endforelse
        </section>

        @if($profile->linkedin_url || $profile->github_url || $profile->portfolio_url)
            <section class="section links">
                <h2 class="section-title">Links</h2>
                @if($profile->linkedin_url)
                    <p>LinkedIn: {{ $profile->linkedin_url }}</p>
                @endif
                @if($profile->github_url)
                    <p>GitHub: {{ $profile->github_url }}</p>
                @endif
                @if($profile->portfolio_url)
                    <p>Portfolio: {{ $profile->portfolio_url }}</p>
                @endif
            </section>
        @endif

        @if(! empty($resume['warnings_or_gaps']))
            <section class="section">
                <h2 class="section-title">Fit Warnings</h2>
                <ul class="list">
                    @foreach($resume['warnings_or_gaps'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</body>
</html>

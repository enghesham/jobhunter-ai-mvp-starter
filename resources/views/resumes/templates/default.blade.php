<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $profile->full_name }} - {{ $job->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; color: #1f2937; line-height: 1.45; font-size: 14px; }
        h1 { margin: 0; font-size: 28px; }
        h2 { margin: 0 0 8px; font-size: 16px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        p { margin: 0 0 8px; }
        ul { padding-left: 18px; margin: 8px 0 0; }
        li { margin-bottom: 6px; }
        .section { margin-top: 20px; }
        .meta { color: #4b5563; margin-top: 4px; }
        .badge { display: inline-block; border: 1px solid #cbd5e1; padding: 4px 8px; margin: 0 6px 6px 0; border-radius: 999px; font-size: 12px; }
        .two-col { display: table; width: 100%; }
        .two-col > div { display: table-cell; vertical-align: top; width: 50%; padding-right: 12px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $profile->full_name }}</h1>
    <p class="meta">{{ $resume['headline'] }}</p>
    <p class="meta">
        Tailored for {{ $job->title }} at {{ $job->company_name }}
        @if($job->location)
            | {{ $job->location }}
        @endif
        @if($match)
            | Match Score: {{ $match->overall_score }}/100
        @endif
    </p>

    <div class="section">
        <h2>Professional Summary</h2>
        <p>{{ $resume['professional_summary'] }}</p>
    </div>

    <div class="section">
        <h2>Selected Skills</h2>
        @foreach($resume['selected_skills'] as $skill)
            <span class="badge">{{ $skill }}</span>
        @endforeach
    </div>

    <div class="section">
        <h2>Selected Experience Bullets</h2>
        <ul>
            @foreach($resume['selected_experience_bullets'] as $bullet)
                <li>{{ $bullet }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Selected Projects</h2>
        <ul>
            @forelse($resume['selected_projects'] as $project)
                <li>{{ $project }}</li>
            @empty
                <li class="muted">No projects were selected for this draft.</li>
            @endforelse
        </ul>
    </div>

    @if(! empty($resume['ats_keywords']))
        <div class="section">
            <h2>ATS Keywords</h2>
            @foreach($resume['ats_keywords'] as $keyword)
                <span class="badge">{{ $keyword }}</span>
            @endforeach
        </div>
    @endif

    <div class="section two-col">
        <div>
            <h2>Core Experience Snapshot</h2>
            @foreach($profile->experiences->take(3) as $experience)
                <p><strong>{{ $experience->title }}</strong> | {{ $experience->company }}</p>
                @if($experience->description)
                    <p class="muted">{{ $experience->description }}</p>
                @endif
            @endforeach
        </div>
        <div>
            <h2>Links</h2>
            @if($profile->linkedin_url)
                <p>LinkedIn: {{ $profile->linkedin_url }}</p>
            @endif
            @if($profile->github_url)
                <p>GitHub: {{ $profile->github_url }}</p>
            @endif
            @if($profile->portfolio_url)
                <p>Portfolio: {{ $profile->portfolio_url }}</p>
            @endif
        </div>
    </div>
</body>
</html>

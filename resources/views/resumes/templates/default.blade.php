<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $profile->full_name }} - Tailored Resume</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #222; }
        h1, h2 { margin-bottom: 8px; }
        .section { margin-bottom: 18px; }
        ul { padding-left: 18px; }
    </style>
</head>
<body>
    <h1>{{ $profile->full_name }}</h1>
    <p>{{ $profile->headline }}</p>

    <div class="section">
        <h2>Professional Summary</h2>
        <p>{{ $tailored['summary'] ?? $profile->base_summary }}</p>
    </div>

    <div class="section">
        <h2>Key Skills</h2>
        <p>{{ implode(', ', $tailored['reordered_skills'] ?? ($profile->core_skills ?? [])) }}</p>
    </div>

    <div class="section">
        <h2>Highlighted Achievements for {{ $job->title }}</h2>
        <ul>
            @foreach(($tailored['highlighted_achievements'] ?? []) as $achievement)
                <li>{{ $achievement }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Experience</h2>
        @foreach($profile->experiences as $experience)
            <p><strong>{{ $experience->title }}</strong> - {{ $experience->company }}</p>
            <p>{{ $experience->description }}</p>
        @endforeach
    </div>
</body>
</html>

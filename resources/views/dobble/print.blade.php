<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dobble-Set drucken</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900">
    <main class="mx-auto max-w-5xl px-5 py-8 print:p-0" style="--print-columns: {{ $columns }}; --card-diameter: {{ $project['radius_mm'] * 2 }}mm">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4 print:hidden">
            <div>
                <a href="{{ route('dobble.index') }}" class="text-sm font-semibold text-[#21665e] hover:underline">← Zurück zur Werkstatt</a>
                <h1 class="mt-3 text-2xl font-semibold">Druckvorschau · {{ count($cards) }} Karten</h1>
                <p class="mt-1 text-sm text-slate-600">Im Druckdialog „Als PDF speichern“ wählen. Druckformat: A4, Hochformat, Skalierung 100 %.</p>
            </div>
            <button type="button" onclick="window.print()" class="rounded-xl bg-[#193a38] px-5 py-3 text-sm font-semibold text-white hover:bg-[#24534f]">
                Drucken / als PDF speichern
            </button>
        </div>

        <div class="print-pages">
            @foreach ($printPages as $page)
                <section class="print-page">
                    <div class="print-card-grid">
                        @foreach ($page as $card)
                            <article class="print-card" style="background-color: {{ $cardBackground['color'] }}">
                                @if ($cardBackground['mode'] === 'image' && $cardBackground['imageUrl'])
                                    <img src="{{ $cardBackground['imageUrl'] }}" alt="" aria-hidden="true" class="print-card-background">
                                @endif
                                @foreach ($card['symbols'] as $symbol)
                                    <img
                                        src="{{ $symbol['imageUrl'] }}"
                                        alt="{{ $symbol['name'] }}"
                                        class="print-card-symbol"
                                        style="--symbol-left: {{ $symbol['placement']['left'] }}%; --symbol-top: {{ $symbol['placement']['top'] }}%; --symbol-size: {{ $symbol['placement']['size'] }}%; --symbol-rotation: {{ $symbol['placement']['rotation'] }}deg; --symbol-scale: {{ $symbol['placement']['scale'] }}"
                                    >
                                @endforeach
                                <span class="print-card-number">{{ $card['id'] }}</span>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </main>
</body>
</html>

<section id="symbole" class="mt-8 scroll-mt-6 border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-[#e7674e]">Schritt 2</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">Eigene Symbole zuordnen</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Lade einzelne Bilder oder mehrere PNG-, JPEG- oder WebP-Bilder auf einmal hoch. Bilder bleiben privat und sind nur in deiner Sitzung erreichbar.
                Für ein scharfes Druckbild empfehlen sich quadratische Dateien ab 256 × 256 Pixel.
            </p>
        </div>
        <p data-symbol-count class="rounded-full bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900">
            {{ count($symbols) - count($missingSymbols) }} von {{ count($symbols) }} Bildern vorhanden
        </p>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
        <label for="bulk-symbol-images" class="block text-sm font-semibold text-slate-800">
            Mehrere Bilder auf einmal hochladen
            <input id="bulk-symbol-images" name="images[]" type="file" accept="image/png,image/jpeg,image/webp" multiple
                data-bulk-image-input @disabled($missingSymbols === [])
                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf2ef] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#193a38] hover:file:bg-[#dcebe6] disabled:opacity-50">
        </label>
        <p class="mt-2 text-xs leading-5 text-slate-600">
            PNG, JPEG oder WebP; maximal 5 MB pro Bild. Die Bilder werden in Dateiauswahl-Reihenfolge den noch leeren Symbolen zugeordnet; der Dateiname wird als Symbolname verwendet.
            Bereits belegte Symbole bleiben unverändert. Du kannst die Zuordnung anschließend unten ändern.
        </p>
        <p data-bulk-upload-status role="status" aria-live="polite" class="mt-2 text-sm text-slate-600"></p>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($symbols as $symbol)
            <article class="border border-slate-200 p-4 shadow-xl/10" data-symbol-card data-symbol-id="{{ $symbol['id'] }}" data-symbol-image-present="{{ $symbol['imageUrl'] ? 'true' : 'false' }}">
                <div class="flex items-center gap-4">
                    <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#f5f2e9]">
                        <img data-symbol-preview src="{{ $symbol['imageUrl'] ?? '' }}" alt="{{ $symbol['name'] }}" class="{{ $symbol['imageUrl'] ? 'size-full object-contain p-1' : 'hidden' }}">
                        <span data-symbol-placeholder class="{{ $symbol['imageUrl'] ? 'hidden' : 'text-2xl font-semibold text-slate-400' }}" aria-hidden="true">{{ $symbol['id'] + 1 }}</span>
                    </div>
                    <div class="min-w-0">
                        <h3 data-symbol-name class="truncate font-semibold">{{ $symbol['name'] }}</h3>
                        <p class="mt-1 text-xs text-slate-500">Symbol {{ $symbol['id'] + 1 }}</p>
                    </div>
                    <span data-symbol-status class="ml-auto rounded-full px-2.5 py-1 text-xs font-medium {{ $symbol['imageUrl'] ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">
                        {{ $symbol['imageUrl'] ? 'Bereit' : 'Bild fehlt' }}
                    </span>
                </div>
                <ul data-quality-warnings class="mt-3 list-inside list-disc rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900 {{ $symbol['qualityWarnings'] === [] ? 'hidden' : '' }}">
                    @foreach ($symbol['qualityWarnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('dobble.symbols.upload', ['symbol' => $symbol['id']]) }}" enctype="multipart/form-data" class="mt-4 space-y-3" data-image-upload-form>
                    @csrf
                    <label class="block text-xs font-medium text-slate-700" for="symbol-name-{{ $symbol['id'] }}">
                        Symbolname
                        <input id="symbol-name-{{ $symbol['id'] }}" name="name" type="text" maxlength="80" required
                            value="{{ $symbol['name'] }}" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20">
                    </label>
                    <label class="block text-xs font-medium text-slate-700" for="symbol-image-{{ $symbol['id'] }}">
                        Bild (PNG, JPEG oder WebP; max. 5 MB)
                        <input id="symbol-image-{{ $symbol['id'] }}" name="image" type="file" accept="image/png,image/jpeg,image/webp" required
                            data-image-input class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf2ef] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#193a38] hover:file:bg-[#dcebe6]">
                    </label>
                    <p data-upload-status role="status" aria-live="polite" class="text-xs text-slate-600"></p>
                </form>
                <form method="POST" action="{{ route('dobble.symbols.remove', ['symbol' => $symbol['id']]) }}" class="mt-2" data-symbol-remove-form @if (! $symbol['imageUrl']) hidden @endif>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Entfernen</button>
                    <p data-remove-status role="status" aria-live="polite" class="text-xs text-slate-600"></p>
                </form>
                <form method="POST" action="{{ route('dobble.symbols.move', ['symbol' => $symbol['id']]) }}" class="mt-3 space-y-2" data-symbol-move-form @if (! $symbol['imageUrl']) hidden @endif>
                    @csrf
                    <label class="block text-xs font-medium text-slate-700" for="symbol-target-{{ $symbol['id'] }}">
                        Verschieben oder mit anderem Symbol tauschen
                        <select id="symbol-target-{{ $symbol['id'] }}" name="target_symbol" required
                            class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20">
                            @foreach ($symbols as $targetSymbol)
                                @if ($targetSymbol['id'] !== $symbol['id'])
                                    <option value="{{ $targetSymbol['id'] }}">
                                        Symbol {{ $targetSymbol['id'] + 1 }}{{ $targetSymbol['imageUrl'] ? ' (Bild wird getauscht)' : ' (leer)' }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Zuordnung ändern
                    </button>
                </form>
            </article>
        @endforeach
    </div>
</section>

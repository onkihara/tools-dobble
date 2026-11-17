<section id="konfiguration" class="mt-8 scroll-mt-6 border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-[#e7674e]">Schritt 1</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">Set konfigurieren</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Wähle eine unterstützte Ordnung. Für kleinere Sets werden Karten aus der vollständigen Konstruktion ausgewählt.
            </p>
        </div>
        <form method="POST" action="{{ route('dobble.reset') }}">
            @csrf
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="submit">
                Neues Set starten
            </button>
        </form>
    </div>

    <form method="POST" action="{{ route('dobble.configuration') }}" enctype="multipart/form-data" class="mt-7 grid gap-5 md:grid-cols-3">
        @csrf
        <label class="block text-sm font-medium text-slate-800" for="order">
            Symbole pro Karte
            <select id="order" name="order" data-order-select class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base shadow-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20">
                @foreach ([2, 3, 5, 7] as $order)
                    <option value="{{ $order }}" data-total="{{ $order ** 2 + $order + 1 }}" @selected(old('order', $project['order']) == $order)>
                        {{ $order + 1 }} Symbole · maximal {{ $order ** 2 + $order + 1 }} Karten
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm font-medium text-slate-800" for="card_count">
            Karten im Set
            <input id="card_count" name="card_count" type="number" min="2" max="57" required value="{{ old('card_count', $project['card_count']) }}" data-card-count
                class="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-3 text-base shadow-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20">
            <span class="mt-1 block text-xs font-normal text-slate-500" data-order-summary>
                Vollständige Konstruktion: {{ $deck['total_cards'] }} Karten
            </span>
        </label>

        <label class="block text-sm font-medium text-slate-800" for="radius_mm">
            Kartenradius für den Druck
            <span class="mt-2 flex items-center gap-3">
                <input id="radius_mm" name="radius_mm" type="number" min="25" max="60" required value="{{ old('radius_mm', $project['radius_mm']) }}"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-3 text-base shadow-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20">
                <span class="shrink-0 text-sm font-normal text-slate-500">mm</span>
            </span>
            <span class="mt-1 block text-xs font-normal text-slate-500">Radius 25–60 mm; Schnittkreis-Durchmesser ist doppelt so groß.</span>
        </label>

        <fieldset class="space-y-3 md:col-span-3">
            <legend class="text-sm font-semibold text-slate-800">Hintergrund der Karten</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input
                            type="radio"
                            name="background_mode"
                            value="color"
                            data-background-mode
                            @checked(old('background_mode', $project['card_background']['mode']) === 'color')
                            class="accent-[#287c72]"
                        >
                        Einfarbiger Hintergrund
                    </label>
                    <label for="background_color" data-background-color-field class="flex items-center gap-3 text-sm text-slate-700">
                        <input
                            id="background_color"
                            name="background_color"
                            type="color"
                            value="{{ old('background_color', $project['card_background']['color']) }}"
                            class="h-10 w-14 cursor-pointer rounded border border-slate-300 p-1"
                        >
                        Kartenfarbe
                    </label>
                </div>

                <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input
                            type="radio"
                            name="background_mode"
                            value="image"
                            data-background-mode
                            @checked(old('background_mode', $project['card_background']['mode']) === 'image')
                            class="accent-[#287c72]"
                        >
                        Bild als Hintergrund
                    </label>
                    <div data-background-image-field class="space-y-2">
                        <label for="background_image" class="block text-sm text-slate-700">
                            Hintergrundbild (PNG, JPEG oder WebP; max. 5 MB)
                        </label>
                        <input
                            id="background_image"
                            name="background_image"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            data-background-image-input
                            data-saved-url="{{ $cardBackground['imageUrl'] ?? '' }}"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf2ef] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#193a38] hover:file:bg-[#dcebe6]"
                        >
                        <div class="flex items-center gap-3">
                            <img
                                @if ($cardBackground['imageUrl']) src="{{ $cardBackground['imageUrl'] }}" @endif
                                alt="Vorschau des Kartenhintergrundbilds"
                                data-background-image-preview
                                @if (! $cardBackground['imageUrl']) hidden @endif
                                class="h-16 w-16 rounded-lg border border-slate-200 object-cover"
                            >
                            <p data-background-image-status class="text-xs text-slate-500">
                                @if ($cardBackground['imageUrl'])
                                    Ein Bild ist gespeichert. Ein neues Bild ersetzt es.
                                @else
                                    Wähle ein Bild aus. Es wird auf die Kartenfläche zugeschnitten.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-3 md:col-span-3">
            <button type="submit" class="rounded-xl bg-blue-500 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-[#287c72] focus:ring-offset-2">
                Konfiguration übernehmen
            </button>
            <p class="text-sm text-slate-500">Unterstützte Primordnungen: 2, 3, 5 und 7.</p>
        </div>
    </form>
</section>

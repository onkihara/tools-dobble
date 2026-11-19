<section id="vorschau" class="mt-8 scroll-mt-6 border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-[#e7674e]">{{ __('dobble.client.preview.step') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('dobble.client.preview.title') }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ __('dobble.client.preview.summary', [
                    'cards' => $project['card_count'],
                    'symbols' => $deck['symbols_per_card'],
                    'used' => count($symbols),
                ]) }}
            </p>
            <p class="mt-1 text-sm text-slate-600">{{ __('dobble.client.preview.randomize_help') }}</p>
        </div>
        <a href="{{ route('dobble.print') }}" data-print-link @if ($missingSymbols !== []) hidden @endif class="inline-flex items-center rounded-xl bg-[#f06a4f] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#dc5b42] focus:outline-none focus:ring-2 focus:ring-[#f06a4f] focus:ring-offset-2">
            {{ __('dobble.client.preview.print') }}
        </a>
        <button type="button" data-print-requirements @if ($missingSymbols === []) hidden @endif disabled class="cursor-not-allowed rounded-xl bg-slate-200 px-5 py-3 text-sm font-semibold text-slate-500" title="{{ __('dobble.client.preview.missing_title') }}">
            {{ __('dobble.client.preview.missing', ['count' => count($missingSymbols)]) }}
        </button>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            <article class="bg-[#f7f5ef] p-4">
                <div
                    class="dobble-card-preview mx-auto cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#e7674f] focus-visible:ring-offset-2"
                    role="button"
                    tabindex="0"
                    aria-label="{{ __('dobble.client.preview.card_aria', ['number' => $card['id']]) }}"
                    data-card-preview
                    data-randomize-url="{{ route('dobble.cards.randomize', ['card' => $card['id']]) }}"
                    style="background-color: {{ $cardBackground['color'] }}"
                >
                    <img
                        @if ($cardBackground['imageUrl']) src="{{ $cardBackground['imageUrl'] }}" @endif
                        alt=""
                        aria-hidden="true"
                        data-card-background-preview
                        @if ($cardBackground['mode'] !== 'image' || ! $cardBackground['imageUrl']) hidden @endif
                        class="pointer-events-none absolute inset-0 size-full object-cover"
                    >
                    @foreach ($card['symbols'] as $cardSymbol)
                        @if ($cardSymbol['imageUrl'])
                            <img
                                src="{{ $cardSymbol['imageUrl'] }}"
                                data-symbol-image-id="{{ $cardSymbol['id'] }}"
                                alt="{{ $cardSymbol['name'] }}"
                                title="{{ $cardSymbol['name'] }}"
                                loading="lazy"
                                class="dobble-preview-symbol"
                                style="--symbol-left: {{ $cardSymbol['placement']['left'] }}%; --symbol-top: {{ $cardSymbol['placement']['top'] }}%; --symbol-size: {{ $cardSymbol['placement']['size'] }}%; --symbol-rotation: {{ $cardSymbol['placement']['rotation'] }}deg; --symbol-scale: {{ $cardSymbol['placement']['scale'] }}"
                            >
                        @else
                            <span
                                class="dobble-preview-symbol dobble-preview-placeholder"
                                data-symbol-image-id="{{ $cardSymbol['id'] }}"
                                title="{{ $cardSymbol['name'] }}"
                                style="--symbol-left: {{ $cardSymbol['placement']['left'] }}%; --symbol-top: {{ $cardSymbol['placement']['top'] }}%; --symbol-size: {{ $cardSymbol['placement']['size'] }}%; --symbol-rotation: {{ $cardSymbol['placement']['rotation'] }}deg; --symbol-scale: {{ $cardSymbol['placement']['scale'] }}"
                            >{{ $cardSymbol['id'] + 1 }}</span>
                        @endif
                    @endforeach
                </div>
                <p class="mt-3 text-center text-sm font-semibold text-slate-700">{{ __('dobble.client.preview.card', ['number' => $card['id']]) }}</p>
                <p data-card-layout-status role="status" class="mt-1 text-center text-xs text-slate-500" aria-live="polite"></p>
            </article>
        @endforeach
    </div>

    <div class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-5 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
        <p>{{ __('dobble.client.preview.footer') }}</p>
        <form method="POST" action="{{ route('dobble.reset') }}" class="shrink-0">
            @csrf
            <button type="submit" class="font-semibold text-[#21665e] underline decoration-[#9cc7bb] underline-offset-4 hover:text-[#193a38]">{{ __('dobble.client.preview.reset') }}</button>
        </form>
    </div>
</section>

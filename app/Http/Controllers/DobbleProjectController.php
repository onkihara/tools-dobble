<?php

namespace App\Http\Controllers;

use App\Services\CardSymbolArranger;
use App\Services\DobbleSetGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DobbleProjectController extends Controller
{
    private const SESSION_KEY = 'dobble_project';

    public function __construct(
        private DobbleSetGenerator $generator,
        private CardSymbolArranger $arranger,
    ) {}

    public function index(Request $request): View
    {
        return view('dobble.index', $this->editorData($request));
    }

    public function updateConfiguration(Request $request): RedirectResponse
    {
        $project = $request->session()->get(self::SESSION_KEY, []);
        $savedBackgroundPath = $project['card_background']['path'] ?? null;
        $minimumSymbolScale = (int) config('dobble.symbol_scale.minimum');
        $maximumSymbolScale = (int) config('dobble.symbol_scale.maximum');
        $defaultMinimumSymbolScale = (int) config('dobble.symbol_scale.default_minimum');
        $defaultMaximumSymbolScale = (int) config('dobble.symbol_scale.default_maximum');

        $validated = $request->validate([
            'order' => ['required', Rule::in(DobbleSetGenerator::SUPPORTED_ORDERS)],
            'card_count' => ['required', 'integer', 'min:2', 'max:57'],
            'radius_mm' => ['required', 'integer', 'min:25', 'max:60'],
            'symbol_size_min' => ['nullable', 'integer', 'min:'.$minimumSymbolScale, 'max:'.$maximumSymbolScale],
            'symbol_size_max' => ['nullable', 'integer', 'min:'.$minimumSymbolScale, 'max:'.$maximumSymbolScale],
            'background_mode' => ['required', Rule::in(['color', 'image'])],
            'background_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'background_image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ]);

        $order = (int) $validated['order'];
        $cardCount = (int) $validated['card_count'];
        $maximumCards = $order ** 2 + $order + 1;
        $symbolSizeMin = isset($validated['symbol_size_min'])
            ? (int) $validated['symbol_size_min']
            : ($project['symbol_size_min'] ?? $defaultMinimumSymbolScale);
        $symbolSizeMax = isset($validated['symbol_size_max'])
            ? (int) $validated['symbol_size_max']
            : ($project['symbol_size_max'] ?? $defaultMaximumSymbolScale);

        if ($cardCount > $maximumCards) {
            return back()
                ->withErrors(['card_count' => "Bei Ordnung {$order} sind höchstens {$maximumCards} Karten möglich."])
                ->withInput();
        }
        if ($symbolSizeMin > $symbolSizeMax) {
            return back()
                ->withErrors(['symbol_size_min' => 'Die minimale Symbolgröße darf nicht größer als die maximale Symbolgröße sein.'])
                ->withInput();
        }

        $backgroundImage = $validated['background_image'] ?? null;
        if (
            $validated['background_mode'] === 'image'
            && ! $backgroundImage instanceof UploadedFile
            && (! is_string($savedBackgroundPath) || ! Storage::disk('local')->exists($savedBackgroundPath))
        ) {
            return back()
                ->withErrors(['background_image' => 'Lade ein Kartenhintergrundbild hoch, bevor du diese Option auswählst.'])
                ->withInput();
        }

        if (isset($project['order']) && $project['order'] !== $order) {
            $this->deleteSymbolUploads($project, $request->session()->getId());
            $project['symbols'] = [];
        }

        $previousBackgroundPath = $savedBackgroundPath;
        if ($validated['background_mode'] === 'image' && $backgroundImage instanceof UploadedFile) {
            $savedBackgroundPath = $backgroundImage->store(
                'dobble-background/'.$request->session()->getId(),
                'local',
            );
            if (! is_string($savedBackgroundPath)) {
                throw new \RuntimeException('The card background image could not be stored.');
            }
        }

        $project['order'] = $order;
        $project['card_count'] = $cardCount;
        $project['radius_mm'] = (int) $validated['radius_mm'];
        $project['symbol_size_min'] = $symbolSizeMin;
        $project['symbol_size_max'] = $symbolSizeMax;
        $project['layout_seed'] = random_int(0, 2_147_483_647);
        $project['card_background'] = [
            'mode' => $validated['background_mode'],
            'color' => $validated['background_color'],
            'path' => $savedBackgroundPath,
        ];
        $request->session()->put(self::SESSION_KEY, $project);

        if (
            $savedBackgroundPath !== $previousBackgroundPath
            && is_string($previousBackgroundPath)
        ) {
            $this->deleteUpload($previousBackgroundPath);
        }

        return back()->with('status', 'Die Set-Konfiguration wurde aktualisiert.');
    }

    public function randomizeCard(Request $request, string $card): JsonResponse
    {
        $data = $this->editorData($request);

        $cardId = (int) $card;
        $cardData = collect($data['deck']['cards'])->firstWhere('id', $cardId);
        abort_unless(is_array($cardData), 404);
        $minimumScale = $data['layoutError'] === null
            ? (int) $data['project']['symbol_size_min']
            : (int) config('dobble.symbol_scale.default_minimum');
        $maximumScale = $data['layoutError'] === null
            ? (int) $data['project']['symbol_size_max']
            : (int) config('dobble.symbol_scale.default_maximum');

        try {
            $placements = $this->arranger->arrange(
                $cardData['symbols'],
                random_int(0, 2_147_483_647),
                $minimumScale,
                $maximumScale,
            );
        } catch (LogicException) {
            return response()->json([
                'errors' => ['layout' => ['Für diese Karte konnte keine neue sichere Anordnung erstellt werden.']],
            ], 422);
        }

        return response()->json(['placements' => $placements]);
    }

    public function uploadSymbol(Request $request, string $symbol): RedirectResponse|JsonResponse
    {
        $symbolId = (int) $symbol;
        $projectData = $this->editorData($request);
        abort_unless(in_array($symbolId, $projectData['deck']['symbols'], true), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ]);

        $file = $validated['image'];
        if (! $file instanceof UploadedFile) {
            throw new LogicException('The validated symbol image is not an uploaded file.');
        }

        $imageSize = getimagesize($file->getPathname());
        if ($imageSize === false) {
            throw new \RuntimeException('The validated symbol image dimensions could not be read.');
        }

        $path = $file->store('dobble/'.$request->session()->getId(), 'local');
        if (! is_string($path)) {
            throw new \RuntimeException('The symbol image could not be stored.');
        }

        $project = $request->session()->get(self::SESSION_KEY, $projectData['project']);
        $previousPath = $project['symbols'][(string) $symbolId]['path'] ?? null;
        $project['symbols'][(string) $symbolId] = [
            'name' => trim($validated['name']),
            'path' => $path,
            'width' => $imageSize[0],
            'height' => $imageSize[1],
        ];
        $request->session()->put(self::SESSION_KEY, $project);

        if (is_string($previousPath)) {
            $this->deleteUpload($previousPath);
        }

        if ($request->expectsJson()) {
            $updatedData = $this->editorData($request);

            return response()->json([
                'symbol' => $updatedData['symbolsById'][$symbolId],
                'uploadedCount' => count($updatedData['symbols']) - count($updatedData['missingSymbols']),
                'totalCount' => count($updatedData['symbols']),
                'missingCount' => count($updatedData['missingSymbols']),
            ]);
        }

        return back()->with('status', 'Das Symbolbild wurde gespeichert.');
    }

    public function moveSymbol(Request $request, string $symbol): RedirectResponse
    {
        $symbolId = (int) $symbol;
        $projectData = $this->editorData($request);
        abort_unless(in_array($symbolId, $projectData['deck']['symbols'], true), 404);

        $validated = $request->validate([
            'target_symbol' => [
                'required',
                'integer',
                Rule::in(array_diff($projectData['deck']['symbols'], [$symbolId])),
            ],
        ]);

        $targetSymbolId = (int) $validated['target_symbol'];
        $project = $request->session()->get(self::SESSION_KEY, $projectData['project']);
        $sourceKey = (string) $symbolId;
        $targetKey = (string) $targetSymbolId;
        $sourceSymbol = $project['symbols'][$sourceKey] ?? null;

        if (! is_array($sourceSymbol)) {
            return back()->withErrors([
                'target_symbol' => 'Für dieses Symbol gibt es kein Bild zum Verschieben.',
            ]);
        }

        $targetSymbol = $project['symbols'][$targetKey] ?? null;
        if (is_array($targetSymbol)) {
            $project['symbols'][$sourceKey] = $targetSymbol;
        } else {
            unset($project['symbols'][$sourceKey]);
        }

        $project['symbols'][$targetKey] = $sourceSymbol;
        $request->session()->put(self::SESSION_KEY, $project);

        return back()->with('status', 'Die Symbolbilder wurden neu zugeordnet.');
    }

    public function removeSymbol(Request $request, string $symbol): RedirectResponse|JsonResponse
    {
        $symbolId = (int) $symbol;
        $projectData = $this->editorData($request);
        abort_unless(in_array($symbolId, $projectData['deck']['symbols'], true), 404);

        $project = $request->session()->get(self::SESSION_KEY, $projectData['project']);
        $path = $project['symbols'][(string) $symbolId]['path'] ?? null;

        if (is_string($path)) {
            $this->deleteUpload($path);
        }

        unset($project['symbols'][(string) $symbolId]);
        $request->session()->put(self::SESSION_KEY, $project);

        if ($request->expectsJson()) {
            $updatedData = $this->editorData($request);

            return response()->json([
                'symbol' => $updatedData['symbolsById'][$symbolId],
                'uploadedCount' => count($updatedData['symbols']) - count($updatedData['missingSymbols']),
                'totalCount' => count($updatedData['symbols']),
                'missingCount' => count($updatedData['missingSymbols']),
            ]);
        }

        return back()->with('status', 'Das Symbolbild wurde entfernt.');
    }

    public function showSymbol(Request $request, string $symbol): BinaryFileResponse
    {
        $symbolId = (int) $symbol;
        $project = $request->session()->get(self::SESSION_KEY, []);
        $path = $project['symbols'][(string) $symbolId]['path'] ?? null;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        $storagePath = Storage::disk('local')->path($path);
        $mimeType = mime_content_type($storagePath);
        abort_unless(is_string($mimeType) && str_starts_with($mimeType, 'image/'), 404);

        return response()->file($storagePath, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="symbol-'.$symbolId.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showCardBackground(Request $request): BinaryFileResponse
    {
        $path = $request->session()->get(self::SESSION_KEY.'.card_background.path');

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        $storagePath = Storage::disk('local')->path($path);
        $mimeType = mime_content_type($storagePath);
        abort_unless(is_string($mimeType) && str_starts_with($mimeType, 'image/'), 404);

        return response()->file($storagePath, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="card-background"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function print(Request $request): View|RedirectResponse
    {
        $data = $this->editorData($request);

        if ($data['layoutError'] !== null) {
            return back()->withErrors(['export' => $data['layoutError']]);
        }

        if ($data['missingSymbols'] !== []) {
            return back()->withErrors([
                'export' => 'Für den Druck fehlen noch '.count($data['missingSymbols']).' Symbolbilder.',
            ]);
        }

        $radius = $data['project']['radius_mm'];
        $columns = $radius <= 45 ? 2 : 1;
        $rows = min(3, max(1, (int) floor(270 / (($radius * 2) + 6))));
        $cardsPerPage = $columns * $rows;

        return view('dobble.print', [
            ...$data,
            'columns' => $columns,
            'printPages' => array_chunk($data['cards'], $cardsPerPage),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->deleteSessionUploads(
            $request->session()->get(self::SESSION_KEY, []),
            $request->session()->getId(),
        );
        $request->session()->forget(self::SESSION_KEY);

        return to_route('dobble.index')->with('status', 'Ein neues Set kann jetzt konfiguriert werden.');
    }

    /**
     * @return array{
     *     project: array{order: int, card_count: int, radius_mm: int, symbol_size_min: int, symbol_size_max: int, layout_seed: int, symbols: array<string, array{name: string, path: string, width: int, height: int}>, card_background: array{mode: string, color: string, path: ?string}},
     *     cardBackground: array{mode: string, color: string, imageUrl: ?string},
     *     deck: array{cards: list<array{id: int, symbols: list<int>}>, symbols: list<int>, total_cards: int, symbols_per_card: int},
     *     symbols: list<array{id: int, name: string, imageUrl: ?string, qualityWarnings: list<string>}>,
     *     symbolsById: array<int, array{id: int, name: string, imageUrl: ?string, qualityWarnings: list<string>}>,
     *     cards: list<array{id: int, symbols: list<array{id: int, name: string, imageUrl: ?string, qualityWarnings: list<string>, placement: array{symbol_id: int, left: float, top: float, size: float, rotation: int, scale: float}}>}>,
     *     missingSymbols: list<int>,
     *     layoutError: ?string
     * }
     */
    private function editorData(Request $request): array
    {
        $defaultMinimumSymbolScale = (int) config('dobble.symbol_scale.default_minimum');
        $defaultMaximumSymbolScale = (int) config('dobble.symbol_scale.default_maximum');

        if (! $request->session()->has(self::SESSION_KEY)) {
            $project = [
                'order' => 3,
                'card_count' => 13,
                'radius_mm' => 35,
                'symbol_size_min' => $defaultMinimumSymbolScale,
                'symbol_size_max' => $defaultMaximumSymbolScale,
                'layout_seed' => random_int(0, 2_147_483_647),
                'symbols' => [],
                'card_background' => ['mode' => 'color', 'color' => '#fffefa', 'path' => null],
            ];
            $request->session()->put(self::SESSION_KEY, $project);
        } else {
            $project = $request->session()->get(self::SESSION_KEY);

            if (! is_array($project)) {
                throw new LogicException('The saved Dobble project has an invalid session format.');
            }
        }

        if (! isset($project['layout_seed'])) {
            $project['layout_seed'] = random_int(0, 2_147_483_647);
            $request->session()->put(self::SESSION_KEY, $project);
        }
        if (! isset($project['symbol_size_min'], $project['symbol_size_max'])) {
            $project['symbol_size_min'] = $defaultMinimumSymbolScale;
            $project['symbol_size_max'] = $defaultMaximumSymbolScale;
            $request->session()->put(self::SESSION_KEY, $project);
        }

        if (! isset($project['card_background'])) {
            $project['card_background'] = ['mode' => 'color', 'color' => '#fffefa', 'path' => null];
            $request->session()->put(self::SESSION_KEY, $project);
        }

        $backgroundPath = $project['card_background']['path'] ?? null;
        $cardBackground = [
            'mode' => $project['card_background']['mode'],
            'color' => $project['card_background']['color'],
            'imageUrl' => is_string($backgroundPath) && Storage::disk('local')->exists($backgroundPath)
                ? route('dobble.background', ['version' => hash('sha256', $backgroundPath)])
                : null,
        ];

        $deck = $this->generator->generate($project['order'], $project['card_count']);
        $symbols = [];
        $symbolsById = [];
        $missingSymbols = [];

        foreach ($deck['symbols'] as $symbolId) {
            $savedSymbol = $project['symbols'][(string) $symbolId] ?? null;
            $imageUrl = is_array($savedSymbol) && isset($savedSymbol['path'])
                ? route('dobble.symbols.show', [
                    'symbol' => $symbolId,
                    'version' => hash('sha256', $savedSymbol['path']),
                ])
                : null;
            $qualityWarnings = [];

            if (is_array($savedSymbol) && isset($savedSymbol['width'], $savedSymbol['height'])) {
                $width = (int) $savedSymbol['width'];
                $height = (int) $savedSymbol['height'];

                if (min($width, $height) < 256) {
                    $qualityWarnings[] = 'Geringe Auflösung: möglichst mindestens 256 × 256 Pixel verwenden.';
                }

                if (min($width, $height) > 0 && max($width, $height) / min($width, $height) > 2) {
                    $qualityWarnings[] = 'Ungewöhnliches Seitenverhältnis: das Bild wird im Kreis mit Rand dargestellt.';
                }
            }

            $symbol = [
                'id' => $symbolId,
                'name' => is_array($savedSymbol) && isset($savedSymbol['name'])
                    ? $savedSymbol['name']
                    : 'Symbol '.($symbolId + 1),
                'imageUrl' => $imageUrl,
                'qualityWarnings' => $qualityWarnings,
            ];
            $symbols[] = $symbol;
            $symbolsById[$symbolId] = $symbol;

            if ($imageUrl === null) {
                $missingSymbols[] = $symbolId;
            }
        }

        $cards = [];
        $layoutError = null;

        foreach ($deck['cards'] as $card) {
            $hash = hash('sha256', $project['layout_seed'].':'.$card['id']);
            $seed = (int) hexdec(substr($hash, 0, 7));

            try {
                $placements = $this->arranger->arrange(
                    $card['symbols'],
                    $seed,
                    (int) $project['symbol_size_min'],
                    (int) $project['symbol_size_max'],
                );
            } catch (LogicException) {
                $layoutError = 'Die Symbole lassen sich mit diesen Größenlimits nicht sicher anordnen. Die Vorschau verwendet ersatzweise die Standardgrößen; passe die Grenzen an, damit der Druck freigegeben wird.';
                $placements = $this->arranger->arrange($card['symbols'], $seed);
            }

            $placementsBySymbol = array_column($placements, null, 'symbol_id');

            $cards[] = [
                'id' => $card['id'],
                'symbols' => array_map(
                    fn (int $symbolId): array => [
                        ...$symbolsById[$symbolId],
                        'placement' => $placementsBySymbol[$symbolId],
                    ],
                    $card['symbols'],
                ),
            ];
        }

        return compact('project', 'cardBackground', 'deck', 'symbols', 'symbolsById', 'cards', 'missingSymbols', 'layoutError');
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function deleteSymbolUploads(array $project, string $sessionId): void
    {
        $directories = collect(['dobble/'.$sessionId])
            ->merge(
                collect($project['symbols'] ?? [])
                    ->pluck('path')
                    ->filter(fn (mixed $path): bool => is_string($path))
                    ->map(fn (string $path): string => dirname($path)),
            )
            ->unique();
        $disk = Storage::disk('local');

        foreach ($directories as $directory) {
            if ($disk->directoryExists($directory) && ! $disk->deleteDirectory($directory)) {
                throw new \RuntimeException('The session symbol image directory could not be deleted.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function deleteSessionUploads(array $project, string $sessionId): void
    {
        $directories = collect(['dobble/'.$sessionId, 'dobble-background/'.$sessionId])
            ->merge(
                collect($project['symbols'] ?? [])
                    ->pluck('path')
                    ->filter(fn (mixed $path): bool => is_string($path))
                    ->map(fn (string $path): string => dirname($path)),
            )
            ->when(
                is_string($project['card_background']['path'] ?? null),
                fn ($directories) => $directories->push(dirname($project['card_background']['path'])),
            )
            ->unique();

        $disk = Storage::disk('local');
        foreach ($directories as $directory) {
            if ($disk->directoryExists($directory) && ! $disk->deleteDirectory($directory)) {
                throw new \RuntimeException('The session symbol image directory could not be deleted.');
            }
        }
    }

    private function deleteUpload(string $path): void
    {
        $disk = Storage::disk('local');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new \RuntimeException('A stored symbol image could not be deleted.');
        }
    }
}

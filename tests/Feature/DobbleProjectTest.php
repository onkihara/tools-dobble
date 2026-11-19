<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the workshop shows the initial set configuration', function () {
    $response = $this->get(route('dobble.index'));

    $response->assertOk()
        ->assertSeeText('Dein Dobble-Set beginnt hier.')
        ->assertSeeText('13 Karten')
        ->assertSeeText('Symbole pro Karte')
        ->assertSeeText('Untere Grenze')
        ->assertSessionHas('dobble_project.layout_seed')
        ->assertSee('--symbol-left:', false)
        ->assertSee('--symbol-rotation:', false);
});

test('configuration rejects more cards than the selected construction allows', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $response = $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 5,
        'card_count' => 32,
        'radius_mm' => 35,
        'background_mode' => 'color',
        'background_color' => '#fffefa',
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors([
            'card_count' => 'Bei Ordnung 5 sind höchstens 31 Karten möglich.',
        ]);
    $this->assertSame([], Storage::disk('local')->allFiles('dobble'));
});

test('symbol size limits can be configured within the supported range', function () {
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'symbol_size_min' => 80,
        'symbol_size_max' => 140,
        'background_mode' => 'color',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'));

    expect(session('dobble_project.symbol_size_min'))->toBe(80)
        ->and(session('dobble_project.symbol_size_max'))->toBe(140);
});

test('the minimum symbol size cannot exceed the maximum symbol size', function () {
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'symbol_size_min' => 150,
        'symbol_size_max' => 100,
        'background_mode' => 'color',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('symbol_size_min');

    expect(session('dobble_project.symbol_size_min'))->toBe(60)
        ->and(session('dobble_project.symbol_size_max'))->toBe(160);
});

test('symbol scaling limits and defaults come from the Dobble configuration', function () {
    config([
        'dobble.symbol_scale.minimum' => 45,
        'dobble.symbol_scale.maximum' => 185,
        'dobble.symbol_scale.default_minimum' => 75,
        'dobble.symbol_scale.default_maximum' => 110,
    ]);

    $this->get(route('dobble.index'))
        ->assertOk()
        ->assertSee('min="45" max="185" required value="75"', false)
        ->assertSee('min="45" max="185" required value="110"', false);

    expect(session('dobble_project.symbol_size_min'))->toBe(75)
        ->and(session('dobble_project.symbol_size_max'))->toBe(110);

    $this->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'symbol_size_min' => 44,
        'symbol_size_max' => 186,
        'background_mode' => 'color',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors(['symbol_size_min', 'symbol_size_max']);
});

test('maximum symbol sizes are shown in the card preview', function () {
    $this->get(route('dobble.index'));

    $this->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'symbol_size_min' => 200,
        'symbol_size_max' => 200,
        'background_mode' => 'color',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'));

    $this->get(route('dobble.index'))
        ->assertOk()
        ->assertDontSeeText('Kartenlayout überprüfen!')
        ->assertSeeText('Karte 1')
        ->assertSeeText('Karte 13')
        ->assertSee('id="vorschau"', false)
        ->assertSee('--symbol-scale: 2', false);

    $this->get(route('dobble.print'))
        ->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('export');
});

test('a card can be randomized again using the configured symbol size limits', function () {
    $this->get(route('dobble.index'));
    $this->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'symbol_size_min' => 80,
        'symbol_size_max' => 100,
        'background_mode' => 'color',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'));

    $page = $this->get(route('dobble.index'));
    $initialPlacements = array_column(
        $page->viewData('cards')[0]['symbols'],
        'placement',
        'id',
    );
    $response = $this->postJson(route('dobble.cards.randomize', ['card' => 1]));

    $response->assertOk()->assertJsonCount(4, 'placements');
    $placements = $response->json('placements');
    $placementsBySymbol = array_column($placements, null, 'symbol_id');
    expect($placementsBySymbol)->not->toEqual($initialPlacements);

    foreach ($placements as $placement) {
        expect($placement['scale'])->toBeGreaterThanOrEqual(0.8)
            ->toBeLessThanOrEqual(1.0);
    }

    $movedSymbols = array_filter($placements, function (array $placement) use ($initialPlacements): bool {
        $initialPlacement = $initialPlacements[$placement['symbol_id']];

        return $placement['left'] !== $initialPlacement['left']
            || $placement['top'] !== $initialPlacement['top'];
    });
    expect($movedSymbols)->not->toBeEmpty();

    $resizedSymbols = array_filter(
        $placements,
        fn (array $placement): bool => $placement['scale'] !== $initialPlacements[$placement['symbol_id']]['scale'],
    );
    expect($resizedSymbols)->not->toBeEmpty();
});

test('the card background can be configured as a color', function () {
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'background_mode' => 'color',
        'background_color' => '#a1b2c3',
    ])->assertRedirect(route('dobble.index'));

    $this->assertSame('color', session('dobble_project.card_background.mode'));
    $this->assertSame('#a1b2c3', session('dobble_project.card_background.color'));

    $this->get(route('dobble.index'))
        ->assertOk()
        ->assertSee('style="background-color: #a1b2c3"', false);
});

test('the card background can be uploaded privately and shown in the preview', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'background_mode' => 'image',
        'background_color' => '#fffefa',
        'background_image' => UploadedFile::fake()->image('background.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));

    expect(Storage::disk('local')->allFiles('dobble-background'))->toHaveCount(1);
    $this->get(route('dobble.index'))
        ->assertOk()
        ->assertSee(route('dobble.background'), false)
        ->assertSee('Vorschau des Kartenhintergrundbilds')
        ->assertSee('data-card-background-preview', false);

    $this->get(route('dobble.background'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $this->post(route('dobble.configuration'), [
        'order' => 2,
        'card_count' => 7,
        'radius_mm' => 35,
        'background_mode' => 'image',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'));
    expect(Storage::disk('local')->allFiles('dobble-background'))->toHaveCount(1);

    $this->withSession(['dobble_project' => []])->get(route('dobble.background'))
        ->assertNotFound();
});

test('image backgrounds require an uploaded or previously saved image', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'background_mode' => 'image',
        'background_color' => '#fffefa',
    ])->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('background_image');

    expect(Storage::disk('local')->allFiles('dobble-background'))->toBe([]);
});

test('an uploaded symbol image remains private to the session', function () {
    Storage::fake('local');

    $this->get(route('dobble.index'));
    $response = $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 128, 128),
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHas('dobble_project.symbols.0.name', 'Sonne');
    expect(Storage::disk('local')->allFiles('dobble'))->toHaveCount(1);

    $this->get(route('dobble.symbols.show', ['symbol' => 0]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    $this->get(route('dobble.index'))
        ->assertOk()
        ->assertSeeText('Geringe Auflösung');
});

test('a JSON image upload returns the updated symbol data without redirecting', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post(route('dobble.symbols.upload', ['symbol' => 0]), [
            'name' => 'Sonne',
            'image' => UploadedFile::fake()->image('sun.png', 256, 256),
        ]);

    $response->assertOk()
        ->assertJsonPath('symbol.name', 'Sonne')
        ->assertJsonPath('symbol.qualityWarnings', [])
        ->assertJsonPath('uploadedCount', 1)
        ->assertJsonPath('totalCount', 13)
        ->assertJsonPath('missingCount', 12);
    expect($response->json('symbol.imageUrl'))->toBeString();
    expect(Storage::disk('local')->allFiles('dobble'))->toHaveCount(1);
});

test('a symbol image can be moved to an empty symbol', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));
    $imagePath = session('dobble_project.symbols.0.path');

    $response = $this->from(route('dobble.index'))->post(route('dobble.symbols.move', ['symbol' => 0]), [
        'target_symbol' => 1,
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHas('dobble_project.symbols.1.name', 'Sonne')
        ->assertSessionHas('dobble_project.symbols.1.path', $imagePath);
    expect(session('dobble_project.symbols.0'))->toBeNull();
    expect(Storage::disk('local')->allFiles('dobble'))->toHaveCount(1);
    $this->get(route('dobble.symbols.show', ['symbol' => 1]))->assertOk();
});

test('moving an image onto an assigned symbol swaps their images and names', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));
    $this->post(route('dobble.symbols.upload', ['symbol' => 1]), [
        'name' => 'Mond',
        'image' => UploadedFile::fake()->image('moon.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));
    $firstImagePath = session('dobble_project.symbols.0.path');
    $secondImagePath = session('dobble_project.symbols.1.path');

    $response = $this->from(route('dobble.index'))->post(route('dobble.symbols.move', ['symbol' => 0]), [
        'target_symbol' => 1,
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHas('dobble_project.symbols.0.name', 'Mond')
        ->assertSessionHas('dobble_project.symbols.1.name', 'Sonne');
    expect(session('dobble_project.symbols.0.path'))->toBe($secondImagePath);
    expect(session('dobble_project.symbols.1.path'))->toBe($firstImagePath);
    expect(Storage::disk('local')->allFiles('dobble'))->toHaveCount(2);
});

test('a symbol image cannot be moved to the same symbol', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));
    $imagePath = session('dobble_project.symbols.0.path');

    $response = $this->from(route('dobble.index'))->post(route('dobble.symbols.move', ['symbol' => 0]), [
        'target_symbol' => 0,
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('target_symbol')
        ->assertSessionHas('dobble_project.symbols.0.path', $imagePath);
    expect(Storage::disk('local')->allFiles('dobble'))->toHaveCount(1);
});

test('a symbol without an image cannot be moved', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $response = $this->from(route('dobble.index'))->post(route('dobble.symbols.move', ['symbol' => 0]), [
        'target_symbol' => 1,
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('target_symbol');
    expect(session('dobble_project.symbols'))->toBe([]);
    expect(Storage::disk('local')->allFiles('dobble'))->toBe([]);
});

test('a JSON image removal returns the updated set and deletes the stored image', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post(route('dobble.symbols.remove', ['symbol' => 0]), ['_method' => 'DELETE']);

    $response->assertOk()
        ->assertJsonPath('symbol.name', 'Symbol 1')
        ->assertJsonPath('symbol.imageUrl', null)
        ->assertJsonPath('uploadedCount', 0)
        ->assertJsonPath('totalCount', 13)
        ->assertJsonPath('missingCount', 13);
    expect(Storage::disk('local')->allFiles('dobble'))->toBe([]);
});

test('symbol images upload automatically and can be removed from the editor', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 128, 128),
    ])->assertRedirect(route('dobble.index'));

    $response = $this->get(route('dobble.index'));

    $response->assertOk()
        ->assertSee('data-image-input', false)
        ->assertSee('data-image-upload-form', false)
        ->assertSee('data-bulk-image-input', false)
        ->assertSee('multiple', false)
        ->assertSee('data-symbol-move-form', false)
        ->assertSeeText('Dateiauswahl-Reihenfolge')
        ->assertSeeText('Entfernen')
        ->assertDontSeeText('Bild speichern')
        ->assertDontSeeText('Bild ersetzen');
});

test('reset deletes the complete session image directory', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->post(route('dobble.configuration'), [
        'order' => 3,
        'card_count' => 13,
        'radius_mm' => 35,
        'background_mode' => 'image',
        'background_color' => '#fffefa',
        'background_image' => UploadedFile::fake()->image('background.png', 256, 256),
    ])->assertRedirect(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 128, 128),
    ])->assertRedirect(route('dobble.index'));

    $directory = dirname(Storage::disk('local')->allFiles('dobble')[0]);
    expect(basename($directory))->toBe($this->app['session.store']->getId());
    expect(Storage::disk('local')->allFiles('dobble-background'))->toHaveCount(1);
    Storage::disk('local')->put($directory.'/orphan.png', 'unused');
    expect(Storage::disk('local')->directoryExists($directory))->toBeTrue();

    $this->from(route('dobble.index'))->post(route('dobble.reset'))
        ->assertRedirect(route('dobble.index'));

    expect(Storage::disk('local')->directoryExists($directory))->toBeFalse();
    expect(Storage::disk('local')->allFiles('dobble-background'))->toBe([]);
});

test('replacing a symbol image uses a new cacheable image URL', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun.png', 128, 128),
    ])->assertRedirect(route('dobble.index'));

    $firstResponse = $this->get(route('dobble.index'));
    $firstImageUrl = $firstResponse->viewData('symbols')[0]['imageUrl'];

    $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Sonne',
        'image' => UploadedFile::fake()->image('sun-replacement.png', 128, 128),
    ])->assertRedirect(route('dobble.index'));

    $replacementResponse = $this->get(route('dobble.index'));
    $replacementImageUrl = $replacementResponse->viewData('symbols')[0]['imageUrl'];

    $firstResponse->assertSee($firstImageUrl, false);
    $replacementResponse->assertSee($replacementImageUrl, false);
    expect($replacementImageUrl)->not->toBe($firstImageUrl);
});

test('a very wide image warns that it may not fit the card well', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Panorama',
        'image' => UploadedFile::fake()->image('panorama.png', 512, 128),
    ])->assertRedirect(route('dobble.index'));

    $this->get(route('dobble.index'))
        ->assertOk()
        ->assertSeeText('Ungewöhnliches Seitenverhältnis');
});

test('an invalid image upload is rejected without storing a file', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));

    $response = $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => 0]), [
        'name' => 'Nicht erlaubt',
        'image' => UploadedFile::fake()->createWithContent('symbol.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    ]);

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('image');
    expect(Storage::disk('local')->allFiles('dobble'))->toBe([]);
});

test('printing is blocked until every used symbol has an image', function () {
    $this->get(route('dobble.index'));

    $response = $this->from(route('dobble.index'))->get(route('dobble.print'));

    $response->assertRedirect(route('dobble.index'))
        ->assertSessionHasErrors('export');
});

test('the completed set opens a print-ready deck', function () {
    Storage::fake('local');
    $this->get(route('dobble.index'));
    $this->from(route('dobble.index'))->post(route('dobble.configuration'), [
        'order' => 2,
        'card_count' => 2,
        'radius_mm' => 35,
        'background_mode' => 'image',
        'background_color' => '#fffefa',
        'background_image' => UploadedFile::fake()->image('background.png', 256, 256),
    ]);

    foreach ([0, 1, 2, 3, 4] as $symbolId) {
        $this->from(route('dobble.index'))->post(route('dobble.symbols.upload', ['symbol' => $symbolId]), [
            'name' => "Symbol {$symbolId}",
            'image' => UploadedFile::fake()->image("symbol-{$symbolId}.png", 128, 128),
        ])->assertRedirect(route('dobble.index'));
    }

    $response = $this->get(route('dobble.print'));

    $response->assertOk()
        ->assertSeeText('2 Karten')
        ->assertSeeText('Drucken / als PDF speichern')
        ->assertSee('--symbol-left:', false)
        ->assertSee('--symbol-rotation:', false)
        ->assertSee('print-card', false)
        ->assertSee('print-card-background', false)
        ->assertSee(route('dobble.background'), false);
});

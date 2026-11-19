<?php

namespace App\Services;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class CardSymbolArranger
{
    private const PROFILES = [
        3 => ['radius' => 0.46, 'size' => 0.40, 'angular_jitter' => 0.035, 'radial_jitter' => 0.018],
        4 => ['radius' => 0.54, 'size' => 0.35, 'angular_jitter' => 0.08, 'radial_jitter' => 0.025],
        6 => ['radius' => 0.66, 'size' => 0.27, 'angular_jitter' => 0.11, 'radial_jitter' => 0.03],
        8 => ['radius' => 0.73, 'size' => 0.22, 'angular_jitter' => 0.14, 'radial_jitter' => 0.03],
    ];

    /**
     * @param  list<int>  $symbolIds
     * @return list<array{symbol_id: int, left: float, top: float, size: float, rotation: int, scale: float}>
     */
    public function arrange(
        array $symbolIds,
        int $seed,
        ?int $minimumScale = null,
        ?int $maximumScale = null,
    ): array {
        $minimumScaleLimit = (int) config('dobble.symbol_scale.minimum');
        $maximumScaleLimit = (int) config('dobble.symbol_scale.maximum');
        $minimumScale ??= (int) config('dobble.symbol_scale.default_minimum');
        $maximumScale ??= (int) config('dobble.symbol_scale.default_maximum');
        $symbolCount = count($symbolIds);

        if (
            $minimumScale < $minimumScaleLimit
            || $minimumScale > $maximumScaleLimit
            || $maximumScale < $minimumScaleLimit
            || $maximumScale > $maximumScaleLimit
            || $minimumScale > $maximumScale
        ) {
            throw new InvalidArgumentException(
                "The symbol size limits must be between {$minimumScaleLimit} and {$maximumScaleLimit} percent.",
            );
        }

        if (! isset(self::PROFILES[$symbolCount]) || count(array_unique($symbolIds)) !== $symbolCount) {
            throw new InvalidArgumentException('A card must contain a supported number of unique symbols.');
        }

        $profile = self::PROFILES[$symbolCount];
        $randomizer = new Randomizer(new Mt19937($seed));

        return $this->createRandomPlacement(
            $symbolIds,
            $profile,
            $randomizer,
            $minimumScale,
            $maximumScale,
        );
    }

    /**
     * @param  list<int>  $symbolIds
     * @param  array{radius: float, size: float, angular_jitter: float, radial_jitter: float}  $profile
     * @return list<array{symbol_id: int, left: float, top: float, size: float, rotation: int, scale: float}>
     */
    private function createRandomPlacement(
        array $symbolIds,
        array $profile,
        Randomizer $randomizer,
        int $minimumScale,
        int $maximumScale,
    ): array {
        $phase = $randomizer->getInt(0, 6283) / 1000;
        $orderedSymbolIds = $randomizer->shuffleArray($symbolIds);
        $placements = [];

        foreach ($orderedSymbolIds as $slot => $symbolId) {
            $angleJitter = $randomizer->getInt(-1000, 1000) / 1000 * $profile['angular_jitter'];
            $radiusJitter = $randomizer->getInt(-1000, 1000) / 1000 * $profile['radial_jitter'];
            $scale = $randomizer->getInt($minimumScale, $maximumScale) / 100;
            $angle = $phase + (2 * M_PI * $slot / count($symbolIds)) + $angleJitter;
            $radius = $profile['radius'] + $radiusJitter;
            $x = cos($angle) * $radius;
            $y = sin($angle) * $radius;
            $rotation = $randomizer->getInt(-180, 180);

            $placements[] = $this->makePlacement(
                $symbolId,
                $x,
                $y,
                $profile['size'],
                $rotation,
                $scale,
            );
        }

        return $placements;
    }

    /**
     * @return array{symbol_id: int, left: float, top: float, size: float, rotation: int, scale: float}
     */
    private function makePlacement(
        int $symbolId,
        float $x,
        float $y,
        float $size,
        int $rotation,
        float $scale,
    ): array {
        return [
            'symbol_id' => $symbolId,
            'left' => (0.5 + ($x / 2)) * 100,
            'top' => (0.5 + ($y / 2)) * 100,
            'size' => ($size / 2) * 100,
            'rotation' => $rotation,
            'scale' => $scale,
        ];
    }
}

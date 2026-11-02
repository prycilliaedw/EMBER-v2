<?php

namespace App\Services;

final class AdministrativeProvinceMatcher
{
    private ?array $features = null;

    public function match(float $latitude, float $longitude): ?string
    {
        $matches = [];

        foreach ($this->features() as $feature) {
            foreach ($this->extractPolygons($feature['geometry'] ?? null) as $polygon) {
                if ($this->pointInPolygon($longitude, $latitude, $polygon)) {
                    $province = $feature['properties']['GAUL1_NAME'] ?? null;
                    if (is_string($province) && trim($province) !== '') {
                        $matches[] = trim($province);
                    }
                    break;
                }
            }
        }

        $matches = array_values(array_unique($matches));

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function features(): array
    {
        if ($this->features !== null) {
            return $this->features;
        }

        $path = storage_path('app/ember/source/EMBER_AOI_10Provinsi_Sumatra_GAUL2025_v2.geojson');
        if (! is_file($path)) {
            return $this->features = [];
        }

        $geojson = json_decode((string) file_get_contents($path), true);
        $this->features = is_array($geojson['features'] ?? null) ? $geojson['features'] : [];

        return $this->features;
    }

    private function extractPolygons(?array $geometry): array
    {
        if (! is_array($geometry)) {
            return [];
        }

        $type = $geometry['type'] ?? null;
        if ($type === 'Polygon') {
            return isset($geometry['coordinates'][0]) ? [$geometry['coordinates']] : [];
        }

        if ($type === 'MultiPolygon') {
            return $geometry['coordinates'] ?? [];
        }

        if ($type === 'GeometryCollection') {
            $polygons = [];
            foreach ($geometry['geometries'] ?? [] as $nestedGeometry) {
                $polygons = array_merge($polygons, $this->extractPolygons($nestedGeometry));
            }

            return $polygons;
        }

        return [];
    }

    private function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        $rings = $polygon;
        if (! isset($rings[0][0]) || ! is_array($rings[0][0])) {
            return false;
        }

        if (! $this->pointInRing($x, $y, $rings[0] ?? [])) {
            return false;
        }

        for ($ringIndex = 1, $ringCount = count($rings); $ringIndex < $ringCount; $ringIndex++) {
            if ($this->pointInRing($x, $y, $rings[$ringIndex])) {
                return false;
            }
        }

        return true;
    }

    private function pointInRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($index = 0, $previous = $count - 1; $index < $count; $previous = $index++) {
            $xi = (float) ($ring[$index][0] ?? 0);
            $yi = (float) ($ring[$index][1] ?? 0);
            $xj = (float) ($ring[$previous][0] ?? 0);
            $yj = (float) ($ring[$previous][1] ?? 0);
            $denominator = ($yj - $yi) ?: 0.0000000001;
            $intersects = (($yi > $y) !== ($yj > $y))
                && ($x < (($xj - $xi) * ($y - $yi) / $denominator) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}

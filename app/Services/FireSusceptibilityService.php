<?php

namespace App\Services;

/**
 * EMBER class-level Hotspot Evidence (HSE) + Historical Burn Evidence (HBE)
 * using a zero-order Sugeno fuzzy inference system.
 *
 * IMPORTANT STATUS:
 * - All 13 MapBiomas Landy classes remain active.
 * - The configured HSE/HBE values are class-level, so hotspots assigned to
 *   the same land-cover class receive the same class-context FSI.
 * - HBE pre-2024 is currently reconstructed from the 2000-2024 frequency
 *   raster and 2024 annual mask. The index is experimental, not a probability.
 */
final class FireSusceptibilityService
{
    public const MODEL_VERSION = 'EMBER-HSE-HBE-SUGENO-DRAFT-1.0.0';

    public const LAND_COVER_NAMES = [
        3 => 'Formasi Hutan',
        5 => 'Mangrove',
        9 => 'Kebun Kayu',
        13 => 'Tumbuhan Non-Hutan Lainnya',
        21 => 'Pertanian Lainnya',
        24 => 'Pemukiman',
        25 => 'Non-Vegetasi Lainnya',
        30 => 'Lubang Tambang',
        31 => 'Tambak',
        33 => 'Sungai, Danau, Laut',
        35 => 'Sawit',
        40 => 'Sawah',
        76 => 'Hutan Rawa Gambut',
    ];

    /** Return configured HSE/HBE values and diagnostic metrics for one class. */
    public function classEvidence(?int $landCoverId): ?array
    {
        if ($landCoverId === null) {
            return null;
        }

        $evidence = config('ember.class_evidence.'.$landCoverId);

        return is_array($evidence) ? $evidence : null;
    }

    /** NASA MODIS confidence class for descriptive display only. */
    public function confidenceClass(?float $confidence): ?string
    {
        if ($confidence === null) {
            return null;
        }

        return match (true) {
            $confidence < 30 => 'Low',
            $confidence < 80 => 'Nominal',
            default => 'High',
        };
    }

    /**
     * Zero-order Sugeno FSI from two class-level inputs.
     *
     * HSE = empirical hotspot evidence (0-100), based on confidence-weighted
     * hotspot density by land-cover class.
     * HBE = historical burned evidence (0-100), based on pre-2024 burned-area
     * footprint and frequency indicators.
     *
     * Membership anchors are the Q1/Q2/Q3 of the 13 class-level scores.
     * Rule consequent constants 10/30/50/70/90 and the monotonic 4x4 rule
     * matrix are explicit EMBER design choices, requiring independent validation.
     */
    public function fsi(?float $hotspotEvidence, ?float $historicalBurnEvidence): ?float
    {
        if ($hotspotEvidence === null || $historicalBurnEvidence === null) {
            return null;
        }

        $hotspotEvidence = max(0.0, min(100.0, $hotspotEvidence));
        $historicalBurnEvidence = max(0.0, min(100.0, $historicalBurnEvidence));

        $hseAnchors = config('ember.membership_anchors.hotspot_evidence');
        $hbeAnchors = config('ember.membership_anchors.historical_burn_evidence');

        $hotspotMemberships = $this->memberships($hotspotEvidence, $hseAnchors);
        $historicalMemberships = $this->memberships($historicalBurnEvidence, $hbeAnchors);

        $outputConstants = config('ember.sugeno_output_constants');
        $ruleBase = config('ember.rule_base');
        $numerator = 0.0;
        $denominator = 0.0;

        foreach ($hotspotMemberships as $hseSet => $hseMembership) {
            if ($hseMembership <= 0.0) {
                continue;
            }

            foreach ($historicalMemberships as $hbeSet => $hbeMembership) {
                if ($hbeMembership <= 0.0) {
                    continue;
                }

                $weight = $hseMembership * $hbeMembership;
                $outputSet = $ruleBase[$hseSet][$hbeSet] ?? null;

                if ($outputSet === null || !isset($outputConstants[$outputSet])) {
                    continue;
                }

                $numerator += $weight * (float) $outputConstants[$outputSet];
                $denominator += $weight;
            }
        }

        if ($denominator <= 0.0) {
            return null;
        }

        return round(max(0.0, min(100.0, $numerator / $denominator)), 2);
    }

    /** Convert numeric FSI to the five linguistic classes. */
    public function fsiClass(?float $fsi): ?string
    {
        if ($fsi === null) {
            return null;
        }

        return match (true) {
            $fsi < 20 => 'Very Low',
            $fsi < 40 => 'Low',
            $fsi < 60 => 'Moderate',
            $fsi < 80 => 'High',
            default => 'Very High',
        };
    }

    /** QA flag only: describes disagreement between HSE and HBE. */
    public function contextFlag(?float $hotspotEvidence, ?float $historicalBurnEvidence): ?string
    {
        if ($hotspotEvidence === null || $historicalBurnEvidence === null) {
            return null;
        }

        $threshold = (float) config('ember.context_divergence_threshold', 35.0);

        return abs($hotspotEvidence - $historicalBurnEvidence) >= $threshold
            ? 'Evidence Divergence'
            : 'Evidence Aligned';
    }

    /** Fuzzy linguistic sets from input-specific quartile anchors. */
    private function memberships(float $x, array $anchors): array
    {
        $q1 = (float) ($anchors['q1'] ?? 25.0);
        $q2 = (float) ($anchors['q2'] ?? 50.0);
        $q3 = (float) ($anchors['q3'] ?? 75.0);

        return [
            'very_low' => $this->leftShoulder($x, 0.0, $q1),
            'low' => $this->triangle($x, 0.0, $q1, $q2),
            'moderate' => $this->triangle($x, $q1, $q2, $q3),
            'high' => $this->rightShoulder($x, $q2, $q3),
        ];
    }

    private function triangle(float $x, float $a, float $b, float $c): float
    {
        if ($x <= $a || $x >= $c) {
            return 0.0;
        }
        if (abs($x - $b) < 1.0e-12) {
            return 1.0;
        }
        if ($x < $b) {
            return ($x - $a) / ($b - $a);
        }
        return ($c - $x) / ($c - $b);
    }

    private function leftShoulder(float $x, float $a, float $b): float
    {
        if ($x <= $a) {
            return 1.0;
        }
        if ($x >= $b) {
            return 0.0;
        }
        return ($b - $x) / ($b - $a);
    }

    private function rightShoulder(float $x, float $a, float $b): float
    {
        if ($x <= $a) {
            return 0.0;
        }
        if ($x >= $b) {
            return 1.0;
        }
        return ($x - $a) / ($b - $a);
    }
}

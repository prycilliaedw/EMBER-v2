<?php

namespace Tests\Unit;

use App\Services\MapBiomasSpatialMatcher;
use App\Services\FireSusceptibilityService;
use Tests\TestCase;

class LandCoverClassificationTest extends TestCase
{
    public function test_raw_collection4_mapping_includes_all_supported_ember_classes(): void
    {
        $matcher = app(MapBiomasSpatialMatcher::class);

        $expected = [
            3 => 3, 5 => 5, 9 => 9, 13 => 13, 21 => 21, 24 => 24,
            25 => 25, 30 => 30, 31 => 31, 33 => 33, 35 => 35,
            40 => 40, 76 => 76,
        ];

        foreach ($expected as $raw => $ember) {
            $this->assertSame($ember, $matcher->mapRawClassId($raw));
        }

        $this->assertCount(13, FireSusceptibilityService::LAND_COVER_NAMES);
    }

    public function test_verified_collection4_raw_ids_35_40_and_76_map_to_their_ember_classes(): void
    {
        $matcher = app(MapBiomasSpatialMatcher::class);

        $this->assertSame(35, $matcher->mapRawClassId(35));
        $this->assertSame(40, $matcher->mapRawClassId(40));
        $this->assertSame(76, $matcher->mapRawClassId(76));
    }

    public function test_unmapped_raw_ids_remain_unmapped(): void
    {
        $matcher = app(MapBiomasSpatialMatcher::class);

        foreach ([27, 41, 999] as $rawClassId) {
            $this->assertNull($matcher->mapRawClassId($rawClassId), "Raw class {$rawClassId} must remain unmapped");
        }
    }

    public function test_multiple_raw_ids_are_parsed_and_conflicting_mappings_are_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ember-crosswalk-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, implode("\n", [
                'ember_class_id,raw_collection4_id,verification',
                '35,"350, 351",verified',
                '40,351,verified',
            ]));

            $loader = new \ReflectionMethod(MapBiomasSpatialMatcher::class, 'loadAuthoritativeCrosswalk');
            $mapping = $loader->invoke(app(MapBiomasSpatialMatcher::class), $path);

            $this->assertSame(35, $mapping[350] ?? null);
            $this->assertArrayNotHasKey(351, $mapping, 'Conflicting raw IDs must not be assigned to either EMBER class');
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_class_level_evidence_still_drives_existing_sugeno_fsi(): void
    {
        $service = app(FireSusceptibilityService::class);
        $evidence = $service->classEvidence(35);

        $this->assertIsArray($evidence);
        $this->assertSame(
            $service->fsi((float) $evidence['hotspot_evidence'], (float) $evidence['historical_burn_evidence']),
            $service->fsi(36.773, 39.725)
        );
    }

    public function test_matcher_uses_configured_project_virtualenv_python(): void
    {
        $venvPython = 'D:\capstone\EMBER-v2-main\.venv\Scripts\python.exe';
        if (! is_file($venvPython)) {
            $this->markTestSkipped('Project virtual environment Python is not installed');
        }

        config()->set('ember.mapbiomas.python_executable', $venvPython);
        $matcher = app(MapBiomasSpatialMatcher::class);

        $this->assertSame($venvPython, $matcher->findPythonExecutable());
    }
}

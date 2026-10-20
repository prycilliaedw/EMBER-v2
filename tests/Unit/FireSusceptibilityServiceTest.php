<?php

namespace Tests\Unit;

use App\Services\FireSusceptibilityService;
use Tests\TestCase;

class FireSusceptibilityServiceTest extends TestCase
{
    public function test_all_thirteen_landy_classes_have_hse_and_hbe_values(): void
    {
        $service = app(FireSusceptibilityService::class);
        $this->assertCount(13, FireSusceptibilityService::LAND_COVER_NAMES);

        foreach (array_keys(FireSusceptibilityService::LAND_COVER_NAMES) as $classId) {
            $evidence = $service->classEvidence($classId);
            $this->assertIsArray($evidence, "Missing class evidence for Landy ID {$classId}");
            $this->assertArrayHasKey('hotspot_evidence', $evidence);
            $this->assertArrayHasKey('historical_burn_evidence', $evidence);
            $this->assertGreaterThanOrEqual(0, $evidence['hotspot_evidence']);
            $this->assertLessThanOrEqual(100, $evidence['hotspot_evidence']);
            $this->assertGreaterThanOrEqual(0, $evidence['historical_burn_evidence']);
            $this->assertLessThanOrEqual(100, $evidence['historical_burn_evidence']);
        }
    }

    public function test_fsi_is_bounded_and_classified(): void
    {
        $service = app(FireSusceptibilityService::class);
        foreach (range(0, 100, 5) as $hse) {
            foreach (range(0, 100, 5) as $hbe) {
                $fsi = $service->fsi((float) $hse, (float) $hbe);
                $this->assertNotNull($fsi);
                $this->assertGreaterThanOrEqual(0, $fsi);
                $this->assertLessThanOrEqual(100, $fsi);
                $this->assertNotNull($service->fsiClass($fsi));
            }
        }
    }

    public function test_null_input_returns_no_fsi(): void
    {
        $service = app(FireSusceptibilityService::class);
        $this->assertNull($service->fsi(null, 50.0));
        $this->assertNull($service->fsi(50.0, null));
    }

    public function test_context_flag_is_qa_only(): void
    {
        $service = app(FireSusceptibilityService::class);
        $this->assertSame('Evidence Aligned', $service->contextFlag(50.0, 40.0));
        $this->assertSame('Evidence Divergence', $service->contextFlag(90.0, 10.0));
    }
}

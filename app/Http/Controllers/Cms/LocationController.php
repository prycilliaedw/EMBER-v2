<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LocationController extends Controller
{
    public function index(): View
    {
        $locations = DB::table('titik_lokasi')->orderByDesc('date')->orderByDesc('id')->paginate(15);
        $locations->getCollection()->transform(function ($location) {
            $location->status = $this->statusFor($location->confidence);

            return $location;
        });

        return view('cms.locations.index', [
            'locations' => $locations,
        ]);
    }

    public function show(int $id): View
    {
        $location = DB::table('titik_lokasi')->where('id', $id)->firstOrFail();
        $location->status = $this->statusFor($location->confidence);

        return view('cms.locations.show', [
            'location' => $location,
        ]);
    }

    public function create(): View
    {
        return view('cms.locations.create');
    }

    public function downloadCsvTemplate(): BinaryFileResponse
    {
        return response()->download(
            resource_path('csv/template-import-titik-lokasi.csv'),
            'template-import-titik-lokasi.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function downloadEnrichedCsvTemplate(): BinaryFileResponse
    {
        return response()->download(
            resource_path('csv/template-import-ember-enriched.csv'),
            'template-import-ember-enriched.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $locationId = DB::table('titik_lokasi')->insertGetId([
            ...$validated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('cms.locations.show', $locationId)
            ->with('success', 'Titik lokasi berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        return view('cms.locations.edit', [
            'location' => DB::table('titik_lokasi')->where('id', $id)->firstOrFail(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        DB::table('titik_lokasi')->where('id', $id)->firstOrFail();

        $validated = $this->validatedData($request);

        DB::table('titik_lokasi')->where('id', $id)->update([
            ...$validated,
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('cms.locations.show', $id)
            ->with('success', 'Detail titik lokasi berhasil diperbarui.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate($this->locationRules());
    }

    private function locationRules(): array
    {
        return [
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kabupaten_kota' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'desa' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'confidence' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function statusFor(mixed $confidence): array
    {
        if ($confidence === null || trim((string) $confidence) === '') {
            return ['label' => 'Belum dinilai', 'class' => 'bg-slate-100 text-slate-600'];
        }

        $value = strtolower(trim((string) $confidence));

        if (in_array($value, ['high', 'tinggi'], true) || (is_numeric($value) && (float) $value >= 80)) {
            return ['label' => 'Tinggi', 'class' => 'bg-red-50 text-red-700'];
        }

        if (in_array($value, ['nominal', 'medium', 'sedang'], true) || (is_numeric($value) && (float) $value >= 50)) {
            return ['label' => 'Sedang', 'class' => 'bg-amber-50 text-amber-700'];
        }

        return ['label' => 'Rendah', 'class' => 'bg-emerald-50 text-emerald-700'];
    }
}

<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(): View
    {
        return view('cms.activities.index', [
            'activities' => DB::table('activities')->orderByDesc('date')->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('cms.activities.form', ['activity' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateActivity($request, true);

        DB::table('activities')->insert([
            ...$this->values($request, $validated),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.activities.index')->with('success', 'Aktivitas berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        return view('cms.activities.form', [
            'activity' => DB::table('activities')->where('id', $id)->firstOrFail(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $activity = DB::table('activities')->where('id', $id)->firstOrFail();
        $validated = $this->validateActivity($request, false);

        DB::table('activities')->where('id', $id)->update([
            ...$this->values($request, $validated, $activity),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.activities.index')->with('success', 'Aktivitas berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $activity = DB::table('activities')->where('id', $id)->firstOrFail();

        Storage::disk('public')->delete(array_filter([$activity->image_id, $activity->image_en]));
        DB::table('activities')->where('id', $id)->delete();

        return back()->with('success', 'Aktivitas berhasil dihapus.');
    }

    private function validateActivity(Request $request, bool $imagesRequired): array
    {
        $imageRules = [$imagesRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        return $request->validate([
            'image_id' => $imageRules,
            'image_en' => $imageRules,
            'description_id' => ['required', 'string', 'max:2000'],
            'description_en' => ['required', 'string', 'max:2000'],
            'content_id' => ['required', 'string'],
            'content_en' => ['required', 'string'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:draft,publish'],
        ]);
    }

    private function values(Request $request, array $validated, ?object $activity = null): array
    {
        $values = [
            'description_id' => $validated['description_id'],
            'description_en' => $validated['description_en'],
            'content_id' => $validated['content_id'],
            'content_en' => $validated['content_en'],
            'date' => $validated['date'],
            'status' => $validated['status'],
        ];

        foreach (['image_id', 'image_en'] as $field) {
            if ($request->hasFile($field)) {
                if ($activity?->{$field}) {
                    Storage::disk('public')->delete($activity->{$field});
                }

                $values[$field] = $request->file($field)->store('activities', 'public');
            }
        }

        return $values;
    }
}

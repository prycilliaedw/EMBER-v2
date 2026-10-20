<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReferenceController extends Controller
{
    public function picker(): View
    {
        return view('cms.references.picker', [
            'photos' => DB::table('photo_references')->latest()->get(),
        ]);
    }

    public function index(): View
    {
        return view('cms.references.index', [
            'photos' => DB::table('photo_references')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photo = $request->file('photo');
        $path = $photo->store('references', 'public');

        DB::table('photo_references')->insert([
            'title' => $validated['title'],
            'alt_text' => $validated['alt_text'] ?? null,
            'photo_path' => $path,
            'original_name' => $photo->getClientOriginalName(),
            'mime_type' => $photo->getMimeType(),
            'file_size' => $photo->getSize(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Foto referensi berhasil disimpan.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $photo = DB::table('photo_references')->where('id', $id)->firstOrFail();

        Storage::disk('public')->delete($photo->photo_path);
        DB::table('photo_references')->where('id', $id)->delete();

        return back()->with('success', 'Foto referensi berhasil dihapus.');
    }
}

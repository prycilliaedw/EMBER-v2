<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('cms.faq.index', [
            'faqs' => DB::table('faqs')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateFaq($request);

        DB::table('faqs')->insert([
            ...$validated,
            'sort_order' => (int) DB::table('faqs')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'FAQ berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        return view('cms.faq.edit', [
            'faq' => DB::table('faqs')->where('id', $id)->firstOrFail(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        DB::table('faqs')->where('id', $id)->firstOrFail();
        $validated = $this->validateFaq($request);

        DB::table('faqs')->where('id', $id)->update([
            ...$validated,
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.faq.index')->with('success', 'FAQ berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table('faqs')->where('id', $id)->firstOrFail();
        DB::table('faqs')->where('id', $id)->delete();

        return back()->with('success', 'FAQ berhasil dihapus.');
    }

    private function validateFaq(Request $request): array
    {
        return $request->validate([
            'question_id' => ['required', 'string', 'max:1000'],
            'question_en' => ['required', 'string', 'max:1000'],
            'answer_id' => ['required', 'string'],
            'answer_en' => ['required', 'string'],
        ]);
    }
}

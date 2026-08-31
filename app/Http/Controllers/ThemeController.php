<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function index()
    {
        $this->authorizeManage();

        return view('themes.index', [
            'themes' => Theme::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('themes.create');
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $this->validated($request);
        $theme = Theme::create($data);

        return redirect()->route('themes.show', $theme)->with('ok', 'Theme created');
    }

    public function show(Theme $theme)
    {
        $this->authorizeManage();

        return view('themes.show', compact('theme'));
    }

    public function edit(Theme $theme)
    {
        $this->authorizeManage();

        return view('themes.edit', compact('theme'));
    }

    public function update(Request $request, Theme $theme)
    {
        $this->authorizeManage();

        $theme->update($this->validated($request, $theme));

        return redirect()->route('themes.show', $theme)->with('ok', 'Theme updated');
    }

    public function destroy(Theme $theme)
    {
        $this->authorizeManage();

        if ($theme->sites()->exists()) {
            return back()->withErrors(['theme' => 'Cannot delete theme assigned to sites.']);
        }

        if ($theme->is_default) {
            return back()->withErrors(['theme' => 'Cannot delete the default theme. Set another default first.']);
        }

        $theme->delete();

        return redirect()->route('themes.index')->with('ok', 'Theme deleted');
    }

    private function validated(Request $request, ?Theme $theme = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('themes', 'slug')->ignore($theme?->id),
            ],
            'git_repo' => ['required', 'string', 'max:500'],
            'src_path' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        if ($data['slug'] === '') {
            $data['slug'] = 'theme-'.Str::lower(Str::random(6));
        }
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default', false);
        $data['src_path'] = filled($data['src_path'] ?? null) ? $data['src_path'] : null;

        return $data;
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['owner', 'dev'], true)) {
            abort(403);
        }
    }
}

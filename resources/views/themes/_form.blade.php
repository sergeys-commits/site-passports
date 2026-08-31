@php($theme = $theme ?? null)
<div>
    <label class="block text-sm mb-1">Name</label>
    <input name="name" value="{{ old('name', $theme->name ?? '') }}" class="w-full border rounded px-3 py-2" required>
</div>
<div>
    <label class="block text-sm mb-1">Slug</label>
    <input name="slug" value="{{ old('slug', $theme->slug ?? '') }}" class="w-full border rounded px-3 py-2" placeholder="wp-theme-core" pattern="[a-z0-9\-]+">
    <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, hyphens. Auto from name if empty.</p>
</div>
<div>
    <label class="block text-sm mb-1">Git repo</label>
    <input name="git_repo" value="{{ old('git_repo', $theme->git_repo ?? '') }}" class="w-full border rounded px-3 py-2" required placeholder="git@github.com:org/theme.git">
</div>
<div>
    <label class="block text-sm mb-1">Src path on panel (optional)</label>
    <input name="src_path" value="{{ old('src_path', $theme->src_path ?? '') }}" class="w-full border rounded px-3 py-2" placeholder="storage/app/theme-src-slug">
    <p class="text-xs text-gray-500 mt-1">Leave empty to use auto path under storage.</p>
</div>
<label class="inline-flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $theme->is_active ?? true))>
    Active
</label>
<label class="inline-flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $theme->is_default ?? false))>
    Default theme
</label>

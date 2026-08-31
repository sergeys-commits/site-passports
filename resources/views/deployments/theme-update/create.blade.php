<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Theme update</h2></x-slot>

    <div class="p-6 space-y-4">
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded">
                @foreach($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <p class="text-sm text-gray-600">
            <a href="{{ route('deployments.index') }}" class="text-blue-600 underline">← Deployments</a>
        </p>

        <form method="POST" action="{{ route('theme-update.run') }}" class="space-y-4 p-4 border rounded" id="theme-update-form">
            @csrf
            <input type="hidden" name="mode" id="run_mode" value="dry_run">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Theme (filter + tags)</label>
                <select id="filter_theme_id" class="w-full border rounded p-2">
                    <option value="">All themes</option>
                    @foreach($themes as $theme)
                        <option
                            value="{{ $theme->id }}"
                            data-tags='@json($themeTags[$theme->id] ?? [])'
                        >{{ $theme->name }}@if($theme->is_default) (default)@endif</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target version</label>
                <input type="text" name="target_version" id="target_version" list="theme-update-tags"
                       value="{{ old('target_version', 'latest') }}"
                       class="w-full border rounded p-2" placeholder="v1.2.3, v2.0.0 or latest" maxlength="100" required>
                <datalist id="theme-update-tags"></datalist>
                <p class="text-xs text-gray-500 mt-1">
                    Tags <code>1.*</code> on legacy sites use git update scripts.
                    Pipeline sites and tags <code>2+</code> / <code>latest</code> use DeployTheme → <code>themes/{theme_slug}</code>.
                    Choose a theme above to load its tags into the list.
                </p>
            </div>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-gray-700">Environment</legend>
                <label class="flex items-center gap-2">
                    <input type="radio" name="environment" value="stage" class="env-radio" @checked(old('environment', 'stage') === 'stage')>
                    <span>Stage</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="environment" value="prod" class="env-radio" @checked(old('environment') === 'prod')>
                    <span>Production</span>
                </label>
            </fieldset>

            <div class="space-y-2">
                <div class="text-sm font-medium text-gray-700">Stage sites</div>
                <div class="space-y-1 border rounded p-2 max-h-48 overflow-y-auto">
                    @forelse($stageSites as $s)
                        <label class="flex items-center gap-2 py-1 site-row" data-row-env="stage" data-theme-id="{{ $s->theme_id ?? '' }}">
                            <input type="checkbox" name="site_ids[]" value="{{ $s->id }}" class="site-cb"
                                   data-site-env="stage"
                                   @disabled(old('environment', 'stage') !== 'stage')
                                   @checked(is_array(old('site_ids')) && in_array($s->id, old('site_ids'), true))>
                            <span>{{ $s->name }} — {{ $s->stage_domain ?? '—' }} — {{ $s->theme?->name ?? $s->theme_name ?? 'theme?' }} {{ $s->theme_version ?? '—' }}</span>
                        </label>
                    @empty
                        <div class="text-sm text-gray-500">No stage sites.</div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-2">
                <div class="text-sm font-medium text-gray-700">Production sites</div>
                <div class="space-y-1 border rounded p-2 max-h-48 overflow-y-auto">
                    @forelse($prodSites as $s)
                        <label class="flex items-center gap-2 py-1 site-row" data-row-env="prod" data-theme-id="{{ $s->theme_id ?? '' }}">
                            <input type="checkbox" name="site_ids[]" value="{{ $s->id }}" class="site-cb"
                                   data-site-env="prod"
                                   @disabled(old('environment', 'stage') === 'stage')
                                   @checked(is_array(old('site_ids')) && in_array($s->id, old('site_ids'), true))>
                            <span>{{ $s->name }} — {{ $s->domain ?? '—' }} — {{ $s->theme?->name ?? $s->theme_name ?? 'theme?' }} {{ $s->theme_version ?? '—' }}</span>
                        </label>
                    @empty
                        <div class="text-sm text-gray-500">No production sites.</div>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" id="btn-dry-run"
                        style="padding:10px 16px;background:#14532d;color:#fff;border-radius:8px;">
                    Dry run
                </button>
                <button type="submit" id="btn-live"
                        style="padding:10px 16px;background:#9a3412;color:#fff;border-radius:8px;">
                    Run live
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('theme-update-form');
            const modeInput = document.getElementById('run_mode');
            const envRadios = document.querySelectorAll('.env-radio');
            const checkboxes = document.querySelectorAll('.site-cb');
            const filterTheme = document.getElementById('filter_theme_id');
            const datalist = document.getElementById('theme-update-tags');
            const siteRows = document.querySelectorAll('.site-row');

            function syncTags() {
                const opt = filterTheme.options[filterTheme.selectedIndex];
                let tags = [];
                try {
                    tags = JSON.parse(opt.getAttribute('data-tags') || '[]');
                } catch (e) {
                    tags = [];
                }
                datalist.innerHTML = '';
                ['latest'].concat(tags).forEach((t) => {
                    const o = document.createElement('option');
                    o.value = t;
                    datalist.appendChild(o);
                });
            }

            function syncEnvAndFilter() {
                const env = document.querySelector('input[name="environment"]:checked')?.value || 'stage';
                const themeId = filterTheme.value;
                siteRows.forEach((row) => {
                    const rowEnv = row.getAttribute('data-row-env');
                    const rowTheme = row.getAttribute('data-theme-id') || '';
                    const envOk = rowEnv === env;
                    const themeOk = !themeId || rowTheme === themeId || rowTheme === '';
                    const show = envOk && themeOk;
                    row.style.display = show ? 'flex' : 'none';
                    const cb = row.querySelector('.site-cb');
                    if (!cb) return;
                    cb.disabled = !show;
                    if (!show) cb.checked = false;
                    row.style.opacity = show ? '1' : '0.45';
                });
            }

            envRadios.forEach((r) => r.addEventListener('change', syncEnvAndFilter));
            filterTheme.addEventListener('change', function () {
                syncTags();
                syncEnvAndFilter();
            });

            document.getElementById('btn-dry-run').addEventListener('click', function () {
                modeInput.value = 'dry_run';
            });
            document.getElementById('btn-live').addEventListener('click', function () {
                modeInput.value = 'live';
            });

            syncTags();
            syncEnvAndFilter();
        })();
    </script>
</x-app-layout>

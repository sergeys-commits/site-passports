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
                <label class="block text-sm font-medium text-gray-700 mb-1">Target version</label>
                <input type="text" name="target_version" value="{{ old('target_version', 'latest') }}"
                       class="w-full border rounded p-2" placeholder="v1.2.3 or latest" maxlength="100" required>
            </div>

            @if(count($tags) > 0)
                <div class="text-sm text-gray-500">Tags (from GitHub) — not wired yet.</div>
            @endif

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
                        <label class="flex items-center gap-2 py-1" data-row-env="stage">
                            <input type="checkbox" name="site_ids[]" value="{{ $s->id }}" class="site-cb"
                                   data-site-env="stage"
                                   @disabled(old('environment', 'stage') !== 'stage')
                                   @checked(is_array(old('site_ids')) && in_array($s->id, old('site_ids'), true))>
                            <span>{{ $s->name }} — {{ $s->stage_domain ?? '—' }} — theme {{ $s->theme_version ?? '—' }}</span>
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
                        <label class="flex items-center gap-2 py-1" data-row-env="prod">
                            <input type="checkbox" name="site_ids[]" value="{{ $s->id }}" class="site-cb"
                                   data-site-env="prod"
                                   @disabled(old('environment', 'stage') === 'stage')
                                   @checked(is_array(old('site_ids')) && in_array($s->id, old('site_ids'), true))>
                            <span>{{ $s->name }} — {{ $s->domain ?? '—' }} — theme {{ $s->theme_version ?? '—' }}</span>
                        </label>
                    @empty
                        <div class="text-sm text-gray-500">No production sites.</div>
                    @endforelse
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" id="btn-dry-run"
                        class="px-4 py-2 rounded text-white bg-gray-800 hover:bg-gray-900">
                    Dry run
                </button>
                <button type="submit" id="btn-live"
                        class="px-4 py-2 rounded text-white bg-orange-700 hover:bg-orange-800">
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

            function syncEnv() {
                const env = document.querySelector('input[name="environment"]:checked')?.value || 'stage';
                checkboxes.forEach((cb) => {
                    const rowEnv = cb.getAttribute('data-site-env');
                    const show = rowEnv === env;
                    cb.disabled = !show;
                    cb.closest('label').style.opacity = show ? '1' : '0.45';
                    if (!show) {
                        cb.checked = false;
                    }
                });
            }

            envRadios.forEach((r) => r.addEventListener('change', syncEnv));

            document.getElementById('btn-dry-run').addEventListener('click', function () {
                modeInput.value = 'dry_run';
            });
            document.getElementById('btn-live').addEventListener('click', function () {
                modeInput.value = 'live';
            });

            syncEnv();
        })();
    </script>
</x-app-layout>

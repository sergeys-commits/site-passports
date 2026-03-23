<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Promote Stage → Production</h2></x-slot>

    <div class="p-6">
        @if(session('ok'))
            <div class="p-3 bg-green-100 rounded mb-4">{{ session('ok') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded mb-4">
                @foreach($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('promote.store') }}" class="space-y-4 p-4 border rounded" id="promote-form">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Site (stage)</label>
                <select name="site_id" id="site_id" class="w-full border rounded p-2" required>
                    <option value="">— Select —</option>
                    @foreach($sites as $s)
                        <option
                            value="{{ $s->id }}"
                            data-stage-domain="{{ $s->stage_domain }}"
                            @selected(old('site_id') == $s->id)
                        >{{ $s->name }} ({{ $s->stage_domain }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stage domain</label>
                <input type="text" name="stage_domain" id="stage_domain" value="{{ old('stage_domain') }}"
                       class="w-full border rounded p-2 bg-gray-50" readonly required placeholder="Select a site first">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Production domain</label>
                <input type="text" name="prod_domain" value="{{ old('prod_domain') }}"
                       class="w-full border rounded p-2" required placeholder="example.com">
            </div>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-gray-700">Mode</legend>
                <label class="flex items-center gap-2">
                    <input type="radio" name="mode" value="dry_run" class="mode-radio" @checked(old('mode', 'dry_run') === 'dry_run')>
                    <span>Dry run (preflight only)</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="mode" value="live" class="mode-radio" @checked(old('mode') === 'live')>
                    <span>Live — promote to production</span>
                </label>
            </fieldset>

            <div id="live-warning" class="hidden p-3 rounded border border-orange-300 bg-orange-50 text-orange-900 text-sm">
                This will overwrite production. Make sure the production domain folder exists in ISPManager.
            </div>

            <div id="confirm-wrap" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type the production domain to confirm</label>
                <input type="text" name="confirm_phrase" id="confirm_phrase" value="{{ old('confirm_phrase') }}"
                       class="w-full border rounded p-2" autocomplete="off">
            </div>

            <button type="submit" id="submit-btn" style="padding:10px 16px;background:#111;color:#fff;border-radius:8px;">
                Run Dry Run
            </button>
        </form>
    </div>

    <script>
        (function () {
            const siteSelect = document.getElementById('site_id');
            const stageInput = document.getElementById('stage_domain');
            const modeRadios = document.querySelectorAll('.mode-radio');
            const confirmWrap = document.getElementById('confirm-wrap');
            const liveWarning = document.getElementById('live-warning');
            const submitBtn = document.getElementById('submit-btn');
            function syncStageDomain() {
                const opt = siteSelect.options[siteSelect.selectedIndex];
                const sd = opt ? opt.getAttribute('data-stage-domain') : '';
                stageInput.value = sd || '';
            }

            function syncModeUi() {
                const live = document.querySelector('input[name="mode"]:checked')?.value === 'live';
                confirmWrap.classList.toggle('hidden', !live);
                liveWarning.classList.toggle('hidden', !live);
                submitBtn.textContent = live ? 'Promote to Production' : 'Run Dry Run';
            }

            siteSelect.addEventListener('change', syncStageDomain);
            modeRadios.forEach((r) => r.addEventListener('change', syncModeUi));

            syncStageDomain();
            syncModeUi();
        })();
    </script>
</x-app-layout>

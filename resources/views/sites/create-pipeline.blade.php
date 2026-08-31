<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Create pipeline site (v2)</h2></x-slot>
    <div class="p-6">
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded mb-4">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <p class="text-sm text-gray-600 mb-4">
            Creates Site + immutable pins + SiteTarget(s). Docroot must already exist (ISP / Hestia).
            Theme tags <code>2+</code> use DeployTheme; create domain folder before provisioning.
        </p>

        <form method="POST" action="{{ route('sites.store_pipeline') }}" class="space-y-3 p-4 border rounded max-w-xl" id="pipeline-form">
            @csrf
            <div>
                <label class="block text-sm mb-1">Name</label>
                <input name="name" value="{{ old('name') }}" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm mb-1">Scenario</label>
                <select name="scenario" id="scenario" class="w-full border rounded px-3 py-2" required>
                    <option value="stage_then_prod" @selected(old('scenario', 'stage_then_prod') === 'stage_then_prod')>A — stage_then_prod</option>
                    <option value="prod_basic_auth" @selected(old('scenario') === 'prod_basic_auth')>B — prod_basic_auth</option>
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">Theme</label>
                <select name="theme_id" id="theme_id" class="w-full border rounded px-3 py-2" required>
                    @foreach($themes as $theme)
                        <option
                            value="{{ $theme->id }}"
                            data-tags='@json($themeTags[$theme->id] ?? [])'
                            @selected((int) old('theme_id', $defaultThemeId) === $theme->id)
                        >
                            {{ $theme->name }}@if($theme->is_default) (default)@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="staging-server-fields">
                <label class="block text-sm mb-1">Staging server</label>
                <select name="staging_server_id" id="staging_server_id" class="w-full border rounded px-3 py-2 server-select">
                    @foreach($servers as $server)
                        <option
                            value="{{ $server->id }}"
                            data-access="{{ $server->access_type }}"
                            @selected((int) old('staging_server_id', old('server_id')) === $server->id)
                        >
                            {{ $server->name }} ({{ $server->access_type }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div id="prod-server-fields">
                <label class="block text-sm mb-1">Production server</label>
                <select name="production_server_id" id="production_server_id" class="w-full border rounded px-3 py-2 server-select">
                    @foreach($servers as $server)
                        <option
                            value="{{ $server->id }}"
                            data-access="{{ $server->access_type }}"
                            @selected((int) old('production_server_id', old('server_id')) === $server->id)
                        >
                            {{ $server->name }} ({{ $server->access_type }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div id="single-server-fields" style="display:none;">
                <label class="block text-sm mb-1">Server</label>
                <select name="server_id" id="server_id" class="w-full border rounded px-3 py-2 server-select">
                    @foreach($servers as $server)
                        <option
                            value="{{ $server->id }}"
                            data-access="{{ $server->access_type }}"
                            @selected((int) old('server_id') === $server->id)
                        >
                            {{ $server->name }} ({{ $server->access_type }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="remote-warning" class="hidden p-3 rounded border border-amber-300 bg-amber-50 text-amber-950 text-sm">
                Remote SSH: WordPress core install is not automated yet. Create the domain docroot and install WP on the server first.
                Leave «Queue ProvisionWordPress» unchecked, then run DeployTheme / theme update after WP exists.
            </div>

            <div id="staging-fields">
                <label class="block text-sm mb-1">Staging domain</label>
                <input name="staging_domain" value="{{ old('staging_domain') }}" class="w-full border rounded px-3 py-2" placeholder="stage.example.com">
            </div>
            <div id="prod-fields">
                <label class="block text-sm mb-1">Production domain</label>
                <input name="production_domain" value="{{ old('production_domain') }}" class="w-full border rounded px-3 py-2" placeholder="example.com">
            </div>
            <label class="inline-flex items-center gap-2 text-sm" id="basic-auth-field">
                <input type="checkbox" name="basic_auth" value="1" @checked(old('basic_auth', true))>
                Basic auth on production (scenario B)
            </label>
            <div>
                <label class="block text-sm mb-1">Profile (optional)</label>
                <select name="profile_id" class="w-full border rounded px-3 py-2">
                    <option value="">auto</option>
                    @foreach(['p01','p02','p03','p04','p05','p06','p07','p08'] as $p)
                        <option value="{{ $p }}" @selected(old('profile_id') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm mb-1">Theme git ref</label>
                <input name="git_ref" id="git_ref" list="git-ref-tags" value="{{ old('git_ref', 'latest') }}" class="w-full border rounded px-3 py-2" placeholder="v2.0.0 or latest">
                <datalist id="git-ref-tags"></datalist>
                <p class="text-xs text-gray-500 mt-1">Pick a tag from the list or type <code>latest</code> / a SHA.</p>
            </div>
            <div>
                <label class="block text-sm mb-1">Group</label>
                <select name="group_id" class="w-full border rounded px-3 py-2">
                    <option value="">No group</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected((int) old('group_id') === $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm" id="provision-wrap">
                <input type="checkbox" name="provision_now" id="provision_now" value="1" @checked(old('provision_now'))>
                <span id="provision-label">Queue ProvisionWordPress now (WP install + DeployTheme)</span>
            </label>
            <div>
                <button type="submit" style="padding:10px 16px;background:#14532d;color:#fff;border-radius:8px;">Create</button>
                <a href="{{ route('sites.index') }}" class="ml-2 text-blue-600 underline">Cancel</a>
            </div>
        </form>
    </div>
    <script>
        (function () {
            const scenario = document.getElementById('scenario');
            const staging = document.getElementById('staging-fields');
            const prod = document.getElementById('prod-fields');
            const auth = document.getElementById('basic-auth-field');
            const stagingServer = document.getElementById('staging-server-fields');
            const prodServer = document.getElementById('prod-server-fields');
            const singleServer = document.getElementById('single-server-fields');
            const remoteWarning = document.getElementById('remote-warning');
            const provisionNow = document.getElementById('provision_now');
            const themeSelect = document.getElementById('theme_id');
            const datalist = document.getElementById('git-ref-tags');

            function selectedAccessTypes() {
                const v = scenario.value;
                const ids = v === 'stage_then_prod'
                    ? ['staging_server_id', 'production_server_id']
                    : ['server_id'];
                return ids.map((id) => {
                    const el = document.getElementById(id);
                    const opt = el?.options[el.selectedIndex];
                    return opt ? opt.getAttribute('data-access') : 'local';
                });
            }

            function syncRemote() {
                const accesses = selectedAccessTypes();
                const hasSsh = accesses.includes('ssh');
                remoteWarning.classList.toggle('hidden', !hasSsh);
                if (hasSsh) {
                    provisionNow.checked = false;
                    provisionNow.disabled = true;
                } else {
                    provisionNow.disabled = false;
                }
            }

            function syncScenario() {
                const v = scenario.value;
                const isA = v === 'stage_then_prod';
                staging.style.display = isA ? 'block' : 'none';
                prod.style.display = isA ? 'none' : 'block';
                auth.style.display = isA ? 'none' : 'flex';
                stagingServer.style.display = isA ? 'block' : 'none';
                prodServer.style.display = isA ? 'block' : 'none';
                singleServer.style.display = isA ? 'none' : 'block';
                syncRemote();
            }

            function syncTags() {
                const opt = themeSelect.options[themeSelect.selectedIndex];
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

            scenario.addEventListener('change', syncScenario);
            document.querySelectorAll('.server-select').forEach((el) => el.addEventListener('change', syncRemote));
            themeSelect.addEventListener('change', syncTags);
            syncScenario();
            syncTags();
        })();
    </script>
</x-app-layout>

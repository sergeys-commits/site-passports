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

        <form method="POST" action="{{ route('sites.store_pipeline') }}" class="space-y-3 p-4 border rounded max-w-xl">
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
                <label class="block text-sm mb-1">Server</label>
                <select name="server_id" class="w-full border rounded px-3 py-2" required>
                    @foreach($servers as $server)
                        <option value="{{ $server->id }}" @selected((int) old('server_id') === $server->id)>
                            {{ $server->name }} ({{ $server->access_type }})
                        </option>
                    @endforeach
                </select>
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
                <label class="block text-sm mb-1">Theme git ref for provision</label>
                <input name="git_ref" value="{{ old('git_ref', 'latest') }}" class="w-full border rounded px-3 py-2" placeholder="v2.0.0 or latest">
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
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="provision_now" value="1" @checked(old('provision_now'))>
                Queue ProvisionWordPress now (WP install + DeployTheme)
            </label>
            <div>
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded">Create</button>
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
            function sync() {
                const v = scenario.value;
                staging.style.display = v === 'stage_then_prod' ? 'block' : 'none';
                prod.style.display = v === 'prod_basic_auth' ? 'block' : 'none';
                auth.style.display = v === 'prod_basic_auth' ? 'flex' : 'none';
            }
            scenario.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>

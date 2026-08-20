@php($server = $server ?? null)
<div>
    <label class="block text-sm mb-1">Name</label>
    <input name="name" value="{{ old('name', $server->name ?? '') }}" class="w-full border rounded px-3 py-2" required>
</div>
<div>
    <label class="block text-sm mb-1">Connection</label>
    <select name="connection" class="w-full border rounded px-3 py-2" required>
        @foreach(['local', 'ssh'] as $c)
            <option value="{{ $c }}" @selected(old('connection', $server->connection ?? 'local') === $c)>{{ $c }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm mb-1">Host</label>
    <input name="host" value="{{ old('host', $server->host ?? '127.0.0.1') }}" class="w-full border rounded px-3 py-2">
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-sm mb-1">SSH port</label>
        <input type="number" name="ssh_port" value="{{ old('ssh_port', $server->ssh_port ?? 22) }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label class="block text-sm mb-1">SSH user</label>
        <input name="ssh_user" value="{{ old('ssh_user', $server->ssh_user ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
</div>
<div>
    <label class="block text-sm mb-1">SSH key path (on panel)</label>
    <input name="ssh_key_path" value="{{ old('ssh_key_path', $server->ssh_key_path ?? '') }}" class="w-full border rounded px-3 py-2" placeholder="/var/www/.../.ssh/id_ed25519">
</div>
<div>
    <label class="block text-sm mb-1">Panel type</label>
    <select name="panel_type" class="w-full border rounded px-3 py-2" required>
        @foreach(['isp', 'hestia', 'none'] as $p)
            <option value="{{ $p }}" @selected(old('panel_type', $server->panel_type ?? 'none') === $p)>{{ $p }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm mb-1">WP sites root</label>
    <input name="wp_sites_root" value="{{ old('wp_sites_root', $server->wp_sites_root ?? '/var/www/www-root/data/www') }}" class="w-full border rounded px-3 py-2" required>
</div>
<label class="inline-flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $server->is_active ?? true))>
    Active
</label>

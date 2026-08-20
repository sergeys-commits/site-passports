<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">{{ $server->name }}</h2>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('servers.check', $server) }}">
                    @csrf
                    <button type="submit" class="px-3 py-2 border rounded text-sm">Check connection</button>
                </form>
                <a href="{{ route('servers.edit', $server) }}" class="px-3 py-2 bg-gray-900 text-white rounded text-sm">Edit</a>
            </div>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        @if(session('ok'))
            <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div>
        @endif
        @if(session('error'))
            <div class="p-3 bg-red-100 rounded">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <div class="p-4 border rounded space-y-1 text-sm">
            <div><b>Connection:</b> {{ $server->access_type }}</div>
            <div><b>Host:</b> {{ $server->host }}</div>
            <div><b>SSH:</b> {{ $server->ssh_user ?: '—' }}:{{ $server->ssh_port }}</div>
            <div><b>Key:</b> <code>{{ $server->ssh_key_path ?: '—' }}</code></div>
            <div><b>Panel:</b> {{ $server->panel_type }}</div>
            <div><b>wp_sites_root:</b> <code>{{ $server->wp_sites_root }}</code></div>
            <div><b>Active:</b> {{ $server->is_active ? 'yes' : 'no' }}</div>
        </div>
        <form method="POST" action="{{ route('servers.destroy', $server) }}" onsubmit="return confirm('Delete this server?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 underline text-sm">Delete server</button>
        </form>
    </div>
</x-app-layout>

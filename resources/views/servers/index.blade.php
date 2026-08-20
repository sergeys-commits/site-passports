<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">Servers</h2>
            <a href="{{ route('servers.create') }}" class="px-3 py-2 bg-gray-900 text-white rounded text-sm">+ Add server</a>
        </div>
    </x-slot>
    <div class="p-6">
        @if(session('ok'))
            <div class="p-3 bg-green-100 rounded mb-4">{{ session('ok') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded mb-4">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <div class="border rounded overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Connection</th>
                        <th class="p-3">Host</th>
                        <th class="p-3">Panel</th>
                        <th class="p-3">wp_sites_root</th>
                        <th class="p-3">Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($servers as $server)
                        <tr class="border-t">
                            <td class="p-3">
                                <a href="{{ route('servers.show', $server) }}" class="text-blue-600 underline">{{ $server->name }}</a>
                            </td>
                            <td class="p-3">{{ $server->access_type }}</td>
                            <td class="p-3">{{ $server->host }}</td>
                            <td class="p-3">{{ $server->panel_type }}</td>
                            <td class="p-3 font-mono text-xs">{{ $server->wp_sites_root }}</td>
                            <td class="p-3">{{ $server->is_active ? 'yes' : 'no' }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="6">No servers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

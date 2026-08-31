<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">Themes</h2>
            <a href="{{ route('themes.create') }}" style="padding:8px 12px;background:#14532d;color:#fff;border-radius:8px;text-decoration:none;font-size:0.875rem;">+ Add theme</a>
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
                        <th class="p-3">Slug</th>
                        <th class="p-3">Git repo</th>
                        <th class="p-3">Default</th>
                        <th class="p-3">Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($themes as $theme)
                        <tr class="border-t">
                            <td class="p-3">
                                <a href="{{ route('themes.show', $theme) }}" class="text-blue-600 underline">{{ $theme->name }}</a>
                            </td>
                            <td class="p-3 font-mono text-xs">{{ $theme->slug }}</td>
                            <td class="p-3 font-mono text-xs break-all">{{ $theme->git_repo }}</td>
                            <td class="p-3">{{ $theme->is_default ? 'yes' : 'no' }}</td>
                            <td class="p-3">{{ $theme->is_active ? 'yes' : 'no' }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="5">No themes yet. Seed or add one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

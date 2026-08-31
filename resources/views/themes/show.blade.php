<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">{{ $theme->name }}</h2>
            <a href="{{ route('themes.edit', $theme) }}" style="padding:8px 12px;background:#14532d;color:#fff;border-radius:8px;text-decoration:none;font-size:0.875rem;">Edit</a>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        @if(session('ok'))
            <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <div class="p-4 border rounded space-y-1 text-sm">
            <div><b>Slug:</b> <code>{{ $theme->slug }}</code></div>
            <div><b>Git repo:</b> <code class="break-all">{{ $theme->git_repo }}</code></div>
            <div><b>Src path:</b> <code>{{ $theme->src_path ?: $theme->resolveSrcPath() }}</code></div>
            <div><b>Default:</b> {{ $theme->is_default ? 'yes' : 'no' }}</div>
            <div><b>Active:</b> {{ $theme->is_active ? 'yes' : 'no' }}</div>
        </div>
        <form method="POST" action="{{ route('themes.destroy', $theme) }}" onsubmit="return confirm('Delete this theme?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 underline text-sm">Delete theme</button>
        </form>
    </div>
</x-app-layout>

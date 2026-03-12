<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Sites</h2></x-slot>
    <div class="p-6 space-y-6">
        @if(session('ok')) <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div> @endif

        <form method="POST" action="{{ route('sites.store') }}" class="p-4 border rounded space-y-3">
            @csrf
            <div><input name="name" placeholder="Name" class="w-full border rounded p-2" required></div>
            <div><input name="domain" placeholder="Domain" class="w-full border rounded p-2" required></div>
            <div><input name="stage_domain" placeholder="Stage domain" class="w-full border rounded p-2"></div>
            <div>
                <select name="group_id" class="w-full border rounded p-2">
                    <option value="">No group</option>
                    @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
            </div>
            <div><input name="theme_name" placeholder="Theme name" class="w-full border rounded p-2"></div>
            <div><input name="theme_version" placeholder="Theme version" class="w-full border rounded p-2"></div>
            <div>
                <select name="status" class="w-full border rounded p-2">
                    <option value="active">active</option>
                    <option value="stage">stage</option>
                    <option value="archived">archived</option>
                </select>
            </div>
            <div><textarea name="notes" placeholder="Notes" class="w-full border rounded p-2"></textarea></div>
            <button class="px-4 py-2 bg-black text-white rounded">Create site</button>
        </form>

        <div class="border rounded">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-100"><th class="p-2 text-left">Name</th><th class="p-2 text-left">Domain</th><th class="p-2">Status</th><th class="p-2">Theme</th></tr></thead>
                <tbody>
                @foreach($sites as $s)
                    <tr class="border-t">
                        <td class="p-2"><a class="underline" href="{{ route('sites.show',$s->id) }}">{{ $s->name }}</a></td>
                        <td class="p-2">{{ $s->domain }}</td>
                        <td class="p-2 text-center">{{ $s->status }}</td>
                        <td class="p-2">{{ $s->theme_name }} {{ $s->theme_version }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

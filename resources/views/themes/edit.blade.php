<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Edit Theme</h2></x-slot>
    <div class="p-6">
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded mb-4">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('themes.update', $theme) }}" class="space-y-3 p-4 border rounded max-w-xl">
            @csrf
            @method('PUT')
            @include('themes._form', ['theme' => $theme])
            <button type="submit" style="padding:10px 16px;background:#14532d;color:#fff;border-radius:8px;">Save</button>
            <a href="{{ route('themes.show', $theme) }}" class="ml-2 text-blue-600 underline">Cancel</a>
        </form>
    </div>
</x-app-layout>

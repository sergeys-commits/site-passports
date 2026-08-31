<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Add Theme</h2></x-slot>
    <div class="p-6">
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded mb-4">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('themes.store') }}" class="space-y-3 p-4 border rounded max-w-xl">
            @csrf
            @include('themes._form')
            <button type="submit" style="padding:10px 16px;background:#14532d;color:#fff;border-radius:8px;">Create</button>
            <a href="{{ route('themes.index') }}" class="ml-2 text-blue-600 underline">Cancel</a>
        </form>
    </div>
</x-app-layout>

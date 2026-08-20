<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Add Server</h2></x-slot>
    <div class="p-6">
        @if($errors->any())
            <div class="p-3 bg-red-100 rounded mb-4">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('servers.store') }}" class="space-y-3 p-4 border rounded max-w-xl">
            @csrf
            @include('servers._form')
            <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded">Create</button>
            <a href="{{ route('servers.index') }}" class="ml-2 text-blue-600 underline">Cancel</a>
        </form>
    </div>
</x-app-layout>

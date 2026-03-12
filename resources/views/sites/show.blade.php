<x-app-layout>
<x-slot name="header">
<div class="flex items-center justify-between">
<h2 class="font-semibold text-xl">{{ $site->name }}</h2>
<a href="{{ route('sites.edit', $site->id) }}" class="px-3 py-2 bg-black text-white rounded text-sm">Edit</a>
</div>
</x-slot>

<div class="p-6 space-y-4">
@if(session('ok'))
<div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div>
@endif

<div class="p-4 border rounded">
<div><b>Domain:</b> {{ $site->domain }}</div>
<div><b>Stage:</b> {{ $site->stage_domain }}</div>
<div><b>Status:</b> {{ $site->status }}</div>
<div><b>Theme:</b> {{ $site->theme_name }} {{ $site->theme_version }}</div>
<div><b>Launch date:</b> {{ optional($site->launch_date)->format('Y-m-d') }}</div>
<div><b>Transfer date:</b> {{ optional($site->transfer_date)->format('Y-m-d') }}</div>
<div><b>Notes:</b> {{ $site->notes }}</div>
</div>

<div class="p-4 border rounded">
<h3 class="font-semibold mb-2">Events</h3>
<ul class="list-disc pl-5">
@forelse($site->events()->latest()->limit(20)->get() as $e)
<li>{{ $e->created_at }} — {{ $e->event_type }} ({{ $e->source }})</li>
@empty
<li>No events</li>
@endforelse
</ul>
</div>
</div>
</x-app-layout>

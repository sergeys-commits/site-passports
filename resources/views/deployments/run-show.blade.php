<x-app-layout>
<x-slot name="header"><h2 class="font-semibold text-xl">Deployment Run #{{ $run->id }}</h2></x-slot>

<div class="p-6 space-y-4">
@if(session('ok')) <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div> @endif

<div class="p-4 border rounded">
<div><b>Action:</b> {{ $run->action_type }}</div>
<div><b>Mode:</b> {{ $run->mode }}</div>
<div><b>Status:</b> {{ $run->status }}</div>
<div><b>Requested by:</b> {{ $run->requester->email ?? 'n/a' }}</div>
<div><b>Started:</b> {{ $run->started_at }}</div>
<div><b>Finished:</b> {{ $run->finished_at }}</div>
</div>

<div class="p-4 border rounded">
<h3 class="font-semibold mb-2">Logs</h3>
<pre style="background:#0b1020;color:#e5e7eb;padding:12px;border-radius:8px;overflow:auto;">@foreach($run->logs as $l)[{{ $l->line_no }}][{{ $l->stream }}] {{ $l->message }}
@endforeach</pre>
</div>
</div>
</x-app-layout>

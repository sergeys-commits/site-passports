<x-app-layout>
<x-slot name="header"><h2 class="font-semibold text-xl">Deployment Run #{{ $run->id }}</h2></x-slot>

<div class="p-6 space-y-4">
@if(session('ok')) <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div> @endif
@if($errors->has('deploy'))
<div class="p-3 bg-red-100 rounded">{{ $errors->first('deploy') }}</div>
@endif

<div class="p-4 border rounded">
<div><b>Action:</b> {{ $run->action_type }}</div>
<div><b>Mode:</b> {{ $run->mode }}</div>
<div><b>Status:</b> {{ $run->status }}
@if(in_array($run->status, ['queued', 'running'], true))
 — <span class="text-amber-700">refresh this page for updates</span>
@endif
</div>
<div><b>Requested by:</b> {{ $run->requester->email ?? 'n/a' }}</div>
<div><b>Started:</b> {{ $run->started_at }}</div>
<div><b>Finished:</b> {{ $run->finished_at }}</div>
@if(!empty($run->meta_json))
<div class="mt-2 text-xs font-mono overflow-auto">{{ json_encode($run->meta_json, JSON_UNESCAPED_SLASHES) }}</div>
@endif
</div>

<div class="p-4 border rounded">
<h3 class="font-semibold mb-2">Logs</h3>
<pre style="background:#0b1020;color:#e5e7eb;padding:12px;border-radius:8px;overflow:auto;">@foreach($run->logs as $l)[{{ $l->line_no }}][{{ $l->stream }}] {{ $l->message }}
@endforeach</pre>
</div>
</div>
</x-app-layout>

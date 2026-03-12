<x-app-layout>
<x-slot name="header"><h2 class="font-semibold text-xl">Create Stage Site</h2></x-slot>
<div class="p-6">
@if(session('ok')) <div class="p-3 bg-green-100 rounded mb-4">{{ session('ok') }}</div> @endif
@if($errors->any())
<div class="p-3 bg-red-100 rounded mb-4">

@foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
</div>
@endif

<form method="POST" action="{{ route('deployments.stage_provision.store') }}" class="space-y-3 p-4 border rounded">
@csrf
<div><input name="name" value="{{ old('name') }}" placeholder="Name" class="w-full border rounded p-2"></div>
<div><input name="stage_domain" value="{{ old('stage_domain') }}" placeholder="Stage domain" class="w-full border rounded p-2"></div>
<select name="mode" class="w-full border rounded p-2">
<option value="dry_run" @selected(old('mode')==='dry_run')>dry_run</option>
<option value="live" @selected(old('mode')==='live')>live</option>
</select>
<div><input name="confirm_phrase" value="{{ old('confirm_phrase') }}" placeholder="CONFIRM STAGE LIVE (only for live)" class="w-full border rounded p-2"></div>

<button style="padding:10px 16px;background:#111;color:#fff;border-radius:8px;">Run</button>
</form>
</div>
</x-app-layout>

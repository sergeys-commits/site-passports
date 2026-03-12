<x-app-layout>
<x-slot name="header">
<h2 class="font-semibold text-xl">Edit site: {{ $site->name }}</h2>
</x-slot>

<div class="p-6">
<form method="POST" action="{{ route('sites.update', $site->id) }}" class="p-4 border rounded space-y-3">
@csrf
@method('PATCH')

<div><input name="name" value="{{ old('name', $site->name) }}" class="w-full border rounded p-2" required></div>
<div><input name="domain" value="{{ old('domain', $site->domain) }}" class="w-full border rounded p-2" required></div>
<div><input name="stage_domain" value="{{ old('stage_domain', $site->stage_domain) }}" class="w-full border rounded p-2"></div>

<div>
<select name="group_id" class="w-full border rounded p-2">
<option value="">No group</option>
@foreach($groups as $g)
<option value="{{ $g->id }}" @selected(old('group_id', $site->group_id)==$g->id)>{{ $g->name }}</option>
@endforeach
</select>
</div>

<div><input name="theme_name" value="{{ old('theme_name', $site->theme_name) }}" class="w-full border rounded p-2"></div>
<div><input name="theme_version" value="{{ old('theme_version', $site->theme_version) }}" class="w-full border rounded p-2"></div>

<div>
<select name="status" class="w-full border rounded p-2">
<option value="active" @selected(old('status', $site->status)==='active')>active</option>
<option value="stage" @selected(old('status', $site->status)==='stage')>stage</option>
<option value="archived" @selected(old('status', $site->status)==='archived')>archived</option>
</select>
</div>

<div><input type="date" name="launch_date" value="{{ old('launch_date', optional($site->launch_date)->format('Y-m-d')) }}" class="w-full border rounded p-2"></div>
<div><input type="date" name="transfer_date" value="{{ old('transfer_date', optional($site->transfer_date)->format('Y-m-d')) }}" class="w-full border rounded p-2"></div>
<div><textarea name="notes" class="w-full border rounded p-2">{{ old('notes', $site->notes) }}</textarea></div>

<div class="flex gap-2">
<button style="padding:10px 16px;background:#0f172a;color:#fff;border:1px solid #0f172a;border-radius:8px;cursor:pointer;">Save</button>
<a href="{{ route('sites.show', $site->id) }}" style="padding:10px 16px;background:#fff;color:#111;border:1px solid #334155;border-radius:8px;text-decoration:none;">Cancel</a>
</div>
</form>
</div>
</x-app-layout>

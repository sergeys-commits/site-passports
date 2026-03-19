<x-app-layout>
<x-slot name="header"><h2 class="font-semibold text-xl">Onboard Existing Prod Site</h2></x-slot>
<div class="p-6">
@if(session('ok')) <div class="p-3 bg-green-100 rounded mb-4">{{ session('ok') }}</div> @endif

<form method="POST" action="{{ route('deployments.onboard_prod.store') }}" class="space-y-3 p-4 border rounded">
@csrf
<div><input name="name" placeholder="Name" class="w-full border rounded p-2" required></div>
<div><input name="domain" placeholder="Prod domain" class="w-full border rounded p-2" required></div>
<div><input name="stage_domain" placeholder="Stage domain (optional)" class="w-full border rounded p-2"></div>
<div><input name="theme_name" placeholder="Theme name" class="w-full border rounded p-2"></div>
<div><input name="theme_version" placeholder="Theme version" class="w-full border rounded p-2"></div>

<div>
<select name="status" class="w-full border rounded p-2">
<option value="active">active</option>
<option value="stage">stage</option>
<option value="archived">archived</option>
</select>
</div>

<div><input type="date" name="launch_date" class="w-full border rounded p-2"></div>
<div><input type="date" name="transfer_date" class="w-full border rounded p-2"></div>
<div><textarea name="notes" placeholder="Notes" class="w-full border rounded p-2"></textarea></div>

<button style="padding:10px 16px;background:#111;color:#fff;border-radius:8px;">Create site card</button>
</form>
</div>
</x-app-layout>

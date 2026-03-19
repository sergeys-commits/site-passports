<?php
namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\SiteGroup;
use Illuminate\Http\Request;

class SiteController extends Controller
{
public function index()
{
$user = auth()->user();
$query = Site::with('group')->latest();

if (!in_array($user->role, ['owner','dev'], true)) {
$groupIds = $user->siteGroups()->pluck('site_groups.id');
$query->whereIn('group_id', $groupIds);
}

return view('sites.index', [
'sites' => $query->get(),
'groups' => SiteGroup::orderBy('name')->get(),
]);
}

public function store(Request $request)
{
$data = $request->validate([
'name' => 'required|string|max:190',
'domain' => 'required|string|max:190|unique:sites,domain',
'stage_domain' => 'nullable|string|max:190',
'group_id' => 'nullable|exists:site_groups,id',
'theme_name' => 'nullable|string|max:190',
'theme_version' => 'nullable|string|max:50',
'status' => 'required|in:active,stage,archived',
'launch_date' => 'nullable|date',
'transfer_date' => 'nullable|date',
'notes' => 'nullable|string',
]);

$site = Site::create($data);

SiteEvent::create([
'site_id' => $site->id,
'event_type' => 'site_created',
'source' => 'manual',
'payload' => $data,
'created_by' => auth()->id(),
]);

return redirect()->route('sites.index')->with('ok', 'Site created');
}

public function show(Site $site)
{
if (!auth()->user()->canAccessSite($site)) abort(403);
$site->load(['group','plugins','events']);
return view('sites.show', compact('site'));
}

public function edit(Site $site)
{
if (!auth()->user()->canAccessSite($site)) abort(403);
return view('sites.edit', [
'site' => $site,
'groups' => SiteGroup::orderBy('name')->get(),
]);
}

public function update(Request $request, Site $site)
{
if (!auth()->user()->canAccessSite($site)) abort(403);

$data = $request->validate([
'name' => 'required|string|max:190',
'domain' => 'required|string|max:190|unique:sites,domain,' . $site->id,
'stage_domain' => 'nullable|string|max:190',
'group_id' => 'nullable|exists:site_groups,id',
'theme_name' => 'nullable|string|max:190',
'theme_version' => 'nullable|string|max:50',
'status' => 'required|in:active,stage,archived',
'launch_date' => 'nullable|date',
'transfer_date' => 'nullable|date',
'notes' => 'nullable|string',
]);

$before = $site->only(array_keys($data));
$site->update($data);
$after = $site->only(array_keys($data));

SiteEvent::create([
'site_id' => $site->id,
'event_type' => 'site_updated',
'source' => 'manual',
'payload' => ['before' => $before, 'after' => $after],
'created_by' => auth()->id(),
]);

return redirect()->route('sites.show', $site->id)->with('ok', 'Site updated');
}
}

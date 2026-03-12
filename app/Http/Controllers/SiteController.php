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
}

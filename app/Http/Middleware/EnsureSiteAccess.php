<?php
namespace App\Http\Middleware;
use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteAccess {
    public function handle(Request $request, Closure $next): Response {
        $site = Site::findOrFail($request->route('site'));
        if (!auth()->user() || !auth()->user()->canAccessSite($site)) {
            abort(403, 'No access to this site');
        }
        return $next($request);
    }
}

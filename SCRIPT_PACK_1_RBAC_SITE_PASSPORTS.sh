#!/usr/bin/env bash
set -euo pipefail

cd /var/www/www-root/data/www/passport-stage.narniapanel.top

# 0) branch
git checkout -b feature/rbac-site-passports-mvp || git checkout feature/rbac-site-passports-mvp

# 1) migrations + models + controller
php artisan make:model SiteGroup -m
php artisan make:model Site -m
php artisan make:model SitePlugin -m
php artisan make:model SiteEvent -m
php artisan make:controller SiteController
php artisan make:middleware EnsureSiteAccess

# 2) add role column to users
php artisan make:migration add_role_to_users_table --table=users

# ---- overwrite migration files ----

# users.role
cat > database/migrations/*_add_role_to_users_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('operator')->index();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
PHP

# site_groups
cat > database/migrations/*_create_site_groups_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('site_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('site_groups');
    }
};
PHP

# sites
cat > database/migrations/*_create_sites_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('stage_domain')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('site_groups')->nullOnDelete();
            $table->string('theme_name')->nullable();
            $table->string('theme_version', 50)->nullable();
            $table->timestamp('theme_changed_at')->nullable();
            $table->string('php_version', 50)->nullable();
            $table->string('wp_version', 50)->nullable();
            $table->enum('status', ['active','stage','archived'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sites');
    }
};
PHP

# site_plugins
cat > database/migrations/*_create_site_plugins_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('site_plugins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('plugin_slug');
            $table->string('plugin_version', 50)->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->unique(['site_id','plugin_slug']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('site_plugins');
    }
};
PHP

# site_events
cat > database/migrations/*_create_site_events_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('source', 100)->default('manual');
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['site_id','created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('site_events');
    }
};
PHP

# pivot user_site_groups
php artisan make:migration create_user_site_groups_table
cat > database/migrations/*_create_user_site_groups_table.php <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_site_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('site_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id','group_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_site_groups');
    }
};
PHP

# 3) models
cat > app/Models/SiteGroup.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteGroup extends Model {
    protected $fillable = ['name'];
    public function sites(): HasMany { return $this->hasMany(Site::class, 'group_id'); }
}
PHP

cat > app/Models/Site.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model {
    protected $fillable = [
        'name','domain','stage_domain','group_id','theme_name','theme_version',
        'theme_changed_at','php_version','wp_version','status','notes'
    ];
    protected $casts = ['theme_changed_at' => 'datetime'];
    public function group(): BelongsTo { return $this->belongsTo(SiteGroup::class, 'group_id'); }
    public function plugins(): HasMany { return $this->hasMany(SitePlugin::class); }
    public function events(): HasMany { return $this->hasMany(SiteEvent::class); }
}
PHP

cat > app/Models/SitePlugin.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePlugin extends Model {
    protected $fillable = ['site_id','plugin_slug','plugin_version','active'];
    protected $casts = ['active' => 'boolean'];
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
}
PHP

cat > app/Models/SiteEvent.php <<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteEvent extends Model {
    protected $fillable = ['site_id','event_type','source','payload','created_by'];
    protected $casts = ['payload' => 'array'];
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
}
PHP

# patch User model (role + groups relation + helper)
php -r '
$f="app/Models/User.php";
$c=file_get_contents($f);
if(strpos($c,"role")===false){
  $c=str_replace("protected \$fillable = [", "protected \$fillable = [\n        '\''role'\'',", $c);
}
if(strpos($c,"siteGroups()")===false){
  $insert="\n    public function siteGroups() {\n        return \$this->belongsToMany(\\App\\Models\\SiteGroup::class, '\''user_site_groups'\'', '\''user_id'\'', '\''group_id'\'');\n    }\n\n    public function canAccessSite(\\App\\Models\\Site \$site): bool {\n        if (in_array(\$this->role, ['\''owner'\'','\''dev'\''], true)) return true;\n        if (!\$site->group_id) return false;\n        return \$this->siteGroups()->where('\''site_groups.id'\'', \$site->group_id)->exists();\n    }\n";
  $c=preg_replace("/}\\s*$/", $insert."\n}\n", $c);
}
file_put_contents($f,$c);
'

# middleware
cat > app/Http/Middleware/EnsureSiteAccess.php <<'PHP'
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
PHP

# register middleware alias
php -r '
$f="bootstrap/app.php";
$c=file_get_contents($f);
if(strpos($c,"ensure.site.access")===false){
  $c=str_replace("->withMiddleware(function (Middleware \$middleware): void {", "->withMiddleware(function (Middleware \$middleware): void {\n        \$middleware->alias([\n            '\''ensure.site.access'\'' => \\App\\Http\\Middleware\\EnsureSiteAccess::class,\n        ]);", $c);
}
file_put_contents($f,$c);
'

# controller
cat > app/Http/Controllers/SiteController.php <<'PHP'
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
PHP

# routes
cat > routes/web.php <<'PHP'
<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('sites.index'))->name('dashboard');

    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
PHP

# views
mkdir -p resources/views/sites

cat > resources/views/sites/index.blade.php <<'BLADE'
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Sites</h2></x-slot>
    <div class="p-6 space-y-6">
        @if(session('ok')) <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div> @endif

        <form method="POST" action="{{ route('sites.store') }}" class="p-4 border rounded space-y-3">
            @csrf
            <div><input name="name" placeholder="Name" class="w-full border rounded p-2" required></div>
            <div><input name="domain" placeholder="Domain" class="w-full border rounded p-2" required></div>
            <div><input name="stage_domain" placeholder="Stage domain" class="w-full border rounded p-2"></div>
            <div>
                <select name="group_id" class="w-full border rounded p-2">
                    <option value="">No group</option>
                    @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
            </div>
            <div><input name="theme_name" placeholder="Theme name" class="w-full border rounded p-2"></div>
            <div><input name="theme_version" placeholder="Theme version" class="w-full border rounded p-2"></div>
            <div>
                <select name="status" class="w-full border rounded p-2">
                    <option value="active">active</option>
                    <option value="stage">stage</option>
                    <option value="archived">archived</option>
                </select>
            </div>
            <div><textarea name="notes" placeholder="Notes" class="w-full border rounded p-2"></textarea></div>
            <button class="px-4 py-2 bg-black text-white rounded">Create site</button>
        </form>

        <div class="border rounded">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-100"><th class="p-2 text-left">Name</th><th class="p-2 text-left">Domain</th><th class="p-2">Status</th><th class="p-2">Theme</th></tr></thead>
                <tbody>
                @foreach($sites as $s)
                    <tr class="border-t">
                        <td class="p-2"><a class="underline" href="{{ route('sites.show',$s->id) }}">{{ $s->name }}</a></td>
                        <td class="p-2">{{ $s->domain }}</td>
                        <td class="p-2 text-center">{{ $s->status }}</td>
                        <td class="p-2">{{ $s->theme_name }} {{ $s->theme_version }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
BLADE

cat > resources/views/sites/show.blade.php <<'BLADE'
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $site->name }}</h2></x-slot>
    <div class="p-6 space-y-4">
        <div class="p-4 border rounded">
            <div><b>Domain:</b> {{ $site->domain }}</div>
            <div><b>Stage:</b> {{ $site->stage_domain }}</div>
            <div><b>Status:</b> {{ $site->status }}</div>
            <div><b>Theme:</b> {{ $site->theme_name }} {{ $site->theme_version }}</div>
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
BLADE

# 4) migrate + build
php artisan migrate --force
php artisan optimize:clear
npm run build

# 5) set first user's role to owner
php artisan tinker --execute="\$u=\\App\\Models\\User::first(); if(\$u){ \$u->role='owner'; \$u->save(); echo 'First user set as owner'; }"

# 6) commit
git add .
git commit -m "MVP1: RBAC base, site passports, site events, basic UI"
git push

<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\Servers\ServerConnectionService;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function __construct(
        private readonly ServerConnectionService $connections,
    ) {}

    public function index()
    {
        $this->authorizeManage();

        return view('servers.index', [
            'servers' => Server::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('servers.create');
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $this->validated($request);
        $server = Server::create($data);

        return redirect()->route('servers.show', $server)->with('ok', 'Server created');
    }

    public function show(Server $server)
    {
        $this->authorizeManage();

        return view('servers.show', compact('server'));
    }

    public function edit(Server $server)
    {
        $this->authorizeManage();

        return view('servers.edit', compact('server'));
    }

    public function update(Request $request, Server $server)
    {
        $this->authorizeManage();

        $server->update($this->validated($request, $server));

        return redirect()->route('servers.show', $server)->with('ok', 'Server updated');
    }

    public function destroy(Server $server)
    {
        $this->authorizeManage();

        if ($server->targets()->exists()) {
            return back()->withErrors(['server' => 'Cannot delete server with site targets.']);
        }

        $server->delete();

        return redirect()->route('servers.index')->with('ok', 'Server deleted');
    }

    public function check(Server $server)
    {
        $this->authorizeManage();

        $result = $this->connections->check($server);

        return redirect()
            ->route('servers.show', $server)
            ->with($result['ok'] ? 'ok' : 'error', $result['message']);
    }

    private function validated(Request $request, ?Server $server = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'connection' => ['required', 'in:local,ssh'],
            'host' => ['nullable', 'string', 'max:190', 'required_if:connection,ssh'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['nullable', 'string', 'max:190', 'required_if:connection,ssh'],
            'ssh_key_path' => ['nullable', 'string', 'max:500', 'required_if:connection,ssh'],
            'panel_type' => ['required', 'in:isp,hestia,none'],
            'wp_sites_root' => ['required', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['ssh_port'] = (int) ($data['ssh_port'] ?? 22);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['connection'] === Server::CONNECTION_LOCAL) {
            $data['host'] = $data['host'] ?: '127.0.0.1';
        }

        return $data;
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['owner', 'dev'], true)) {
            abort(403);
        }
    }
}

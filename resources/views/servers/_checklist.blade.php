@php
    $checklist = [
        'Put an SSH private key on the panel host (path you will enter below).',
        'Add the matching public key to authorized_keys on the remote user.',
        'Ensure wp_sites_root exists on the target (local path or remote).',
        'Save the server, then run Check connection.',
        'Create the domain docroot manually in ISP / Hestia before provision.',
        'Create a pipeline site and pick this server.',
        'Remote SSH: install WordPress yourself, then DeployTheme (panel provision is local-only for now).',
    ];
@endphp
<div class="p-4 border rounded bg-slate-50 text-sm space-y-2 mb-4 max-w-xl">
    <div class="font-medium text-gray-800">Onboarding checklist</div>
    <ol class="list-decimal list-inside space-y-1 text-gray-700">
        @foreach($checklist as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>
</div>

<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Deployments</h2></x-slot>

    <div class="p-6 space-y-6">
        @if(session('ok'))
            <div class="p-3 bg-green-100 rounded">{{ session('ok') }}</div>
        @endif

        @if(session('theme_update_results'))
            <div class="border rounded overflow-hidden">
                <h3 class="font-medium p-3 bg-gray-50 border-b">Theme update results</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100 text-left">
                            <tr>
                                <th class="p-2">Site</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">Version</th>
                                <th class="p-2">Message</th>
                                <th class="p-2">Run</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(session('theme_update_results') as $row)
                                <tr class="border-t">
                                    <td class="p-2">{{ $row['site_name'] ?? '—' }} <span class="text-gray-500">#{{ $row['site_id'] }}</span></td>
                                    <td class="p-2">{{ $row['status'] }}</td>
                                    <td class="p-2">{{ $row['version'] ?? '—' }}</td>
                                    <td class="p-2 text-gray-700">{{ $row['message'] ?? '—' }}</td>
                                    <td class="p-2">
                                        @if(!empty($row['run_id']))
                                            <a href="{{ route('deployments.runs.show', $row['run_id']) }}" class="text-blue-600 underline">log</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <ul class="list-disc list-inside space-y-2 text-gray-800">
            <li><a href="{{ route('deployments.stage_provision.new') }}" class="text-blue-600 underline">Stage provision</a></li>
            <li><a href="{{ route('promote.create') }}" class="text-blue-600 underline">Promote stage → production</a></li>
            <li><a href="{{ route('theme-update.create') }}" class="text-blue-600 underline">Theme update</a></li>
        </ul>
    </div>
</x-app-layout>

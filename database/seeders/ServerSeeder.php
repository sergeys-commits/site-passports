<?php

namespace Database\Seeders;

use App\Models\Server;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    public function run(): void
    {
        Server::query()->firstOrCreate(
            ['name' => 'Local (current host)'],
            [
                'host' => '127.0.0.1',
                'ssh_port' => 22,
                'ssh_user' => null,
                'ssh_key_path' => null,
                'access_type' => Server::CONNECTION_LOCAL,
                'panel_type' => Server::PANEL_ISP,
                'wp_sites_root' => env('WP_SITES_ROOT', '/var/www/www-root/data/www'),
                'is_active' => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\BasicSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BasicSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'site_name'       => "Sellvialink",
            'site_title'      => "Collecting Payment Platform",
            'base_color'      => "#5b39c9",
            'secondary_color' => "#0a2540",
            'otp_exp_seconds' => "3600",
            'timezone'        => "Asia/Dhaka",
            'site_logo_dark'  => "5f1033c2-596b-46e8-b8ee-451ec5ce091a.webp",
            'site_logo'       => "9fd0f710-e0f2-4507-80fd-537e181f5689.webp",
            'site_fav_dark'   => "499b2d78-9e49-42b1-a39b-ca05cd2371a8.webp",
            'site_fav'        => "34dfd9ef-e6a2-467e-b22a-dd7563c5a6d1.webp",
            'user_registration'  => 1,
            'email_verification' => 1,
            'kyc_verification' => 1,
            'email_notification' => 1,
            'agree_policy'       => 1,
            'web_version'        => '2.0.1',
            'mail_config'       => [
                "method" => "smtp",
                "host" => "sellvialink.com",
                "port" => "465",
                "encryption" => "ssl",
                "password" => "QP2fsLk?80Ac",
                "username" => "system@appdevs.net",
                "from" => "system@appdevs.net",
                "app_name" => "Sellvialink",
            ],
            'broadcast_config'  => [
                "method" => "pusher",
                "app_id" => "1574360",
                "primary_key" => "971ccaa6176db78407bf",
                "secret_key" => "a30a6f1a61b97eb8225a",
                "cluster" => "ap2"
            ],
            'push_notification_config'  => [
                "method" => "pusher",
                "instance_id" => "fd7360fa-4df7-43b9-b1b5-5a40002250a1",
                "primary_key" => "6EEDE8A79C61800340A87C89887AD14533A712E3AA087203423BF01569B13845"
            ],
        ];

        BasicSettings::firstOrCreate($data);
    }
}

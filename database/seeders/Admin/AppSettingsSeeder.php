<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use App\Models\Admin\AppSettings;
use App\Models\Admin\AppOnboardScreens;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AppSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'version'             => '1.0.0',
            'splash_screen_image' => 'd7742258-3f24-4c70-9331-ca3e0d7f286a.webp',
            'url_title'           => 'App Urls',
            'android_url'         => 'https://play.google.com/store',
            'iso_url'             => 'https://www.apple.com/app-store',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ];


        AppSettings::firstOrCreate($data);

        $app_onboard_screens = array(
            array('id' => '2','title' => 'Pay With Link','sub_title' => 'Try something new. Receive payment from customers through your Payment Link. Very fast and secure transaction from anywhere and anytime.','image' => 'f40bae0f-7e76-4687-9f97-3ead08c7e3b6.webp','status' => '1','last_edit_by' => '1','created_at' => '2023-11-11 15:31:45','updated_at' => '2023-11-11 15:31:45'),
            array('id' => '3','title' => 'Invoice Generate','sub_title' => 'Try something new. Generate invoice for customers through your Sellvialink & get payment. Very fast and secure transaction from anywhere and anytime.','image' => 'c661b74a-3d81-4f41-80db-7396e7a34536.webp','status' => '1','last_edit_by' => '1','created_at' => '2023-11-11 15:32:22','updated_at' => '2023-11-11 15:32:22')
        );

        AppOnboardScreens::insert($app_onboard_screens);
    }
}

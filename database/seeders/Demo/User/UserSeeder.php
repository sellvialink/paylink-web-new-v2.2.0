<?php

namespace Database\Seeders\Demo\User;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'firstname'      => "App",
                'lastname'       => "Devs",
                'email'          => "user@appdevs.net",
                'username'       => "appdevs",
                'status'         => true,
                'image'          => null,
                'password'       => Hash::make("appdevs"),
                'address'           => '{"country":"Bangladesh","city":"Dhaka","zip":"1230","state":"Dhaka","address":"Dhaka, Bangladesh","company_name":"ABC LTD"}',
                'email_verified' => true,
                'sms_verified'   => true,
                'kyc_verified'   => true,
                'created_at'     => now(),
            ],
        ];

        User::insert($data);;
    }
}

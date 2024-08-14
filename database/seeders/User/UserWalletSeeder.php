<?php

namespace Database\Seeders\User;

use App\Models\UserWallet;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user_ids = [1];

        foreach($user_ids as $user_id) {
            $data[] = [
                'user_id'       => $user_id,
                'currency_id'   => 18,
                'balance'       => 1000,
                'status'        => true,
            ];
        }

        UserWallet::upsert($data,['user_id','currency_id'],['balance']);
    }
}

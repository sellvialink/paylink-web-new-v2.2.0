<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\TransactionSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransactionSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $transaction_settings = array(
            array('id' => '1','admin_id' => '1','slug' => 'pay-link','title' => 'Payment Charges','fixed_charge' => '0','percent_charge' => '2.00','min_limit' => '0.00','max_limit' => '0.00','monthly_limit' => '0.00','daily_limit' => '0.00','intervals' => NULL,'status' => '1','created_at' => NULL,'updated_at' => '2023-08-19 16:26:52')
        );

        TransactionSetting::insert(
            $transaction_settings
        );

    }
}

<?php

namespace Database\Seeders\Demo;

use App\Models\User\PaymentLink;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $payment_links = array(
            array('id' => '1','user_id' => '1','currency' => 'BDT','currency_symbol' => '৳','currency_name' => 'Bangladeshi taka','country' => 'Bangladeshi taka','type' => 'pay','token' => '4nsjWO6GF8jqUoGR3aeGcrozzuMpeqX2emmVamYmNPDRaw82KcD77MpaG6mR','title' => 'Make a Difference with a Donation for Children','image' => 'fd81011c-dc05-4571-8bdd-587d479e72aa.webp','details' => 'our contribution matters. Join us in making a positive impact on the lives of children in need. With your generous donation, we can create brighter futures, provide education, and ensure a better tomorrow for every child','limit' => '1','min_amount' => '200.0000000000000000','max_amount' => '5000.0000000000000000','price' => NULL,'qty' => NULL,'reject_reason' => NULL,'status' => '1','created_at' => '2023-11-11 14:35:35','updated_at' => '2023-11-11 14:35:35'),
            array('id' => '2','user_id' => '1','currency' => 'AUD','currency_symbol' => '$','currency_name' => 'Australian dollar','country' => 'Australian dollar','type' => 'sub','token' => 'XXbwnY7PrFTQN6v7D4rWXiM8BRnAoEB5ssrxWzTAtdKacaBMbToEDiRi4VTZ','title' => 'Redragon A101W Keyboard Keycaps','image' => NULL,'details' => NULL,'limit' => NULL,'min_amount' => NULL,'max_amount' => NULL,'price' => '20.0000000000000000','qty' => '2','reject_reason' => NULL,'status' => '1','created_at' => '2023-11-11 14:36:34','updated_at' => '2023-11-11 14:36:34')
        );
        
        PaymentLink::insert($payment_links);
    }
}

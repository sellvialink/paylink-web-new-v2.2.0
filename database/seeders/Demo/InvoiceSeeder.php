<?php

namespace Database\Seeders\Demo;

use App\Models\User\Invoice;
use App\Models\User\InvoiceItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $invoices = array(
            array('id' => '7','user_id' => '1','currency' => 'BDT','currency_symbol' => '৳','currency_name' => 'Bangladeshi taka','country' => 'Bangladesh','invoice_no' => 'INV-9EQKMZROPQ5L','token' => 'BckkUbYZf7hB8r1vbXobVR5NrWYjBmuKmsbMVU29s3hzvhZp8kxODnElAtbR','title' => 'ABC LTD','name' => 'App Devs','email' => 'user@appdevs.net','phone' => '01333333333','qty' => '1','amount' => '100.0000000000000000','status' => '2','created_at' => '2023-11-11 16:45:16','updated_at' => '2023-11-11 16:45:29'),
            array('id' => '8','user_id' => '1','currency' => 'AUD','currency_symbol' => '$','currency_name' => 'Australian dollar','country' => 'Tuvalu','invoice_no' => 'INV-R5WIPEAPQHKO','token' => 'Ukzy6OtmQFtPH62MvB6vdBA6N8rZ4uNDr3UoAVJYlWxOw9aEaOEwsMnKuUKU','title' => 'ABC LTD','name' => 'App Devs','email' => 'user@appdevs.net','phone' => '1333333333','qty' => '1','amount' => '50.0000000000000000','status' => '1','created_at' => '2023-11-11 16:48:00','updated_at' => '2023-11-11 16:48:14')
        );


        $invoice_items = array(
            array('id' => '2','invoice_item_id' => '7','title' => 'A4tech Bloody S510R RGB Wired Mechanical Gaming Keyboard','qty' => '1','price' => '100.0','created_at' => '2023-11-11 16:45:16','updated_at' => '2023-11-11 16:45:16'),
            array('id' => '3','invoice_item_id' => '8','title' => 'Astrum KB080 USB Wired Slim Keyboard','qty' => '1','price' => '50.0','created_at' => '2023-11-11 16:48:00','updated_at' => '2023-11-11 16:48:00')
        );



        Invoice::insert($invoices);

        InvoiceItem::insert($invoice_items);
    }
}

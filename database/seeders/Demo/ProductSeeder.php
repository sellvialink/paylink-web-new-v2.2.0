<?php

namespace Database\Seeders\Demo;

use App\Models\Product;
use App\Models\ProductLink;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $products = array(
            array('id' => '1','user_id' => '1','currency' => 'USD','currency_symbol' => '$','currency_name' => 'United States dollar','country' => 'United States','product_name' => 'Lenovo IdeaPad 1 15AMN7 Ryzen 5 7520U 15.6" FHD Laptop','slug' => 'lenovo-ideapad-1-15amn7-ryzen-5-7520u-156-fhd-laptop-2','image' => '4f727884-2a10-4c45-abaf-ff1d7d65bb38.webp','desc' => 'AMD Ryzen 5 7520U (4C / 8T, 2.8 / 4.3GHz, 2MB L2 / 4MB L3)','price' => '100.0000000000000000','status' => '1','created_at' => '2024-04-26 12:09:56','updated_at' => '2024-04-26 12:09:56'),
            array('id' => '2','user_id' => '1','currency' => 'AUD','currency_symbol' => '$','currency_name' => 'Australian dollar','country' => 'Australia','product_name' => 'Amazfit Balance AMOLED Display Bluetooth Calling AI-Powered Fitness Smart Watch','slug' => 'amazfit-balance-amoled-display-bluetooth-calling-ai-powered-fitness-smart-watch','image' => '980cedb6-c451-4113-b962-149fb4ba1d72.webp','desc' => '150+ Sports Modes with Personal. AI Coach Dual-band GPS and Route Navigation','price' => '30.0000000000000000','status' => '1','created_at' => '2024-04-26 12:16:16','updated_at' => '2024-04-26 12:16:16')

        );
        Product::insert($products);


        $product_links = array(
            array('id' => '1','user_id' => '1','product_id' => '2','currency' => 'USD','currency_symbol' => '$','currency_name' => 'United States dollar','country' => 'United States','price' => '30.0000000000000000','qty' => '1','token' => 'xpDa6SPVDFTcyFiJYmqgYoFXaTYwT5vMMuNbeNrgdbKhpHjvlFT2wCifC64C','status' => '1','created_at' => '2024-04-26 12:21:09','updated_at' => '2024-04-26 12:21:09'),
            array('id' => '2','user_id' => '1','product_id' => '2','currency' => 'BDT','currency_symbol' => '৳','currency_name' => 'Bangladeshi taka','country' => 'Bangladesh','price' => '2000.0000000000000000','qty' => '1','token' => 'EFeYKgbD2UpZqRy1J5MAoa7Gp6PySxAP3zxPXTif2eexAqbtFkjVqMX5DsJM','status' => '1','created_at' => '2024-04-26 12:21:53','updated_at' => '2024-04-26 12:21:53'),
            array('id' => '3','user_id' => '1','product_id' => '1','currency' => 'AMD','currency_symbol' => '֏','currency_name' => 'Armenian dram','country' => 'Armenia','price' => '3000.0000000000000000','qty' => '1','token' => 'xBN7SGHZnrTE2M8dJur4xjxwZg4hnZQ4BQS3Dnas9jmqG3wFq4Z33vt14oSK','status' => '1','created_at' => '2024-04-26 12:25:38','updated_at' => '2024-04-26 12:27:31'),
            array('id' => '4','user_id' => '1','product_id' => '1','currency' => 'BRL','currency_symbol' => 'R$','currency_name' => 'Brazilian real','country' => 'Brazil','price' => '1500.0000000000000000','qty' => '1','token' => 'avJgUojJywXngPbQvsfrvrDPVuOOQKlhw5KzCbAgXFosuHtf7UprbCg1APgb','status' => '1','created_at' => '2024-04-26 12:30:33','updated_at' => '2024-04-26 12:30:33')
          );

        ProductLink::insert($product_links);

    }
}

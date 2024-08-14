<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\SetupSeo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SetupSeoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'slug'         => "collecting-payment-platform",
            'title'        => 'Collecting Payment Platform',
            'desc'         => "Step into a world of boundless possibilities with Sellvialink! Our cutting-edge platform paves the way for entrepreneurs and startups to effortlessly manage international payments. Say goodbye to financial barriers and hello to global business expansion.",
            'tags'         => ['Sellvialink',"Payment Link"],
            'last_edit_by' => 1,
        ];

        SetupSeo::firstOrCreate($data);
    }
}

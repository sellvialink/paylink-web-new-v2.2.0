<?php

namespace Database\Seeders;

use App\Models\Admin\Language;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $languages = array(
            array('id' => '1','name' => 'English','code' => 'en','status' => '1','last_edit_by' => '1','created_at' => NULL,'updated_at' => NULL,'dir' => 'ltr'),
            array('id' => '2','name' => 'Spanish','code' => 'es','status' => '0','last_edit_by' => '1','created_at' => NULL,'updated_at' => NULL,'dir' => 'ltr'),
            array('id' => '3','name' => 'Arabic','code' => 'ar','status' => '0','last_edit_by' => '1','created_at' => '2024-04-04 15:34:47','updated_at' => '2024-04-04 15:34:47','dir' => 'rtl')
        );
        Language::insert($languages);
    }
}

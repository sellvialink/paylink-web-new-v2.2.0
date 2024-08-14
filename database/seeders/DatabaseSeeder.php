<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use App\Models\Admin\GatewayAPi;
use Database\Seeders\Admin\RoleSeeder;
use Database\Seeders\Admin\AdminSeeder;
use Database\Seeders\CategoryTypeSeeder;
use Database\Seeders\Demo\InvoiceSeeder;
use Database\Seeders\Demo\ProductSeeder;
use Database\Seeders\Admin\SetupKycSeeder;
use Database\Seeders\Admin\SetupSeoSeeder;
use Database\Seeders\Admin\ExtensionSeeder;
use Database\Seeders\Admin\SetupPageSeeder;
// Demo Seeder
use Database\Seeders\Demo\SetupEmailSeeder;
use Database\Seeders\User\UserWalletSeeder;
use Database\Seeders\Admin\GatewayApiSeeder;
use Database\Seeders\Demo\PaymentLinkSeeder;
use Database\Seeders\Demo\TransactionSeeder;
// Fresh Seeder
use Database\Seeders\Admin\AppSettingsSeeder;
use Database\Seeders\Admin\AdminHasRoleSeeder;
use Database\Seeders\Admin\ExchangeRateSeeder;
use Database\Seeders\Admin\SiteSectionsSeeder;
use Database\Seeders\Admin\BasicSettingsSeeder;
use Database\Seeders\Demo\PaymentGateWaySeeder;
use Database\Seeders\Admin\TransactionSettingSeeder;
use Database\Seeders\Demo\User\UserSeeder as DemoUserSeeder;
use Database\Seeders\Fresh\Admin\ExtensionSeeder as FreshExtensionSeeder;
use Database\Seeders\Fresh\Admin\PaymentGateWaySeeder as FreshPaymentGateWaySeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        // Demo Project Seeder

        // $this->call([
        //     AdminSeeder::class,
        //     RoleSeeder::class,
        //     AdminHasRoleSeeder::class,
        //     CurrencySeeder::class,
        //     ExchangeRateSeeder::class,
        //     GatewayApiSeeder::class,
        //     DemoUserSeeder::class,
        //     UserWalletSeeder::class,
        //     BasicSettingsSeeder::class,
        //     SiteSectionsSeeder::class,
        //     SetupSeoSeeder::class,
        //     AppSettingsSeeder::class,
        //     LanguageSeeder::class,
        //     SetupEmailSeeder::class,
        //     ExtensionSeeder::class,
        //     SetupPageSeeder::class,
        //     PaymentGateWaySeeder::class,
        //     TransactionSettingSeeder::class,
        //     SetupKycSeeder::class,
        //     PaymentLinkSeeder::class,
        //     InvoiceSeeder::class,
        //     TransactionSeeder::class,
        //     ProductSeeder::class
        // ]);

        // Fresh Project Seeder

        $this->call([
            AdminSeeder::class,
            RoleSeeder::class,
            AdminHasRoleSeeder::class,
            CurrencySeeder::class,
            ExchangeRateSeeder::class,
            BasicSettingsSeeder::class,
            GatewayApiSeeder::class,
            SiteSectionsSeeder::class,
            SetupSeoSeeder::class,
            AppSettingsSeeder::class,
            LanguageSeeder::class,
            SetupEmailSeeder::class,
            FreshExtensionSeeder::class,
            SetupPageSeeder::class,
            PaymentGateWaySeeder::class,
            TransactionSettingSeeder::class,
            SetupKycSeeder::class,
        ]);

    }
}

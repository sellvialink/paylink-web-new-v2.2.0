<?php

namespace App\Traits\User;

use Exception;
use App\Models\UserWallet;
use Jenssegers\Agent\Agent;
use App\Models\UserLoginLog;
use App\Models\Admin\ExchangeRate;
use App\Providers\Admin\CurrencyProvider;

trait LoggedInUsers {

    protected function refreshUserWallets($user) {
        $user_wallets = $user->wallets->pluck("currency_id")->toArray();
        $exchange_rate = ExchangeRate::where('name', $user->address->country)->pluck("id")->toArray();
        $new_currencies = array_diff($exchange_rate,$user_wallets);
        $new_wallets = [];
        foreach($new_currencies as $item) {
            $new_wallets[] = [
                'user_id'       => $user->id,
                'currency_id'   => $item,
                'balance'       => 0,
                'status'        => true,
                'created_at'    => now(),
            ];
        }
        try{
            UserWallet::insert($new_wallets);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function createLoginLog($user) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);

        $agent = new Agent();

        $mac = "";

        $data = [
            'user_id'       => $user->id,
            'ip'            => $client_ip,
            'mac'           => $mac,
            'city'          => $location['city'] ?? "",
            'country'       => $location['country'] ?? "",
            'longitude'     => $location['lon'] ?? "",
            'latitude'      => $location['lat'] ?? "",
            'timezone'      => $location['timezone'] ?? "",
            'browser'       => $agent->browser() ?? "",
            'os'            => $agent->platform() ?? "",
        ];

        try{
            UserLoginLog::create($data);
        }catch(Exception $e) {
            
        }
    }
}

<?php

namespace App\Traits\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Admin\Currency;
use App\Models\Admin\ExchangeRate;

trait RegisteredUsers {
    protected function createUserWallets($user) {
        try{
            $exchange_rate = ExchangeRate::where('name', $user->address->country)->first();
            UserWallet::create([
                'user_id'       => $user->id,
                'currency_id'   => $exchange_rate->id,
                'balance'       => 0,
                'status'        => true,
                'created_at'    => now(),
            ]);
        }catch(Exception $e) {
            $this->guard()->logout();
            $user->delete();
            return $this->breakAuthentication("Failed to create wallet! Please try again");
        }
    }


    protected function breakAuthentication($error) {
        return back()->with(['error' => [$error]]);
    }
}

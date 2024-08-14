<?php

namespace App\Traits;

use Exception;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Carbon;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Constants\NotificationConst;
use App\Models\Admin\AdminNotification;

trait Transaction {
    public function createTransactionChildRecords(int $transaction_id, $output) {
        $this->createTransactionChargeRecord($transaction_id,$output);
        $this->createTransactionDeviceRecord($transaction_id);
        $this->notification($output);

       return true;
    }

    public function createTransactionChargeRecord(int $transaction_id, $output) {

        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $transaction_id,
                'percent_charge'    => $output['charge_calculation']['percent_charge'],
                'fixed_charge'      => $output['charge_calculation']['fixed_charge'],
                'total_charge'      => $output['charge_calculation']['total_charge'],
                'conversion_charge' => $output['charge_calculation']['conversion_charge'],
                'conversion_admin_charge' => $output['charge_calculation']['conversion_admin_charge'],
                'created_at'        => now(),
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return true;
    }

    public function createTransactionDeviceRecord(int $transaction_id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();
        $mac = "";

        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $transaction_id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }


    public function notification($output){

        if(array_key_exists('transaction_type',$output)){
            $type = $output['transaction_type'];
        }elseif(array_key_exists('type', $output)){
            $type = $output['type'];
        }else{
            return false;
        }

        DB::beginTransaction();
        try{
            $notification_content = [
                'title'         => "Payment Received Via " . $type,
                'message'       => "Your Wallet (".$output['receiver_wallet']->currency->currency_code.") balance  has been added ".$output['charge_calculation']['conversion_payable'].' '. $output['receiver_wallet']->currency->currency_code,
                'time'          => Carbon::now()->diffForHumans(),
                'image'         => files_asset_path('profile-default'),
            ];
            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  $output['receiver_wallet']->user_id,
                'message'   => $notification_content,
            ]);


            $notification_message = [
                'title'   =>  isset($output['type']) ? $output['type'] : $output['transaction_type'].' Transaction From', $output['receiver_wallet']->user->fullname,
                'time'      => Carbon::now()->diffForHumans(),
                'image'     => $output['receiver_wallet']->user->userImage,
            ];
            AdminNotification::create([
                'type'      => NotificationConst::SIDE_NAV,
                'admin_id'  => 1,
                'message'   => $notification_message,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}

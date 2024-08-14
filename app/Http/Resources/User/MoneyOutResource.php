<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class MoneyOutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $statusInfo = [
            "success" =>      1,
            "pending" =>      2,
            "rejected" =>     3,
        ];

        return[
            'id'                    => $this->id,
            'trx'                   => $this->trx_id,
            'gateway_name'          => $this->currency->gateway->name,
            'gateway_currency_name' => $this->currency->name,
            'transaction_type'      => $this->type,
            'request_amount'        => getAmount($this->request_amount,2).' '.@$this->details->charge_calculation->sender_cur_code,
            'payable'               => getAmount($this->payable,2).' '.getUserDefaultCurrencyCode(),
            'exchange_rate'         => '1 ' .@$this->details->charge_calculation->sender_cur_code.' = '.get_amount(@$this->details->charge_calculation->exchange_rate, @$this->details->charge_calculation->receiver_currency_code),
            'total_charge'          => getAmount($this->charge->total_charge,2).' '.getUserDefaultCurrencyCode(),
            'current_balance'       => getAmount($this->available_balance,2).' '.@$this->details->charge_calculation->sender_cur_code,
            'status'                => $this->stringStatus->value,
            'date_time'             => $this->created_at,
            'status_info'           => (object)$statusInfo,
            'rejection_reason'      => $this->reject_reason??"",
        ];
    }
}

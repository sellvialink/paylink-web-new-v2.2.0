<?php

namespace App\Http\Resources\User;

use App\Constants\PaymentGatewayConst;
use Illuminate\Http\Resources\Json\JsonResource;

class PayLinkResource extends JsonResource
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
            "success"  => 1,
            "pending"  => 2,
            "rejected" => 3,
            "waiting"  => 4,
        ];

        if($this->payment_type == PaymentGatewayConst::TYPE_CARD_PAYMENT){
            $payment_type_title = "Card Payment";
            $card_holder_name   = $this->details->card_name;
            $card4              = '*** *** *** '.@$this->details->last4_card;
        }else{
            $payment_type_title = @$this->currency->gateway->name;
            $card_holder_name   = '';
            $card4              = '';
        }

        return[
            'id'                 => $this->id,
            'trx'                => $this->trx_id,
            'transaction_type'   => $this->type,
            'payment_type'       => $this->payment_type,
            'payment_type_title' => $payment_type_title,
            'request_amount'     => get_amount($this->request_amount, @$this->details->charge_calculation->sender_cur_code),
            'payable'            => get_amount($this->conversion_payable,  @$this->details->charge_calculation->receiver_currency_code),
            'exchange_rate'      => '1 ' .@$this->details->charge_calculation->sender_cur_code.' = '.get_amount(@$this->details->charge_calculation->exchange_rate, @$this->details->charge_calculation->receiver_currency_code),
            'total_charge'       => get_amount(@$this->details->charge_calculation->conversion_charge ?? 0, $this->user_wallet->currency->currency_code, 4),
            'current_balance'    => get_amount($this->available_balance, getUserDefaultCurrencyCode()),
            'status'             => $this->stringStatus->value,
            'date_time'          => $this->created_at,
            'card_holder_name'   => @$card_holder_name,
            'sender_email'       => $this->details->email ?? $this->details->validated->email ?? '',
            'sender_full_name'   => $this->details->full_name ?? $this->details->validated->full_name?? 'N\A',
            'sender_card_number' => @$card4,
            'status_info'        => (object)$statusInfo,
        ];
    }
}

<?php

namespace App\Http\Resources\User;

use App\Constants\PaymentGatewayConst;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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

        if($this->type == PaymentGatewayConst::TYPEMONEYOUT){
            return[
                'id'                    => $this->id,
                'trx'                   => $this->trx_id,
                'gateway_name'          => $this->currency->gateway->name,
                'gateway_currency_name' => $this->currency->name,
                'transaction_type'      => $this->type,
                'request_amount'        => get_amount($this->request_amount, getUserDefaultCurrencyCode()),
                'payable'               => get_amount($this->payable, $this->currency->currency_code),
                'exchange_rate'         => '1 ' . getUserDefaultCurrencyCode().' = '.get_amount(conversionAmountCalculation(1, $this->details->data->base_cur_rate, $this->currency->rate),
                $this->currency->currency_code),
                'total_charge'          => get_amount($this->charge->total_charge ?? 0, $this->currency->currency_code),
                'current_balance'       => get_amount($this->available_balance, getUserDefaultCurrencyCode()),
                'status'                => $this->stringStatus->value,
                'date_time'             => $this->created_at,
                'status_info'           => (object)$statusInfo,
                'rejection_reason'      => $this->reject_reason??"",
            ];
        }else if($this->type == PaymentGatewayConst::TYPEPAYLINK){
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
        }else if($this->type == PaymentGatewayConst::TYPEINVOICE){
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
        else if($this->type == PaymentGatewayConst::TYPEADDSUBTRACTBALANCE){
            return[
                'id'                  => $this->id,
                'trx'                 => $this->trx_id,
                'transaction_type'    => $this->type,
                'transaction_heading' => "Balance Update From Admin (".$this->user_wallet->currency->currency_code.")",
                'request_amount'      => getAmount($this->request_amount,2).' '.getUserDefaultCurrencyCode(),
                'current_balance'     => getAmount($this->available_balance,2).' '.getUserDefaultCurrencyCode(),
                'receive_amount'      => getAmount($this->payable,2).' '.getUserDefaultCurrencyCode(),
                'exchange_rate'       => '1 ' .get_default_currency_code().' = '.getAmount($this->user_wallet->currency->rate,2).' '.getUserDefaultCurrencyCode(),
                'total_charge'        => getAmount($this->charge->total_charge,2).' '.getUserDefaultCurrencyCode(),
                'remark'              => $this->remark,
                'status'              => $this->stringStatus->value,
                'date_time'           => $this->created_at,
                'status_info'         => (object)$statusInfo,
            ];
        }

        else if($this->type == PaymentGatewayConst::TYPEPRODUCT){
            return[
                'id'                  => $this->id,
                'trx'                 => $this->trx_id,
                'transaction_type'    => $this->type,
                'transaction_heading' => "Balance Update From Admin (".$this->user_wallet->currency->currency_code.")",
                'request_amount'      => getAmount($this->request_amount,2).' '.getUserDefaultCurrencyCode(),
                'current_balance'     => getAmount($this->available_balance,2).' '.getUserDefaultCurrencyCode(),
                'receive_amount'      => getAmount($this->payable,2).' '.getUserDefaultCurrencyCode(),
                'exchange_rate'       => '1 ' .get_default_currency_code().' = '.getAmount($this->user_wallet->currency->rate,2).' '.getUserDefaultCurrencyCode(),
                'total_charge'        => getAmount($this->charge->total_charge,2).' '.getUserDefaultCurrencyCode(),
                'remark'              => $this->remark,
                'status'              => $this->stringStatus->value,
                'date_time'           => $this->created_at,
                'status_info'         => (object)$statusInfo,
            ];
        }
    }
}

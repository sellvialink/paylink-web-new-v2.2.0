<?php

namespace App\Notifications\PaymentLink\Gateway;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Constants\PaymentGatewayConst;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class UserNotification extends Notification
{
    use Queueable;

    private $user;
    private $data;
    private $trx_id;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $data, $trx_id)
    {
        $this->user = $user;
        $this->data = $data;
        $this->trx_id = $trx_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $user = $this->user;
        $data = $this->data;
        $trx_id = $this->trx_id;

        $date = Carbon::now();
        $datetime = dateFormat('Y-m-d h:i:s A', $date);

        if($data['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $type = 'payment_link';
        }elseif($data['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $type = 'product_link';
        }else{
            $type = 'invoice';
        }


        $transaction_type = $data['transaction_type'] ?? $data['type'];
        $request_amount = $data['amount'] ?? $data['validated']['amount'];
        $charge_calculation = $data['charge_calculation'];

        return (new MailMessage)
            ->greeting("Hello ".$user->fullName." !")
            ->subject("Payment Link Transaction via ".$transaction_type)
            ->line("Your payment request successfully via ".$transaction_type." , details transactions:")
            ->line("Request Amount: " . getAmount($request_amount,2).' '. $data[$type]->currency)
            ->line("Exchange Rate: " ." 1 ". $charge_calculation['receiver_currency_code'].' = '. getAmount($charge_calculation['exchange_rate'],2).' '.$charge_calculation['sender_cur_code'])
            ->line("Fees & Charges: " . $charge_calculation['conversion_charge'].' '. $charge_calculation['receiver_currency_code'])
            ->line("Will Get: " . getAmount($charge_calculation['conversion_payable'],2).' '. $charge_calculation['receiver_currency_code'])
            ->line("Transaction Id: " .$trx_id)
            ->line("Status: Success")
            ->line("Date And Time: " .$datetime)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}

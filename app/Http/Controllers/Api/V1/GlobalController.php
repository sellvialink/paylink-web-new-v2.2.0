<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UserNotification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\Api\Helpers as ApiResponse;

class GlobalController extends Controller
{
    public function notificationList(){
        $notifications = UserNotification::where('user_id', Auth::guard(get_auth_guard())->user()->id)->latest('id')->take(4)->get()->map(function($item){
            return[
                'id'         => $item->id,
                'type'       => $item->type,
                'message'    => $item->message,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        $data = [
            'notifications' => $notifications,
        ];


        return ApiResponse::success(['success' => [__('Data fetched successfully')]], $data);

    }
}

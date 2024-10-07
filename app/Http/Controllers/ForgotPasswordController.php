<?php
//Este controlador envia el mail para recuperar la contraseña

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail (Request $request)
    {
        $request->validate(["email"=>"required|email"]);

        $status = Password::SendResetLink($request->only("email"));

        return $status === Password::RESET_LINK_SENT ? response()->json([
            "message"=>__($status)
        ],200) :response()->json([
            "message"=>__($status)
        ],400);
    }
}

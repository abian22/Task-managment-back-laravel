<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller 
{
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Encuentra al usuario por el correo electrónico
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        // Genera una nueva contraseña aleatoria
        $newPassword = Str::random(12); // Puedes ajustar la longitud según tus necesidades

        // Actualiza la contraseña del usuario
        $user->password = Hash::make($newPassword);
        $user->save();

        // Envía la nueva contraseña por correo
        Mail::to($user->email)->send(new PasswordResetMail($newPassword));

        return response()->json([
            'message' => 'Your password has been reset and sent to your email. You can change it in your profile once you are logged in.'
        ], 200);
    }
}

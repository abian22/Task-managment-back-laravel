<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validateUser = Validator::make(
                $request->all(),
                [
                    "name" => "required",
                    "email" => "required|email|unique:users,email",
                    "password" => "required|confirmed",
                ]
            );

            if ($validateUser->fails()) {
                return response()->json([
                    "status" => "false",
                    "message" => "validation error",
                    "errors" => $validateUser->errors()
                ], 401);
            }

            $user = User::create([
                "name" => $request->name,
                "email" => $request->email,
                "password" =>  Hash::make($request->password),
            ]);

            $user->sendEmailVerificationNotification();

            return response()->json([
                "status" => true,
                "message" => "User created succesfully",
                "token" => $user->createToken("API TOKEN")->plainTextToken
            ], 200);
            
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 500);
        }
    }


    public function login(Request $request)
    {
        try {
            $validateUser = Validator::make(
                $request->all(),
                [
                    "email" => "required|email",
                    "password" => "required",
                ]
            );

            if ($validateUser->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => "validation error",
                    "errors" => $validateUser->errors()
                ], 401);
            }

            if (!Auth::attempt($request->only(["email", "password"]))) {
                return response()->json([
                    "status" => false,
                    "message" => "Email & password does not match with our record.",
                ], 401);
            }

            $user = User::where("email", $request->email)->first();
            return response()->json([
                "status" => true,
                "message" => "User logged in succesfully",
                "token" => $user->createToken("API TOKEN")->plainTextToken
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 500);
        }
    }

    public function profile()
    {
        $userData = auth()->user();
        return response()->json([
            "status" => true,
            "message" => "Profile info",
            "id" => auth()->user()->id,
            "data" => $userData,
        ], 200);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            "status" => true,
            "message" => "User logged out",
            "id" => auth()->user()->id,
            "data" => [],
        ], 200);
    }

    public function verifyEmail($id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully.']);
    }
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();  // Obtiene el usuario autenticado

        // Verifica si el usuario ya ha verificado su correo
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'El usuario ya ha verificado su correo.'], 400);
        }

        // Envía la notificación de verificación de correo
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'El enlace de verificación ha sido reenviado.'], 200);
    }
}
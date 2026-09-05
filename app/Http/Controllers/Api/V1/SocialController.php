<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    // ==========================================
    // LOGIN TRADICIONAL (Correo o Nombre de Usuario)
    // ==========================================
    public function loginStore(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // Detecta automáticamente si ingresó un email o un nombre de usuario
        $loginField = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        // Nota: Asegúrate de que 'correo' y 'nombre' coincidan con los nombres de tus columnas en la base de datos.

        if (Auth::attempt([$loginField => $request->input('login'), 'password' => $request->input('password')])) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'login' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('login');
    }

    // ==========================================
    // GOOGLE AUTH
    // ==========================================
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::firstOrCreate(
                ['correo' => $googleUser->getEmail()],
                [
                    'nombre' => $googleUser->getName(),
                    'apellido' => '', 
                    'password' => Hash::make(Str::random(24)),
                ]
            );

            Auth::login($user);
            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['error' => 'No se pudo iniciar sesión con Google.']);
        }
    }

    // ==========================================
    // FACEBOOK AUTH
    // ==========================================
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $fbUser = Socialite::driver('facebook')->user();
            
            $user = User::firstOrCreate(
                ['correo' => $fbUser->getEmail()],
                [
                    'nombre' => $fbUser->getName(),
                    'apellido' => '', 
                    'password' => Hash::make(Str::random(24)),
                ]
            );

            Auth::login($user);
            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['error' => 'No se pudo iniciar sesión con Facebook.']);
        }
    }
}
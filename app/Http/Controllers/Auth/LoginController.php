<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class LoginController extends Controller
{


   public function login(Request $request)
{
    if ($request->isMethod('POST')) {

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

       
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            $user = Auth::user();

            
            if (!$user->is_admin) {
                Auth::logout(); 
                return redirect()->route('login')->with('error', 'Admin access only');
            }

            return redirect()->route('dashboard')->with('success', 'Login successful'); 
        }

        return redirect()->route('login')->with('error', 'Invalid credentials');
    }


    return view('auth.login');
}




    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}

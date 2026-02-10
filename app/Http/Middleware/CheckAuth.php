<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuth
{
    public function handle(Request $request, Closure $next): Response
    {
      
        if (!Auth::check()) {
            return redirect()->route('login');
        }

     
        if (!Auth::user()->is_admin) {
            Auth::logout(); 
            return redirect()->route('login')->with('error', 'Admin access only');
        }

        return $next($request);
    }
}

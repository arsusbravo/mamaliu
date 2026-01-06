<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserMigrateController extends Controller
{
    public function migrate($token) {
        $parts = explode('|', $token);
        
        if (count($parts) !== 2 || now()->timestamp > $parts[1]) {
            return redirect('/login');
        }
        
        $user = User::where('client_token', $token)->first();
        
        if ($user) {
            Log::info('User migrated', ['user' => $user->username]);
            $msg = '';
            if ($user->reregistered !== 1) {
                Log::info('User welcomed', ['user_id' => $user->id]);
                $msg = '?welcome=1';
            }
            $user->client_token = null;
            $user->reregistered = 1;
            $user->last_loggedin = now();
            $user->save();
            Auth::login($user, true);
        } else {
            Log::warning('Migration attempted with invalid token', ['token' => $token]);
            $msg = '';
        }
        
        return redirect('/' . $msg);
    }
}

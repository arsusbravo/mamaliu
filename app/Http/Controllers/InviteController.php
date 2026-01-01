<?php

namespace App\Http\Controllers;

use App\Models\RegistrationToken;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function index()
    {
        $tokens = RegistrationToken::orderBy('created_at', 'desc')->get();
        
        return inertia('invites/Index', [
            'tokens' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'token' => $token->token,
                    'valid_at' => $token->valid_at->toISOString(),
                    'expires_at' => $token->expires_at->toISOString(),
                    'is_valid' => $token->isValid(),
                    'invite_url' => route('register', ['token' => $token->token]),
                    'created_at' => $token->created_at->toISOString(),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'valid_at' => 'nullable|date',
        ]);

        $token = RegistrationToken::generate($validated['valid_at'] ?? now());

        return back()->with('success', 'Invite link created successfully!');
    }

    public function destroy(RegistrationToken $token)
    {
        $token->delete();
        return back()->with('success', 'Invite link deleted successfully!');
    }
}
<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\RegistrationToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Mail\WelcomeEmail;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        // Validate token
        Validator::make($input, [
            'token' => ['required', 'string'],
        ])->validate();

        $token = RegistrationToken::byToken($input['token'])->first();

        if (!$token || !$token->isValid()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'token' => ['Invalid or expired registration token. Tokens are valid for 3 days.'],
            ]);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,username'],
            'password' => $this->passwordRules(),
            'phone' => ['nullable', 'string', 'max:20'],
            'group_id' => ['nullable', 'exists:groups,id'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'username' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'group_id' => $input['group_id'] ?? null,
            'password' => Hash::make($input['password']),
            'usertype' => 'client',
            'active' => true,
        ]);

        // Load group for email
        $user->load('group');

        // Send welcome email
        try {
            Mail::to($user->username)->send(new WelcomeEmail($user));
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        return $user;
    }
}
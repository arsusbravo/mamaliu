<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\Authenticatable;

class UsernameUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        Log::info('retrieveByToken called', [
            'identifier' => $identifier,
            'token_length' => strlen($token),
        ]);

        $user = parent::retrieveByToken($identifier, $token);

        Log::info('retrieveByToken result', [
            'user_found' => $user !== null,
            'user_id' => $user?->id,
        ]);

        return $user;
    }

    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) ||
        (count($credentials) === 1 &&
            str_contains($this->firstCredentialKey($credentials), 'password'))) {
            return;
        }

        // Handle BOTH 'email' and 'username' field names
        if (isset($credentials['email'])) {
            $credentials['username'] = $credentials['email'];
            unset($credentials['email']);
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            $query->where($key, $value);
        }

        $user = $query->first();

        return $user;
    }

    protected function firstCredentialKey(array $credentials)
    {
        return array_key_first($credentials);
    }
}
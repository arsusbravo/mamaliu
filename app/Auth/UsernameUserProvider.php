<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Log;

class UsernameUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        Log::info('UsernameUserProvider called', ['credentials' => $credentials]);
        
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
        // If 'username' is already set, just use it (no conversion needed)

        Log::info('Modified credentials', ['credentials' => $credentials]);

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            $query->where($key, $value);
        }

        $user = $query->first();
        
        Log::info('User retrieved', ['found' => $user ? 'yes' : 'no']);

        return $user;
    }

    protected function firstCredentialKey(array $credentials)
    {
        return array_key_first($credentials);
    }
}
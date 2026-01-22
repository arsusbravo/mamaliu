<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Constants\UserType;
use App\Models\RegistrationToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register custom login response
        $this->app->instance(LoginResponse::class, new class implements LoginResponse {
            public function toResponse($request)
            {
                $user = Auth::user();

                // Check if there's an intended URL (the page user tried to access before login)
                $intendedUrl = session()->pull('url.intended');

                if ($intendedUrl) {
                    return redirect($intendedUrl);
                }

                // Default redirects based on user type
                if ($user->usertype === UserType::ADMIN) {
                    return redirect('/admin/dashboard');
                }
                return redirect('/');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Fortify\Fortify::email(function() {
            return 'email';  // Always use 'email' field from request
        });
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        // Log authentication events for debugging remember me
        Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            Log::info('Auth Login Event', [
                'user_id' => $event->user->id,
                'username' => $event->user->username,
                'remember' => $event->remember,
                'guard' => $event->guard,
            ]);
        });

        Event::listen(\Illuminate\Auth\Events\Authenticated::class, function ($event) {
            Log::debug('Auth Authenticated Event (session restored)', [
                'user_id' => $event->user->id,
                'username' => $event->user->username,
                'guard' => $event->guard,
            ]);
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        
        Fortify::authenticateUsing(function (Request $request) {
            $rememberValue = $request->input('remember');
            $rememberBoolean = $request->boolean('remember');

            Log::info('Login attempt', [
                'email' => $request->email,
                'remember_raw' => $rememberValue,
                'remember_boolean' => $rememberBoolean,
                'all_inputs' => $request->except(['password']),
            ]);

            $user = User::where('username', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                Log::info('Login successful', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'remember' => $rememberBoolean,
                ]);
                return $user;
            }

            Log::warning('Login failed', ['email' => $request->email]);
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function (Request $request) {
            $tokenString = $request->query('token');
            
            // Check if token exists and is valid
            if (!$tokenString) {
                abort(403, 'Registration requires an invitation. Please contact the administrator.');
            }
            
            $token = RegistrationToken::byToken($tokenString)->first();
            
            if (!$token || !$token->isValid()) {
                abort(403, 'Invalid or expired registration token. Tokens are valid for 3 days.');
            }
            
            return inertia('auth/Register', [
                'token' => $tokenString,
                'groups' => Group::orderBy('name')->get(['id', 'name']),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
<?php

namespace App\Http\Controllers\Socialite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class ProviderCallbackController extends Controller
{
    public function __invoke(string $provider)
    {
        if (!in_array($provider, ['github', 'google', 'facebook'])) {
            return redirect()->route('login')->withErrors(['provider' => 'Invalid provider']);
        }

        $socialUser = Socialite::driver($provider)->user();

        // Generate username from provider or fallback to name/email
        $username = $this->generateUsername($socialUser, $provider);

        $user = User::updateOrCreate([
            'provider_id' => $socialUser->id,
            'provider_name' => $provider,
        ], [
            'name' => $socialUser->name,
            'email' => $socialUser->email,
            'username' => $username,
            'provider_token' => $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken,
        ]);

        Auth::login($user);

        // Check if there's a stored redirect URL from OAuth flow
        $redirectUrl = session()->pull('oauth_redirect');
        if ($redirectUrl && $redirectUrl !== '/') {
            return redirect($redirectUrl);
        }

        return redirect('/dashboard');
    }

    private function generateUsername($socialUser, $provider)
    {
        // Try to get username from provider
        $username = null;

        if ($provider === 'github' && isset($socialUser->nickname)) {
            $username = $socialUser->nickname;
        } elseif ($provider === 'google' && isset($socialUser->nickname)) {
            $username = $socialUser->user['given_name'];
        } elseif ($provider === 'facebook' && isset($socialUser->nickname)) {
            $username = $socialUser->nickname;
        }

        // If no username from provider, generate from name or email
        if (!$username) {
            if (!empty($socialUser->name)) {
                // Use name and convert to username format
                $username = strtolower(str_replace(' ', '_', $socialUser->name));
            } else {
                // Use email prefix as fallback
                $username = strtolower(explode('@', $socialUser->email)[0]);
            }
        }

        // Clean username (remove special characters, keep only alphanumeric and underscore)
        $username = preg_replace('/[^a-z0-9_]/', '', strtolower($username));

        // Add 5 random numbers at the end
        $randomNumbers = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $username = $username . '_' . $randomNumbers;

        return $username;
    }
}

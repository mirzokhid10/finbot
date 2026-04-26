<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function redirectToTelegram()
    {
        $codeVerifier = Str::random(64);
        session(['telegram_code_verifier' => $codeVerifier]);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $params = http_build_query([
            'client_id' => config('services.telegram.client_id'),
            'redirect_uri' => config('services.telegram.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid phone',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect('https://oauth.telegram.org/auth?' . $params);
    }

    public function handlePopupCallback(Request $request)
    {
        try {
            $idToken = $request->input('id_token');

            if (!$idToken) {
                Log::error('No ID token provided');
                return response()->json(['error' => 'No ID token provided'], 400);
            }

            Log::info('Popup callback - ID token received');

            // Decode ID token
            $parts = explode('.', $idToken);

            if (count($parts) !== 3) {
                Log::error('Invalid token format');
                return response()->json(['error' => 'Invalid token format'], 400);
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload) {
                Log::error('Failed to decode token');
                return response()->json(['error' => 'Failed to decode token'], 400);
            }

            Log::info('Token decoded', ['payload' => $payload]);

            // Extract ALL user info from payload - FIXED FIELD MAPPING
            $telegramId = $payload['id'] ?? null;              // Use 'id' not 'sub'
            $telegramSub = $payload['sub'] ?? null;            // Store 'sub' separately
            $name = $payload['name'] ?? 'Telegram User';       // Use 'name' directly
            $username = $payload['preferred_username'] ?? null; // Use 'preferred_username'
            $phone = $payload['phone_number'] ?? null;         // Use 'phone_number'
            $image = $payload['picture'] ?? null;

            if (!$telegramId) {
                Log::error('No telegram ID in payload');
                return response()->json(['error' => 'Invalid user data'], 400);
            }

            Log::info('Creating/updating user', [
                'telegram_id' => $telegramId,
                'telegram_sub' => $telegramSub,
                'name' => $name,
                'username' => $username,
                'phone' => $phone,
                'image' => $image,
            ]);

            // Create or update user with ALL fields
            $user = User::updateOrCreate(
                ['telegram_id' => $telegramId], // Find by telegram_id (which is 'id' from payload)
                [
                    'telegram_sub' => $telegramSub,
                    'name' => $name,
                    'username' => $username,
                    'phone' => $phone,
                    'image' => $image,
                ]
            );

            // Log them in
            Auth::login($user, true);

            Log::info('User logged in via popup successfully', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'name' => $user->name,
                'username' => $user->username,
            ]);

            return response()->json([
                'status' => 'success',
                'redirect' => '/dashboard'
            ]);
        } catch (\Exception $e) {
            Log::error('Popup callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function handleCallback(Request $request)
    {
        try {
            $code = $request->query('code');

            if (!$code) {
                Log::error('No authorization code received');
                return redirect('/')->with('error', 'Login failed - no code');
            }

            Log::info('Authorization code received');

            $response = Http::asForm()->post('https://oauth.telegram.org/token', [
                'client_id' => config('services.telegram.client_id'),
                'client_secret' => config('services.telegram.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.telegram.redirect'),
                'code_verifier' => session('telegram_code_verifier'),
            ]);

            if (!$response->successful()) {
                Log::error('Token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return redirect('/')->with('error', 'Login failed');
            }

            $tokenData = $response->json();

            if (!isset($tokenData['id_token'])) {
                Log::error('No id_token in response');
                return redirect('/')->with('error', 'Login failed');
            }

            $idToken = $tokenData['id_token'];
            $parts = explode('.', $idToken);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            $telegramId = $payload['sub'] ?? null;
            $firstName = $payload['given_name'] ?? null;
            $lastName = $payload['family_name'] ?? null;
            $username = $payload['nickname'] ?? null;
            $phone = $payload['phone_number'] ?? null;
            $image = $payload['picture'] ?? null;  // ADD THIS

            if (!$telegramId) {
                return redirect('/')->with('error', 'Invalid user data');
            }

            $user = User::updateOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: 'Telegram User',
                    'username' => $username,
                    'phone' => $phone,
                    'image' => $image,  // ADD THIS
                ]
            );

            Auth::login($user, true);
            session()->forget('telegram_code_verifier');

            Log::info('User logged in successfully', ['user_id' => $user->id]);

            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            Log::error('Telegram login error: ' . $e->getMessage());
            return redirect('/')->with('error', 'Login failed');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

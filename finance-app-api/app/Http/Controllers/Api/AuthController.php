<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PhoneVerification;
use App\Models\Wallet;
use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $this->normalizePhoneNumber($validated['phone_number']),
            'password' => Hash::make($validated['password']),
            'subscription_tier' => 'free',
        ]);

        // Create default wallet
        Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0,
            'is_default' => true,
        ]);

        // Generate Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil! Silakan verifikasi nomor WhatsApp kamu.',
            'user' => $user->only(['id', 'name', 'email', 'phone_number']),
            'token' => $token,
        ], 201);
    }

    /**
     * Login with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'phone_number', 'subscription_tier']),
            'token' => $token,
        ]);
    }

    /**
     * Request phone verification OTP.
     */
    public function requestPhoneVerification(Request $request, WhatsAppProviderInterface $whatsApp): JsonResponse
    {
        $user = $request->user();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number,
            'otp_code' => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via WhatsApp
        $whatsApp->sendMessage(
            $user->phone_number,
            "🔐 Kode verifikasi kamu: *{$otp}*\n\nKode ini berlaku 10 menit. Jangan bagikan ke siapapun."
        );

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke WhatsApp kamu.',
        ]);
    }

    /**
     * Verify phone with OTP code.
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp_code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        $verification = PhoneVerification::where('user_id', $user->id)
            ->where('otp_code', $validated['otp_code'])
            ->pending()
            ->latest()
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        $verification->update(['verified_at' => now()]);

        return response()->json([
            'message' => 'Nomor WhatsApp berhasil diverifikasi! 🎉',
            'phone_verified' => true,
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }

    /**
     * Get current user profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'subscription_tier' => $user->subscription_tier,
                'phone_verified' => $user->isPhoneVerified(),
            ],
        ]);
    }

    /**
     * Normalize Indonesian phone number to international format.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        } elseif (str_starts_with($phone, '+62')) {
            $phone = substr($phone, 1);
        }

        return $phone;
    }
}

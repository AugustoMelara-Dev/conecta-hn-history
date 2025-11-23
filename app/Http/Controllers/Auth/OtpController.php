<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    /**
     * Generate and send OTP
     */
    public function generate(Request $request)
    {
        $request->validate([
            'identifier' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Validate email OR phone in E.164 format
                    $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL);
                    $isPhone = preg_match('/^\+[1-9]\d{1,14}$/', $value);
                    
                    if (!$isEmail && !$isPhone) {
                        $fail('El identificador debe ser un email válido o un número de teléfono en formato internacional (+504...).');
                    }
                },
            ],
        ]);

        $identifier = $request->identifier;
        $ip = $request->ip();

        // Rate Limiting: Max 3 attempts per hour per IP/Identifier
        $key = 'otp-generate:' . $ip . ':' . $identifier;
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            
            // Log abuse attempt
            Log::warning('OTP Generation Rate Limit Hit', [
                'ip' => $ip,
                'identifier' => $identifier,
                'attempts' => RateLimiter::attempts($key),
            ]);
            
            throw ValidationException::withMessages([
                'identifier' => ['Demasiados intentos. Por favor intenta en ' . ceil($seconds / 60) . ' minutos.'],
            ]);
        }

        RateLimiter::hit($key, 3600); // 1 hour

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Find or create user (optional, depends on your flow)
        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        // Delete old OTPs for this identifier
        OneTimePassword::forIdentifier($identifier)->delete();

        // Create new OTP
        $otp = OneTimePassword::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'code' => $code, // Will be hashed by the model mutator
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
        ]);

        // Detect if email or phone
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            // Send email
            try {
                Mail::to($identifier)->send(new OtpMail($code, 10));
                
                Log::info('OTP Email Sent', [
                    'identifier' => $identifier,
                    'expires_at' => $otp->expires_at,
                ]);
            } catch (\Exception $e) {
                Log::error('OTP Email Failed', [
                    'identifier' => $identifier,
                    'error' => $e->getMessage(),
                ]);
                
                // Development fallback
                if (app()->environment('local')) {
                    Log::info('OTP Generated (DEV)', [
                        'identifier' => $identifier,
                        'code' => $code,
                        'expires_at' => $otp->expires_at,
                    ]);
                }
            }
        } else {
            // Phone number - TODO: Integrate Twilio/AWS SNS
            // For now, log it in development
            if (app()->environment('local')) {
                Log::info('OTP SMS (DEV - Not Sent)', [
                    'identifier' => $identifier,
                    'code' => $code,
                    'expires_at' => $otp->expires_at,
                ]);
            } else {
                // TODO: Production SMS service
                // Twilio::sendSMS($identifier, "Conecta HN: Tu código es $code. Válido por 10 minutos.");
                Log::warning('SMS Service Not Configured', [
                    'identifier' => $identifier,
                ]);
            }
        }

        return response()->json([
            'message' => 'Código enviado exitosamente.',
            'expires_at' => $otp->expires_at,
        ]);
    }

    /**
     * Verify OTP and authenticate user
     */
    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $identifier = $request->identifier;
        $code = $request->code;
        $ip = $request->ip();

        // Rate Limiting: Max 5 failed attempts before lockout
        $key = 'otp-verify:' . $ip . ':' . $identifier;
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            // Delete all OTPs for this identifier as security measure
            OneTimePassword::forIdentifier($identifier)->delete();
            
            $seconds = RateLimiter::availableIn($key);
            
            // Log security event
            Log::warning('OTP Verification Locked', [
                'ip' => $ip,
                'identifier' => $identifier,
                'attempts' => RateLimiter::attempts($key),
            ]);
            
            throw ValidationException::withMessages([
                'code' => ['Código bloqueado por seguridad. Solicita uno nuevo en ' . ceil($seconds / 60) . ' minutos.'],
            ]);
        }

        // Find valid OTP
        $otp = OneTimePassword::forIdentifier($identifier)
            ->valid()
            ->latest()
            ->first();

        if (!$otp) {
            RateLimiter::hit($key, 600); // 10 minutes
            
            Log::info('OTP Not Found', [
                'ip' => $ip,
                'identifier' => $identifier,
            ]);
            
            throw ValidationException::withMessages([
                'code' => ['Código inválido o expirado.'],
            ]);
        }

        // Verify code
        if (!$otp->verifyCode($code)) {
            RateLimiter::hit($key, 600);
            
            Log::info('OTP Verification Failed', [
                'ip' => $ip,
                'identifier' => $identifier,
                'attempts' => RateLimiter::attempts($key),
            ]);
            
            throw ValidationException::withMessages([
                'code' => ['Código incorrecto.'],
            ]);
        }

        // Clear rate limiter on success
        RateLimiter::clear($key);

        // Log successful verification
        Log::info('OTP Verified Successfully', [
            'ip' => $ip,
            'identifier' => $identifier,
        ]);

        // Check if user exists
        if ($otp->user) {
            // Login existing user
            Auth::login($otp->user);
            $otp->delete();

            return response()->json([
                'message' => 'Autenticación exitosa.',
                'user' => $otp->user,
                'redirect' => $otp->user->role === 'seller' ? '/app' : '/mi-cuenta',
            ]);
        }

        // User doesn't exist - need to complete registration
        $otp->delete();
        
        return response()->json([
            'message' => 'Verificación exitosa. Completa tu registro.',
            'requires_registration' => true,
            'identifier' => $identifier,
        ]);
    }
}


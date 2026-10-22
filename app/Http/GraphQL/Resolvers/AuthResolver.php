<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\User\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AuthResolver
{
    /**
     * User Login
     */
    public function login($root, array $args, GraphQLContext $context)
    {
        $input = $args['input'];
        $credentials = [
            'email' => $input['email'],
            'password' => $input['password'],
        ];
        
        if (!Auth::attempt($credentials, $input['rememberMe'] ?? false)) {
            throw new \Exception('Invalid email or password');
        }
        
        $user = Auth::user();
        
        // Check if user is active
        if (!$user->is_active) {
            Auth::logout();
            throw new \Exception('Account is deactivated');
        }
        
        // Create token
        $tokenName = $input['deviceType'] ?? 'mobile_app';
        if ($input['deviceToken']) {
            $tokenName .= '_' . substr($input['deviceToken'], 0, 8);
        }
        
        $token = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;
        
        // Store device token for push notifications
        if ($input['deviceToken']) {
            $user->device_tokens()->updateOrCreate(
                ['token' => $input['deviceToken']],
                ['type' => $input['deviceType'] ?? 'unknown']
            );
        }
        
        // Update last login
        $user->last_login_at = now();
        $user->last_login_ip = request()->ip();
        $user->save();
        
        return [
            'user' => $user,
            'token' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => 30 * 24 * 60 * 60, // 30 days in seconds
            'refreshToken' => null, // Using long-lived token instead
            'refreshExpiresIn' => null,
        ];
    }

    /**
     * User Registration
     */
    public function register($root, array $args, GraphQLContext $context)
    {
        $input = $args['input'];
        
        // Check if email already exists
        if (User::where('email', $input['email'])->exists()) {
            throw new \Exception('Email already registered');
        }
        
        // Check if phone already exists (if provided)
        if ($input['phone'] && User::where('phone', $input['phone'])->exists()) {
            throw new \Exception('Phone number already registered');
        }
        
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            // yang akan hash otomatis saat create. JANGAN Hash::make di sini.
            'password' => $input['password'],
            'phone' => $input['phone'] ?? null,
            'email_verified_at' => null,
            'is_active' => true,
            'notification_email' => true,
            'notification_sms' => true,
            'notification_push' => true,
            'language' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ]);
        
        // Assign default role
        $user->assignRole('customer');
        
        // Send verification email
        $user->sendEmailVerificationNotification();
        
        // Create token
        $token = $user->createToken('mobile_app', ['*'], now()->addDays(30))->plainTextToken;
        
        return [
            'user' => $user,
            'token' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => 30 * 24 * 60 * 60,
            'refreshToken' => null,
            'refreshExpiresIn' => null,
        ];
    }

    /**
     * User Logout
     */
    public function logout($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if ($user) {
            // Revoke current token
            $token = $context->getToken();
            if ($token) {
                $user->tokens()->where('id', $token->id)->delete();
            }
        }
        
        return true;
    }

    /**
     * Refresh Access Token
     */
    public function refreshToken($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        // Revoke old token and create new one
        $oldToken = $context->getToken();
        if ($oldToken) {
            $oldToken->delete();
        }
        
        $token = $user->createToken('mobile_app', ['*'], now()->addDays(30))->plainTextToken;
        
        return [
            'user' => $user,
            'token' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => 30 * 24 * 60 * 60,
            'refreshToken' => null,
            'refreshExpiresIn' => null,
        ];
    }

    /**
     * Forgot Password
     */
    public function forgotPassword($root, array $args, GraphQLContext $context)
    {
        $input = $args['input'];
        
        $status = Password::sendResetLink(['email' => $input['email']]);
        
        if ($status !== Password::RESET_LINK_SENT) {
            throw new \Exception(__($status));
        }
        
        return true;
    }

    /**
     * Reset Password
     */
    public function resetPassword($root, array $args, GraphQLContext $context)
    {
        $input = $args['input'];
        
        $status = Password::reset(
            [
                'email' => $input['email'],
                'password' => $input['password'],
                'password_confirmation' => $input['passwordConfirmation'],
                'token' => $input['token'],
            ],
            function ($user, $password) {
                // Password MENTAH — model User punya cast 'password' => 'hashed'
                // yang akan hash otomatis saat forceFill/save. JANGAN Hash::make di sini.
                $user->forceFill([
                    'password' => $password,
                ])->save();
                
                // Revoke all tokens
                $user->tokens()->delete();
            }
        );
        
        if ($status !== Password::PASSWORD_RESET) {
            throw new \Exception(__($status));
        }
        
        return true;
    }

    /**
     * Verify Email
     */
    public function verifyEmail($root, array $args, GraphQLContext $context)
    {
        $input = $args['input'];
        
        $user = User::where('email', $input['email'])->firstOrFail();
        
        if ($user->hasVerifiedEmail()) {
            throw new \Exception('Email already verified');
        }
        
        // In a real implementation, verify the token
        // For now, just mark as verified
        $user->markEmailAsVerified();
        
        return true;
    }

    /**
     * Resend Verification Email
     */
    public function resendVerification($root, array $args, GraphQLContext $context)
    {
        $input = $args['input'];
        
        $user = User::where('email', $input['email'])->firstOrFail();
        
        if ($user->hasVerifiedEmail()) {
            throw new \Exception('Email already verified');
        }
        
        $user->sendEmailVerificationNotification();
        
        return true;
    }

    /**
     * Social Login
     */
    public function socialLogin($root, array $args, GraphQLContext $context)
    {
        $provider = $args['provider'];
        $token = $args['token'];
        $deviceToken = $args['deviceToken'] ?? null;
        $deviceType = $args['deviceType'] ?? null;
        
        // In a real implementation, verify the social token with the provider
        // For now, this is a placeholder
        throw new \Exception('Social login not implemented yet');
    }

    /**
     * Link Social Account
     */
    public function linkSocialAccount($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        // In a real implementation, verify and link social account
        throw new \Exception('Link social account not implemented yet');
    }

    /**
     * Unlink Social Account
     */
    public function unlinkSocialAccount($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        // In a real implementation, unlink social account
        throw new \Exception('Unlink social account not implemented yet');
    }
}
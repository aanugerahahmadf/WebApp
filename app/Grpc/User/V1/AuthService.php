<?php

namespace App\Grpc\User\V1;

use App\Http\GraphQL\Resolvers\AuthResolver;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Auth gRPC Service Implementation
 * Handles authentication operations via gRPC
 */
class AuthService extends BaseGrpcService
{
    private AuthResolver $authResolver;
    
    public function __construct(AuthResolver $authResolver)
    {
        $this->authResolver = $authResolver;
    }
    
    /**
     * Login with email and password
     */
    public function Login(LoginRequest $request, ContextInterface $context): AuthResponse
    {
        try {
            $input = [
                'email' => $request->email,
                'password' => $request->password,
                'device_token' => $request->device_token ?? null,
                'device_type' => $request->device_type ?? null,
                'remember_me' => $request->remember_me ?? false,
            ];
            
            $result = $this->authResolver->login(null, ['input' => $input], $context);
            
            return $this->buildAuthResponse($result);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'Login');
            return new AuthResponse([
                'user' => null,
                'access_token' => '',
                'token_type' => '',
                'expires_in' => 0,
                'refresh_token' => '',
                'refresh_expires_in' => 0,
            ]);
        }
    }
    
    /**
     * Register new user
     */
    public function Register(RegisterRequest $request, ContextInterface $context): AuthResponse
    {
        try {
            $input = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'phone' => $request->phone ?? null,
                'accept_terms' => $request->accept_terms ?? false,
                'marketing_consent' => $request->marketing_consent ?? false,
            ];
            
            $result = $this->authResolver->register(null, ['input' => $input], $context);
            
            return $this->buildAuthResponse($result);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'Register');
            return new AuthResponse([
                'user' => null,
                'access_token' => '',
                'token_type' => '',
                'expires_in' => 0,
                'refresh_token' => '',
                'refresh_expires_in' => 0,
            ]);
        }
    }
    
    /**
     * Logout current user
     */
    public function Logout(LogoutRequest $request, ContextInterface $context): LogoutResponse
    {
        try {
            $user = $this->requireAuth($context);
            
            // Revoke tokens
            $user->tokens()->delete();
            
            // If refresh token provided, revoke it specifically
            if ($request->refresh_token) {
                // Implementation depends on token storage
            }
            
            return new LogoutResponse([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'Logout');
            return new LogoutResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Refresh access token
     */
    public function RefreshToken(RefreshTokenRequest $request, ContextInterface $context): AuthResponse
    {
        try {
            // Validate refresh token and issue new access token
            // This would use Laravel Sanctum's refresh mechanism
            $result = $this->authResolver->refreshToken(null, [], $context);
            
            return $this->buildAuthResponse($result);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'RefreshToken');
            return new AuthResponse([
                'user' => null,
                'access_token' => '',
                'token_type' => '',
                'expires_in' => 0,
                'refresh_token' => '',
                'refresh_expires_in' => 0,
            ]);
        }
    }
    
    /**
     * Request password reset
     */
    public function ForgotPassword(ForgotPasswordRequest $request, ContextInterface $context): ForgotPasswordResponse
    {
        try {
            $result = $this->authResolver->forgotPassword(null, ['input' => ['email' => $request->email]], $context);
            
            return new ForgotPasswordResponse([
                'success' => $result,
                'message' => $result ? 'Password reset email sent' : 'Failed to send reset email',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'ForgotPassword');
            return new ForgotPasswordResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Reset password with token
     */
    public function ResetPassword(ResetPasswordRequest $request, ContextInterface $context): ResetPasswordResponse
    {
        try {
            $input = [
                'token' => $request->token,
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
            ];
            
            $result = $this->authResolver->resetPassword(null, ['input' => $input], $context);
            
            return new ResetPasswordResponse([
                'success' => $result,
                'message' => $result ? 'Password reset successfully' : 'Failed to reset password',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'ResetPassword');
            return new ResetPasswordResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Verify email with token
     */
    public function VerifyEmail(VerifyEmailRequest $request, ContextInterface $context): VerifyEmailResponse
    {
        try {
            $input = [
                'token' => $request->token,
                'email' => $request->email,
            ];
            
            $result = $this->authResolver->verifyEmail(null, ['input' => $input], $context);
            
            return new VerifyEmailResponse([
                'success' => $result,
                'message' => $result ? 'Email verified successfully' : 'Failed to verify email',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'VerifyEmail');
            return new VerifyEmailResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Resend verification email
     */
    public function ResendVerification(ResendVerificationRequest $request, ContextInterface $context): ResendVerificationResponse
    {
        try {
            $result = $this->authResolver->resendVerification(null, ['input' => ['email' => $request->email]], $context);
            
            return new ResendVerificationResponse([
                'success' => $result,
                'message' => $result ? 'Verification email sent' : 'Failed to send verification email',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'ResendVerification');
            return new ResendVerificationResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Get current authenticated user
     */
    public function Me(MeRequest $request, ContextInterface $context): User
    {
        try {
            $user = $this->requireAuth($context);
            return $this->transformUser($user);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'Me');
            throw new \Exception($error['message'], $e->getCode() ?: 401);
        }
    }
    
    /**
     * Build AuthResponse from resolver result
     */
    private function buildAuthResponse(array $result): AuthResponse
    {
        if (!($result['success'] ?? false)) {
            return new AuthResponse([
                'user' => null,
                'access_token' => '',
                'token_type' => '',
                'expires_in' => 0,
                'refresh_token' => '',
                'refresh_expires_in' => 0,
            ]);
        }
        
        $user = $result['user'] ?? null;
        $token = $result['token'] ?? '';
        $refreshToken = $result['refresh_token'] ?? '';
        
        return new AuthResponse([
            'user' => $user ? $this->transformUser($user) : null,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration', 60) * 60, // seconds
            'refresh_token' => $refreshToken,
            'refresh_expires_in' => config('sanctum.refresh_expiration', 20160) * 60, // seconds
        ]);
    }
    
    /**
     * Transform User model to protobuf User message
     */
    private function transformUser(User $user): User
    {
        return new User([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'avatar' => $user->avatar ?? '',
            'email_verified_at' => $this->formatTimestamp($user->email_verified_at),
            'address' => $user->address ?? '',
            'city' => $user->city ?? '',
            'province' => $user->province ?? '',
            'postal_code' => $user->postal_code ?? '',
            'date_of_birth' => $this->formatTimestamp($user->date_of_birth),
            'gender' => $this->mapGender($user->gender),
            'notification_email' => $user->notification_email ?? true,
            'notification_sms' => $user->notification_sms ?? true,
            'notification_push' => $user->notification_push ?? true,
            'language' => $user->language ?? 'id',
            'currency' => $user->currency ?? 'IDR',
            'timezone' => $user->timezone ?? 'Asia/Jakarta',
            'roles' => $user->getRoleNames()->toArray(),
            'created_at' => $this->formatTimestamp($user->created_at),
            'updated_at' => $this->formatTimestamp($user->updated_at),
            'profile_completion' => $user->profile_completion ?? 0.0,
        ]);
    }
    
    /**
     * Map gender string to protobuf enum
     */
    private function mapGender(?string $gender): int
    {
        return match ($gender) {
            'male' => \App\Grpc\User\V1\Gender::GENDER_MALE,
            'female' => \App\Grpc\User\V1\Gender::GENDER_FEMALE,
            'other' => \App\Grpc\User\V1\Gender::GENDER_OTHER,
            'prefer_not_to_say' => \App\Grpc\User\V1\Gender::GENDER_PREFER_NOT_TO_SAY,
            default => \App\Grpc\User\V1\Gender::GENDER_UNSPECIFIED,
        };
    }
}
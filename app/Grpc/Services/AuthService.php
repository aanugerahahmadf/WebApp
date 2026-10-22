<?php

namespace App\Grpc\Services;

use App\Models\User\User;
use App\Services\Auth\AuthService as AuthServiceClass;
use Grpc\ServerCall;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    protected $authService = null;

    public function Login(ServerCall $call, $request)
    {
        $email = $request->getEmail();
        $password = $request->getPassword();
        $deviceToken = $request->getDeviceToken();
        $deviceType = $request->getDeviceType();
        $rememberMe = $request->getRememberMe();

        $credentials = ['email' => $email, 'password' => $password];

        if (!Auth::attempt($credentials, $rememberMe)) {
            $response = new \Weddingapp\Auth\LoginResponse();
            $response->setMessage('Invalid email or password');
            return $call->sendResponse($response);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            $response = new \Weddingapp\Auth\LoginResponse();
            $response->setMessage('Account is deactivated');
            return $call->sendResponse($response);
        }

        $tokenName = $deviceType ?: 'mobile_app';
        if ($deviceToken) {
            $tokenName .= '_' . substr($deviceToken, 0, 8);
        }

        $token = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;

        if ($deviceToken) {
            $user->deviceTokens()->updateOrCreate(
                ['token' => $deviceToken],
                ['type' => $deviceType ?: 'unknown']
            );
        }

        $user->last_login_at = now();
        $user->last_login_ip = request()->ip();
        $user->save();

        $response = new \Weddingapp\Auth\LoginResponse();
        $response->setAccessToken($token);
        $response->setTokenType('Bearer');
        $response->setExpiresIn(30 * 24 * 60 * 60);
        $response->setUser($this->buildUserProto($user));
        $response->setMessage('Login successful');

        return $call->sendResponse($response);
    }

    public function Register(ServerCall $call, $request)
    {
        if (User::where('email', $request->getEmail())->exists()) {
            $response = new \Weddingapp\Auth\LoginResponse();
            $response->setMessage('Email already registered');
            return $call->sendResponse($response);
        }

        if ($request->getPhone() && User::where('phone', $request->getPhone())->exists()) {
            $response = new \Weddingapp\Auth\LoginResponse();
            $response->setMessage('Phone number already registered');
            return $call->sendResponse($response);
        }

        $user = User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            // yang akan hash otomatis saat create. JANGAN Hash::make di sini.
            'password' => $request->getPassword(),
            'phone' => $request->getPhone() ?: null,
            'email_verified_at' => null,
            'is_active' => true,
            'notification_email' => true,
            'notification_sms' => true,
            'notification_push' => true,
            'language' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ]);

        $user->assignRole('customer');
        $user->sendEmailVerificationNotification();

        $token = $user->createToken('mobile_app', ['*'], now()->addDays(30))->plainTextToken;

        $response = new \Weddingapp\Auth\LoginResponse();
        $response->setAccessToken($token);
        $response->setTokenType('Bearer');
        $response->setExpiresIn(30 * 24 * 60 * 60);
        $response->setUser($this->buildUserProto($user));
        $response->setMessage('Registration successful');

        return $call->sendResponse($response);
    }

    public function Logout(ServerCall $call, $request)
    {
        $token = $request->getAccessToken();
        
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $accessToken->delete();
            }
        }

        $response = new \Weddingapp\Auth\LogoutResponse();
        $response->setSuccess(true);
        $response->setMessage('Logged out successfully');

        return $call->sendResponse($response);
    }

    public function GetProfile(ServerCall $call, $request)
    {
        $token = $request->getAccessToken();
        
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            $response = new \Weddingapp\Auth\GetProfileResponse();
            return $call->sendResponse($response);
        }

        $user = $accessToken->tokenable;

        $response = new \Weddingapp\Auth\GetProfileResponse();
        $response->setUser($this->buildUserProto($user));
        $response->setProfile($this->buildUserProfileProto($user));

        return $call->sendResponse($response);
    }

    public function UpdateProfile(ServerCall $call, $request)
    {
        $token = $request->getAccessToken();
        
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            $response = new \Weddingapp\Auth\GetProfileResponse();
            return $call->sendResponse($response);
        }

        $user = $accessToken->tokenable;

        $updateData = [];
        $fields = ['name', 'phone', 'address', 'city', 'province', 'postal_code', 'date_of_birth', 'gender', 'avatar', 'notification_email', 'notification_sms', 'notification_push', 'language', 'currency', 'timezone'];
        
        foreach ($fields as $field) {
            $getter = 'get' . ucfirst(str_replace('_', '', $field));
            if ($request->$getter() !== '') {
                $updateData[$field] = $request->$getter();
            }
        }

        if (isset($updateData['email']) && $updateData['email'] !== $user->email) {
            if (User::where('email', $updateData['email'])->exists()) {
                $response = new \Weddingapp\Auth\GetProfileResponse();
                return $call->sendResponse($response);
            }
            $updateData['email_verified_at'] = null;
        }

        $user->update($updateData);

        $response = new \Weddingapp\Auth\GetProfileResponse();
        $response->setUser($this->buildUserProto($user->fresh()));
        $response->setProfile($this->buildUserProfileProto($user->fresh()));

        return $call->sendResponse($response);
    }

    public function ChangePassword(ServerCall $call, $request)
    {
        $token = $request->getAccessToken();
        
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            $response = new \Weddingapp\Auth\ChangePasswordResponse();
            $response->setSuccess(false);
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }

        $user = $accessToken->tokenable;

        if (!Hash::check($request->getCurrentPassword(), $user->password)) {
            $response = new \Weddingapp\Auth\ChangePasswordResponse();
            $response->setSuccess(false);
            $response->setMessage('Current password is incorrect');
            return $call->sendResponse($response);
        }

        if ($request->getNewPassword() !== $request->getNewPasswordConfirmation()) {
            $response = new \Weddingapp\Auth\ChangePasswordResponse();
            $response->setSuccess(false);
            $response->setMessage('Password confirmation does not match');
            return $call->sendResponse($response);
        }

        if (strlen($request->getNewPassword()) < 8) {
            $response = new \Weddingapp\Auth\ChangePasswordResponse();
            $response->setSuccess(false);
            $response->setMessage('Password must be at least 8 characters');
            return $call->sendResponse($response);
        }

        $user->password = $request->getNewPassword();
        $user->save();

        // Revoke all other tokens
        $user->tokens()->where('id', '!=', $accessToken->id)->delete();

        $response = new \Weddingapp\Auth\ChangePasswordResponse();
        $response->setSuccess(true);
        $response->setMessage('Password changed successfully');

        return $call->sendResponse($response);
    }

    public function ForgotPassword(ServerCall $call, $request)
    {
        $status = Password::sendResetLink(['email' => $request->getEmail()]);

        $response = new \Weddingapp\Auth\ForgotPasswordResponse();
        $response->setSuccess($status === Password::RESET_LINK_SENT);
        $response->setMessage(__($status));

        return $call->sendResponse($response);
    }

    public function ResetPassword(ServerCall $call, $request)
    {
        $status = Password::reset(
            [
                'email' => $request->getEmail(),
                'password' => $request->getPassword(),
                'password_confirmation' => $request->getPasswordConfirmation(),
                'token' => $request->getToken(),
            ],
            function ($user, $password) {
                // Password MENTAH — model User punya cast 'password' => 'hashed'
                // yang akan hash otomatis saat forceFill/save. JANGAN Hash::make di sini.
                $user->forceFill([
                    'password' => $password,
                ])->save();
                $user->tokens()->delete();
            }
        );

        $response = new \Weddingapp\Auth\ResetPasswordResponse();
        $response->setSuccess($status === Password::PASSWORD_RESET);
        $response->setMessage(__($status));

        return $call->sendResponse($response);
    }

    public function RefreshToken(ServerCall $call, $request)
    {
        $refreshToken = $request->getRefreshToken();
        
        // In this implementation, we use long-lived tokens
        // For refresh token flow, you'd implement a separate token storage
        
        $response = new \Weddingapp\Auth\LoginResponse();
        $response->setMessage('Refresh token flow not implemented - using long-lived tokens');
        return $call->sendResponse($response);
    }

    public function VerifyEmail(ServerCall $call, $request)
    {
        $user = User::where('email', $request->getEmail())->firstOrFail();
        
        if ($user->hasVerifiedEmail()) {
            $response = new \Weddingapp\Auth\VerifyEmailResponse();
            $response->setSuccess(false);
            $response->setMessage('Email already verified');
            return $call->sendResponse($response);
        }

        // Verify token logic here
        $user->markEmailAsVerified();

        $response = new \Weddingapp\Auth\VerifyEmailResponse();
        $response->setSuccess(true);
        $response->setMessage('Email verified successfully');

        return $call->sendResponse($response);
    }

    public function ResendVerificationEmail(ServerCall $call, $request)
    {
        $user = User::where('email', $request->getEmail())->firstOrFail();
        
        if ($user->hasVerifiedEmail()) {
            $response = new \Weddingapp\Auth\ResendVerificationResponse();
            $response->setSuccess(false);
            $response->setMessage('Email already verified');
            return $call->sendResponse($response);
        }

        $user->sendEmailVerificationNotification();

        $response = new \Weddingapp\Auth\ResendVerificationResponse();
        $response->setSuccess(true);
        $response->setMessage('Verification email sent');

        return $call->sendResponse($response);
    }

    protected function buildUserProto(User $user): \Weddingapp\Auth\User
    {
        $proto = new \Weddingapp\Auth\User();
        $proto->setId($user->id);
        $proto->setName($user->name);
        $proto->setEmail($user->email);
        $proto->setPhone($user->phone ?? '');
        $proto->setAvatar($user->avatar ?? '');
        $proto->setEmailVerifiedAt($user->email_verified_at?->toISOString() ?? '');
        $proto->setAddress($user->address ?? '');
        $proto->setCity($user->city ?? '');
        $proto->setProvince($user->province ?? '');
        $proto->setPostalCode($user->postal_code ?? '');
        $proto->setDateOfBirth($user->date_of_birth?->toDateString() ?? '');
        $proto->setGender($user->gender ?? '');
        $proto->setRoles($user->getRoleNames()->toArray());
        $proto->setNotificationEmail($user->notification_email);
        $proto->setNotificationSms($user->notification_sms);
        $proto->setNotificationPush($user->notification_push);
        $proto->setLanguage($user->language);
        $proto->setCurrency($user->currency);
        $proto->setTimezone($user->timezone);
        $proto->setCreatedAt($user->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($user->updated_at?->toISOString() ?? '');
        return $proto;
    }

    protected function buildUserProfileProto(User $user): \Weddingapp\Auth\UserProfile
    {
        $proto = new \Weddingapp\Auth\UserProfile();
        $proto->setOrderCount($user->orders()->count());
        $proto->setReviewCount($user->reviews()->count());
        $proto->setWishlistCount($user->wishlist()->count());
        $proto->setTotalSpent($user->orders()->where('payment_status', 'paid')->sum('total_price'));
        
        $favCategory = \App\Models\Category\Category::query()
            ->join('catalogs', 'categories.id', '=', 'catalogs.category_id')
            ->join('orders', 'catalogs.id', '=', 'orders.catalog_id')
            ->where('orders.user_id', $user->id)
            ->where('orders.payment_status', 'paid')
            ->select('categories.*', \DB::raw('count(*) as order_count'))
            ->groupBy('categories.id')
            ->orderByDesc('order_count')
            ->first();
        
        if ($favCategory) {
            $catProto = new \Weddingapp\Auth\Category();
            $catProto->setId($favCategory->id);
            $catProto->setName($favCategory->name);
            $catProto->setSlug($favCategory->slug);
            $catProto->setDescription($favCategory->description ?? '');
            $catProto->setImageUrl($favCategory->image_url ?? '');
            $catProto->setIsActive($favCategory->is_active);
            $catProto->setCreatedAt($favCategory->created_at?->toISOString() ?? '');
            $proto->setFavoriteCategory($catProto);
        }
        
        $proto->setJoinedAt($user->created_at?->toISOString() ?? '');
        
        return $proto;
    }
}
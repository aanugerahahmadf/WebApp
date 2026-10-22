<?php

namespace App\Grpc\User\V1;

use App\Http\GraphQL\Resolvers\ProfileResolver;
use App\Models\User\User;
use App\Models\User\UserAddress;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Profile gRPC Service Implementation
 */
class ProfileService extends BaseGrpcService
{
    private ProfileResolver $profileResolver;
    
    public function __construct(ProfileResolver $profileResolver)
    {
        $this->profileResolver = $profileResolver;
    }
    
    /**
     * Get user profile
     */
    public function GetProfile(GetProfileRequest $request, ContextInterface $context): UserProfile
    {
        try {
            $user = $request->user_id ? User::find($request->user_id) : $this->requireAuth($context);
            
            if (!$user) {
                throw new \Exception('User not found', 404);
            }
            
            return $this->buildUserProfile($user);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetProfile');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Update user profile
     */
    public function UpdateProfile(UpdateProfileRequest $request, ContextInterface $context): User
    {
        try {
            $user = $this->requireAuth($context);
            
            $input = [
                'name' => $request->name ?? null,
                'phone' => $request->phone ?? null,
                'address' => $request->address ?? null,
                'city' => $request->city ?? null,
                'province' => $request->province ?? null,
                'postal_code' => $request->postal_code ?? null,
                'date_of_birth' => $request->date_of_birth ?? null,
                'gender' => $this->mapGenderFromProto($request->gender) ?? null,
                'avatar' => $request->avatar ?? null,
                'notification_email' => $request->notification_email ?? null,
                'notification_sms' => $request->notification_sms ?? null,
                'notification_push' => $request->notification_push ?? null,
                'language' => $request->language ?? null,
                'currency' => $request->currency ?? null,
                'timezone' => $request->timezone ?? null,
            ];
            
            // Filter out null values
            $input = array_filter($input, fn($v) => $v !== null);
            
            $updatedUser = $this->profileResolver->update(null, ['input' => $input], $context);
            
            return $this->transformUser($updatedUser);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'UpdateProfile');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Change password
     */
    public function ChangePassword(ChangePasswordRequest $request, ContextInterface $context): ChangePasswordResponse
    {
        try {
            $user = $this->requireAuth($context);
            
            $input = [
                'current_password' => $request->current_password,
                'new_password' => $request->new_password,
                'new_password_confirmation' => $request->new_password_confirmation,
            ];
            
            $result = $this->profileResolver->changePassword(null, ['input' => $input], $context);
            
            return new ChangePasswordResponse([
                'success' => $result,
                'message' => $result ? 'Password changed successfully' : 'Failed to change password',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'ChangePassword');
            return new ChangePasswordResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Update avatar
     */
    public function UpdateAvatar(UpdateAvatarRequest $request, ContextInterface $context): User
    {
        try {
            $user = $this->requireAuth($context);
            
            $input = [
                'avatar' => $request->avatar,
                'delete_current' => $request->delete_current ?? false,
            ];
            
            $updatedUser = $this->profileResolver->updateAvatar(null, ['input' => $input], $context);
            
            return $this->transformUser($updatedUser);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'UpdateAvatar');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Delete avatar
     */
    public function DeleteAvatar(DeleteAvatarRequest $request, ContextInterface $context): User
    {
        try {
            $user = $this->requireAuth($context);
            
            $updatedUser = $this->profileResolver->deleteAvatar(null, [], $context);
            
            return $this->transformUser($updatedUser);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'DeleteAvatar');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Delete account
     */
    public function DeleteAccount(DeleteAccountRequest $request, ContextInterface $context): DeleteAccountResponse
    {
        try {
            $user = $this->requireAuth($context);
            
            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                throw new \Exception('Invalid password', 401);
            }
            
            $input = [
                'password' => $request->password,
                'reason' => $request->reason ?? null,
            ];
            
            $result = $this->profileResolver->deleteAccount(null, ['input' => $input], $context);
            
            return new DeleteAccountResponse([
                'success' => $result,
                'message' => $result ? 'Account deleted successfully' : 'Failed to delete account',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'DeleteAccount');
            return new DeleteAccountResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Update notification preferences
     */
    public function UpdateNotificationPreferences(UpdateNotificationPreferencesRequest $request, ContextInterface $context): User
    {
        try {
            $user = $this->requireAuth($context);
            
            $input = [
                'notification_email' => $request->notification_email,
                'notification_sms' => $request->notification_sms,
                'notification_push' => $request->notification_push,
            ];
            
            $updatedUser = $this->profileResolver->updateNotifications(null, ['input' => $input], $context);
            
            return $this->transformUser($updatedUser);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'UpdateNotificationPreferences');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Update locale preferences
     */
    public function UpdateLocalePreferences(UpdateLocalePreferencesRequest $request, ContextInterface $context): User
    {
        try {
            $user = $this->requireAuth($context);
            
            $input = [
                'language' => $request->language ?? null,
                'currency' => $request->currency ?? null,
                'timezone' => $request->timezone ?? null,
            ];
            
            $input = array_filter($input, fn($v) => $v !== null);
            
            $updatedUser = $this->profileResolver->updateLocale(null, ['input' => $input], $context);
            
            return $this->transformUser($updatedUser);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'UpdateLocalePreferences');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get user addresses
     */
    public function GetAddresses(GetAddressesRequest $request, ContextInterface $context): UserAddressesResponse
    {
        try {
            $user = $request->user_id ? User::find($request->user_id) : $this->requireAuth($context);
            
            if (!$user) {
                throw new \Exception('User not found', 404);
            }
            
            $addresses = $user->addresses()->orderBy('is_default', 'desc')->get();
            
            return new UserAddressesResponse([
                'addresses' => array_map([$this, 'transformAddress'], $addresses->toArray()),
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetAddresses');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Create address
     */
    public function CreateAddress(CreateAddressRequest $request, ContextInterface $context): UserAddress
    {
        try {
            $user = $this->requireAuth($context);
            
            // If this is default, unset other defaults
            if ($request->is_default) {
                $user->addresses()->update(['is_default' => false]);
            }
            
            $address = $user->addresses()->create([
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'province' => $request->province,
                'postal_code' => $request->postal_code,
                'type' => $this->mapAddressTypeFromProto($request->type),
                'is_default' => $request->is_default,
            ]);
            
            return $this->transformAddress($address->toArray());
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'CreateAddress');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Update address
     */
    public function UpdateAddress(UpdateAddressRequest $request, ContextInterface $context): UserAddress
    {
        try {
            $user = $this->requireAuth($context);
            
            $address = $user->addresses()->findOrFail($request->id);
            
            // If this is default, unset other defaults
            if ($request->is_default) {
                $user->addresses()->where('id', '!=', $request->id)->update(['is_default' => false]);
            }
            
            $address->update([
                'name' => $request->name ?? $address->name,
                'phone' => $request->phone ?? $address->phone,
                'address' => $request->address ?? $address->address,
                'city' => $request->city ?? $address->city,
                'province' => $request->province ?? $address->province,
                'postal_code' => $request->postal_code ?? $address->postal_code,
                'type' => $request->type !== \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_UNSPECIFIED 
                    ? $this->mapAddressTypeFromProto($request->type) : $address->type,
                'is_default' => $request->is_default ?? $address->is_default,
            ]);
            
            return $this->transformAddress($address->toArray());
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'UpdateAddress');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Delete address
     */
    public function DeleteAddress(DeleteAddressRequest $request, ContextInterface $context): DeleteAddressResponse
    {
        try {
            $user = $this->requireAuth($context);
            
            $address = $user->addresses()->findOrFail($request->id);
            $wasDefault = $address->is_default;
            $address->delete();
            
            // If deleted address was default, set another as default
            if ($wasDefault) {
                $newDefault = $user->addresses()->first();
                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
            
            return new DeleteAddressResponse([
                'success' => true,
                'message' => 'Address deleted successfully',
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'DeleteAddress');
            return new DeleteAddressResponse([
                'success' => false,
                'message' => $error['message'],
            ]);
        }
    }
    
    /**
     * Set default address
     */
    public function SetDefaultAddress(SetDefaultAddressRequest $request, ContextInterface $context): UserAddress
    {
        try {
            $user = $this->requireAuth($context);
            
            $address = $user->addresses()->findOrFail($request->id);
            
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
            
            return $this->transformAddress($address->fresh()->toArray());
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'SetDefaultAddress');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Build full user profile with stats
     */
    private function buildUserProfile(User $user): UserProfile
    {
        $favoriteCategory = null;
        // Could compute from order history
        
        return new UserProfile([
            'user' => $this->transformUser($user),
            'order_count' => $user->orders()->count(),
            'review_count' => $user->reviews()->count(),
            'wishlist_count' => $user->wishlist()->count(),
            'total_spent' => (float) $user->orders()->where('payment_status', 'paid')->sum('total_price'),
            'favorite_category' => $favoriteCategory,
            'joined_at' => $this->formatTimestamp($user->created_at),
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
     * Transform address array to protobuf UserAddress
     */
    private function transformAddress(array $address): UserAddress
    {
        return new UserAddress([
            'id' => (string) $address['id'],
            'user_id' => (string) $address['user_id'],
            'name' => $address['name'],
            'phone' => $address['phone'],
            'address' => $address['address'],
            'city' => $address['city'],
            'province' => $address['province'],
            'postal_code' => $address['postal_code'],
            'type' => $this->mapAddressType($address['type']),
            'is_default' => $address['is_default'],
            'created_at' => $this->formatTimestamp($address['created_at'] ?? null),
            'updated_at' => $this->formatTimestamp($address['updated_at'] ?? null),
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
    
    /**
     * Map protobuf gender enum to string
     */
    private function mapGenderFromProto(int $gender): ?string
    {
        return match ($gender) {
            \App\Grpc\User\V1\Gender::GENDER_MALE => 'male',
            \App\Grpc\User\V1\Gender::GENDER_FEMALE => 'female',
            \App\Grpc\User\V1\Gender::GENDER_OTHER => 'other',
            \App\Grpc\User\V1\Gender::GENDER_PREFER_NOT_TO_SAY => 'prefer_not_to_say',
            default => null,
        };
    }
    
    /**
     * Map address type string to protobuf enum
     */
    private function mapAddressType(string $type): int
    {
        return match ($type) {
            'home' => \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_HOME,
            'work' => \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_WORK,
            'other' => \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_OTHER,
            default => \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_UNSPECIFIED,
        };
    }
    
    /**
     * Map protobuf address type enum to string
     */
    private function mapAddressTypeFromProto(int $type): string
    {
        return match ($type) {
            \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_HOME => 'home',
            \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_WORK => 'work',
            \App\Grpc\User\V1\AddressType::ADDRESS_TYPE_OTHER => 'other',
            default => 'other',
        };
    }
}
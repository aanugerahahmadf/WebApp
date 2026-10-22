<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\User\User;
use App\Models\User\UserAddress;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ProfileResolver
{
    /**
     * Update Profile
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = $args['input'];
        
        // Remove null values
        $input = array_filter($input, fn($v) => $v !== null);
        
        // Check email uniqueness if changing
        if (isset($input['email']) && $input['email'] !== $user->email) {
            if (User::where('email', $input['email'])->exists()) {
                throw new \Exception('Email already in use');
            }
            $input['email_verified_at'] = null; // Require re-verification
        }
        
        // Check phone uniqueness if changing
        if (isset($input['phone']) && $input['phone'] !== $user->phone) {
            if (User::where('phone', $input['phone'])->exists()) {
                throw new \Exception('Phone number already in use');
            }
        }
        
        $user->update($input);
        
        return $user->fresh();
    }

    /**
     * Change Password
     */
    public function changePassword($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = $args['input'];
        
        // Verify current password
        if (!Hash::check($input['currentPassword'], $user->password)) {
            throw new \Exception('Current password is incorrect');
        }
        
        // Validate new password
        if ($input['newPassword'] !== $input['newPasswordConfirmation']) {
            throw new \Exception('Password confirmation does not match');
        }
        
        if (strlen($input['newPassword']) < 8) {
            throw new \Exception('Password must be at least 8 characters');
        }
        
        $user->password = $input['newPassword'];
        $user->save();
        
        // Revoke all tokens except current
        $currentToken = $context->getToken();
        $user->tokens()->where('id', '!=', $currentToken?->id)->delete();
        
        return true;
    }

    /**
     * Update Avatar
     */
    public function updateAvatar($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = $args['input'];
        
        // Delete old avatar if requested
        if ($input['deleteCurrent'] && $user->avatar) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
        }
        
        $user->avatar = $input['avatar'];
        $user->save();
        
        return $user->fresh();
    }

    /**
     * Delete Avatar
     */
    public function deleteAvatar($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        if ($user->avatar) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            $user->avatar = null;
            $user->save();
        }
        
        return $user->fresh();
    }

    /**
     * Delete Account
     */
    public function deleteAccount($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = $args['input'];
        
        // Verify password
        if (!Hash::check($input['password'], $user->password)) {
            throw new \Exception('Password is incorrect');
        }
        
        // Soft delete user
        $user->delete();
        
        // Revoke all tokens
        $user->tokens()->delete();
        
        return true;
    }

    /**
     * Update Notification Preferences
     */
    public function updateNotifications($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = array_filter($args, fn($v) => $v !== null, ARRAY_FILTER_USE_BOTH);
        unset($input['input']);
        
        $user->update($input);
        
        return $user->fresh();
    }

    /**
     * Update Locale Preferences
     */
    public function updateLocale($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = array_filter($args, fn($v) => $v !== null, ARRAY_FILTER_USE_BOTH);
        unset($input['input']);
        
        $user->update($input);
        
        return $user->fresh();
    }

    /**
     * Get Profile Completion
     */
    public function completion($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return 0;
        }
        
        $fields = [
            'name' => !empty($user->name),
            'email' => !empty($user->email),
            'phone' => !empty($user->phone),
            'avatar' => !empty($user->avatar),
            'address' => !empty($user->address),
            'city' => !empty($user->city),
            'province' => !empty($user->province),
            'postal_code' => !empty($user->postal_code),
            'date_of_birth' => !empty($user->date_of_birth),
            'gender' => !empty($user->gender),
        ];
        
        $completed = count(array_filter($fields));
        $total = count($fields);
        
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Add User Address
     */
    public function addAddress($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $input = $args['input'];
        
        // If this is default, unset other defaults
        if ($input['isDefault']) {
            UserAddress::where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
        
        $address = UserAddress::create([
            'user_id' => $user->id,
            'name' => $input['name'],
            'phone' => $input['phone'],
            'address' => $input['address'],
            'city' => $input['city'],
            'province' => $input['province'],
            'postal_code' => $input['postal_code'],
            'type' => $input['type'] ?? 'HOME',
            'is_default' => $input['isDefault'] ?? false,
        ]);
        
        return $address;
    }

    /**
     * Update User Address
     */
    public function updateAddress($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $address = UserAddress::where('id', $args['addressId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $input = $args['input'];
        
        // If this is default, unset other defaults
        if ($input['isDefault']) {
            UserAddress::where('user_id', $user->id)
                ->where('id', '!=', $address->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
        
        $address->update($input);
        
        return $address->fresh();
    }

    /**
     * Delete User Address
     */
    public function deleteAddress($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $address = UserAddress::where('id', $args['addressId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $address->delete();
        
        return true;
    }
}
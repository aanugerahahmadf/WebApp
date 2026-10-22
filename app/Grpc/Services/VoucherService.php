<?php

namespace App\Grpc\Services;

use App\Models\Voucher\Voucher;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use App\Models\User\User;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class VoucherService
{
    public function ValidateVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $code = $request->getCode();
        $cartTotal = $request->getCartTotal();
        $items = $request->getItems();
        
        $voucher = Voucher::where('code', $code)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();
        
        if (!$voucher) {
            $response = new \Weddingapp\Voucher\ValidateVoucherResponse();
            $response->setValid(false);
            $response->setDiscount(0);
            $response->setMessage('Invalid or expired voucher code');
            return $call->sendResponse($response);
        }
        
        if ($voucher->max_uses && $voucher->uses_count >= $voucher->max_uses) {
            $response = new \Weddingapp\Voucher\ValidateVoucherResponse();
            $response->setValid(false);
            $response->setVoucher($this->buildVoucherProto($voucher));
            $response->setDiscount(0);
            $response->setMessage('Voucher usage limit reached');
            return $call->sendResponse($response);
        }
        
        if ($cartTotal < $voucher->min_purchase) {
            $response = new \Weddingapp\Voucher\ValidateVoucherResponse();
            $response->setValid(false);
            $response->setVoucher($this->buildVoucherProto($voucher));
            $response->setDiscount(0);
            $response->setMessage("Minimum purchase of {$voucher->min_purchase} required");
            return $call->sendResponse($response);
        }
        
        $applicableItems = [];
        $totalDiscount = 0;
        
        foreach ($items as $item) {
            $isApplicable = $this->isItemApplicable($voucher, $item);
            $isExcluded = $this->isItemExcluded($voucher, $item);
            
            if ($isApplicable && !$isExcluded) {
                $itemTotal = $item->getPrice() * $item->getQuantity();
                $discount = $this->calculateDiscount($voucher, $itemTotal);
                
                if ($voucher->max_discount && $discount > $voucher->max_discount) {
                    $discount = $voucher->max_discount;
                }
                
                $validatedItem = new \Weddingapp\Voucher\ValidatedItem();
                $validatedItem->setId($item->getId());
                $validatedItem->setType($item->getType());
                $validatedItem->setOriginalPrice($itemTotal);
                $validatedItem->setDiscountedPrice($itemTotal - $discount);
                $validatedItem->setDiscount($discount);
                $applicableItems[] = $validatedItem;
                
                $totalDiscount += $discount;
            }
        }
        
        if (empty($applicableItems)) {
            $response = new \Weddingapp\Voucher\ValidateVoucherResponse();
            $response->setValid(false);
            $response->setVoucher($this->buildVoucherProto($voucher));
            $response->setDiscount(0);
            $response->setMessage('No applicable items for this voucher');
            return $call->sendResponse($response);
        }
        
        $response = new \Weddingapp\Voucher\ValidateVoucherResponse();
        $response->setValid(true);
        $response->setVoucher($this->buildVoucherProto($voucher));
        $response->setDiscount($totalDiscount);
        $response->setMessage('Voucher applied successfully');
        foreach ($applicableItems as $item) {
            $response->addAppliedItems($item);
        }
        
        return $call->sendResponse($response);
    }

    public function GetVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $voucher = Voucher::find($request->getId());
        
        $response = new \Weddingapp\Voucher\VoucherResponse();
        if ($voucher) {
            $response->setVoucher($this->buildVoucherProto($voucher));
            $response->setMessage('Voucher found');
        } else {
            $response->setMessage('Voucher not found');
        }
        
        return $call->sendResponse($response);
    }

    public function GetVouchers(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\VouchersResponse();
            return $call->sendResponse($response);
        }
        
        $query = Voucher::query();
        
        if ($request->getCode()) {
            $query->where('code', 'like', "%{$request->getCode()}%");
        }
        if ($request->getActive()) {
            $query->where('is_active', $request->getActive());
        }
        if ($request->getIsGlobal()) {
            $query->where('is_global', $request->getIsGlobal());
        }
        
        $perPage = $request->getPerPage() ?: 20;
        $page = $request->getPage() ?: 1;
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Voucher\VouchersResponse();
        foreach ($paginated->items() as $voucher) {
            $response->addVouchers($this->buildVoucherProto($voucher));
        }
        
        $pagination = new \Weddingapp\Voucher\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetMyVouchers(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = Voucher::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($q) use ($user) {
                $q->where('is_global', true)
                  ->orWhereJsonContains('user_ids', $user->id);
            })
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Voucher\VouchersResponse();
        foreach ($paginated->items() as $voucher) {
            $response->addVouchers($this->buildVoucherProto($voucher));
        }
        
        $pagination = new \Weddingapp\Voucher\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetVoucherStats(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\VoucherStatsResponse();
            return $call->sendResponse($response);
        }
        
        $voucher = Voucher::find($request->getVoucherId());
        if (!$voucher) {
            $response = new \Weddingapp\Voucher\VoucherStatsResponse();
            return $call->sendResponse($response);
        }
        
        $usageByUser = \DB::table('voucher_usages')
            ->join('users', 'voucher_usages.user_id', '=', 'users.id')
            ->where('voucher_usages.voucher_id', $voucher->id)
            ->select('users.*', \DB::raw('count(*) as uses_count'), \DB::raw('min(voucher_usages.created_at) as first_used_at'), \DB::raw('max(voucher_usages.created_at) as last_used_at'))
            ->groupBy('users.id')
            ->get();
        
        $revenueImpact = \DB::table('orders')
            ->join('voucher_usages', 'orders.id', '=', 'voucher_usages.order_id')
            ->where('voucher_usages.voucher_id', $voucher->id)
            ->where('orders.payment_status', 'paid')
            ->sum('orders.total_price');
        
        $stats = new \Weddingapp\Voucher\VoucherStats();
        $stats->setVoucher($this->buildVoucherProto($voucher));
        $stats->setTotalUses($voucher->uses_count);
        $stats->setRemainingUses($voucher->max_uses ? $voucher->max_uses - $voucher->uses_count : 0);
        $stats->setRevenueImpact($revenueImpact);
        
        foreach ($usageByUser as $usage) {
            $userProto = new \Weddingapp\Voucher\User();
            $userProto->setId($usage->id);
            $userProto->setName($usage->name);
            $userProto->setEmail($usage->email);
            $userProto->setAvatar($usage->avatar ?? '');
            
            $usageProto = new \Weddingapp\Voucher\VoucherUserUsage();
            $usageProto->setUser($userProto);
            $usageProto->setUsesCount($usage->uses_count);
            $usageProto->setFirstUsedAt($usage->first_used_at ?? '');
            $usageProto->setLastUsedAt($usage->last_used_at ?? '');
            $stats->addUsageByUser($usageProto);
        }
        
        $response = new \Weddingapp\Voucher\VoucherStatsResponse();
        $response->setStats($stats);
        
        return $call->sendResponse($response);
    }

    public function CreateVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\VoucherResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $originalCode = $request->getCode();
        $counter = 1;
        $code = $originalCode;
        while (Voucher::where('code', $code)->exists()) {
            $code = $originalCode . '-' . $counter++;
        }
        
        $voucher = Voucher::create([
            'name' => $request->getName(),
            'code' => $code,
            'description' => $request->getDescription() ?: null,
            'discount_amount' => $request->getDiscountAmount(),
            'discount_type' => $this->mapDiscountType($request->getDiscountType()),
            'min_purchase' => $request->getMinPurchase(),
            'max_discount' => $request->getMaxDiscount() ?: null,
            'usage_limit' => $request->getUsageLimit() ?: null,
            'usage_limit_per_user' => $request->getUsageLimitPerUser() ?: 1,
            'valid_from' => $request->getValidFrom(),
            'valid_until' => $request->getValidUntil(),
            'is_global' => $request->getIsGlobal(),
            'applicable_catalogs' => $request->getApplicableCatalogs() ?: [],
            'applicable_packages' => $request->getApplicablePackages() ?: [],
            'applicable_categories' => $request->getApplicableCategories() ?: [],
            'excluded_catalogs' => $request->getExcludedCatalogs() ?: [],
            'excluded_packages' => $request->getExcludedPackages() ?: [],
            'excluded_categories' => $request->getExcludedCategories() ?: [],
            'user_ids' => $request->getUserIds() ?: [],
        ]);
        
        $response = new \Weddingapp\Voucher\VoucherResponse();
        $response->setVoucher($this->buildVoucherProto($voucher));
        $response->setMessage('Voucher created successfully');
        
        return $call->sendResponse($response);
    }

    public function UpdateVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\VoucherResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $voucher = Voucher::find($request->getId());
        if (!$voucher) {
            $response = new \Weddingapp\Voucher\VoucherResponse();
            $response->setMessage('Voucher not found');
            return $call->sendResponse($response);
        }
        
        $updateData = [];
        $fields = ['name', 'description', 'discount_amount', 'min_purchase', 'max_discount', 'usage_limit', 'usage_limit_per_user', 'valid_from', 'valid_until', 'is_active', 'is_global', 'applicable_catalogs', 'applicable_packages', 'applicable_categories', 'excluded_catalogs', 'excluded_packages', 'excluded_categories', 'user_ids'];
        
        foreach ($fields as $field) {
            $getter = 'get' . ucfirst(str_replace('_', '', $field));
            if ($request->$getter() !== '') {
                $updateData[$field] = $request->$getter();
            }
        }
        
        if ($request->getDiscountType() !== \Weddingapp\Voucher\DiscountType::DISCOUNT_TYPE_UNSPECIFIED) {
            $updateData['discount_type'] = $this->mapDiscountType($request->getDiscountType());
        }
        
        $voucher->update($updateData);
        
        $response = new \Weddingapp\Voucher\VoucherResponse();
        $response->setVoucher($this->buildVoucherProto($voucher->fresh()));
        $response->setMessage('Voucher updated successfully');
        
        return $call->sendResponse($response);
    }

    public function DeleteVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\DeleteVoucherResponse();
            $response->setSuccess(false);
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $voucher = Voucher::find($request->getId());
        if (!$voucher) {
            $response = new \Weddingapp\Voucher\DeleteVoucherResponse();
            $response->setSuccess(false);
            $response->setMessage('Voucher not found');
            return $call->sendResponse($response);
        }
        
        $voucher->delete();
        
        $response = new \Weddingapp\Voucher\DeleteVoucherResponse();
        $response->setSuccess(true);
        $response->setMessage('Voucher deleted successfully');
        
        return $call->sendResponse($response);
    }

    public function ToggleVoucherStatus(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\VoucherResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $voucher = Voucher::find($request->getId());
        if (!$voucher) {
            $response = new \Weddingapp\Voucher\VoucherResponse();
            $response->setMessage('Voucher not found');
            return $call->sendResponse($response);
        }
        
        $voucher->is_active = !$voucher->is_active;
        $voucher->save();
        
        $response = new \Weddingapp\Voucher\VoucherResponse();
        $response->setVoucher($this->buildVoucherProto($voucher->fresh()));
        $response->setMessage('Voucher status toggled');
        
        return $call->sendResponse($response);
    }

    public function BulkCreateVouchers(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\BulkCreateVouchersResponse();
            return $call->sendResponse($response);
        }
        
        $vouchers = [];
        
        for ($i = 0; $i < $request->getCount(); $i++) {
            $code = $request->getPrefix() . $this->generateRandomCode($request->getLength());
            
            while (Voucher::where('code', $code)->exists()) {
                $code = $request->getPrefix() . $this->generateRandomCode($request->getLength());
            }
            
            $voucher = Voucher::create([
                'name' => $request->getPrefix() . ' Voucher ' . ($i + 1),
                'code' => $code,
                'discount_amount' => $request->getDiscountAmount(),
                'discount_type' => $this->mapDiscountType($request->getDiscountType()),
                'min_purchase' => $request->getMinPurchase(),
                'max_discount' => $request->getMaxDiscount() ?: null,
                'usage_limit' => $request->getUsageLimit() ?: null,
                'usage_limit_per_user' => $request->getUsageLimitPerUser() ?: 1,
                'valid_from' => $request->getValidFrom(),
                'valid_until' => $request->getValidUntil(),
                'is_global' => true,
            ]);
            
            $vouchers[] = $voucher;
        }
        
        $response = new \Weddingapp\Voucher\BulkCreateVouchersResponse();
        foreach ($vouchers as $voucher) {
            $response->addVouchers($this->buildVoucherProto($voucher));
        }
        
        return $call->sendResponse($response);
    }

    public function ExportVoucherCodes(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Voucher\ExportVoucherCodesResponse();
            return $call->sendResponse($response);
        }
        
        $voucher = Voucher::find($request->getVoucherId());
        if (!$voucher) {
            $response = new \Weddingapp\Voucher\ExportVoucherCodesResponse();
            return $call->sendResponse($response);
        }
        
        $codes = Voucher::where('code', 'like', $voucher->code . '%')
            ->pluck('code')
            ->toArray();
        
        $csv = "Code,Discount Type,Discount Amount,Valid From,Valid Until,Max Uses,Uses Count\n";
        foreach ($codes as $code) {
            $v = Voucher::where('code', $code)->first();
            $csv .= "{$code},{$v->discount_type},{$v->discount_amount},{$v->valid_from},{$v->valid_until},{$v->max_uses},{$v->uses_count}\n";
        }
        
        $filename = 'vouchers_' . $voucher->code . '_' . date('Ymd_His') . '.csv';
        $path = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $csv);
        
        $response = new \Weddingapp\Voucher\ExportVoucherCodesResponse();
        $response->setVoucher($this->buildVoucherProto($voucher));
        $response->setCodes($codes);
        $response->setCsvUrl(url('storage/exports/' . $filename));
        $response->setExportedAt(now()->toISOString());
        
        return $call->sendResponse($response);
    }

    protected function getUserFromToken(string $token): \App\Models\User\User
    {
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            throw new \Exception('Unauthorized: Invalid token');
        }
        return $accessToken->tokenable;
    }

    protected function buildVoucherProto($voucher): \Weddingapp\Voucher\Voucher
    {
        $proto = new \Weddingapp\Voucher\Voucher();
        $proto->setId($voucher->id);
        $proto->setName($voucher->name ?? '');
        $proto->setCode($voucher->code);
        $proto->setDescription($voucher->description ?? '');
        $proto->setIsActive($voucher->is_active);
        $proto->setIsGlobal($voucher->is_global);
        $proto->setMaxUses($voucher->max_uses ?? 0);
        $proto->setUsesCount($voucher->uses_count);
        $proto->setExpiresAt($voucher->expires_at?->toISOString() ?? '');
        $proto->setDiscountAmount($voucher->discount_amount);
        $proto->setDiscountType($this->mapDiscountTypeProto($voucher->discount_type));
        $proto->setMinPurchase($voucher->min_purchase);
        $proto->setMaxDiscount($voucher->max_discount ?? 0);
        $proto->setCreatedAt($voucher->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($voucher->updated_at?->toISOString() ?? '');
        
        if ($voucher->applicable_catalogs) {
            foreach ($voucher->applicable_catalogs as $catalogId) {
                $catalog = Catalog::find($catalogId);
                if ($catalog) {
                    $catalogProto = new \Weddingapp\Catalog\Catalog();
                    $catalogProto->setId($catalog->id);
                    $catalogProto->setName($catalog->name);
                    $catalogProto->setSlug($catalog->slug);
                    $catalogProto->setImageUrl($catalog->image_url ?? '');
                    $proto->addApplicableCatalogs($catalogProto);
                }
            }
        }
        
        if ($voucher->applicable_packages) {
            foreach ($voucher->applicable_packages as $packageId) {
                $package = Package::find($packageId);
                if ($package) {
                    $packageProto = new \Weddingapp\Package\Package();
                    $packageProto->setId($package->id);
                    $packageProto->setName($package->name);
                    $packageProto->setSlug($package->slug);
                    $packageProto->setImageUrl($package->image_url ?? '');
                    $proto->addApplicablePackages($packageProto);
                }
            }
        }
        
        if ($voucher->applicable_categories) {
            foreach ($voucher->applicable_categories as $categoryId) {
                $category = Category::find($categoryId);
                if ($category) {
                    $categoryProto = new \Weddingapp\Catalog\Category();
                    $categoryProto->setId($category->id);
                    $categoryProto->setName($category->name);
                    $categoryProto->setSlug($category->slug);
                    $categoryProto->setImageUrl($category->image_url ?? '');
                    $proto->addApplicableCategories($categoryProto);
                }
            }
        }
        
        if ($voucher->user_ids) {
            foreach ($voucher->user_ids as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $userProto = new \Weddingapp\Voucher\User();
                    $userProto->setId($user->id);
                    $userProto->setName($user->name);
                    $userProto->setEmail($user->email);
                    $userProto->setAvatar($user->avatar ?? '');
                    $proto->addUsers($userProto);
                }
            }
        }
        
        return $proto;
    }

    protected function isItemApplicable(Voucher $voucher, $item): bool
    {
        if ($voucher->is_global) {
            return true;
        }
        
        $itemType = $item->getType();
        $itemId = $item->getId();
        
        if ($itemType === 'catalog') {
            if ($voucher->applicable_catalogs && in_array($itemId, $voucher->applicable_catalogs)) {
                return true;
            }
            $catalog = Catalog::find($itemId);
            if ($catalog && $voucher->applicable_categories && in_array($catalog->category_id, $voucher->applicable_categories)) {
                return true;
            }
        } elseif ($itemType === 'package') {
            if ($voucher->applicable_packages && in_array($itemId, $voucher->applicable_packages)) {
                return true;
            }
            $package = Package::find($itemId);
            if ($package && $voucher->applicable_categories && in_array($package->category_id, $voucher->applicable_categories)) {
                return true;
            }
        }
        
        return false;
    }

    protected function isItemExcluded(Voucher $voucher, $item): bool
    {
        $itemType = $item->getType();
        $itemId = $item->getId();
        
        if ($itemType === 'catalog') {
            if ($voucher->excluded_catalogs && in_array($itemId, $voucher->excluded_catalogs)) {
                return true;
            }
            $catalog = Catalog::find($itemId);
            if ($catalog && $voucher->excluded_categories && in_array($catalog->category_id, $voucher->excluded_categories)) {
                return true;
            }
        } elseif ($itemType === 'package') {
            if ($voucher->excluded_packages && in_array($itemId, $voucher->excluded_packages)) {
                return true;
            }
            $package = Package::find($itemId);
            if ($package && $voucher->excluded_categories && in_array($package->category_id, $voucher->excluded_categories)) {
                return true;
            }
        }
        
        return false;
    }

    protected function calculateDiscount(Voucher $voucher, float $itemTotal): float
    {
        switch ($voucher->discount_type) {
            case 'PERCENTAGE':
                return $itemTotal * ($voucher->discount_amount / 100);
            case 'FIXED_AMOUNT':
                return min($voucher->discount_amount, $itemTotal);
            case 'FREE_SHIPPING':
                return 0;
            default:
                return 0;
        }
    }

    protected function generateRandomCode(int $length): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    protected function mapDiscountType(int $type): string
    {
        $map = [
            0 => 'PERCENTAGE',
            1 => 'PERCENTAGE',
            2 => 'FIXED_AMOUNT',
            3 => 'FREE_SHIPPING',
        ];
        return $map[$type] ?? 'PERCENTAGE';
    }

    protected function mapDiscountTypeProto(string $type): int
    {
        $map = [
            'PERCENTAGE' => \Weddingapp\Voucher\DiscountType::PERCENTAGE,
            'FIXED_AMOUNT' => \Weddingapp\Voucher\DiscountType::FIXED_AMOUNT,
            'FREE_SHIPPING' => \Weddingapp\Voucher\DiscountType::FREE_SHIPPING,
        ];
        return $map[$type] ?? \Weddingapp\Voucher\DiscountType::PERCENTAGE;
    }
}
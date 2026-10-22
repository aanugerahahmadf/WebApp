<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Voucher\Voucher;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class VoucherResolver
{
    /**
     * Get voucher by code
     */
    public function byCode($root, array $args, GraphQLContext $context)
    {
        return Voucher::where('code', $args['code'])
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();
    }

    /**
     * Admin: Get all vouchers
     */
    public function all($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $query = Voucher::query();
        
        if (isset($args['code'])) {
            $query->where('code', 'like', "%{$args['code']}%");
        }
        
        if (isset($args['active'])) {
            $query->where('is_active', $args['active']);
        }
        
        if (isset($args['isGlobal'])) {
            $query->where('is_global', $args['isGlobal']);
        }
        
        return $query->latest()->paginate($args['first'] ?? 20);
    }

    /**
     * Get voucher statistics
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $voucher = Voucher::findOrFail($args['voucherId']);
        
        $usageByUser = DB::table('voucher_usages')
            ->join('users', 'voucher_usages.user_id', '=', 'users.id')
            ->where('voucher_usages.voucher_id', $voucher->id)
            ->select('users.*', DB::raw('count(*) as uses_count'), DB::raw('min(voucher_usages.created_at) as first_used_at'), DB::raw('max(voucher_usages.created_at) as last_used_at'))
            ->groupBy('users.id')
            ->get();
        
        $revenueImpact = DB::table('orders')
            ->join('voucher_usages', 'orders.id', '=', 'voucher_usages.order_id')
            ->where('voucher_usages.voucher_id', $voucher->id)
            ->where('orders.payment_status', 'paid')
            ->sum('orders.total_price');
        
        return [
            'voucher' => $voucher,
            'totalUses' => $voucher->uses_count,
            'remainingUses' => $voucher->max_uses ? $voucher->max_uses - $voucher->uses_count : null,
            'usageByUser' => $usageByUser,
            'revenueImpact' => $revenueImpact,
        ];
    }

    /**
     * Validate voucher
     */
    public function validate($root, array $args, GraphQLContext $context)
    {
        $code = $args['code'];
        $cartTotal = $args['cartTotal'];
        $items = $args['items'] ?? [];
        
        $voucher = Voucher::where('code', $code)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();
        
        if (!$voucher) {
            return [
                'valid' => false,
                'voucher' => null,
                'discount' => 0,
                'message' => 'Invalid or expired voucher code',
                'appliedItems' => [],
            ];
        }
        
        // Check usage limits
        if ($voucher->max_uses && $voucher->uses_count >= $voucher->max_uses) {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'discount' => 0,
                'message' => 'Voucher usage limit reached',
                'appliedItems' => [],
            ];
        }
        
        // Check minimum purchase
        if ($cartTotal < $voucher->min_purchase) {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'discount' => 0,
                'message' => "Minimum purchase of {$voucher->min_purchase} required",
                'appliedItems' => [],
            ];
        }
        
        // Check applicable items
        $applicableItems = [];
        $totalDiscount = 0;
        
        foreach ($items as $item) {
            $isApplicable = $this->isItemApplicable($voucher, $item);
            $isExcluded = $this->isItemExcluded($voucher, $item);
            
            if ($isApplicable && !$isExcluded) {
                $itemTotal = $item['price'] * $item['quantity'];
                $discount = $this->calculateDiscount($voucher, $itemTotal);
                
                if ($voucher->max_discount && $discount > $voucher->max_discount) {
                    $discount = $voucher->max_discount;
                }
                
                $applicableItems[] = [
                    'id' => $item['id'],
                    'type' => $item['type'],
                    'originalPrice' => $itemTotal,
                    'discountedPrice' => $itemTotal - $discount,
                    'discount' => $discount,
                ];
                
                $totalDiscount += $discount;
            }
        }
        
        if (empty($applicableItems)) {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'discount' => 0,
                'message' => 'No applicable items for this voucher',
                'appliedItems' => [],
            ];
        }
        
        return [
            'valid' => true,
            'voucher' => $voucher,
            'discount' => $totalDiscount,
            'message' => 'Voucher applied successfully',
            'appliedItems' => $applicableItems,
        ];
    }

    /**
     * Check if item is applicable for voucher
     */
    private function isItemApplicable(Voucher $voucher, array $item): bool
    {
        // If voucher is global, all items are applicable
        if ($voucher->is_global) {
            return true;
        }
        
        $itemType = $item['type']; // 'catalog' or 'package'
        $itemId = $item['id'];
        
        // Check specific catalogs/packages
        if ($itemType === 'catalog') {
            if ($voucher->applicable_catalogs && in_array($itemId, $voucher->applicable_catalogs)) {
                return true;
            }
            // Check category
            $catalog = Catalog::find($itemId);
            if ($catalog && $voucher->applicable_categories && in_array($catalog->category_id, $voucher->applicable_categories)) {
                return true;
            }
        } elseif ($itemType === 'package') {
            if ($voucher->applicable_packages && in_array($itemId, $voucher->applicable_packages)) {
                return true;
            }
            // Check category
            $package = Package::find($itemId);
            if ($package && $voucher->applicable_categories && in_array($package->category_id, $voucher->applicable_categories)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if item is excluded from voucher
     */
    private function isItemExcluded(Voucher $voucher, array $item): bool
    {
        $itemType = $item['type'];
        $itemId = $item['id'];
        
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

    /**
     * Calculate discount for item
     */
    private function calculateDiscount(Voucher $voucher, float $itemTotal): float
    {
        switch ($voucher->discount_type) {
            case 'PERCENTAGE':
                return $itemTotal * ($voucher->discount_amount / 100);
            case 'FIXED_AMOUNT':
                return min($voucher->discount_amount, $itemTotal);
            case 'FREE_SHIPPING':
                return 0; // Handled separately
            default:
                return 0;
        }
    }

    /**
     * Admin: Create voucher
     */
    public function create($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $input = $args['input'];
        
        // Ensure unique code
        $originalCode = $input['code'];
        $counter = 1;
        while (Voucher::where('code', $input['code'])->exists()) {
            $input['code'] = $originalCode . '-' . $counter++;
        }
        
        $voucher = Voucher::create($input);
        
        return $voucher;
    }

    /**
     * Admin: Update voucher
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $voucher = Voucher::findOrFail($args['id']);
        $input = $args['input'];
        
        $voucher->update($input);
        
        return $voucher->fresh();
    }

    /**
     * Admin: Delete voucher
     */
    public function delete($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $voucher = Voucher::findOrFail($args['id']);
        $voucher->delete();
        
        return true;
    }

    /**
     * Admin: Toggle voucher status
     */
    public function toggleStatus($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $voucher = Voucher::findOrFail($args['id']);
        $voucher->is_active = !$voucher->is_active;
        $voucher->save();
        
        return $voucher->fresh();
    }

    /**
     * Admin: Bulk create vouchers
     */
    public function bulkCreate($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $input = $args['input'];
        $vouchers = [];
        
        for ($i = 0; $i < $input['count']; $i++) {
            $code = $input['prefix'] . $this->generateRandomCode($input['length']);
            
            // Ensure unique code
            while (Voucher::where('code', $code)->exists()) {
                $code = $input['prefix'] . $this->generateRandomCode($input['length']);
            }
            
            $voucher = Voucher::create([
                'name' => $input['prefix'] . ' Voucher ' . ($i + 1),
                'code' => $code,
                'discount_amount' => $input['discount_amount'],
                'discount_type' => $input['discount_type'],
                'min_purchase' => $input['min_purchase'],
                'max_discount' => $input['max_discount'] ?? null,
                'usage_limit' => $input['usage_limit'],
                'usage_limit_per_user' => $input['usage_limit_per_user'],
                'valid_from' => $input['valid_from'],
                'valid_until' => $input['valid_until'],
                'is_global' => $input['is_global'] ?? true,
            ]);
            
            $vouchers[] = $voucher;
        }
        
        return $vouchers;
    }

    /**
     * Generate random code
     */
    private function generateRandomCode(int $length): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Admin: Export voucher codes
     */
    public function exportCodes($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $voucher = Voucher::findOrFail($args['voucherId']);
        
        // For bulk vouchers, get all with same prefix
        $codes = Voucher::where('code', 'like', $voucher->code . '%')
            ->pluck('code')
            ->toArray();
        
        // Generate CSV
        $csv = "Code,Discount Type,Discount Amount,Valid From,Valid Until,Max Uses,Uses Count\n";
        foreach ($codes as $code) {
            $v = Voucher::where('code', $code)->first();
            $csv .= "{$code},{$v->discount_type},{$v->discount_amount},{$v->valid_from},{$v->valid_until},{$v->max_uses},{$v->uses_count}\n";
        }
        
        // Store CSV and return URL (simplified)
        $filename = 'vouchers_' . $voucher->code . '_' . date('Ymd_His') . '.csv';
        $path = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $csv);
        
        return [
            'voucher' => $voucher,
            'codes' => $codes,
            'csvUrl' => url('storage/exports/' . $filename),
            'exportedAt' => now(),
        ];
    }

    /**
     * Get applicable catalogs
     */
    public function applicableCatalogs(Voucher $voucher)
    {
        if (!$voucher->applicable_catalogs) {
            return [];
        }
        return Catalog::whereIn('id', $voucher->applicable_catalogs)->get();
    }

    /**
     * Get applicable packages
     */
    public function applicablePackages(Voucher $voucher)
    {
        if (!$voucher->applicable_packages) {
            return [];
        }
        return Package::whereIn('id', $voucher->applicable_packages)->get();
    }

    /**
     * Get applicable categories
     */
    public function applicableCategories(Voucher $voucher)
    {
        if (!$voucher->applicable_categories) {
            return [];
        }
        return Category::whereIn('id', $voucher->applicable_categories)->get();
    }

    /**
     * Get excluded catalogs
     */
    public function excludedCatalogs(Voucher $voucher)
    {
        if (!$voucher->excluded_catalogs) {
            return [];
        }
        return Catalog::whereIn('id', $voucher->excluded_catalogs)->get();
    }

    /**
     * Get excluded packages
     */
    public function excludedPackages(Voucher $voucher)
    {
        if (!$voucher->excluded_packages) {
            return [];
        }
        return Package::whereIn('id', $voucher->excluded_packages)->get();
    }

    /**
     * Get excluded categories
     */
    public function excludedCategories(Voucher $voucher)
    {
        if (!$voucher->excluded_categories) {
            return [];
        }
        return Category::whereIn('id', $voucher->excluded_categories)->get();
    }

    /**
     * Get users for voucher
     */
    public function users(Voucher $voucher)
    {
        if (!$voucher->user_ids) {
            return [];
        }
        return User::whereIn('id', $voucher->user_ids)->get();
    }
}
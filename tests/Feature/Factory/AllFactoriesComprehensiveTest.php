<?php

namespace Tests\Feature\Factory;

use App\Enums\DiscountType\DiscountType;
use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Models\Admin\Admin;
use App\Models\Cart\Cart;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Discount\Discount;
use App\Models\Help\Help;
use App\Models\History\History;
use App\Models\Home\HeroBanner;
use App\Models\Home\Promotion;
use App\Models\Order\Order;
use App\Models\Order\OrderPayment;
use App\Models\Order\OrderTimeline;
use App\Models\Order\RefundRequest;
use App\Models\Package\Package;
use App\Models\Review\Review;
use App\Models\Review\ReviewReport;
use App\Models\ReviewReply\ReviewReply;
use App\Models\Search\SearchLog;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Models\User\UserAddress;
use App\Models\Voucher\Voucher;
use App\Models\Wishlist\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllFactoriesComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_factory_creates_valid_admin(): void
    {
        $admin = Admin::factory()->create();
        $this->assertNotEmpty($admin->full_name);
        $this->assertStringContainsString('@', $admin->email);
        $this->assertNotEmpty($admin->username);
        $this->assertDatabaseHas('admins', ['id' => $admin->id]);

        $inactive = Admin::factory()->inactive()->create();
        $this->assertFalse($inactive->active_status);
    }

    public function test_user_factory_creates_valid_user(): void
    {
        $user = User::factory()->create();
        $this->assertNotEmpty($user->full_name);
        $this->assertStringContainsString('@', $user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $unverified = User::factory()->unverified()->create();
        $this->assertNull($unverified->email_verified_at);
    }

    public function test_category_factory_creates_valid_category(): void
    {
        $category = Category::factory()->create();
        $this->assertNotEmpty($category->name);
        $this->assertNotEmpty($category->slug);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $packageCat = Category::factory()->packageType()->create();
        $this->assertEquals('package', $packageCat->type);
    }

    public function test_catalog_factory_creates_valid_catalog(): void
    {
        $catalog = Catalog::factory()->create();
        $this->assertNotEmpty($catalog->name);
        $this->assertGreaterThan(0, $catalog->price);
        $this->assertDatabaseHas('catalogs', ['id' => $catalog->id]);

        $featured = Catalog::factory()->featured()->create();
        $this->assertTrue((bool) $featured->is_featured);
    }

    public function test_package_factory_creates_valid_package(): void
    {
        $package = Package::factory()->create();
        $this->assertNotEmpty($package->name);
        $this->assertGreaterThan(0, $package->price);
        $this->assertDatabaseHas('packages', ['id' => $package->id]);

        $gold = Package::factory()->gold()->create();
        $this->assertNotEmpty($gold->name);
    }

    public function test_discount_factory_creates_valid_discount(): void
    {
        $discount = Discount::factory()->create();
        $this->assertNotEmpty($discount->description);
        $this->assertGreaterThan(0, $discount->value);
        $this->assertDatabaseHas('discounts', ['id' => $discount->id]);

        $percentage = Discount::factory()->percentage(25)->create();
        $this->assertEquals(DiscountType::PERCENTAGE, $percentage->type);
        $this->assertEquals(25.0, (float) $percentage->value);

        $fixed = Discount::factory()->fixed(50000)->create();
        $this->assertEquals(DiscountType::FIXED, $fixed->type);
        $this->assertEquals(50000.0, (float) $fixed->value);
    }

    public function test_voucher_factory_creates_valid_voucher(): void
    {
        $voucher = Voucher::factory()->create();
        $this->assertNotEmpty($voucher->code);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);

        $global = Voucher::factory()->global()->create();
        $this->assertTrue((bool) $global->is_global);

        $expired = Voucher::factory()->expired()->create();
        $this->assertLessThan(now(), $expired->expires_at);
    }

    public function test_review_factory_creates_valid_review(): void
    {
        $review = Review::factory()->create();
        $this->assertGreaterThanOrEqual(1, $review->rating);
        $this->assertLessThanOrEqual(5, $review->rating);
        $this->assertNotEmpty($review->comment);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);

        $fiveStar = Review::factory()->fiveStar()->create();
        $this->assertEquals(5, $fiveStar->rating);
    }

    public function test_review_reply_factory_creates_valid_review_reply(): void
    {
        $reply = ReviewReply::factory()->create();
        $this->assertNotEmpty($reply->comment);
        $this->assertDatabaseHas('review_replies', ['id' => $reply->id]);
    }

    public function test_review_report_factory_creates_valid_review_report(): void
    {
        $report = ReviewReport::factory()->create();
        $this->assertNotEmpty($report->reason);
        $this->assertDatabaseHas('review_reports', ['id' => $report->id]);

        $reviewed = ReviewReport::factory()->reviewed()->create();
        $this->assertEquals('reviewed', $reviewed->status);
    }

    public function test_cart_factory_creates_valid_cart(): void
    {
        $cart = Cart::factory()->create();
        $this->assertGreaterThanOrEqual(1, $cart->quantity);
        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
    }

    public function test_wishlist_factory_creates_valid_wishlist(): void
    {
        $wishlist = Wishlist::factory()->create();
        $this->assertNotNull($wishlist->user_id);
        $this->assertDatabaseHas('wishlists', ['id' => $wishlist->id]);
    }

    public function test_transaction_factory_creates_valid_transaction(): void
    {
        $transaction = Transaction::factory()->create();
        $this->assertNotEmpty($transaction->reference_number);
        $this->assertGreaterThan(0, $transaction->amount);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);

        $success = Transaction::factory()->success()->create();
        $this->assertEquals(\App\Enums\PaymentStatus\PaymentStatus::SUCCESS, $success->status);
    }

    public function test_hero_banner_factory_creates_valid_hero_banner(): void
    {
        $banner = HeroBanner::factory()->create();
        $this->assertNotEmpty($banner->title);
        $this->assertDatabaseHas('hero_banners', ['id' => $banner->id]);

        $active = HeroBanner::factory()->active()->create();
        $this->assertTrue((bool) $active->is_active);
    }

    public function test_promotion_factory_creates_valid_promotion(): void
    {
        $promotion = Promotion::factory()->create();
        $this->assertNotEmpty($promotion->title);
        $this->assertDatabaseHas('promotions', ['id' => $promotion->id]);

        $bigSale = Promotion::factory()->bigSale(60)->create();
        $this->assertEquals(60, $bigSale->discount_percent);
    }

    public function test_help_factory_creates_valid_help(): void
    {
        $help = Help::factory()->create();
        $this->assertNotEmpty($help->title);
        $this->assertNotEmpty($help->faqs);
        $this->assertDatabaseHas('helps', ['id' => $help->id]);
    }

    public function test_history_factory_creates_valid_history(): void
    {
        $history = History::factory()->create();
        $this->assertNotEmpty($history->type);
        $this->assertNotEmpty($history->reference_number);
        $this->assertDatabaseHas('histories', ['id' => $history->id]);
    }

    public function test_user_address_factory_creates_valid_user_address(): void
    {
        $address = UserAddress::factory()->create();
        $this->assertNotEmpty($address->name);
        $this->assertNotEmpty($address->address);
        $this->assertDatabaseHas('user_addresses', ['id' => $address->id]);

        $default = UserAddress::factory()->default()->create();
        $this->assertTrue((bool) $default->is_default);
    }

    public function test_order_payment_factory_creates_valid_order_payment(): void
    {
        $payment = OrderPayment::factory()->create();
        $this->assertNotEmpty($payment->method);
        $this->assertGreaterThan(0, $payment->amount);
        $this->assertDatabaseHas('order_payments', ['id' => $payment->id]);

        $paid = OrderPayment::factory()->paid()->create();
        $this->assertEquals('paid', $paid->status);
    }

    public function test_order_timeline_factory_creates_valid_order_timeline(): void
    {
        $timeline = OrderTimeline::factory()->create();
        $this->assertNotEmpty($timeline->description);
        $this->assertNotEmpty($timeline->status);
        $this->assertDatabaseHas('order_timelines', ['id' => $timeline->id]);
    }

    public function test_refund_request_factory_creates_valid_refund_request(): void
    {
        $refund = RefundRequest::factory()->create();
        $this->assertNotEmpty($refund->reason);
        $this->assertGreaterThan(0, $refund->amount);
        $this->assertDatabaseHas('refund_requests', ['id' => $refund->id]);

        $approved = RefundRequest::factory()->approved()->create();
        $this->assertEquals('approved', $approved->status);
    }

    public function test_search_log_factory_creates_valid_search_log(): void
    {
        $log = SearchLog::factory()->create();
        $this->assertNotEmpty($log->query);
        $this->assertDatabaseHas('search_logs', ['id' => $log->id]);
    }
}

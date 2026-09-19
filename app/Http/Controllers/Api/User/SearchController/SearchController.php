<?php

namespace App\Http\Controllers\Api\User\SearchController;

use App\Http\Controllers\Controller;
use App\Models\Category\Category;
use App\Models\Help\Help;
use App\Models\History\History;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\Product\Product;
use App\Models\Review\Review;
use App\Models\TermsOfService\TermsOfService;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Models\Vendor\Vendor;
use App\Models\Voucher\Voucher;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use App\Services\CBIRService\CBIRService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function byText(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $locale = app()->getLocale();
        $query = $request->input('query');

        $authUser = $request->user();

        $packages = Package::with(['media', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->get();

        $products = Product::with(['media', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->get()
            ->map(function ($p) use ($locale) {
                $p->name = $p->trans('name', $locale);
                $p->description = $p->trans('description', $locale);

                return $p;
            });

        $categories = Category::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        })->get()->map(function ($c) use ($locale) {
            $c->name = $c->trans('name', $locale);
            $c->description = $c->trans('description', $locale);

            return $c;
        });

        $vouchers = Voucher::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })->get()->map(function ($v) use ($locale) {
                $v->description = $v->trans('description', $locale);

                return $v;
            });

        $orders = $authUser
            ? Order::where('user_id', $authUser->id)
                ->where(function ($q) use ($query) {
                    $q->where('order_number', 'like', "%{$query}%")
                        ->orWhere('notes', 'like', "%{$query}%");
                })
                ->with('package:id,name,name_translations')
                ->get()
            : collect();

        $reviews = $authUser
            ? Review::where('user_id', $authUser->id)
                ->where('comment', 'like', "%{$query}%")
                ->with('package:id,name,name_translations')
                ->get()
            : collect();

        $terms = TermsOfService::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })->get()->map(fn ($t) => [
            '_type' => 'terms',
            'id' => $t->id,
            'name' => is_string($t->trans('content', $locale))
                ? substr($t->trans('content', $locale), 0, 120)
                : (is_array($t->trans('content', $locale)) ? ($t->trans('content', $locale)['text'] ?? $t->trans('title', $locale)) : $t->trans('title', $locale)),
            'title' => $t->trans('title', $locale),
        ]);

        $privacy = PrivacyPolicy::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })->get()->map(fn ($p) => [
            '_type' => 'privacy',
            'id' => $p->id,
            'name' => is_string($p->trans('content', $locale))
                ? substr($p->trans('content', $locale), 0, 120)
                : (is_array($p->trans('content', $locale)) ? ($p->trans('content', $locale)['text'] ?? $p->trans('title', $locale)) : $p->trans('title', $locale)),
            'title' => $p->trans('title', $locale),
        ]);

        $helps = Help::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('subtitle', 'like', "%{$query}%");
        })->get()->map(fn ($h) => [
            '_type' => 'helps',
            'id' => $h->id,
            'name' => $h->trans('subtitle', $locale) ?? $h->trans('title', $locale),
            'title' => $h->trans('title', $locale),
        ]);

        $weddingPolicy = WeddingDecorationPolicy::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })->get()->map(fn ($w) => [
            '_type' => 'wedding_policy',
            'id' => $w->id,
            'name' => is_string($w->trans('content', $locale))
                ? substr($w->trans('content', $locale), 0, 120)
                : (is_array($w->trans('content', $locale)) ? ($w->trans('content', $locale)['text'] ?? $w->trans('title', $locale)) : $w->trans('title', $locale)),
            'title' => $w->trans('title', $locale),
        ]);

        $histories = $authUser
            ? History::where('user_id', $authUser->id)
                ->where(function ($q) use ($query) {
                    $q->where('reference_number', 'like', "%{$query}%")
                        ->orWhere('type', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%")
                        ->orWhere('notes', 'like', "%{$query}%")
                        ->orWhere('info', 'like', "%{$query}%");
                })
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
            : collect();

        $vendors = Vendor::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('store_name', 'like', "%{$query}%")
                    ->orWhere('store_description', 'like', "%{$query}%");
            })
            ->where(fn ($q) => $q->has('packages')->orHas('products'))
            ->withCount(['packages', 'products'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'packages' => $packages,
                'products' => $products,
                'categories' => $categories,
                'vouchers' => $vouchers,
                'orders' => $orders,
                'reviews' => $reviews,
                'terms' => $terms,
                'privacy' => $privacy,
                'helps' => $helps,
                'wedding_policy' => $weddingPolicy,
                'histories' => $histories,
                'vendors' => $vendors,
            ],
        ]);
    }

    public function byTextAdmin(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $locale = app()->getLocale();
        $query = $request->input('query');

        $packages = Package::with(['media', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->get();

        $products = Product::with(['media', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->get()
            ->map(function ($p) use ($locale) {
                $p->name = $p->trans('name', $locale);
                $p->description = $p->trans('description', $locale);

                return $p;
            });

        $categories = Category::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        })->get()->map(function ($c) use ($locale) {
            $c->name = $c->trans('name', $locale);
            $c->description = $c->trans('description', $locale);

            return $c;
        });

        $vouchers = Voucher::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })->get()->map(function ($v) use ($locale) {
                $v->description = $v->trans('description', $locale);

                return $v;
            });

        $orders = Order::where(function ($q) use ($query) {
            $q->where('order_number', 'like', "%{$query}%")
                ->orWhere('notes', 'like', "%{$query}%");
        })
            ->with(['package:id,name,name_translations', 'user:id,name,email'])
            ->get();

        $reviews = Review::where('comment', 'like', "%{$query}%")
            ->with(['package:id,name,name_translations', 'user:id,name'])
            ->get();

        $terms = TermsOfService::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })->get()->map(fn ($t) => [
            '_type' => 'terms',
            'id' => $t->id,
            'name' => is_string($t->trans('content', $locale))
                ? substr($t->trans('content', $locale), 0, 120)
                : (is_array($t->trans('content', $locale)) ? ($t->trans('content', $locale)['text'] ?? $t->trans('title', $locale)) : $t->trans('title', $locale)),
            'title' => $t->trans('title', $locale),
        ]);

        $privacy = PrivacyPolicy::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })->get()->map(fn ($p) => [
            '_type' => 'privacy',
            'id' => $p->id,
            'name' => is_string($p->trans('content', $locale))
                ? substr($p->trans('content', $locale), 0, 120)
                : (is_array($p->trans('content', $locale)) ? ($p->trans('content', $locale)['text'] ?? $p->trans('title', $locale)) : $p->trans('title', $locale)),
            'title' => $p->trans('title', $locale),
        ]);

        $helps = Help::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('subtitle', 'like', "%{$query}%");
        })->get()->map(fn ($h) => [
            '_type' => 'helps',
            'id' => $h->id,
            'name' => $h->trans('subtitle', $locale) ?? $h->trans('title', $locale),
            'title' => $h->trans('title', $locale),
        ]);

        $weddingPolicy = WeddingDecorationPolicy::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%");
        })->get()->map(fn ($w) => [
            '_type' => 'wedding_policy',
            'id' => $w->id,
            'name' => is_string($w->trans('content', $locale))
                ? substr($w->trans('content', $locale), 0, 120)
                : (is_array($w->trans('content', $locale)) ? ($w->trans('content', $locale)['text'] ?? $w->trans('title', $locale)) : $w->trans('title', $locale)),
            'title' => $w->trans('title', $locale),
        ]);

        $histories = History::where(function ($q) use ($query) {
            $q->where('reference_number', 'like', "%{$query}%")
                ->orWhere('type', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhere('notes', 'like', "%{$query}%")
                ->orWhere('info', 'like', "%{$query}%");
        })
            ->orderBy('created_at', 'desc')
            ->get();

        $users = User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->orWhere('username', 'like', "%{$query}%");
        })
            ->with('roles')
            ->get()
            ->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->full_name,
            'email' => $u->email,
            'phone' => $u->phone,
            'avatar_url' => $u->avatar_url,
            'role' => $u->roles()->pluck('name'),
            'is_active' => $u->is_active,
        ]);

        $transactions = Transaction::where(function ($q) use ($query) {
            $q->where('reference_number', 'like', "%{$query}%")
                ->orWhere('payment_method', 'like', "%{$query}%")
                ->orWhere('payment_gateway', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%");
        })
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
            'id' => $t->id,
            'reference_number' => $t->reference_number,
            'transaction_id' => $t->reference_number,
            'amount' => $t->total_amount ?? $t->amount,
            'status' => $t->status,
            'payment_method' => $t->payment_method,
            'payment_gateway' => $t->payment_gateway,
            'type' => $t->type,
            'user_id' => $t->user_id,
            'order_id' => $t->order_id,
            'created_at' => $t->created_at,
            'user' => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email] : null,
        ]);

        $vendors = Vendor::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('store_name', 'like', "%{$query}%")
                    ->orWhere('store_description', 'like', "%{$query}%");
            })
            ->where(fn ($q) => $q->has('packages')->orHas('products'))
            ->withCount(['packages', 'products'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'packages' => $packages,
                'products' => $products,
                'categories' => $categories,
                'vouchers' => $vouchers,
                'orders' => $orders,
                'reviews' => $reviews,
                'terms' => $terms,
                'privacy' => $privacy,
                'helps' => $helps,
                'wedding_policy' => $weddingPolicy,
                'histories' => $histories,
                'users' => $users,
                'transactions' => $transactions,
                'vendors' => $vendors,
            ],
        ]);
    }

    public function byImage(Request $request, CBIRService $cbirService)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,bmp,webp,mp4,mov,m4v,webm,3gp|max:51200',
        ]);

        $apiResponse = $cbirService->searchByImage($request->file('image'));

        if (isset($apiResponse['error']) || ! ($apiResponse['success'] ?? false)) {
            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => $apiResponse['message'] ?? __('Pencarian gambar gagal.'),
            ]);
        }

        $results = $apiResponse['results'] ?? [];
        $mixedResults = [];
        $seen = [];

        foreach ($results as $r) {
            $type = $r['type'] ?? 'product';
            $id = $r['owner_id'] ?? null;

            if (! $id) {
                continue;
            }

            $key = "{$type}_{$id}";
            if (isset($seen[$key])) {
                continue;
            }

            $model = $type === 'package'
                ? Package::with(['category', 'media'])->find($id)
                : Product::with(['category', 'media'])->find($id);

            if (! $model) {
                continue;
            }

            $mixedResults[] = [
                'type' => $type,
                'similarity' => max(0, (float) ($r['similarity'] ?? 0)),
                'score' => max(0, (float) ($r['score'] ?? 0)),
                'data' => array_merge($model->toArray(), [
                    'image_url' => $model->image_url,
                    'category' => $model->category?->toArray(),
                    'average_rating' => (float) number_format($model->reviews()->avg('rating') ?: 0, 1),
                ]),
            ];

            $seen[$key] = true;
        }

        usort($mixedResults, fn ($a, $b) => ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0));

        $minSimilarity = (float) config('services.cbir_min_similarity', 30.0);
        $mixedResults = array_values(array_filter($mixedResults, fn ($r) => ($r['similarity'] ?? 0) >= $minSimilarity));

        return response()->json([
            'status' => 'success',
            'data' => $mixedResults,
        ]);
    }
}

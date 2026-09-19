<?php

namespace App\Http\Controllers\Api\User\FirebaseController;

use App\Http\Controllers\Controller;

use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Services\FirebaseService\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirebaseController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Check Firebase connection status
     */
    public function status(): JsonResponse
    {
        try {
            $isConnected = $this->firebaseService->isConnected();

            return response()->json([
                'status' => $isConnected ? 'connected' : 'disconnected',
                'connected' => $isConnected,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] status error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Terjadi kesalahan server'),
            ], 500);
        }
    }

    /**
     * Read data from Firebase
     */
    public function read(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'cache' => 'boolean',
            ]);

            $path = $request->input('path');
            $useCache = $request->boolean('cache', true);

            $data = $useCache
                ? $this->firebaseService->read($path)
                : $this->firebaseService->readDirect($path);

            return response()->json([
                'success' => true,
                'path' => $path,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] read error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal membaca data'),
            ], 400);
        }
    }

    /**
     * Write data to Firebase
     */
    public function write(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'data' => 'required|array',
            ]);

            $path = $request->input('path');
            $data = $request->input('data');

            $this->firebaseService->write($path, $data);

            return response()->json([
                'success' => true,
                'path' => $path,
                'message' => 'Data written successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] write error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal menulis data'),
            ], 400);
        }
    }

    /**
     * Update data in Firebase
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'data' => 'required|array',
            ]);

            $path = $request->input('path');
            $data = $request->input('data');

            $this->firebaseService->update($path, $data);

            return response()->json([
                'success' => true,
                'path' => $path,
                'message' => 'Data updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal memperbarui data'),
            ], 400);
        }
    }

    /**
     * Delete data from Firebase
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
            ]);

            $path = $request->input('path');

            $this->firebaseService->delete($path);

            return response()->json([
                'success' => true,
                'path' => $path,
                'message' => 'Data deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal menghapus data'),
            ], 400);
        }
    }

    /**
     * Push new data to Firebase (creates child)
     */
    public function push(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'data' => 'required|array',
            ]);

            $path = $request->input('path');
            $data = $request->input('data');

            $newKey = $this->firebaseService->push($path, $data);

            return response()->json([
                'success' => true,
                'path' => $path,
                'key' => $newKey,
                'message' => 'Data pushed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] push error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal push data'),
            ], 400);
        }
    }

    /**
     * Get all children at a path
     */
    public function children(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
            ]);

            $path = $request->input('path');
            $children = $this->firebaseService->getChildren($path);

            return response()->json([
                'success' => true,
                'path' => $path,
                'count' => count($children),
                'children' => $children,
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] children error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal mengambil data'),
            ], 400);
        }
    }

    /**
     * Check if path exists
     */
    public function exists(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'required|string',
            ]);

            $path = $request->input('path');
            $exists = $this->firebaseService->exists($path);

            return response()->json([
                'success' => true,
                'path' => $path,
                'exists' => $exists,
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] exists error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal memeriksa data'),
            ], 400);
        }
    }

    /**
     * Sync order data to Firebase
     */
    public function syncOrder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
            ]);

            $orderId = $request->input('order_id');
            $order = Order::findOrFail($orderId);

            $orderData = [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'status' => $order->status,
                'total_price' => $order->total_price,
                'created_at' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
            ];

            $this->firebaseService->write("orders/{$orderId}", $orderData);

            return response()->json([
                'success' => true,
                'message' => 'Order synced to Firebase',
                'order_id' => $orderId,
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] syncOrder error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal sync pesanan'),
            ], 400);
        }
    }

    /**
     * Sync message to Firebase
     */
    public function syncMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'message_id' => 'required|integer|exists:messages,id',
            ]);

            $messageId = $request->input('message_id');
            $message = Message::findOrFail($messageId);

            $messageData = [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'recipient_id' => $message->recipient_id,
                'body' => $message->body,
                'read_at' => $message->read_at?->toIso8601String(),
                'created_at' => $message->created_at->toIso8601String(),
            ];

            $this->firebaseService->write("messages/{$messageId}", $messageData);

            return response()->json([
                'success' => true,
                'message' => 'Message synced to Firebase',
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] syncMessage error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal sync pesan'),
            ], 400);
        }
    }

    /**
     * Clear Firebase cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
            ]);

            $path = $request->input('path');

            if ($path) {
                $this->firebaseService->clearCache($path);
                $message = "Cache cleared for path: {$path}";
            } else {
                Cache::flush();
                $message = 'All cache cleared';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('[Firebase] clearCache error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Gagal menghapus cache'),
            ], 400);
        }
    }
}

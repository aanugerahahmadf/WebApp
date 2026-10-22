<?php

namespace App\Grpc\Services;

use App\Models\Cbir\CbirSearch;
use App\Models\Cbir\CbirSearchResult;
use App\Models\Cbir\CbirFeedback;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use Grpc\ServerCall;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;

class CbirService
{
    public function Search(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $imageData = $request->getImageData();
        $limit = $request->getLimit() > 0 ? $request->getLimit() : 10;
        $minSimilarity = $request->getMinSimilarity();
        $rerank = $request->getRerank();
        
        if (!$imageData) {
            $response = new \Weddingapp\Cbir\CbirSearchResponse();
            $response->setTotalResults(0);
            $response->setSearchTime(0);
            return $call->sendResponse($response);
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'cbir_') . '.jpg';
        file_put_contents($tempFile, $imageData);
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $http = Http::timeout(30);
            $responseObj = $http->attach('image', $imageData, 'search.jpg')
                ->post("{$aiCoreUrl}/api/search", [
                    'limit' => $limit,
                    'min_similarity' => $minSimilarity,
                    'rerank' => $rerank,
                ]);
            
            $data = $responseObj->json();
            
            $resultsProto = [];
            if (isset($data['results']) && is_array($data['results'])) {
                foreach ($data['results'] as $result) {
                    $proto = new \Weddingapp\Cbir\CbirResult();
                    $proto->setType($result['type'] ?? '');
                    $proto->setSimilarity($result['similarity'] ?? 0.0);
                    $proto->setScore($result['score'] ?? 0.0);
                    
                    $dataProto = new \Weddingapp\Cbir\CbirResultData();
                    $dataProto->setId($result['id'] ?? 0);
                    $dataProto->setName($result['name'] ?? '');
                    $dataProto->setSlug($result['slug'] ?? '');
                    $dataProto->setDescription($result['description'] ?? '');
                    $dataProto->setPrice($result['price'] ?? 0.0);
                    $dataProto->setDiscountPrice($result['discount_price'] ?? 0.0);
                    $dataProto->setImageUrl($result['image_url'] ?? '');
                    $dataProto->setCategory($result['category'] ?? '');
                    
                    if (isset($result['category_id']) && $result['category_id']) {
                        $category = Category::find($result['category_id']);
                        if ($category) {
                            $catProto = new \Weddingapp\Cbir\Category();
                            $catProto->setId($category->id);
                            $catProto->setName($category->name);
                            $catProto->setSlug($category->slug);
                            $catProto->setImageUrl($category->image_url ?? '');
                            $dataProto->setCategoryObj($catProto);
                        }
                    }
                    
                    $isAvailable = false;
                    if ($result['type'] === 'catalog') {
                        $catalog = Catalog::find($result['id']);
                        $isAvailable = $catalog && $catalog->is_active && $catalog->stock > 0;
                    } elseif ($result['type'] === 'package') {
                        $package = Package::find($result['id']);
                        $isAvailable = $package && $package->is_active;
                    }
                    $dataProto->setIsAvailable($isAvailable);
                    
                    $proto->setData($dataProto);
                    $resultsProto[] = $proto;
                }
            }
            
            $response = new \Weddingapp\Cbir\CbirSearchResponse();
            foreach ($resultsProto as $result) {
                $response->addResults($result);
            }
            $response->setSearchTime($data['query_time_seconds'] ?? 0.0);
            $response->setTotalResults(count($resultsProto));
            
            return $call->sendResponse($response);
        } catch (\Exception $e) {
            $response = new \Weddingapp\Cbir\CbirSearchResponse();
            $response->setError($e->getMessage());
            $response->setTotalResults(0);
            $response->setSearchTime(0);
            return $call->sendResponse($response);
        } finally {
            @unlink($tempFile);
        }
    }

    public function GetStats(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $responseObj = Http::timeout(10)->get("{$aiCoreUrl}/status");
            $data = $responseObj->json();
            
            $response = new \Weddingapp\Cbir\CbirStatsResponse();
            $response->setTotalImages($data['total_images'] ?? 0);
            $response->setTotalFeatures($data['total_features'] ?? 0);
            $response->setDatabaseSize($data['database_size'] ?? '');
            $response->setLastUpdated($data['last_updated'] ?? '');
            $response->setStatus($data['status'] ?? 'unknown');
            $response->setMessage($data['message'] ?? '');
            
            return $call->sendResponse($response);
        } catch (\Exception $e) {
            $response = new \Weddingapp\Cbir\CbirStatsResponse();
            $response->setStatus('error');
            $response->setMessage('Failed to connect to AI Core');
            return $call->sendResponse($response);
        }
    }

    public function AddToIndex(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Cbir\AddToIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $start = microtime(true);
            
            $responseObj = Http::timeout(60)->post("{$aiCoreUrl}/api/index/add", [
                'media_id' => $request->getMediaId(),
                'image_path' => $request->getImagePath(),
                'metadata' => $request->getMetadata(),
            ]);
            
            $elapsed = microtime(true) - $start;
            
            if ($responseObj->successful()) {
                $data = $responseObj->json();
                
                $response = new \Weddingapp\Cbir\AddToIndexResponse();
                $response->setSuccess(true);
                $response->setMessage($data['message'] ?? 'Image added to index');
                $response->setEntryId($data['entry_id'] ?? 0);
                
                return $call->sendResponse($response);
            }
            
            $response = new \Weddingapp\Cbir\AddToIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Failed to add image to index');
            
            return $call->sendResponse($response);
        } catch (\Exception $e) {
            $response = new \Weddingapp\Cbir\AddToIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Error: ' . $e->getMessage());
            return $call->sendResponse($response);
        }
    }

    public function RemoveFromIndex(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Cbir\RemoveFromIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $start = microtime(true);
            
            $responseObj = Http::timeout(30)->post("{$aiCoreUrl}/api/index/remove", [
                'metadata_id' => $request->getMediaId(),
            ]);
            
            $elapsed = microtime(true) - $start;
            
            if ($responseObj->successful()) {
                $data = $responseObj->json();
                
                $response = new \Weddingapp\Cbir\RemoveFromIndexResponse();
                $response->setSuccess(true);
                $response->setMessage($data['message'] ?? 'Image removed from index');
                $response->setRemoved($data['removed'] ?? 0);
                
                return $call->sendResponse($response);
            }
            
            $response = new \Weddingapp\Cbir\RemoveFromIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Failed to remove image from index');
            
            return $call->sendResponse($response);
        } catch (\Exception $e) {
            $response = new \Weddingapp\Cbir\RemoveFromIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Error: ' . $e->getMessage());
            return $call->sendResponse($response);
        }
    }

    public function GetHistory(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $perPage = $request->getPerPage() ?: 20;
        $page = $request->getPage() ?: 1;
        
        $paginated = CbirSearch::where('user_id', $user->id)
            ->with(['results.item'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Cbir\CbirHistoryResponse();
        foreach ($paginated->items() as $search) {
            $historyProto = new \Weddingapp\Cbir\CbirSearchHistory();
            $historyProto->setId($search->id);
            $historyProto->setUserId($search->user_id);
            $historyProto->setQueryImageUrl($search->query_image_url);
            $historyProto->setResultsCount($search->results_count);
            $historyProto->setTopSimilarity($search->top_similarity);
            $historyProto->setCreatedAt($search->created_at?->toISOString() ?? '');
            
            foreach ($search->results as $result) {
                $resultProto = new \Weddingapp\Cbir\CbirHistoryResult();
                $resultProto->setType($result->type);
                $resultProto->setItemId($result->item_id);
                $resultProto->setSimilarity($result->similarity);
                $resultProto->setScore($result->score);
                
                if ($result->item) {
                    $itemData = new \Weddingapp\Cbir\CbirResultData();
                    $itemData->setId($result->item->id);
                    $itemData->setName($result->item->name);
                    $itemData->setSlug($result->item->slug);
                    $itemData->setImageUrl($result->item->image_url ?? '');
                    $resultProto->setItem($itemData);
                }
                
                $historyProto->addResults($resultProto);
            }
            
            $response->addHistory($historyProto);
        }
        
        $pagination = new \Weddingapp\Cbir\PaginationMeta();
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

    public function SaveSearch(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $search = CbirSearch::create([
            'user_id' => $user->id,
            'query_image_url' => $request->getQueryImageUrl(),
            'results_count' => count($request->getResults()),
            'top_similarity' => $this->getTopSimilarity($request->getResults()),
        ]);
        
        foreach ($request->getResults() as $result) {
            CbirSearchResult::create([
                'search_id' => $search->id,
                'type' => $result->getType(),
                'item_id' => $result->getItemId(),
                'similarity' => $result->getSimilarity(),
                'score' => $result->getScore(),
            ]);
        }
        
        $historyProto = new \Weddingapp\Cbir\CbirSearchHistory();
        $historyProto->setId($search->id);
        $historyProto->setUserId($search->user_id);
        $historyProto->setQueryImageUrl($search->query_image_url);
        $historyProto->setResultsCount($search->results_count);
        $historyProto->setTopSimilarity($search->top_similarity);
        $historyProto->setCreatedAt($search->created_at?->toISOString() ?? '');
        
        $response = new \Weddingapp\Cbir\CbirSearchHistoryResponse();
        $response->setHistory($historyProto);
        
        return $call->sendResponse($response);
    }

    public function DeleteSearch(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $search = CbirSearch::where('id', $request->getSearchId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$search) {
            $response = new \Weddingapp\Cbir\DeleteSearchResponse();
            $response->setSuccess(false);
            $response->setMessage('Search not found');
            return $call->sendResponse($response);
        }
        
        $search->delete();
        
        $response = new \Weddingapp\Cbir\DeleteSearchResponse();
        $response->setSuccess(true);
        $response->setMessage('Search deleted');
        
        return $call->sendResponse($response);
    }

    public function ClearHistory(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        CbirSearch::where('user_id', $user->id)->delete();
        
        $response = new \Weddingapp\Cbir\ClearHistoryResponse();
        $response->setSuccess(true);
        $response->setMessage('History cleared');
        
        return $call->sendResponse($response);
    }

    public function SubmitFeedback(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        CbirFeedback::create([
            'user_id' => $user->id,
            'search_id' => $request->getSearchId(),
            'item_id' => $request->getItemId(),
            'item_type' => $request->getItemType(),
            'is_relevant' => $request->getIsRelevant(),
            'feedback' => $request->getFeedback() ?: null,
        ]);
        
        $response = new \Weddingapp\Cbir\SubmitFeedbackResponse();
        $response->setSuccess(true);
        $response->setMessage('Feedback submitted');
        
        return $call->sendResponse($response);
    }

    public function RebuildIndex(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Cbir\CbirIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $start = microtime(true);
            
            $responseObj = Http::timeout(300)->post("{$aiCoreUrl}/api/index/rebuild-from-dataset", [
                'app_url' => config('app.url'),
            ]);
            
            $elapsed = microtime(true) - $start;
            
            if ($responseObj->successful()) {
                $data = $responseObj->json();
                
                $response = new \Weddingapp\Cbir\CbirIndexResponse();
                $response->setSuccess(true);
                $response->setMessage($data['message'] ?? 'Index rebuilt successfully');
                $response->setAffectedCount($data['total'] ?? 0);
                $response->setProcessingTime(round($elapsed, 2));
                
                return $call->sendResponse($response);
            }
            
            $response = new \Weddingapp\Cbir\CbirIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Failed to rebuild index');
            $response->setProcessingTime(round($elapsed, 2));
            
            return $call->sendResponse($response);
        } catch (\Exception $e) {
            $response = new \Weddingapp\Cbir\CbirIndexResponse();
            $response->setSuccess(false);
            $response->setMessage('Error: ' . $e->getMessage());
            return $call->sendResponse($response);
        }
    }

    protected function getUserFromToken(string $token): \App\Models\User\User
    {
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            throw new \Exception('Unauthorized: Invalid token');
        }
        return $accessToken->tokenable;
    }

    protected function getTopSimilarity($results): float
    {
        $max = 0;
        foreach ($results as $result) {
            if ($result->getSimilarity() > $max) {
                $max = $result->getSimilarity();
            }
        }
        return $max;
    }
}
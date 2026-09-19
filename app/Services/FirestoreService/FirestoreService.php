<?php

namespace App\Services\FirestoreService;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Log;

class FirestoreService
{
    protected FirestoreClient $firestore;

    public function __construct()
    {
        // Gunakan gRPC transport untuk performa lebih baik
        // Fallback ke REST jika gRPC tidak tersedia
        $config = [
            'projectId' => config('firebase.project_id'),
            'keyFilePath' => config('firebase.credentials'),
        ];

        // Gunakan gRPC jika tersedia, fallback ke REST
        if (extension_loaded('grpc')) {
            $config['transport'] = 'grpc';
        } else {
            $config['transport'] = 'rest';
        }

        $this->firestore = new FirestoreClient($config);
    }

    /**
     * Get Firestore client instance
     */
    public function client(): FirestoreClient
    {
        return $this->firestore;
    }

    /**
     * Get a collection reference
     */
    public function collection(string $collectionName)
    {
        return $this->firestore->collection($collectionName);
    }

    /**
     * Get a document
     */
    public function getDocument(string $collectionName, string $documentId)
    {
        try {
            $docRef = $this->firestore->collection($collectionName)->document($documentId);
            $snapshot = $docRef->snapshot();

            if ($snapshot->exists()) {
                return $snapshot->data();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Firestore getDocument error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create or update a document
     */
    public function setDocument(string $collectionName, string $documentId, array $data)
    {
        try {
            $docRef = $this->firestore->collection($collectionName)->document($documentId);
            $docRef->set($data);

            return true;
        } catch (\Exception $e) {
            Log::error('Firestore setDocument error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Add a document with auto-generated ID
     */
    public function addDocument(string $collectionName, array $data)
    {
        try {
            $docRef = $this->firestore->collection($collectionName)->add($data);

            return $docRef->id();
        } catch (\Exception $e) {
            Log::error('Firestore addDocument error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a document
     */
    public function updateDocument(string $collectionName, string $documentId, array $data)
    {
        try {
            $docRef = $this->firestore->collection($collectionName)->document($documentId);
            $docRef->update($data);

            return true;
        } catch (\Exception $e) {
            Log::error('Firestore updateDocument error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a document
     */
    public function deleteDocument(string $collectionName, string $documentId)
    {
        try {
            $docRef = $this->firestore->collection($collectionName)->document($documentId);
            $docRef->delete();

            return true;
        } catch (\Exception $e) {
            Log::error('Firestore deleteDocument error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Query documents
     */
    public function queryDocuments(string $collectionName, array $filters = [], ?int $limit = null)
    {
        try {
            $query = $this->firestore->collection($collectionName);

            // Apply filters
            foreach ($filters as $filter) {
                if (count($filter) === 3) {
                    [$field, $operator, $value] = $filter;
                    $query = $query->where($field, $operator, $value);
                }
            }

            // Apply limit
            if ($limit) {
                $query = $query->limit($limit);
            }

            $documents = $query->documents();
            $results = [];

            foreach ($documents as $document) {
                if ($document->exists()) {
                    $results[] = [
                        'id' => $document->id(),
                        'data' => $document->data(),
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Firestore queryDocuments error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all documents in a collection
     */
    public function getAllDocuments(string $collectionName)
    {
        return $this->queryDocuments($collectionName);
    }
}

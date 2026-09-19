<?php

namespace Tests\Feature;

use App\Services\FirestoreService\FirestoreService;
use Tests\TestCase;

class FirestoreServiceTest extends TestCase
{
    protected FirestoreService $firestoreService;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip test jika credentials tidak ada
        if (! file_exists(config('firebase.credentials'))) {
            $this->markTestSkipped('Firebase credentials file not found');
        }

        $this->firestoreService = new FirestoreService;
    }

    public function test_can_connect_to_firestore()
    {
        $this->assertInstanceOf(FirestoreService::class, $this->firestoreService);
    }

    public function test_can_add_and_get_document()
    {
        $testCollection = 'test_collection';
        $testData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now()->toIso8601String(),
        ];

        // Add document
        $documentId = $this->firestoreService->addDocument($testCollection, $testData);
        $this->assertNotEmpty($documentId);

        // Get document
        $retrievedData = $this->firestoreService->getDocument($testCollection, $documentId);
        $this->assertNotNull($retrievedData);
        $this->assertEquals($testData['name'], $retrievedData['name']);
        $this->assertEquals($testData['email'], $retrievedData['email']);

        // Cleanup
        $this->firestoreService->deleteDocument($testCollection, $documentId);
    }

    public function test_can_update_document()
    {
        $testCollection = 'test_collection';
        $testData = [
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ];

        // Add document
        $documentId = $this->firestoreService->addDocument($testCollection, $testData);

        // Update document
        $updateData = [
            ['path' => 'name', 'value' => 'Updated Name'],
        ];
        $this->firestoreService->updateDocument($testCollection, $documentId, $updateData);

        // Verify update
        $retrievedData = $this->firestoreService->getDocument($testCollection, $documentId);
        $this->assertEquals('Updated Name', $retrievedData['name']);

        // Cleanup
        $this->firestoreService->deleteDocument($testCollection, $documentId);
    }

    public function test_can_query_documents()
    {
        $testCollection = 'test_collection';

        // Add multiple documents
        $doc1Id = $this->firestoreService->addDocument($testCollection, [
            'name' => 'User 1',
            'age' => 25,
        ]);

        $doc2Id = $this->firestoreService->addDocument($testCollection, [
            'name' => 'User 2',
            'age' => 30,
        ]);

        // Query documents
        $results = $this->firestoreService->queryDocuments($testCollection, [
            ['age', '>=', 25],
        ]);

        $this->assertCount(2, $results);

        // Cleanup
        $this->firestoreService->deleteDocument($testCollection, $doc1Id);
        $this->firestoreService->deleteDocument($testCollection, $doc2Id);
    }
}

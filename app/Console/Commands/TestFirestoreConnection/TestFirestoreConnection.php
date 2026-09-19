<?php

namespace App\Console\Commands\TestFirestoreConnection;

use App\Services\FirestoreService\FirestoreService;
use Illuminate\Console\Command;

class TestFirestoreConnection extends Command
{
    protected $signature = 'firestore:test';

    protected $description = 'Test Firestore connection using REST transport';

    public function handle()
    {
        $this->info('Testing Firestore connection...');

        // Check if gRPC is loaded
        if (extension_loaded('grpc')) {
            $this->info('✓ Using gRPC transport (faster)');
        } else {
            $this->warn('⚠ Using REST transport (gRPC not available)');
        }

        $this->newLine();

        try {
            // Check credentials file
            $credentialsPath = config('firebase.credentials');
            if (! file_exists($credentialsPath)) {
                $this->error("Firebase credentials file not found at: {$credentialsPath}");

                return Command::FAILURE;
            }

            $this->info('✓ Credentials file found');

            // Initialize Firestore service
            $firestore = new FirestoreService;
            $this->info('✓ Firestore service initialized');

            // Test: Create a test document
            $testCollection = 'connection_test';
            $testData = [
                'message' => 'Test from Laravel',
                'timestamp' => now()->toIso8601String(),
                'transport' => extension_loaded('grpc') ? 'gRPC' : 'REST',
                'php_version' => PHP_VERSION,
            ];

            $this->info('Creating test document...');
            $documentId = $firestore->addDocument($testCollection, $testData);
            $this->info("✓ Document created with ID: {$documentId}");

            // Test: Read the document
            $this->info('Reading test document...');
            $retrievedData = $firestore->getDocument($testCollection, $documentId);

            if ($retrievedData) {
                $this->info('✓ Document retrieved successfully');
                $this->table(
                    ['Field', 'Value'],
                    collect($retrievedData)->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value])
                );
            }

            // Test: Update the document
            $this->info('Updating test document...');
            $firestore->updateDocument($testCollection, $documentId, [
                ['path' => 'message', 'value' => 'Updated from Laravel'],
                ['path' => 'updated_at', 'value' => now()->toIso8601String()],
            ]);
            $this->info('✓ Document updated');

            // Test: Delete the document
            $this->info('Deleting test document...');
            $firestore->deleteDocument($testCollection, $documentId);
            $this->info('✓ Document deleted');

            $this->newLine();
            $this->info('🎉 All Firestore operations successful!');
            $transportType = extension_loaded('grpc') ? 'gRPC' : 'REST';
            $this->info("Firestore is working correctly with {$transportType} transport.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Firestore test failed:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Stack trace:');
            $this->line($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}

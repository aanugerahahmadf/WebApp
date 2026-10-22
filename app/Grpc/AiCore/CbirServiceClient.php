<?php

namespace App\Grpc\AiCore;

use Grpc\BaseStub;

/**
 * Klien gRPC untuk AI Core (Python, `src/grpc/server.py`).
 *
 * Kenapa file ini ditulis tangan, bukan hasil `protoc --grpc_out`:
 * plugin `grpc_php_plugin` tidak terpasang di mesin ini (dan tidak dibutuhkan
 * untuk sekadar membuat stub). Class message-nya tetap hasil generate protoc
 * dari `proto/aicore/cbir.proto`, yang merupakan SALINAN proto server Python.
 *
 * Nama method di path HARUS `weddingapp.cbir.CbirService/...` — itu yang
 * didaftarkan `add_CbirServiceServicer_to_server()` di sisi Python. Mengubah
 * package di proto akan memutus panggilan tanpa error di sisi PHP.
 */
class CbirServiceClient extends BaseStub
{
    public function Search(CbirSearchRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/Search',
            $argument,
            [CbirSearchResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function GetStats(CbirStatsRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/GetStats',
            $argument,
            [CbirStatsResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function GetHealth(HealthRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/GetHealth',
            $argument,
            [HealthResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function ExtractFeatures(ExtractFeaturesRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/ExtractFeatures',
            $argument,
            [ExtractFeaturesResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function AddToIndex(AddToIndexRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/AddToIndex',
            $argument,
            [AddToIndexResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function RemoveFromIndex(RemoveFromIndexRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/RemoveFromIndex',
            $argument,
            [RemoveFromIndexResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function RebuildIndex(RebuildIndexRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/RebuildIndex',
            $argument,
            [RebuildIndexResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function ClearIndex(ClearIndexRequest $argument, array $metadata = [], array $options = [])
    {
        return $this->_simpleRequest(
            '/weddingapp.cbir.CbirService/ClearIndex',
            $argument,
            [ClearIndexResponse::class, 'decode'],
            $metadata,
            $options
        );
    }
}

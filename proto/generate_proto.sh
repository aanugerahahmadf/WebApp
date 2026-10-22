#!/bin/bash
# Generate PHP gRPC code from proto files
# Wedding Flower Decoration App - Laravel Backend

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PROTO_DIR="$SCRIPT_DIR"
OUTPUT_DIR="$PROJECT_ROOT/app/Grpc/Generated"

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Check if protoc is installed
if ! command -v protoc &> /dev/null; then
    echo "Error: protoc is not installed. Please install Protocol Buffers compiler."
    echo "Visit: https://grpc.io/docs/protoc-installation/"
    exit 1
fi

# Check if grpc_php_plugin is installed
if ! command -v grpc_php_plugin &> /dev/null; then
    echo "Error: grpc_php_plugin is not installed. Please install the PHP gRPC plugin."
    echo "Visit: https://grpc.io/docs/languages/php/quickstart/"
    exit 1
fi

# Generate PHP gRPC code for all proto files
for proto_file in "$PROTO_DIR"/*.proto; do
    if [ -f "$proto_file" ]; then
        echo "Generating PHP code for: $(basename "$proto_file")"
        
        protoc \
            --proto_path="$PROTO_DIR" \
            --php_out="$OUTPUT_DIR" \
            --grpc_out="$OUTPUT_DIR" \
            --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
            "$proto_file"
    fi
done

echo ""
echo "PHP gRPC code generated successfully!"
echo "Output directory: $OUTPUT_DIR"
ls -la "$OUTPUT_DIR"
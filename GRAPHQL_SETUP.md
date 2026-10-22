# GraphQL Implementation Guide for Wedding Flower Decoration App

## Overview

This document guides the installation and setup of GraphQL support using the **Lighthouse** package for Laravel. The existing REST API remains intact (hybrid approach), and GraphQL is added as a new endpoint at `/graphql`.

## Architecture

```
Flutter App → Laravel API (REST + GraphQL) → AI Core (Flask) → MySQL
                ↑                              ↑
          /api/* (REST)                  /graphql (GraphQL)
```

## Files Created

### Laravel Backend (`Mobile-And-Backend/`)

1. **`graphql.schema`** — GraphQL schema definition (450 lines)
   - Queries: User, Category, Product, Package, Order, Review, Cart, Wishlist, Voucher, Notification, CBIR, Home, Search
   - Mutations: Auth, Profile, Cart, Wishlist, Order, Review, CBIR Search, Notification, Voucher
   - Types: All entity types with relationships

2. **`config/lighthouse.php`** — Lighthouse configuration

3. **`app/Http/GraphQL/Resolvers/`** — Custom resolvers:
   - `AuthResolver.php` — Login, register, logout, password reset
   - `ProfileResolver.php` — Update profile, change password
   - `CartResolver.php` — Add, update, remove, clear cart
   - `WishlistResolver.php` — Toggle, remove wishlist
   - `OrderResolver.php` — Create order, cancel order
   - `ReviewResolver.php` — Create, update, delete review
   - `CbirResolver.php` — CBIR image search, arithmetic search, stats
   - `NotificationResolver.php` — Mark read, mark all read, delete
   - `VoucherResolver.php` — Validate voucher
   - `HomeResolver.php` — Home page data aggregation
   - `SearchResolver.php` — Text search across products and packages

## Installation Steps

### Step 1: Install Lighthouse Package

```bash
cd Mobile-And-Backend
composer require nuwave/lighthouse
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=lighthouse-config
```

Note: The `config/lighthouse.php` file is already created. You can replace the published file with our custom configuration or merge the settings.

### Step 3: Register GraphQL Route

Lighthouse automatically registers the `/graphql` route. The route uses the `api` middleware group for CORS and rate limiting.

### Step 4: Add GraphQL Endpoint to .env

```env
# Add to .env file
LIGHTHOUSE_ENABLE_INTROSPECTION=true
```

### Step 5: Clear Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 6: Test GraphQL Endpoint

Open browser or use GraphQL Playground:
```
http://192.168.100.63:8000/graphql
```

Or test with curl:
```bash
curl -X POST http://192.168.100.63:8000/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __typename }"}'
```

## Flutter Integration

### Step 1: Add Dependencies

Add to `mobile_app/pubspec.yaml`:

```yaml
dependencies:
  graphql_flutter: ^5.1.2
  gql: ^1.0.1
  gql_http_link: ^1.0.1
  gql_dedupe_link: ^2.0.3

dev_dependencies:
  graphql_codegen: ^0.8.0
```

### Step 2: Run pub get

```bash
cd mobile_app
flutter pub get
```

### Step 3: Create GraphQL Client

See `mobile_app/lib/core/api/graphql_client/` for the GraphQL client implementation.

## Example GraphQL Queries

### Get All Products

```graphql
query GetProducts($first: Int!, $categoryId: ID) {
  products(first: $first, categoryId: $categoryId) {
    data {
      id
      name
      slug
      price
      discountPrice
      finalPrice
      imageUrl
      category {
        name
      }
    }
    totalCount
  }
}
```

### CBIR Image Search

```graphql
mutation CbirSearch($image: Upload!) {
  cbirSearch(image: $image) {
    success
    results {
      type
      similarity
      score
      data {
        id
        name
        price
        imageUrl
        category
      }
    }
    totalResults
    queryTimeSeconds
  }
}
```

### Create Order

```graphql
mutation CreateOrder($input: CreateOrderInput!) {
  createOrder(input: $input) {
    id
    orderNumber
    status
    total
    items {
      product {
        name
      }
      quantity
      price
    }
  }
}
```

### Login

```graphql
mutation Login($email: String!, $password: String!) {
  login(email: $email, password: $password) {
    user {
      id
      name
      email
    }
    token
    tokenType
    expiresIn
  }
}
```

## Migration from REST to GraphQL

The hybrid approach allows gradual migration:

1. **Phase 1** (Current): REST API remains primary, GraphQL is available for new features
2. **Phase 2**: New features use GraphQL, existing features use REST
3. **Phase 3**: Gradually migrate existing features to GraphQL
4. **Phase 4**: Deprecate REST endpoints (optional)

## Authentication

GraphQL uses the same Sanctum Bearer Token authentication as REST API:

```graphql
# Header
Authorization: Bearer <token>
```

The `@can` directive in the schema ensures only authenticated users can access protected queries and mutations.

## CBIR Integration

The CBIR resolver communicates with the AI Core Flask service at the configured `AI_CORE_URL`. Image uploads are handled via GraphQL multipart request specification.

## Notes

- The `http_get_json()` helper function in `CbirResolver` should be replaced with Laravel's HTTP client: `Http::get()->json()`
- Model relationships (`@hasMany`, `@belongsTo`) in the schema require proper foreign keys in the database
- The `OrderItem` model may need to be created if it doesn't exist
- Adjust field names in resolvers to match your actual database column names

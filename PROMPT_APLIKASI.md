# PROMPT APLIKASI: Dekorasi Bunga Pernikahan - Wedding Flower Decorations CBIR

## 📋 OVERVIEW APLIKASI

**Nama Aplikasi:** Dekorasi Bunga Pernikahan (Wedding Flower Decorations CBIR)
**Penulis:** Anugerah Ahmad Fachrurochim
**Deskripsi:** Toko dekorasi bunga pernikahan berbasis AI dengan Content-Based Image Retrieval (CBIR) yang membantu calon pengantin merencanakan pernikahan. Pengguna bisa upload foto referensi untuk menemukan dekorasi yang cocok, mengelola anggaran, dan booking layanan langsung bersama tim dekorasi internal kami.

**Tipe:** Single-vendor (khusus untuk satu perusahaan: Dekorasi Bunga Pernikahan)
**Bahasa:** Indonesian (default), English, Arabic, +30 bahasa via auto-translation
**Lisensi:** Proprietary

---

## 🏗️ TECH STACK

### Backend
| Komponen | Teknologi |
|----------|-----------|
| Framework | Laravel 12 (PHP ^8.2) |
| Admin Panel | Filament v3.3 (dual panel: Admin + User) |
| Database | MySQL 8.0 (primary), PostgreSQL (production), SQLite (testing) |
| Mobile Runtime | NativePHP (Android & iOS) |
| Real-time | Laravel Reverb / Pusher Channels |
| Payment Gateway | Midtrans Snap + Snap-BI |
| AI/CBIR Engine | Python Flask server (port 5000, terpisah) |
| Queue | Database-driven queue |
| Cache | Database-driven (file/redis alternatif) |

### Frontend Web
- Laravel Vite + TailwindCSS v4
- Firebase JS SDK v10
- Google Cloud APIs (Language, Vision, Translate, Retail, BigQuery)
- Axios, Livewire v3
- Emoji picker, Popper.js

### Key Composer Packages
- `filament/filament` ^3.3 - Admin panel
- `spatie/laravel-permission` - RBAC
- `spatie/laravel-medialibrary` - File management
- `spatie/laravel-activitylog` - Activity logging
- `spatie/laravel-backup` - Database backup
- `kreait/laravel-firebase` - Firebase integration
- `midtrans/midtrans-php` - Payment gateway
- `laravel/sanctum` - API token auth
- `laravel/socialite` - Social login (Google & Facebook)
- `laravel/reverb` - WebSockets
- `nativephp/laravel` - Mobile app runtime
- `dompdf/dompdf` - PDF generation
- `phpoffice/phpspreadsheet` - Excel exports
- `livewire/livewire` - Dynamic UI components
- `stichoza/google-translate-php` - Auto-translation
- `coolsam/modules` - Modular architecture
- `pxlrbt/filament-excel` - Excel export

---

## 🗂️ STRUKTUR DIREKTORI

```
├── app/
│   ├── Actions/              # Filament custom actions (EmojiPicker)
│   ├── Channels/             # Custom notification channels (NativePHP)
│   ├── Console/Commands/     # 15+ artisan commands
│   ├── Enums/                # 8 PHP enums
│   ├── Filament/
│   │   ├── Admin/            # Admin Panel (15 resources)
│   │   │   ├── Auth/         # Login, Register, OTP, Reset Password
│   │   │   ├── Exports/      # Excel exports
│   │   │   ├── Pages/        # Dashboard, EditProfile, Messages
│   │   │   ├── Resources/    # 15 CRUD resources
│   │   │   └── Widgets/      # StatsOverview, OrdersChart, RevenueChart, RecentOrders
│   │   └── User/             # User Panel (11 resources)
│   │       ├── Auth/         # Login, Register, OTP, Reset Password
│   │       ├── Pages/        # Dashboard, EditProfile, Messages, CbirSearchPage
│   │       ├── Resources/    # 11 CRUD resources
│   │       └── Widgets/      # 6 dashboard widgets
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/          # 25+ API controllers
│   │   │   ├── Auth/         # SocialiteController
│   │   │   ├── Controller.php
│   │   │   ├── DatabaseProxyController.php  # Proxy MySQL untuk mobile
│   │   │   ├── LanguageController.php
│   │   │   └── PusherAuthController.php
│   │   └── Middleware/       # 6 middleware classes
│   ├── Jobs/                 # SendBotReply
│   ├── Livewire/             # 8 Livewire components
│   ├── Mail/                 # OrderPaymentNotification
│   ├── Mcp/                  # MCP Server (AI agent integration)
│   ├── Models/               # 25+ Eloquent models
│   ├── Notifications/        # Notification traits
│   ├── Observers/            # 6 model observers
│   ├── Providers/            # 7 service providers
│   ├── Services/             # 8 service classes
│   ├── Traits/               # 3 traits
│   ├── Translators/          # Translation helpers
│   └── View/                 # View composers
├── config/                   # 47 config files
├── database/
│   ├── migrations/           # 40 migration files
│   ├── seeders/              # 10 seeders
│   └── factories/            # Model factories
├── docker/                   # Docker configs (Nginx)
├── lang/                     # Language files
├── nativephp/                # NativePHP mobile config
├── resources/                # Views, CSS, JS
├── routes/                   # 6 route files
├── tests/                    # Pest PHP tests
├── .env.example              # Template environment
├── cloudbuild.yaml           # GCP Cloud Build
├── docker-compose.yml        # Docker services
├── Dockerfile                # PHP-FPM Docker image
├── Dockerfile.cloudrun       # Cloud Run optimized
├── firebase.json             # Firebase emulator config
├── netlify.toml              # Netlify deployment
└── render.yaml               # Render deployment
```

---

## 🗄️ DATABASE MODELS (27 Models)

### Core Models
| Model | Fields Kunci | Relasi |
|-------|-------------|--------|
| **User** | full_name, username, email, password, phone, whatsapp, address, avatar_url, balance, budget, wedding_date, theme_preference, color_preference, event_concept, dream_venue, ip_address, login_city, login_region, login_country, latitude, longitude, active_status, social_id, social_type, gender | hasMany orders, wishlists, transactions, withdrawals; belongsToMany vouchers |
| **WeddingFlowersDecorasi** | name, slug, description, address, latitude, longitude, rating, is_verified, phone, whatsapp, email, instagram, operational_hours | hasMany packages, products, reviews; mediaCollections: logo, gallery, videos |
| **Category** | name, slug, icon, color, description | hasMany packages |
| **Package** | wedding_flowers_decorasi_id, category_id, name, slug, description, price, discount_price, is_featured, features (json), theme, color, min_capacity, max_capacity, stock, article_id | belongsTo category, weddingFlowersDecorasi; hasMany orders, reviews, wishlists; mediaCollections: package_image, videos |
| **Product** | wedding_flowers_decorasi_id, category_id, name, slug, description, price, discount_price, stock, is_featured | belongsTo category, weddingFlowersDecorasi; hasMany orders, reviews, wishlists |
| **Banner** | title, description, image, link, sort_order, is_active | - |

### Commerce Models
| Model | Fields Kunci | Relasi |
|-------|-------------|--------|
| **Cart** | user_id, (product_id OR package_id), quantity, meta (json) | belongsTo user, product, package |
| **Order** | user_id, package_id/product_id, order_number, total_price, status (enum), payment_status (enum), booking_date, booking_time, quantity, notes | belongsTo user, package, product; hasMany transactions |
| **Wishlist** | user_id, (product_id OR package_id) | polymorphic wishlistable |
| **Voucher** | code, type (fixed/percentage), value, min_purchase, max_discount, usage_limit, user_limit, is_active, expires_at | belongsToMany users (pivot: claimed_at, used_at, order_id) |
| **Review** | user_id, package_id/product_id, rating, review | belongsTo user, package, product |

### Payment/Finance Models
| Model | Fields Kunci |
|-------|-------------|
| **Payment** | order_id, user_id, payment_number, amount, admin_fee, total_amount, payment_method, payment_gateway, transaction_id, status (enum), bank_name, account_number, account_holder, payment_proof, payment_url, paid_at, expired_at, cancelled_at, notes, metadata (json) |
| **Transaction** | user_id, order_id, type (topup/order), reference_number, amount, admin_fee, total_amount, payment_method, status, snap_token, payment_url, paid_at, expired_at, notes |
| **Topup** | user_id, amount, admin_fee, total_amount, status (enum), payment_method, proof, notes, expired_at |
| **Withdrawal** | user_id, amount, admin_fee, total_amount, bank_name, bank_account, bank_holder, status, notes |
| **PaymentMethod** | name, code, type (enum), icon, is_active, description |
| **Bank** | name, code, account_name, account_number, is_active |

### Communication Models
| Model | Fields Kunci |
|-------|-------------|
| **Inbox** (`fm_inboxes`) | user_ids (json), last_message, last_message_at, type |
| **Message** (`fm_messages`) | inbox_id, user_id, message, meta (json - product/order cards), soft deletes |

### Content Models
| Model | Fields Kunci |
|-------|-------------|
| **Article** | title, slug, content, excerpt, category, image, author, is_published, published_at |
| **TermsOfService** | content (json) |
| **PrivacyPolicy** | content (json) |
| **LegalPage** | type, title, content, is_active |

### System Models
| Model | Fields Kunci |
|-------|-------------|
| **History** | user_id, type, reference_type, reference_id, description, amount, balance_before, balance_after |
| **Translation** | source_hash, source_text, target_locale, translated_text |
| **UserLanguage** | user_id, locale, is_default |

### Enums (Backed by PHP Enums)
| Enum | Values |
|------|--------|
| **OrderStatus** | pending, confirmed, preparing, event_day, completed, cancelled |
| **OrderPaymentStatus** | unpaid, pending, partial, paid, failed, refunded, cancelled |
| **PaymentStatus** | pending, processing, success, failed, expired, cancelled, refunded |
| **PaymentMethodType** | bank_transfer, ewallet, qris, cod, wallet |
| **TopupStatus** | pending, success, failed, cancelled |
| **WithdrawalStatus** | pending, processing, success, failed, cancelled |
| **DiscountType** | fixed, percentage |

---

## 📡 API ENDPOINTS (Public)

| Method | Endpoint | Controller | Deskripsi |
|--------|----------|------------|-----------|
| GET | `/api/settings` | AppSettingsController | Konfigurasi aplikasi (nama, owner, demo video) |
| GET | `/api/ping` | Closure | Diagnostic endpoint |
| POST | `/api/db-proxy` | DatabaseProxyController | Proxy MySQL untuk mobile app |
| POST | `/api/register` | AuthController | Registrasi user |
| POST | `/api/login` | AuthController | Login user (email atau username) |
| POST | `/api/forgot-password` | AuthController | Lupa password |
| POST | `/api/reset-password` | AuthController | Reset password |
| POST | `/api/auth/send-otp` | AuthController | Kirim OTP via email |
| POST | `/api/auth/verify-otp` | AuthController | Verifikasi kode OTP |
| GET | `/api/packages/public` | PackageController | List paket publik |
| GET | `/api/legal/terms` | LegalController | Terms of Service |
| GET | `/api/legal/privacy` | LegalController | Privacy Policy |
| POST | `/api/webhooks/midtrans` | PaymentWebhookController | Notifikasi Midtrans |
| POST | `/api/v1.0/payment/notify` | PaymentWebhookController | Snap-BI notification |
| POST | `/api/webhooks/fonnte` | FonnteWebhookController | WhatsApp incoming |
| POST | `/api/webhooks/fonnte/connect` | FonnteWebhookController | Status koneksi WA |
| POST | `/api/webhooks/fonnte/status` | FonnteWebhookController | Status pesan WA |
| GET | `/api/cbir/stats` | CBIRController | Statistik CBIR |
| GET | `/api/cbir/health` | CBIRController | Health check CBIR |
| GET | `/api/firebase/status` | FirebaseController | Status Firebase |

## 📡 API ENDPOINTS (Authenticated - Sanctum)

### Auth & Profile
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/logout` | Logout (revoke token) |
| DELETE | `/api/user/account` | Hapus akun |
| GET | `/api/user` | Data user saat ini |
| GET/PUT | `/api/profile` | Lihat/update profil |
| POST | `/api/profile/avatar` | Update avatar |
| POST | `/api/profile/change-password` | Ganti password |
| GET | `/api/profile/dashboard` | Data dashboard user |

### Cart
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cart` | List cart |
| POST | `/api/cart/add` | Tambah ke cart |
| PUT | `/api/cart/{cart}` | Update cart item |
| DELETE | `/api/cart/{cart}` | Hapus dari cart |

### Home, Categories, Search
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/home` | Data home screen |
| GET | `/api/categories` | List kategori |
| GET | `/api/categories/{id}` | Detail kategori |
| GET | `/api/categories-with-packages` | Kategori dengan paket |
| GET | `/api/search` | Pencarian teks |
| POST | `/api/search/image` | Pencarian gambar (CBIR) |

### Vouchers & Articles
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/vouchers` | List voucher |
| POST | `/api/vouchers/{voucher}/claim` | Claim voucher |
| GET | `/api/articles` | List artikel |
| GET | `/api/histories` | Riwayat user |

### Packages
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/packages` | List paket |
| GET | `/api/packages/{id}` | Detail paket |
| GET | `/api/packages/featured` | Paket unggulan |
| GET | `/api/packages/on-sale` | Paket diskon |

### Wishlist
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/wishlist` | List wishlist |
| POST | `/api/wishlist/toggle` | Toggle wishlist |
| GET | `/api/wishlist/{packageId}/check` | Cek wishlist |
| POST | `/api/wishlist/bulk-add` | Bulk add |
| DELETE | `/api/wishlist/{packageId}` | Hapus wishlist |

### Chat/Messages
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/messages/conversations` | Daftar percakapan |
| GET | `/api/messages/conversations/{inboxId}` | Detail percakapan |
| GET | `/api/messages/unread-count` | Jumlah belum dibaca |
| GET | `/api/messages/customers` | Daftar pelanggan |
| POST | `/api/messages/send` | Kirim pesan |
| POST | `/api/messages/start` | Mulai percakapan |

### Orders/Bookings
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/bookings` | List booking |
| POST | `/api/bookings` | Buat booking |
| POST | `/api/bookings/{id}/pay` | Bayar booking |
| GET | `/api/bookings/track/{orderNumber}` | Lacak pesanan |
| GET | `/api/bookings/{id}` | Detail booking |
| POST | `/api/bookings/{id}/cancel` | Batalkan booking |
| GET | `/api/orders` | List pesanan |
| POST | `/api/orders` | Buat pesanan |
| POST | `/api/orders/{id}/pay` | Bayar pesanan |
| GET | `/api/orders/{id}` | Detail pesanan |
| POST | `/api/orders/{id}/cancel` | Batalkan pesanan |

### Payments
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/payments/methods` | Metode pembayaran |
| POST | `/api/payments` | Buat pembayaran |
| GET | `/api/payments` | Riwayat pembayaran |
| GET | `/api/payments/{paymentNumber}` | Detail pembayaran |
| POST | `/api/payments/{paymentNumber}/upload-proof` | Upload bukti |
| POST | `/api/payments/{paymentNumber}/cancel` | Batalkan |
| GET | `/api/payments/{paymentNumber}/status` | Cek status |

### Reviews
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/reviews` | Buat review |
| PUT | `/api/reviews/{id}` | Update review |
| DELETE | `/api/reviews/{id}` | Hapus review |
| GET | `/api/reviews/user` | Review user |
| GET | `/api/reviews/package/{packageId}` | Review per paket |


### Wallet
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/wallet` | Data wallet |
| GET | `/api/wallet/history` | Riwayat wallet |
| POST | `/api/wallet/topup` | Topup saldo |
| POST | `/api/wallet/topup/{id}/proof` | Upload bukti topup |
| GET | `/api/wallet/withdrawal` | List penarikan |
| POST | `/api/wallet/withdrawal` | Request penarikan |
| GET | `/api/wallet/withdrawal/history` | Riwayat penarikan |

### CBIR (AI Visual Search)
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/cbir/search` | Cari berdasarkan gambar upload |
| POST | `/api/cbir/index/product` | Index item ke AI Core |
| POST | `/api/cbir/index/build` | Rebuild index |
| GET | `/api/cbir/stats` | Statistik CBIR |
| GET | `/api/cbir/health` | Health check |

### Firebase Operations
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/firebase/read` | Baca data Firebase |
| POST | `/api/firebase/write` | Tulis data Firebase |
| POST | `/api/firebase/update` | Update data Firebase |
| POST | `/api/firebase/delete` | Hapus data Firebase |
| POST | `/api/firebase/sync-order` | Sync order ke Firebase |
| POST | `/api/firebase/sync-message` | Sync message ke Firebase |

### Web Routes (non-API)
| Method | URL | Deskripsi |
|--------|-----|-----------|
| GET | `/` | Welcome page |
| GET | `/admin/inbox` | Redirect ke inbox admin |
| GET | `/mobile/settings` | NativePHP app settings |
| GET | `/language/switch/{locale}` | Ganti bahasa |
| GET | `/auth/{provider}/redirect` | Social login redirect |
| GET | `/auth/{provider}/callback` | Social login callback |
| GET | `/invoice/{order}/pdf` | Download invoice PDF |
| GET | `/mcp/demo` | MCP Server demo |

---

## 🔐 AUTENTIKASI & OTORISASI

### Authentication
1. **Sanctum Token Auth** (API): Login by email OR username, token creation, revocation on logout
2. **Session Auth** (Web): Laravel session + Filament panel auth
3. **Social Login**: Google & Facebook via Laravel Socialite with mobile deep-link support (`weddingapp://`)
4. **OTP System**: Email-based OTP for email verification & password reset
5. **Registration**: full_name, username, email, password, optional avatar

### Authorization (RBAC)
- **Roles**: `super_admin` dan `user` (via Spatie Laravel Permission)
- **Admin Panel** (`/admin`): Only `super_admin`
- **User Panel** (`/user`): Semua authenticated user
- **SuperAdmin Middleware**: Guard untuk admin routes
- **Gate::before()**: Super_admin auto-granted all permissions
- **Account Status**: Inactive users return 403

### Security
- Bcrypt rounds: 12
- Sanctum token: never expires
- Midtrans 3DS: enabled
- CSP middleware for payment pages
- IP/Geolocation tracking via ip-api.com

---

## 🎯 FITUR DETAIL

### 1. Dual Panel Filament
**Admin Panel (`/admin`) - 15 Resources:**
- Dashboard (stats overview, revenue chart, orders chart, recent orders)
- User management
- Category management
- Profil toko dekorasi bunga pernikahan
- Package management
- Product management
- Order management (CRUD + status tracking)
- Payment management
- Article/blog management
- Banner management
- Voucher management
- Legal pages (Terms, Privacy)
- Wallet management (topups, withdrawals)
- Message/inbox
- Activity logs

**User Panel (`/user`) - 11 Resources:**
- Dashboard (widgets: orders summary, budget tracker, upcoming events)
- Profile & wedding preferences
- Browse packages & products
- Shopping cart
- Wishlist
- Orders & booking
- Payment & invoices
- Wallet & topup
- Messages/inbox
- CBIR visual search
- Voucher claims

### 2. AI-Powered CBIR (Content-Based Image Retrieval)
- **Visual Search**: Upload foto atau gunakan kamera untuk cari dekorasi mirip
- **Python Flask Server** (terpisah, port 5000):
  - `/api/search` - Search by image (upload file)
  - `/api/index/add` - Index media baru
  - `/api/index/remove` - Remove dari index
  - `/api/stats` - Statistik index
  - `/api/health` - Health check
- **Auto-indexing**: Observers auto-index media saat upload
- **Cache**: Hash-based caching dengan versioning (cache busting)
- **Session-based**: Hasil pencarian disimpan di session
- **Similarity ranking**: Kembalikan results dengan score & similarity

### 3. Wedding Planning Tools
- **Wedding Date**: Tanggal pernikahan user
- **Budget Tracker**: Budget planning & tracking
- **Theme Preference**: Tema dekorasi yang diinginkan
- **Color Preference**: Warna favorit untuk dekorasi
- **Event Concept**: Konsep acara
- **Dream Venue**: Venue impian
- **Guest Count**: Jumlah tamu

### 4. Commerce & Order Management
**Order Lifecycle:**
1. **Pending** - Menunggu konfirmasi
2. **Confirmed** - Dikonfirmasi admin
3. **Preparing** - Sedang disiapkan
4. **Event Day** - Hari-H acara
5. **Completed** - Selesai
6. **Cancelled** - Dibatalkan

**Payment Status:**
1. **Unpaid** - Belum bayar
2. **Pending** - Menunggu konfirmasi
3. **Partial** - DP / Sebagian
4. **Paid** - Lunas
5. **Failed** - Gagal
6. **Refunded** - Dikembalikan
7. **Cancelled** - Dibatalkan

**Shopping Features:**
- Cart (polymorphic: product or package items)
- Wishlist (toggle, bulk add, check)
- Voucher (fixed amount / percentage, usage limits, claim system)
- Invoice PDF (downloadable via dompdf)

### 5. Midtrans Payment Integration
**Dual Integration:**
- **Standard Snap**: Credit card, bank transfer, e-wallet, QRIS
- **Snap-BI**: Standar nasional Indonesia (Open API)

**Flow:**
1. User create transaction → system generates `reference_number` (MID-XXXX format)
2. Create Snap token via Midtrans API
3. User redirected to Midtrans Snap popup/redirect
4. Midtrans sends notification to webhook `/api/webhooks/midtrans`
5. Signature verification (SHA512: order_id + status_code + gross_amount + server_key)
6. Update transaction & order status
7. Multi-channel notifications sent

**Transaction types:**
- `order` - Pembayaran pesanan
- `topup` - Topup wallet balance

### 6. Digital Wallet System
- **Balance**: Setiap user punya balance (decimal:2)
- **Topup**: Request topup → bayar via Midtrans → balance otomatis bertambah
- **Withdrawal**: Request penarikan ke bank → admin approve
- **Transaction History**: Semua transaksi tercatat (topup & order)
- **Admin Fee**: Configurable fee untuk topup & withdrawal
- **Payment Proof**: Upload bukti transfer manual

### 7. Multi-Channel Notifications
| Channel | Teknologi | Digunakan Untuk |
|---------|-----------|-----------------|
| In-App Bell | Filament Database Notification | Semua notifikasi |
| Email | SMTP Gmail via Laravel Mail | Order confirmation, payment status |
| WhatsApp | Fonnte API | Order updates, cancellation |
| Desktop | NativePHP Notification | Desktop app notifications |
| Mobile Toast | NativePHP Dialog | Mobile toast notifications |
| Inbox | Custom chat system | Order & payment messages |
| Firebase RTDB | Kreait Firebase | Real-time sync |

**Trigger Events:**
- Order status change
- Payment status change
- Order cancellation
- New message from admin
- Welcome message after registration

### 8. Firebase Integration
- **Firebase Project**: `wedding-decorasi-flower`
- **Services Used**:
  - **Realtime Database**: Sync orders, messages, users
  - **Authentication**: Firebase Auth integration
  - **Storage**: File storage
  - **Cloud Messaging**: Push notifications
- **Sync Operations**: Orders & messages auto-synced to RTDB
- **Cache**: Firebase reads cached via Laravel Cache
- **REST API**: All Firebase operations available via API endpoints

### 9. Chat & Messaging System
- **Inbox System**: Multi-participant, JSON user_ids
- **Rich Messages**: Text + metadata (product cards, order cards, bot replies)
- **AI Bot Reply**: Auto-reply di-delay 5 detik via Job queue
- **Context Cards**: Product/package card display dalam chat
- **Order Cards**: Order confirmation dengan status pembayaran
- **Conversation History**: Full message history with timestamps

### 10. Auto-Translation System
- **30+ Languages**: Indonesia (default), English, Arabic, dan 30+ lainnya
- **Provider**: MyMemory API (dengan fallback)
- **Caching**: DB-level (Translations model) + Cache-level (active_trans_map)
- **Smart Skip**: Skip locale 'id', numeric, language names
- **Budget Management**: Max 8 API calls per request, max 3 detik total
- **Fallback**: Return original text if API fails

### 11. GeoLocation & Maps
- **Login Tracking**: IP geolocation via ip-api.com (city, region, country)
- **Auto-Geocoding**: WeddingFlowersDecorasi address → lat/lng via Nominatim (OpenStreetMap)


### 12. MCP Server (AI Agent)
- **Protokol**: Model Context Protocol
- **Path**: `/app/Mcp/`
- **Integration**: AI agent dapat query data aplikasi via MCP
- **Demo Route**: `/mcp/demo` - test endpoint

### 13. NativePHP Mobile App
- **Platform**: Android & iOS
- **DB Proxy**: MySQL queries via HTTP proxy (no direct pdo_mysql needed on device)
- **Native Camera**: Camera integration for CBIR search
- **Deep Linking**: OAuth callbacks via `weddingapp://` scheme
- **APIs**: Screen, Device, Network APIs
- **Auto-Init**: First-run DB migration/seeding

### 14. Laravel Reverb / Pusher
- **WebSocket**: Real-time broadcasting
- **Events**: Order updates, message notifications
- **Config**: Reverb for local, Pusher for production

---

## ⚙️ SCHEDULED TASKS (Cron)

| Task | Frequency | Description |
|------|-----------|-------------|
| `app:update-order-status` | Every minute | Auto-complete/cancel orders |
| `app:mark-expired-payments` | Every minute | Expire pending payments |
| `cleanup-pending-topups` | Daily | Clean pending topups |
| `clear-logs` | Daily | Clear log files |
| `daily-summary` | Daily | Daily report |
| `expire-vouchers` | Daily | Expire vouchers |
| `publish-articles` | Every 5 min | Publish scheduled articles |
| `sync-ai-core` | Hourly | Sync media ke AI Core |
| `sync-cbir-csv` | Hourly | Sync CSV ke AI Core |

---

## 🔧 ARTISAN COMMANDS

| Command | Description |
|---------|-------------|
| `php artisan app:install` | Initial setup wizard |
| `php artisan app:init-admin` | Create super admin user |
| `php artisan app:update-order-status` | Auto-update order statuses |
| `php artisan app:mark-expired-payments` | Expire overdue payments |
| `php artisan cleanup-pending-topups` | Clean pending topups |
| `php artisan clear-logs` | Clear log files |
| `php artisan daily-summary` | Daily report |
| `php artisan expire-vouchers` | Expire vouchers |
| `php artisan publish-articles` | Publish scheduled articles |
| `php artisan sync-ai-core` | Sync media to AI Core |
| `php artisan sync-cbir-csv` | Sync CSV to AI Core |
| `php artisan test-payment-notification` | Test notifications |
| `php artisan translate-json-keys` | Translation maintenance |

---

## 🚀 DEPLOYMENT

### Docker (local)
```yaml
# docker-compose.yml
services:
  app: PHP-FPM 8.2 + Nginx, port 8000
  mysql: MySQL 8.0, port 3307
  phpmyadmin: port 8081
network: wedding-flower-decorations
```

### Google Cloud Run
- **Build**: Dockerfile.cloudrun (PHP 8.4-fpm)
- **Deploy**: Cloud Build → Artifact Registry → Cloud Run (asia-southeast2)
- **Specs**: 512Mi memory, 1 CPU, 0-10 instances, 300s timeout
- **Firebase Hosting**: Deployed alongside

### Other Platforms
- **Netlify**: Publish `public/` directory
- **Render**: Docker web service, PostgreSQL database
- **Vercel**: Read-only filesystem workaround (AppServiceProvider)

---

## 📊 ADMIN DASHBOARD WIDGETS

| Widget | Description |
|--------|-------------|
| **StatsOverview** | Total users, orders, revenue, pending tasks |
| **RevenueChart** | Monthly income visualization (line chart) |
| **OrdersChart** | Order status distribution (pie chart) |
| **RecentOrders** | Latest 10 orders quick view |
| **Export** | All resources exportable to XLSX via Filament Excel |

## 📱 USER DASHBOARD WIDGETS

| Widget | Description |
|--------|-------------|
| **ActiveOrdersWidget** | Active orders list |
| **BalanceWidget** | Wallet balance display |
| **BudgetWidget** | Budget tracker |
| **UpcomingEventWidget** | Upcoming wedding event |
| **RecentTransactionsWidget** | Recent transactions |
| **WishlistWidget** | Favorite items |

---

## 🔄 BUSINESS LOGIC FLOWS

### Flow Pembayaran Midtrans
```
User → Checkout → Create Transaction → Get Snap Token → Redirect Snap
                                                              ↓
                                                    User bayar di Snap
                                                              ↓
                                              Midtrans → Webhook → Verify Signature
                                                              ↓
                                                    Update Order & Payment Status
                                                              ↓
                                              Multi-channel Notifications
                                              (Inbox, Email, WA, Bell, Desktop, Mobile)
```

### Flow CBIR Visual Search
```
User → Upload Image → POST /api/cbir/search
                           ↓
                Hash file → Cek Cache
                           ↓
                (miss) → Panggil AI Core /api/search
                           ↓
                Dapatkan results (owner_id, type, score, similarity)
                           ↓
                Load data dari DB berdasarkan owner_id
                           ↓
                Tampilkan hasil ke user
```

### Flow Auto-Translation
```
Label (ID) → Terdeteksi locale target
                  ↓
          Cek Memory (static $activeMap)
                  ↓
          (miss) Cek DB (Translations table)
                  ↓
          (miss) Cek Cache (active_trans_map_{locale})
                  ↓
          (miss) Panggil MyMemory API (max budget)
                  ↓
          Simpan ke DB + Cache + Memory
                  ↓
          Return translated text
```

### Flow Order -> Firebase Sync
```
Order status change (Observer)
    ↓
Update local DB
    ↓
Multi-channel notifications
    ↓
Sync to Firebase Realtime Database:
  - /orders/{order_number}
  - /messages/{inbox_id}/messages/{id}
```

---

## 🌐 ENVIRONMENT VARIABLES

| Variable | Purpose |
|----------|---------|
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Core app |
| `DB_*` (MySQL/PostgreSQL) | Database |
| `REVERB_*` / `PUSHER_*` | WebSocket |
| `MIDTRANS_*` (server_key, client_key, is_production, snap_bi_*) | Payment gateway |
| `FIREBASE_*` (credentials, database_url) | Firebase |
| `GOOGLE_CLIENT_*`, `FACEBOOK_*` | Social login |
| `FONNTE_TOKEN` | WhatsApp |
| `AI_CORE_URL`, `CBIR_API_URL` | AI service |
| `NATIVEPHP_*` | Mobile app |
| `MAIL_*` | Email SMTP |
| `AWS_*` | S3 file storage |
| `WEDDING_APP_*` | App branding |

---

## 📝 TESTING

**Framework**: Pest PHP v4
**Database**: SQLite in-memory
**Cache**: Array driver
**Queue**: Sync driver

**Test Files:**
- `tests/Feature/WeddingAppTest.php` - User access tests
- `tests/Feature/AdminAccessTest.php` - Admin RBAC tests
- `tests/Unit/UserTest.php` - User model tests

**Run Tests:**
```bash
php artisan test
# or
./vendor/bin/pest
```

---

## 📌 KEY BUSINESS RULES

1. **Single Vendor**: Hanya 1 record WeddingFlowersDecorasi boleh ada. Jika coba buat baru, throw Exception.
2. **Auto-Geocoding**: Saat address WeddingFlowersDecorasi diubah, otomatis cari coordinates via Nominatim (timeout 5s, silent fail)
3. **Order Number**: Format otomatis (ORD-timestamp-random)
4. **Midtrans Order ID**: Format `MID-{Timestamp}-{ID}` untuk tracking
5. **24 Jam Expiry**: Semua transaksi Midtrans expire dalam 24 jam
6. **Wishlist Uniqueness**: Cegah duplicate product/package di wishlist
7. **Voucher Usage Limit**: Batas penggunaan global + per user
8. **Cart Deduplication**: Jika item sudah ada di cart, increment quantity
9. **Notification Deduplication**: Cegah notifikasi ganda dalam 60 detik (Cache lock)
10. **Payment Lock**: Cegah multiple payment untuk order yang sama
11. **Balance Tracking**: Setiap perubahan balance tercatat di Histories
12. **Cache Busting AI**: Setiap ada index baru, cache version increment

# Welcome Panel — Storefront Guide

> Panel ID: `welcome` · Path prefix: `/welcome` · Auth guard: `web`

The **Welcome panel** is the public-facing storefront of the Weeding Organizer CBIR application.
It is built on [Filament v3](https://filamentphp.com/) and lives under `app/Filament/Welcome/`.
Unlike the User panel (`/user`), the Welcome panel is **accessible to guests** — unauthenticated
visitors can browse the landing page, catalog, and product/package detail pages without logging in.

---

## Contents

1. [Panel Registration](#1-panel-registration)
2. [Authentication & Guest Access](#2-authentication--guest-access)
3. [Navigation Structure](#3-navigation-structure)
4. [Pages](#4-pages)
5. [Resources](#5-resources)
6. [Widgets](#6-widgets)
7. [Guest Identity](#7-guest-identity)
8. [Chat System](#8-chat-system)
9. [CBIR Search](#9-cbir-search)
10. [Differences from User Panel](#10-differences-from-user-panel)

---

## 1. Panel Registration

The panel is registered in `app/Providers/Filament/WelcomePanelProvider.php`.

| Setting | Value |
|---|---|
| Panel ID | `welcome` |
| URL path | `/welcome` |
| Auth guard | `web` |
| Login page | **None** (guests allowed) |
| Registration | **None** (handled by User panel) |
| SPA mode | Inherited from User panel |
| Top navigation | Inherited from User panel |

---

## 2. Authentication & Guest Access

### AuthenticateWelcome Middleware

Filament's built-in `Authenticate` middleware rejects unauthenticated users with a 403 because
there is no login page to redirect to. The Welcome panel replaces it with `AuthenticateWelcome`
(`app/Http/Middleware/AuthenticateWelcome/AuthenticateWelcome.php`).

**Behaviour:**
- Public pages (`/welcome/home`, `/welcome/products`, `/welcome/packages`, detail pages): **allow guests**.
- Account pages (`/welcome/carts`, `/welcome/orders`, `/welcome/wishlists`, `/welcome/settings`, etc.):
  **redirect guests to** `route('filament.user.auth.index')` — the auth landing page `/user/auth`, which offers
  both Sign In and Continue With Google. Not the `/user/signin` form directly: a guest who only wants to
  add to a cart should not be pushed past the Google door.
- The `url.intended` session key is set before redirecting, so after login the user lands back
  on the page they were trying to reach.

```php
// AuthenticateWelcome::LOGIN_ROUTE
public const LOGIN_ROUTE = 'filament.user.auth.index';   // GET /user/auth
```

The same constant backs every guest redirect in the panel — `ProductResource`, `PackageResource`,
`ManageProducts`, `ManagePackages`, `CheckoutProduct` and `CheckoutPackage` all call
`route(AuthenticateWelcome::LOGIN_ROUTE)`, so there is no second place to keep in sync.

### Public Storefront Routes

| Route | Auth required |
|---|---|
| `GET /welcome/home` | No |
| `GET /welcome/products` | No |
| `GET /welcome/products/{record}` | No |
| `GET /welcome/packages` | No |
| `GET /welcome/packages/{record}` | No |
| `GET /welcome/cbir-search` | No |
| `GET /welcome/help-center` | No |
| `GET /welcome/privacy-policy` | No |
| `GET /welcome/terms-of-service` | No |
| `GET /welcome/wedding-policy` | No |

### Protected Account Routes

| Route | Auth required |
|---|---|
| `GET /welcome/carts` | Yes |
| `GET /welcome/orders` | Yes |
| `GET /welcome/wishlists` | Yes |
| `GET /welcome/reviews` | Yes |
| `GET /welcome/vouchers` | Yes |
| `GET /welcome/histories` | Yes |
| `GET /welcome/settings` | Yes |
| `GET /welcome/messages/{id?}` | Semi (guest via GuestIdentity) |

### Checkout Protection

The checkout wizard pages (`/welcome/products/{id}/checkout`,
`/welcome/packages/{id}/checkout`) are guarded at the **Livewire component level**: when
`Filament::auth()->check()` returns false, the page immediately redirects to the login route.

---

## 3. Navigation Structure

The topbar rendered for **guests** shows:
- Brand logo (left)
- Theme switcher dropdown (right) — moved out of the user menu since guests have no user menu
- **Masuk** button → `route('filament.user.auth.index')` (`/user/auth`: Sign In + Continue With Google)

The topbar rendered for **signed-in customers** shows:
- Brand logo (left)
- **Beranda** button → `route('filament.user.pages.home')` (User panel dashboard)
- No user menu (deliberately omitted)

### Navigation Groups

| Group | Resources / Pages |
|---|---|
| Belanja & Jelajahi | Products, Packages |
| Akun | Orders, Carts, Wishlists, Reviews, Vouchers, Histories |
| Komunikasi | Messages |
| Pengaturan | Settings |

---

## 4. Pages

| Class | Route | Description |
|---|---|---|
| `Dashboard` | `/welcome/home` | Landing dashboard with catalog widget |
| `MessagesPage` | `/welcome/messages/{id?}` | Chat inbox + conversation view |
| `CbirSearchPage` | `/welcome/cbir-search` | CBIR visual search |
| `CompleteProfilePage` | `/welcome/complete-profile` | Profile completion wizard |
| `EditProfilePage` | `/welcome/edit-profile-page` | Profile edit |
| `HelpCenterPage` | `/welcome/help-center` | FAQ / help articles |
| `NotificationDetailPage` | `/welcome/notifications/{id}` | Single notification view |
| `PrivacyPolicyPage` | `/welcome/privacy-policy` | Privacy policy |
| `PrivacyTermsPage` | `/welcome/privacy-terms` | Privacy terms |
| `SettingsPage` | `/welcome/settings` | Account settings |
| `PasswordSecurityPage` | `/welcome/settings/password-security` | Password & 2FA |
| `TermsOfServicePage` | `/welcome/terms-of-service` | ToS |
| `WeddingPolicyPage` | `/welcome/wedding-policy` | Wedding decoration policy |

### MessagesPage slug format

```php
public static function getSlug(): string
{
    return config('messages.slug', 'messages').'/{id?}';
}
```

The optional `{id?}` segment pre-selects a conversation. When absent the inbox list is shown
with no conversation open. When present the `mount(?int $id)` method:
1. Loads the `Inbox` by the given ID.
2. Verifies the current user (or guest) is a member of `inbox.user_ids`.
3. Aborts 403 if not.
4. Sets `$selectedConversation` for the Livewire chat component.

---

## 5. Resources

### ProductResource

**Model:** `App\Models\Product\Product`  
**Slug:** `products`  
**Pages:** `ManageProducts`, `ViewProduct`, `CheckoutProduct`

The detail page (`ViewProduct`) renders a full product card with:

| Button | Guest behaviour | Signed-in behaviour |
|---|---|---|
| Pesan Sekarang | Redirect to login | Opens checkout wizard |
| Masukkan ke Keranjang | Redirect to login | Adds to cart (Cart model) |
| **Chat Admin** | Works via GuestIdentity | Works via Filament auth |
| **Tambah ke Favorit** | Redirect to login | Toggles Wishlist row |
| Tulis Ulasan | Redirect to login | Opens review slide-over |
| Bagikan | Opens share modal | Opens share modal |
| Lapor | Redirect to login | Sends report to admin chat |

> **Important:** `chat_admin` uses `$livewire->redirect()` (not raw `redirect()`), because
> Filament infolist actions do not process a returned `RedirectResponse`.

> **Important:** `wishlist_detail` has **no** `->form()` call. Adding a hidden form forces
> Filament to open a modal with a Submit POST, which expires the session (419). Direct actions
> do not open a modal and do not require CSRF re-submission.

### PackageResource

**Model:** `App\Models\Package\Package`  
**Slug:** `packages`  
**Pages:** `ManagePackages`, `ViewPackage`, `CheckoutPackage`

Identical button matrix to `ProductResource` above. The only difference is the model column
used for wishlist (`package_id` vs `product_id`).

### CartResource

**Model:** `App\Models\Cart\Cart`  
**Slug:** `carts`  
**Auth:** Required (account resource)

### OrderResource

**Model:** `App\Models\Order\Order`  
**Slug:** `orders`  
**Pages:** `ManageOrders`, `ViewOrder`, `EditOrder`  
**Auth:** Required

### WishlistResource

**Model:** `App\Models\Wishlist\Wishlist`  
**Slug:** `wishlists`  
**Auth:** Required

### ReviewResource

**Model:** `App\Models\Review\Review`  
**Slug:** `reviews`  
**Auth:** Required

### VoucherResource

**Model:** `App\Models\Voucher\Voucher`  
**Slug:** `vouchers`  
**Auth:** Required

### HistoryResource

**Model:** `App\Models\History\History`  
**Slug:** `histories`  
**Auth:** Required

---

## 6. Widgets

| Widget | Dashboard position | Description |
|---|---|---|
| `CombinedCatalogWidget` | Main | Lazy-loaded product + package grid |
| `ProfileCompletionWidget` | Header | Profile completion % bar |
| `ProfileOverview` | Header | Name, avatar, quick stats |
| `QuickAccessWidget` | Header | Shortcut tiles (Orders, Cart, Wishlist) |
| `StatsOverview` | Header | Order/spend summary stats |
| `LatestBookings` | Body | Last 5 orders |
| `UnifiedHistoryWidget` | Body | Combined order + event history |
| `UserOrdersChart` | Body | Monthly orders bar chart |
| `UserSpendingChart` | Body | Monthly spending line chart |
| `VoucherCarouselWidget` | Body | Available vouchers carousel |

---

## 7. Guest Identity

The `GuestIdentity` service (`app/Services/GuestIdentity/GuestIdentity.php`) resolves a
**synthetic user row** from the browser session, allowing guests to start admin chat
conversations without registering.

```php
// Retrieve or create a guest user row for the current browser session
$guestUser = app(GuestIdentity::class)->user();
$guestId   = app(GuestIdentity::class)->id();
```

**How it works:**
1. On first call, a `User` record is created with `email = guest_{hash}@guest.local`.
2. The guest row's ID is stored in the session.
3. On subsequent requests the row is fetched by session key — no login required.
4. Guest rows are **excluded** from the user panel, admin panel, and navigation badges
   by filtering on the `@guest.local` email domain.

**GuestIdentity in MessagesPage:**

```php
// mount(?int $id)
$userId = app(GuestIdentity::class)->id();
// null for fully anonymous sessions that haven't opened a conversation yet
```

---

## 8. Chat System

### Architecture

```
Browser → MessagesPage (Filament page)
           └─ Welcome\Messages\Inbox (Livewire) ← inbox list
           └─ Welcome\Messages\Messages (Livewire) ← conversation thread
```

Both Livewire components live in `app/Livewire/Welcome/Messages/`.

### ChatService

`app/Services/ChatService/ChatService.php` is the single point of entry for all
chat operations:

| Method | Description |
|---|---|
| `getOrCreateInboxWithAdmin($userId)` | Find or create a 1-on-1 inbox between the given user and the super_admin |
| `sendContextMessage($inbox, $meta, $userId)` | Send a catalog context card (product/package enquiry) |
| `sendReportMessage($inbox, $category, $itemName, ...)` | Send a report card and set `cs_category` on the inbox |

### Admin Chat Flow (guest)

```
1. User clicks "Chat Admin" on a product/package detail page
2. PackageResource/ProductResource::getChatUser() checks Filament auth first,
   falls back to GuestIdentity::user()
3. ChatService::getOrCreateInboxWithAdmin($chatUser->getKey()) finds or creates
   an Inbox row with user_ids = [$guestUserId, $adminId]
4. ChatService::sendContextMessage() writes a Message row with the item meta
5. $livewire->redirect(MessagesPage::getUrl(['id' => $inbox->id]), navigate: true)
6. MessagesPage::mount() verifies the guest is in user_ids, loads the conversation
```

### Message Interactions (Welcome panel)

The conversation thread blade (`resources/views/Welcome/livewire/messages/messages/messages.blade.php`)
supports:

| Feature | Implementation |
|---|---|
| Emoji reactions | `toggleReaction(messageId, emoji)` Livewire method |
| Star / bookmark | `toggleStar(messageId)` |
| Reply threading | `setReplyTo(messageId)` → `$replyTo` property → `buildReplyToMeta()` |
| Delete for me | Sets `meta.deleted_by[]` with the current user ID |
| Delete for everyone | Hard-deletes the Message row (own messages or super_admin only) |
| Forwarding | `forwardMessageAction()` — slide-over with inbox picker |
| Translation | `translateMessageAction()` — calls Google Translate public API |
| Message info | `messageInfoAction()` — shows sender, time, read-by |
| Live typing indicator | `setTyping()` → Cache key `typing_{inboxId}_{userId}` (5 s TTL) |
| Poll for new messages | `wire:poll.visible.{interval}="pollMessages()"` |

> **Bug fix (2026-10-01):** `$canDeleteEveryone` in both the Welcome and User
> messages blades used `->hasRole()` on a potentially-null `chatUser()` / `auth()->user()`.
> Fixed with null-safe `?->hasRole('super_admin')`.

---

## 9. CBIR Search

CBIR (Content-Based Image Retrieval) lets visitors upload a photo and find visually similar
products/packages in the catalog.

**Route:** `GET /welcome/cbir-search`  
**Page class:** `CbirSearchPage`  
**Service:** `CBIRService` (`app/Services/CBIRService/CBIRService.php`)

**Flow:**
1. User uploads an image on `CbirSearchPage`.
2. `CBIRService` sends the image to the CBIR backend and receives ranked IDs.
3. IDs are stored in the session (`cbir_product_results_ids`, `cbir_package_results_ids`).
4. The product/package tables filter by those IDs, showing only matching items.
5. A "Tampilkan Semua" action clears the session keys and resets the catalog.

---

## 10. Differences from User Panel

| Aspect | User panel (`/user`) | Welcome panel (`/welcome`) |
|---|---|---|
| Auth middleware | `Filament\Authenticate` | `AuthenticateWelcome` |
| Login / registration pages | Present | **None** |
| User menu | Profile, Pengaturan, Riwayat, Ulasan, Privacy, Bantuan | **None** |
| Topbar button | Avatar / user menu | **Masuk** (guest) or **Beranda** (signed-in) |
| Theme switcher | Inside user menu | Standalone dropdown in topbar |
| Message blade | `User/livewire/messages/...` | `Welcome/livewire/messages/...` |
| `$isMine` detection | `auth()->id()` | `$this->chatUserId()` (GuestIdentity-aware) |
| Checkout guard | Filament auth check | Redirect to login |
| Chat Admin | Requires login | Works for guests (GuestIdentity) |
| Add to Favorites | Modal form + POST | **Direct action** (no modal) |

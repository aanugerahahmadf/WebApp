# Catalog Detail Actions — Developer Guide

Applies to `PackageResource` and `ProductResource` in the **Welcome panel**.

---

## Action Matrix

| Action | `->form()` | Guest | Signed-in | Redirect method |
|---|---|---|---|---|
| `share_item` | Yes (share URL) | Works | Works | N/A (modal only) |
| `report_item` | No | → login | ChatService | `$livewire->redirect()` |
| `buy_now_detail` | No | → login | → checkout | `redirect()` (page action) |
| `add_to_cart_detail` | Yes (quantity) | → login | Cart create | `$livewire->redirect()` |
| **`chat_admin`** | **No** | GuestIdentity | FilamentAuth | **`$livewire->redirect()`** |
| **`wishlist_detail`** | **No** | → login | Wishlist toggle | `$livewire->redirect()` |
| `write_review` | Yes (review form) | → login | Review create | `$livewire->redirect()` |
| `report_review` | No | Hidden | ChatService | `$livewire->redirect()` |

---

## `chat_admin` — How It Works

```php
// In PackageResource / ProductResource
Action::make('chat_admin')
    ->action(function ($record, Component $livewire) {
        // Step 1: resolve user (signed-in member OR guest row)
        $chatUser = static::getChatUser();
        if (! $chatUser) { /* show error, return */ }

        // Step 2: find or create the 1-on-1 inbox with super_admin
        $inbox = ChatService::getOrCreateInboxWithAdmin($chatUser->getKey());
        if (! $inbox) { /* show warning, return */ }

        // Step 3: send a catalog context message (shows product/package card)
        ChatService::sendContextMessage($inbox, [
            'type'  => 'package',
            'id'    => $record->id,
            'name'  => $record->name,
            'price' => $record->price,
            'image' => $record->image_url,
            'url'   => PackageResource::getUrl('view', ['record' => $record->id]),
        ], $chatUser->getKey());

        // Step 4: navigate to the messages page for this inbox
        $livewire->redirect(MessagesPage::getUrl(['id' => $inbox->id]), navigate: true);
    });
```

> **Why `$livewire->redirect()` and not `return redirect()`?**
>
> Filament infolist actions run inside a Livewire component. Returning a
> `RedirectResponse` from the action closure is silently ignored — Livewire
> never processes the return value of action closures. Only `$livewire->redirect()`
> (which calls `$this->js("window.location.href = '...'")` internally) actually
> navigates the browser.

---

## `wishlist_detail` — Why No Form

A `->form([Hidden::make('confirm')])` was previously on this action. Any `->form()` call
makes Filament render a modal with a **Submit** button. The form submission is a POST
request, and when the session has expired (e.g. tab left open overnight), Laravel returns
a **419 Page Expired** CSRF error.

The wishlist toggle has no data to collect from the user, so the form was removed:

```php
// Correct — direct action, no modal, no CSRF issue
Action::make('wishlist_detail')
    ->action(function ($record, Component $livewire) {
        if ($redirect = static::redirectGuestToLogin(...)) {
            $livewire->redirect($redirect->getTargetUrl(), navigate: true);
            return;
        }
        // toggle logic ...
    });
// No ->form() call
```

---

## `getChatUser()` — Guest Fallback

```php
protected static function getChatUser(): ?User
{
    if (Filament::auth()->check()) {
        return Filament::auth()->user(); // signed-in member
    }
    return app(GuestIdentity::class)->user(); // guest row
}
```

Returns `null` only if `GuestIdentity` fails to create/find a row, which should
not happen in practice (it creates on demand).

---

## `redirectGuestToLogin()` — Login Guard

```php
public static function redirectGuestToLogin(?string $intendedUrl = null): ?RedirectResponse
{
    if (Filament::auth()->check()) {
        return null; // signed in — no redirect
    }
    if ($intendedUrl) {
        session()->put('url.intended', $intendedUrl);
    }
    return redirect()->to(route(AuthenticateWelcome::LOGIN_ROUTE));
}
```

Calling code checks the return value:

```php
if ($redirect = static::redirectGuestToLogin($currentUrl)) {
    $livewire->redirect($redirect->getTargetUrl(), navigate: true);
    return;
}
// ... proceed with authenticated logic
```

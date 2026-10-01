<?php

namespace App\Services\GuestIdentity;

use App\Models\User\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Who a chat belongs to when nobody is signed in.
 *
 * Customer service has to answer people who never made an account, so the
 * welcome storefront lets guests open Messages. They are deliberately *not*
 * logged in: User::canAccessPanel() opens the 'welcome' panel to anyone
 * authenticated, and a guest row carries no role to hold that back, so signing
 * one in would hand them the cart, the orders and the settings along with the
 * chat. Instead a guest carries a long-lived `guest_id` cookie, and that id maps
 * to a real `users` row with an `@guest.local` email -- exactly the shape the
 * mobile app already produces through ChatController::guestStart. Reusing that
 * shape is what lets the admin inbox, the unread counters and the bot replies
 * work against a guest without any of them knowing guests are a thing.
 *
 * Registered as a singleton (see AppServiceProvider): resolving mints a cookie
 * and may create a row, and both have to happen at most once per request even
 * though half a dozen components ask for the identity independently.
 */
class GuestIdentity
{
    /**
     * Cookie holding the guest id. Encrypted on the way out by
     * EncryptCookies, which the panel already runs -- a plaintext copy would
     * let anyone mint an id pointing at somebody else's conversation.
     */
    public const COOKIE = 'welcome_guest_id';

    /**
     * A year. A thread that evaporates when the session cookie is cleared is
     * not a thread: a guest returning next month should still see the answer
     * they were given.
     */
    public const COOKIE_MINUTES = 525600;

    /**
     * Stored verbatim, never translated, and doubles as the "this guest has
     * not introduced itself yet" marker (see needsName()).
     *
     * The mobile flow used to store __('Tamu'), which meant the same browser
     * produced a differently named row depending on the locale live at the
     * time -- and left nothing stable to compare against. A name is a name;
     * whatever the admin's own locale renders it as is a display concern.
     */
    public const DEFAULT_NAME = 'Tamu';

    protected ?User $cached = null;

    protected bool $resolved = false;

    public function __construct(
        protected Request $request,
        protected AuthFactory $auth,
    ) {}

    /**
     * True when the visitor is browsing the storefront without an account.
     */
    public function isGuest(): bool
    {
        return ! $this->auth->guard()->check();
    }

    /**
     * The account chat actions should act as: the signed-in visitor, or the
     * guest row behind this browser. Null only when neither can be resolved.
     */
    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->cached;
        }

        $this->resolved = true;

        if ($authUser = $this->auth->guard()->user()) {
            return $this->cached = $authUser instanceof User ? $authUser : null;
        }

        return $this->cached = self::resolve($this->guestId());
    }

    public function id(): ?int
    {
        return $this->user()?->getKey();
    }

    /**
     * The id inside the cookie, minting and queueing one on first sight.
     */
    public function guestId(): string
    {
        $guestId = $this->request->cookie(self::COOKIE);

        if (! is_string($guestId) || $guestId === '') {
            $guestId = (string) Str::uuid();

            Cookie::queue(Cookie::make(
                name: self::COOKIE,
                value: $guestId,
                minutes: self::COOKIE_MINUTES,
                path: '/',
                domain: null,
                secure: $this->request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $guestId;
    }

    /**
     * Find or create the guest account behind an id.
     *
     * Shared with the mobile guest flow so the two surfaces cannot drift on
     * what a guest row looks like; ChatController::guestStart owned this
     * before and now defers here.
     */
    public static function resolve(string $guestId, ?string $name = null): User
    {
        $email = 'guest_'.md5($guestId).'@guest.local';

        /** @var User $guest */
        $guest = User::firstOrCreate(
            ['email' => $email],
            [
                'username' => 'tamu_'.substr(md5($guestId), 0, 8),
                'full_name' => filled($name) ? $name : self::DEFAULT_NAME,
                'password' => Hash::make(Str::random(40)),
            ],
        );

        return $guest;
    }

    /**
     * Record the name a guest gave us, so the admin is not answering "Tamu"
     * and has something to call them.
     */
    public function setName(string $name): void
    {
        $name = trim($name);

        if ($name === '' || ! $this->isGuest()) {
            return;
        }

        $guest = $this->user();

        if (! $guest) {
            return;
        }

        $guest->forceFill(['full_name' => $name])->save();
        $this->cached = $guest;
    }

    /**
     * Whether to still ask the guest who they are.
     *
     * Never for a signed-in visitor -- their name is already real. Comparing
     * against DEFAULT_NAME instead of storing a "has introduced themselves"
     * flag is a deliberate trade: a guest who literally types "Tamu" gets
     * asked once more, which costs them one text field, whereas a column to
     * track it would be permanent schema for a purely cosmetic distinction.
     */
    public function needsName(): bool
    {
        if (! $this->isGuest()) {
            return false;
        }

        $name = trim((string) $this->user()?->full_name);

        return $name === '' || $name === self::DEFAULT_NAME;
    }
}

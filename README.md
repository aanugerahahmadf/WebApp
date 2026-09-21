# 💍 Wedding Organizer — Wedding Flower Decorations

Wedding Organizer is a multi-platform application for wedding planning and decoration commerce. It combines a customer catalogue, orders, payments, chat, notifications, account security, visual/CBIR search, and a Filament administration panel.

It runs as a Laravel web application and supports NativePHP Mobile (Android/iOS) and NativePHP Electron (Windows/macOS).

> This is an application repository. Do not commit `.env`, production data, private keys, Firebase credentials, payment credentials, OAuth secrets, or generated local state.

## Contents

- Features
- Technology
- Requirements
- Quick start
- Configuration
- Development and platform commands
- Real-time services
- Tests and quality checks
- Project layout
- Security, deployment, contributing, and license

## Features

### User application

- Browse products and wedding service packages.
- Search the catalogue and use image-based/CBIR discovery when the configured AI service is available.
- Save favourites, apply vouchers, manage a cart, create orders, and follow transaction activity.
- Manage profile details, photo, address, preferred language, and WhatsApp number.
- Choose an international calling code from a searchable Filament selector with ISO country code, country name, and calling code.
- Use the message centre for conversations and attachments.
- Receive database, broadcast, native, and Firebase notifications when the platform/service is configured.
- Open an individual notification detail page. Security notifications can open sign-in activity while keeping contextual back navigation.
- Use password change, email-change OTP confirmation, two-factor controls, trusted devices, saved sign-in preferences, backup codes, and account check-up tools.
- Change language through the language switcher. User-facing static text must use Laravel translation helpers.

### Administration

- Manage users, roles, permissions, products, packages, media, vouchers, banners, reviews, orders, and transactions.
- Use Filament tables, forms, actions, filters, and dashboard widgets for daily operations.
- Manage customer-facing catalogue and content data.

### Supported platforms

| Platform | Capability |
| --- | --- |
| Web | Laravel browser app with Vite assets and browser APIs where supported |
| Mobile | NativePHP Mobile with Android/iOS integration where configured |
| Desktop | NativePHP Electron with Windows/macOS support where configured |

## Technology

| Area | Main technology |
| --- | --- |
| Backend | PHP 8.3+, Laravel 12 |
| User interface | Filament 3, Livewire 3, Blade, Tailwind CSS |
| Front-end build | Vite 7 and Node.js |
| Database | MySQL by default with Laravel migrations/seeders |
| Real-time | Laravel Reverb, Echo, Pusher-compatible configuration |
| API and authentication | Laravel sessions and Sanctum |
| Native apps | NativePHP Mobile, NativePHP Electron, NativePHP Laravel |
| Media/documents | Spatie Media Library, Dompdf, PhpSpreadsheet |
| Integrations | Firebase, Midtrans, Google/Facebook OAuth, Fonnte/WhatsApp, CBIR/AI |
| Quality tools | Pest, Laravel Pint, PHPStan, Rector |

Dependency versions are defined by `composer.json`, `composer.lock`, `package.json`, and the lockfiles. Use them for audits and reproducible deployments.

## Requirements

| Requirement | Purpose |
| --- | --- |
| PHP 8.3+ | Laravel runtime; Composer platform is set to PHP 8.4.99 |
| Composer 2 | PHP dependency installation |
| Node.js LTS + npm | Vite build tooling |
| MySQL | Default local database |
| Git | Clone, update, and contribute |
| Redis (optional) | Redis-backed cache, queue, or realtime services |
| Android SDK/JDK (mobile) | Android NativePHP development |
| Xcode (iOS) | iOS development on macOS |

External features require credentials from their providers. Values in `.env.example` are placeholders and not valid production secrets.

## Quick start

Run commands from the repository root:

```bash
git clone https://github.com/aanugerahahmadf/Wedding-Organizer.git
cd Wedding-Organizer
composer install
npm install
```

Create the environment file and application key:

```powershell
# Windows PowerShell
Copy-Item .env.example .env
php artisan key:generate
```

```bash
# macOS/Linux/Git Bash
cp .env.example .env
php artisan key:generate
```

Create the MySQL database named by `DB_DATABASE`, set its credentials in `.env`, then run:

```bash
php artisan migrate --seed
npm run build:web
php artisan serve --port=8000
```

Open `http://127.0.0.1:8000`. Use locally created accounts only; never publish or reuse production credentials.

For the standard local development stack (Laravel server, queue listener, Vite), run:

```bash
composer dev
```

## Configuration

### Environment files

| File | Use |
| --- | --- |
| `.env` | Shared base configuration |
| `.env.web` | Optional web-specific overrides |
| `.env.mobile` | Optional NativePHP Mobile overrides |
| `.env.desktop` | Optional NativePHP Electron overrides |

Platform files are layered over `.env`; values in a platform file take precedence.

At minimum configure URL, language, timezone, and database:

```dotenv
APP_URL=http://127.0.0.1:8000
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wedding_flowers_decorasi
DB_USERNAME=root
DB_PASSWORD=
```

After editing cached production configuration run:

```bash
php artisan config:clear
```

### Optional integrations

| Feature | Primary settings |
| --- | --- |
| Email and OTP | `MAIL_*` |
| Reverb broadcasts | `REVERB_*`, `VITE_REVERB_*` |
| Pusher-compatible broadcasts | `PUSHER_*` |
| Firebase push | `FIREBASE_*`, `FIREBASE_CREDENTIALS_PATH` |
| CBIR/AI image search | `AI_CORE_URL`, `CBIR_API_URL` |
| Google/Facebook sign-in | `GOOGLE_*`, `FACEBOOK_*` |
| Midtrans | `MIDTRANS_*` |
| WhatsApp/Fonnte | `FONNTE_TOKEN` |
| Android build/signing | `ANDROID_*`, `JAVA_HOME`, `NATIVEPHP_*` |

## Development and platform commands

### Web

Start Vite HMR in one terminal:

```bash
npm run dev:web
```

Start Laravel in another terminal:

```bash
php artisan serve --port=8000
```

For a compiled local build:

```bash
npm run build:web
php artisan serve --port=8000
```

### Assets

| Command | Result |
| --- | --- |
| `npm run dev` | Default Vite server |
| `npm run dev:web` | Web HMR |
| `npm run dev:mobile` | Mobile HMR |
| `npm run dev:desktop` | Desktop HMR |
| `npm run build` | Default Vite build |
| `npm run build:web` | Web bundle |
| `npm run build:mobile` | Mobile bundle |
| `npm run build:desktop` | Desktop bundle |
| `npm run build:all` | Builds all target bundles |

`public/build/` contains generated assets. Commit them only when the deployment workflow requires prebuilt assets.

### Native targets

| Target | Start command | Asset command | Notes |
| --- | --- | --- | --- |
| Web | `php artisan serve` | `npm run build:web` | Browser target |
| Android/iOS | `php artisan native:run` | `npm run build:mobile` | Requires NativePHP Mobile and platform tools |
| Windows/macOS | `php artisan native:serve` | `npm run build:desktop` | Requires NativePHP Electron |

Check or reset platform state:

```bash
php artisan platform:status
php artisan platform:clear
```

Read [docs/command-guide.md](docs/command-guide/command-guide.md) and [docs/command-decision-tree/command-decision-tree.md](docs/command-decision-tree/command-decision-tree.md) before setting up a native target.

## Real-time, queues, and notifications

Run a worker when `QUEUE_CONNECTION` is not `sync`:

```bash
php artisan queue:listen --tries=1
```

Run Reverb when the broadcast connection uses it:

```bash
php artisan reverb:start
```

Run scheduled tasks locally when enabled:

```bash
php artisan schedule:work
```

Notification flow:

1. An application event creates a Filament database notification.
2. The notification can broadcast to active clients.
3. Native/Firebase delivery is attempted only if the active platform and service configuration allow it.
4. A click opens `/user/notifications/{id}` for the selected item.
5. Login activity can open the appropriate security page and retain a validated return link.

## Tests and quality checks

Run relevant checks before release:

```bash
composer test
php artisan test
./vendor/bin/pint --test
./vendor/bin/pint
./vendor/bin/phpstan analyse
php artisan view:clear
php artisan view:cache
npm run build:web
```

On Windows PowerShell, use `vendor\bin\pint.bat` and `vendor\bin\phpstan.bat` if Unix executable files are unavailable.

## Project layout

| Path | Purpose |
| --- | --- |
| `app/Filament/` | Admin/user pages, resources, widgets |
| `app/Livewire/` | Interactive components |
| `app/Models/` | Eloquent models |
| `app/Services/` | Domain and integration services |
| `app/Support/` | Shared helpers |
| `config/` | Application configuration |
| `database/` | Migrations, factories, seeders |
| `docs/` | Platform and command guides |
| `lang/` | Laravel JSON/package translations |
| `public/` | Public entry point and built assets |
| `resources/` | Blade, CSS, JavaScript |
| `routes/` | Web, API, and platform routes |
| `tests/` | Pest/Laravel tests |

## Security and deployment

- Set `APP_DEBUG=false`, use HTTPS, secure cookies, and a unique production `APP_KEY`.
- Never commit environment files, Firebase credential files, keystores, SMTP passwords, OAuth secrets, payment keys, or database exports.
- Review roles, permissions, policies, and admin access before deployment.
- Supervise queue workers and scheduled tasks in production.
- Back up database and media before migrations or releases.
- Keep Composer/Node lockfiles committed for reproducible builds.
- Build assets and run migrations through the deployment pipeline, then clear/rebuild Laravel caches as appropriate.

## Documentation

| Document | Description |
| --- | --- |
| [docs/command-guide.md](docs/command-guide/command-guide.md) | Commands, prerequisites, troubleshooting |
| [docs/command-decision-tree.md](docs/command-decision-tree/command-decision-tree.md) | Select web, mobile, or desktop mode |
| [docs/environment-configuration.md](docs/environment-configuration/environment-configuration.md) | Environment layering |
| [docs/asset-compilation.md](docs/asset-compilation/asset-compilation.md) | Vite build process |
| [docs/platform-support.md](docs/platform-support/platform-support.md) | Platform architecture |
| [docs/platform-features.md](docs/platform-features/platform-features.md) | Feature matrix by platform |

## Contributing

1. Create a focused branch from the default branch.
2. Keep changes scoped and avoid unrelated generated files/formatting.
3. Update tests for behaviour changes.
4. Run relevant PHP, Blade, and asset checks.
5. Keep secrets and personal data out of commits.
6. Explain user-visible behaviour and verification in the pull request.

## License

This project is licensed under the [MIT License](LICENSE). The complete legal terms are in the root [`LICENSE`](LICENSE) file.

Copyright (c) 2026 Anugerah Ahmad.

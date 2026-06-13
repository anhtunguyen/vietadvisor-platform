# 🗺️ VIETADVISOR PLATFORM - ARCHITECTURE LOG

## ⚙️ 1. ENVIRONMENT & CONFIGURATION
- **Framework Type**: Custom MVC Core with Dependency Injection Registry (`App` class).
- **Domain Strategy**: Multilingual Subdomains (`vietadvisor.test`, `vi.vietadvisor.test`, `ru.vietadvisor.test`).
- **Assets URL**: Multi-protocol Cookie-Free Domain managed via `APP_ASSETS_DOMAIN` in `.env`.
- **Admin Security**: Obfuscated admin path variable `ADMIN_PATH` in `.env`, falls back to 404 fake error for bots.
- **Resource Management**: Globally shared extensions configuration (`STATIC_EXTENSIONS` in `.env`) to bypass firewall computing for stateless assets.
- **Dynamic Firewall Inbound**: Configurable rate limit thresholds (`RATE_LIMIT_MAX_GET`, `RATE_LIMIT_MAX_WRITE`, `RATE_LIMIT_BLOCK`) completely detached from source code into environment scope.

## 🛡️ 2. SECURITY FIREWALL PIPELINE (Order of Execution in public/index.php)
1. `\App\Security\RateLimiter::check()` -> Method-based dynamic rate limiting. Applies loose ceilings for resource viewing (`GET`) and tight restrictions for data mutation (`POST`, `PUT`, `DELETE`). Intercepts and rejects flood spikes within `0ms` via memory or via file mapping.
2. `Guard::validateIpBlacklist()` -> Fast JSON-based IP & CIDR subnet blocking. Tightly integrated with the Temporary Ban System to auto-unban expired restricted IPs.
3. `\App\Security\CorsHandle::handle()` -> Token-driven & Domain-bound CORS verification (Supports partners via JWT and local subdomains with Allow-Credentials).
4. `Guard::validateReferer()` -> Tight control over `HTTP_REFERER` based on `STATIC_EXTENSIONS` configuration to prevent Cross-Site Request Forgery (CSRF) and asset hotlinking.
5. `\App\Security\BotManager::validate()` -> Zone-aware bot detection and browser spoofing mitigation. Redirects bots visiting admin path to a 404 page.
6. `SessionGuard::validateProxy()` -> Block malicious open proxies.
7. `Guard::validateHost()` -> Mitigate Host Header Injection.
8. `SessionGuard::validateFingerprint()` -> Device tracking to prevent Session Hijacking.
9. `Guard::handleOnboarding()` -> Language routing mechanism based on user browser preference.

## 🗣️ 3. LOCALIZATION ENGINE (YAML Syntax)
- Powered by Symfony Translator using YAML loader (`addLoader('yaml')`).
- **Memory Optimized**: Strictly loads `DEFAULT_LANGUAGE` (as fallback bệ đỡ) and the current active Subdomain language file.
- **Pluralization**: Handled automatically natively via Symfony 5/6/7 formatting using `%count%` parameter inside `translations/messages.{lang}.yaml`.
- Global Translator function registered globally at `src/Helpers/functions.php` as `trans()`.

## 🖥️ 4. USER DEVICE ACCESSIBILITY
- `App::bind('client_info', ...)` -> Instantiated once at bootstrap using `WhichBrowser\Parser` package.
- Exposed to Smarty templates as `$client` for inline layout conditional switches.

## ⚡ 5. BACKEND CACHE ACCELERATOR
- `\App\Services\CacheService::boot()` -> Decoupled infrastructure gateway executing at bootstrap. Automatically sniffs environmental variables to map high-speed in-memory database connections (`Redis` or `Memcached`) into the Core Registry container under the handler name `cache_engine`. Gracefully falls back to filesystem if services are disconnected.

## 🎨 6. FRONTEND LAYOUT INHERITANCE (Smarty Engine)
- **Base Scaffold Layout**: `templates/layout.tpl` (Contains shared Global Meta, Apple Web App PWA Manifest, CSS, Icons, Dynamic Global Header, fixed Footer, and the Multilingual Language Switcher loop).
- **Child Implementation**: Child modules or views extend the base skeleton cleanly via `{extends file='layout.tpl'}` and override the specific dynamic area inside the `{block name="content"}` wrapper wrapper to reduce layout duplication.

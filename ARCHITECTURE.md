# 🗺️ VIETADVISOR PLATFORM - ARCHITECTURE LOG

## ⚙️ 1. ENVIRONMENT & CONFIGURATION
- **Framework Type**: Custom MVC Core with Dependency Injection Registry (`App` class).
- **Domain Strategy**: Multilingual Subdomains (`vietadvisor.test`, `vi.vietadvisor.test`, `ru.vietadvisor.test`).
- **Assets URL**: Multi-protocol Cookie-Free Domain managed via `APP_ASSETS_DOMAIN` in `.env`.
- **Admin Security**: Obfuscated admin path variable `ADMIN_PATH` in `.env`, falls back to 404 fake error for bots.

## 🛡️ 2. SECURITY FIREWALL PIPELINE (Order of Execution in public/index.php)
1. `Guard::validateIpBlacklist()` -> Fast JSON-based IP & CIDR subnet blocking.
2. `\App\Security\CorsHandle::handle()` -> Token-driven & Domain-bound CORS verification (Supports partners via JWT and local subdomains with Allow-Credentials).
3. `Guard::validateReferer()` -> Tight control over `HTTP_REFERER` to prevent Cross-Site Request Forgery (CSRF) and graphical hotlinking.
4. `\App\Security\BotManager::validate()` -> Zone-aware bot detection and browser spoofing mitigation. Redirects bots visiting admin path to a 404 page.
5. `SessionGuard::validateProxy()` -> Block malicious open proxies.
6. `Guard::validateHost()` -> Mitigate Host Header Injection.
7. `SessionGuard::validateFingerprint()` -> Device tracking to prevent Session Hijacking.
8. `Guard::handleOnboarding()` -> Language routing mechanism based on user browser preference.

## 🗣️ 3. LOCALIZATION ENGINE (YAML Syntax)
- Powered by Symfony Translator using YAML loader (`addLoader('yaml')`).
- **Memory Optimized**: Strictly loads `DEFAULT_LANGUAGE` (as fallback bệ đỡ) and the current active Subdomain language file.
- **Pluralization**: Handled automatically natively via Symfony 5/6/7 formatting using `%count%` parameter inside `translations/messages.{lang}.yaml`.
- Global Translator function registered globally at `src/Helpers/functions.php` as `trans()`.

## 🖥️ 4. USER DEVICE ACCESSIBILITY
- `App::bind('client_info', ...)` -> Instantiated once at bootstrap using `WhichBrowser\Parser` package.
- Exposed to Smarty templates as `$client` for inline layout conditional switches.

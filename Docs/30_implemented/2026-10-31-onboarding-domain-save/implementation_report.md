# Same-Origin Onboarding Save and Clear Errors

Date: **2026-10-31 WIB**. Base: `dd720d5d75001e804309a6374bc3dbbd727f87c2` plus uncommitted changes. No commit/push or VDI deployment performed for this change.

## Finding and Diagnostic Boundary

**User-confirmed resolution:** a hard refresh restored the VDI guide on 31 October, without database/configuration changes or agent deployment. The original failed request status was not captured. Stale assets/page state/session token are possible explanations, not individually proven causes. The local defensive changes described below were not deployed as the remote repair.

The screenshot's generic network warning comes from a catch shared by fetch failure, HTTP rejection, and invalid JSON. It does **not** establish that the internet connection failed or that the endpoint returned a particular status.

The layout emitted an absolute `route('onboarding.finish')`. If a reverse proxy leaves the application's request root as internal HTTP/localhost, that URL can be wrong for another computer or blocked as mixed content. The preference is same-origin, so it need not depend on that absolute root. This is a code-level risk, not a confirmed diagnosis of the remote screenshot.

The user confirmed VDI starts with `php artisan serve --port 4002`, which reads that machine's main `.env`, not this workspace's private UAT profile. A read-only public GET of the domain's login returned HTTP 200, an HTTPS login action, same-domain HTTPS assets, and `no-cache, private`. No authenticated remote onboarding request was submitted; its status, logs, and migration state remain unknown. Network status was requested from the user without cookies/tokens.

## Changes

- `resources/views/components/layouts/app.blade.php`: named route generated with `absolute=false`, yielding `/panduan/selesai` on the page's origin.
- `resources/js/onboarding.js`: expired session/CSRF 401/419 and auth redirects ask to reload/log in; 403 asks for an account check; 5xx asks for server assistance. Network and invalid/non-JSON responses remain failures rather than false success. Buttons are re-enabled and the guide remains retryable.
- `tests/Feature/DashboardOnboardingTest.php`: internal HTTP forced-root regression verifies a relative endpoint, retained token, first-login preference, and no inventory movement.
- `scripts/test-onboarding-browser.cjs`: verifies the actual POST URL and HTTP 200 for saving, plus controlled 401/419/403/500, network, and non-JSON failure/recovery scenarios. Existing role, session-persistence, mobile, and placement cases remain.
- [UAT guide](../../00_current/uat_guide.md): adds status-based VDI diagnosis and cautions against using setup/reset/key rotation as a workaround.

No controller persistence, route permission, CSRF exemption, proxy-trust expansion, session purge, schema, inventory rule, or live data was changed.

## Actual Verification

| Check | Result |
| --- | --- |
| Focused PHPUnit | **7 tests / 53 assertions passed** |
| Full PHPUnit / SQLite `:memory:` | **214 tests / 2352 assertions passed**, zero failures/errors; runtime 39.824 seconds |
| Real local HTTP onboarding browser / temporary MySQL | **14/14 groups passed**; five groups include controlled response/failure injection, not remote VDI failures |
| Stock safety | Original simulated Gudang/Apotek balances unchanged; reconciliation passed; generated browser database removed |
| Vite production build | Passed |
| Changed JS syntax / PHP lint / Pint | Passed |
| Public login read-only observation | HTTP 200 and HTTPS form/assets; not proof of authenticated save success |

Ignored local evidence: `storage/app/qa/inventory/onboarding-2026-10-31.xml`, `storage/app/qa/inventory/phpunit-results.xml`, and `storage/app/qa/onboarding/results.json` with browser screenshots. Results are from this working tree; historical published Capstone images/results were not replaced.

## Optional Diagnosis If It Recurs

The case is resolved by the user's hard refresh; no further remote action is required now. If it recurs, obtain the failing authenticated `POST /panduan/selesai` status. A 419 calls for session/cookie/CSRF checks; 500 requires a sanitized exception and read-only migration status, including the onboarding preference column; failed/blocked requires the request URL/browser console. Do not assume switching to the UAT runner fixes an unprovisioned VDI profile. Apply migrations or deployment changes only after confirming the intended environment/database and authorization.

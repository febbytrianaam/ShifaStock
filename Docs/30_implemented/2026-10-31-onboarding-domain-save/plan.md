# Onboarding Save Behind a Domain

Date: 2026-10-31. Scope: diagnose the reported inability to skip onboarding when accessing a VDI-hosted application through HTTPS.

1. Inspect endpoint generation, authenticated persistence, proxy/session configuration, and actual public login URLs without logging credentials.
2. Keep onboarding save on the page's origin rather than trusting an absolute server-generated host/scheme.
3. Distinguish session, access, server, network, and invalid-response failures; preserve retry, authentication, and CSRF protection.
4. Add a backend regression for an internal HTTP root and browser failure/retry coverage; use isolated synthetic databases only.
5. Document plain artisan versus private UAT runtime behavior, actual test results, and the limits of remote diagnosis. Do not deploy, reset sessions/databases, migrate the VDI, or publish without another request.

Local implementation and verification completed. The user subsequently confirmed a hard refresh resolved the VDI case. Its failing request status was not obtained; do not label the local hardening as the cause of that remote recovery.

# Privacy And Retention

## User Data That Exists

- User name
- Username
- Email
- Role
- Active/inactive status
- Password hash
- Login/logout audit events
- IP address and user agent in audit logs

## Operational Data Stored

- Medicine master data
- Units, packages, dosage/strength metadata
- Batch numbers and expiry dates
- Stock balances and batch balances
- Inventory movement history
- Receipt documents and line items
- Daily physical check records
- Audit log metadata

## What Is Not Stored

- No patient records were identified in the inspected code.
- No payment or insurance data was identified.
- No external third-party processing is wired into the app runtime.

## Retention Assumptions

- Assumption: inventory movement and audit history should be retained long-term for accountability.
- Assumption: user records are deactivated rather than physically deleted.
- Unknown: clinic-approved retention duration for audit logs, stock history, and user metadata.

## Deletion Assumptions

- Users are currently nonactivated/deactivated for removal-like behavior.
- Hard deletion policy for audit logs and operational history is unknown.
- Any future deletion must preserve inventory accountability.

## Third-Party Processing

No runtime third-party data processing was identified. Dependencies are installed via Composer/npm. GitHub stores source code.

## Privacy Risks

- Audit logs can contain IP addresses, user agents, actor identity, and change metadata.
- Medicine stock and supplier information may be operationally sensitive.
- If deployed publicly, session security, HTTPS, and rate limiting need confirmation.


# Inventory Flow Safeguards

1. Require explicit batches for special adjustments on both client and server.
2. Preserve aggregate/batch/ledger agreement and reject quantities below database precision.
3. Inspect adjacent stock mutation and RBAC paths for unintended effects.
4. Lock mutable documents before validating final status or reversal eligibility.
5. Add isolated regression tests and document remaining limitations.

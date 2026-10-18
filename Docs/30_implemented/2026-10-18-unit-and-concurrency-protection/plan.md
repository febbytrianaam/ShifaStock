# Unit And Concurrency Protection

1. Prevent historical inventory from being relabeled by medicine-unit edits or reference-unit renames.
2. Keep purchase packaging independently editable and permit unit changes before a medicine is used.
3. Serialize inventory transactions before stock reads/FEFO/snapshot construction, including absent balance rows.
4. Recheck movement references after obtaining the shared transaction lock.
5. Test competing processes in a disposable MySQL database; fix any MySQL-specific behavior exposed by those tests.
6. Run isolated regression tests, additive migrations, Blade compilation, and a read-only clinic-data audit.

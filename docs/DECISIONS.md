# Decision log

The canonical record of closed decisions, their reasoning, and rejected alternatives. Consult it before reopening a question. Entries run oldest first; append new decisions with a blank line before each dated heading. The issue tracker remains the record of each ticket's status.

## 2026-11-06 Retain retired implementations outside coverage (#11)

**Decision.** Keep all six PHP files in `includes/_unused` and explicitly exclude that directory from coverage and mutation testing. Measure the four active PHP files loaded by the plugin.

**Why.** The plugin does not load the retired files. They duplicate active class names and already sit outside the quality tools' population. Retention preserves the existing 13-file download without maintaining tests for unregistered implementations.

**Rejected.** Deleting the files changes the download and risks external manual includes. Testing the retired implementations requires isolated processes for duplicate classes and maintains behavior the plugin does not register. Retired code remaining in downloads is an accepted cost, not a claim that it is covered.

**Companion.** [UAMS-Web/UAMSWP-Content-Syndication#11](https://github.com/UAMS-Web/UAMSWP-Content-Syndication/issues/11), which remains a single coverage issue.

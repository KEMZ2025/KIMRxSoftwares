# KIM Rx UAT And Release Rehearsal Guide

## 1. Purpose

Use this guide to run a practical user acceptance test before live rollout or before a major update reaches real clients.

This guide is designed around the current KIM Rx platform setup:

- `Platform Sandbox` for risky testing
- `VIP Pharmacy` for normal tenant workflow testing
- owner workspace for package, subscription, backup, and recovery checks

## 2. Recommended Order

Run UAT in this order:

1. `Platform Sandbox`
2. `VIP Pharmacy`
3. deployment rehearsal commands
4. final fix pass for any issues found

## 3. Core Environments

### Platform Sandbox

Use this when:

- testing a new feature
- testing risky flows
- trying destructive or unusual scenarios
- checking if a code change broke something

### VIP Pharmacy

Use this when:

- simulating a real client tenant
- checking role-based workflows
- checking package or module visibility
- validating normal daily pharmacy operations

## 4. Owner Workspace UAT

Login as platform owner and confirm:

1. Owner workspace opens
2. client setup screen opens
3. package preset selection works
4. subscription quick actions are visible
5. backups screen opens
6. client exports screen opens
7. support settings screen opens
8. owner dashboard summary cards load

Expected result:

- no 500 errors
- no broken navigation
- owner can move between workspace, clients, backups, and exports normally

## 5. Tenant Admin UAT

Login as tenant admin inside `VIP Pharmacy` and confirm:

1. dashboard opens
2. product create page opens
3. purchase create page opens
4. sale create page opens
5. reports screen opens
6. settings screen opens
7. users screen opens
8. roles screen opens
9. support screen opens

Expected result:

- all allowed modules open normally
- disabled modules remain hidden or blocked correctly

## 6. Dispenser UAT

Login as dispenser and test:

1. search medicines
2. create a normal retail sale
3. create a wholesale sale if wholesale is enabled
4. use cash payment
5. use credit sale if allowed
6. use insurance sale if enabled
7. print receipt or invoice

Expected result:

- quantities reduce correctly
- batches are respected
- totals calculate correctly
- payment mode logic stays correct

## 7. Cashier UAT

Login as cashier and test:

1. open shift with `0`
2. open shift with float amount
3. process cash sale
4. confirm drawer warning threshold behavior
5. record cash draw with reason
6. close shift with counted cash
7. review shortage or overage result

Expected result:

- drawer only counts the intended cash logic
- draw cannot exceed tracked cash
- shift close reconciliation works

## 8. Stock Manager UAT

Login as stock manager and test:

1. create or edit product
2. receive purchase stock
3. view stock page
4. run stock adjustment
5. confirm expiry or batch visibility

Expected result:

- stock increases and decreases correctly
- batch and expiry information stays accurate

## 9. Accountant UAT

Login as accountant and test:

1. accounting overview
2. chart of accounts
3. general ledger
4. trial balance
5. profit and loss
6. balance sheet
7. expenses
8. fixed assets if enabled

Expected result:

- accounting pages load
- exports or print views open
- insurance and payment entries appear in the expected area

## 10. Insurance UAT

If insurance is enabled for the client, test:

1. create insurer
2. create insurance sale from POS
3. confirm patient top-up and insurer-covered split
4. open claims desk
5. create a remittance
6. create a claim batch
7. open insurer statement

Expected result:

- insurance part does not distort normal cash or receivable logic
- claim status and insurer balances update correctly

## 11. Backup And Recovery UAT

Login as platform owner and test:

1. create full platform backup
2. review backup manifest
3. create client export
4. review client export manifest
5. import client export as a clone
6. confirm imported clone appears as a new client

Expected result:

- backup and export archives generate successfully
- clone import does not overwrite an existing client

## 12. Deployment Rehearsal

Run these commands in order:

```bash
php artisan platform:backup:auto --force-run --keep=14
php artisan platform:go-live-check --allow-non-production
php artisan platform:post-deploy-smoke-test
```

Expected result:

- backup command succeeds
- go-live check has no blocking failures for rehearsal
- smoke test has no blocking failures

## 13. Sign-Off Checklist

Mark each as `PASS`, `FAIL`, or `N/A`:

- owner workspace
- client setup
- backups
- client exports
- tenant admin core screens
- dispenser sale flow
- cashier cash drawer flow
- stock flow
- accounting flow
- insurance flow
- backup and clone import
- deployment rehearsal commands

## 14. Rule For Fixes

After UAT:

- fix only real issues found during testing
- rerun the affected role flow
- rerun:

```bash
php artisan platform:post-deploy-smoke-test
```

before calling the release ready

# UOG Rosewood Royale backend test reorganisation

Scope: `/Users/hsuhtet/KMD/PJ/Program/backend` only. No frontend, Playwright, Bruno, production database configuration, existing database contents or original uploads were changed. No commits or pushes were made.

## Classification and preservation

- Before: 93 surviving tests in 16 files; 261 deleted Git-tracked tests in 43 files. Deleted sources were inspected from the backend repository HEAD without checkout/reset.
- After: 354 test statements: 10 Unit and 344 Integration. Every original surviving and recovered test statement is preserved byte-for-byte, including assertions, dataset chains and security checks.
- Unit: no application boot, API call, database query, external service or real storage. Unsaved Eloquent models used as values do not make a test an integration test.
- Integration: Laravel routing/middleware, authentication, Eloquent persistence, services, Blade/document converters or storage participate. Mocked mail/HTTP/converter boundaries do not remove the remaining component integration.
- The six existing arithmetic checks are retained as formula examples. They recalculate formulas inside the test rather than exercise production calculation code; the report must not claim production-code coverage from those six checks.
- The pure DocumentFilename check was extracted intact from the recovered SaleContractDocument integration file.
- Unit/Timebox1 and Unit/Timebox5 have no audited genuine unit tests. Their `.gitkeep` files retain the requested directories without introducing placeholder tests.

## Assertion-level overlap decisions

| Surviving workflow | Recovered checks retained | Why the coverage is not replaced |
|---|---|---|
| PropertyManagement | BuildingRoomCrud; PublicPropertyDataRule | Surviving CRUD covers create/read/update and image upload; recovered assertions add deletion/bulk protection, soft-delete retention, per-building number uniqueness and public field provenance. |
| Auth | AccountOnboarding; ChangePassword; PasswordReset | Login/me/logout checks do not assert reset-token ownership, anti-enumeration, forced first-login change, password hashing, cross-user isolation or complete token revocation. |
| CustomerContract | RentContractDraft; SaleContractDraft; SaleApproval; contract documents | Happy-path approvals do not replace installment recalculation, number non-reuse, joint-party search, forbidden deposit fields, rejection or document authorization. |
| UtilityApproval / UtilityInvoice | ConsolidatedBilling; InvoiceSchedule; InvoiceSourceMix; UtilitySeeder | Recovered assertions add finalized-bill immutability, source/period uniqueness, sale installments, due-date boundaries, meter continuity and deterministic seeding. |
| PaymentReceipt | BillingWorkflow; PaymentConcurrency; ReceiptDelivery; PaymentNotification | Current payment approval is not the same as asserting partial-payment rejection state, contract completion, notification recipients, controlled conflicts, resend/retry state or receipt cardinality. |
| MaintenanceAdmin / MaintenanceFeeInvoice | CustomerMaintenanceRequest; MaintenanceCategory; LifecycleSafety | Recovered assertions add attachment validation, active-category visibility, rejection detail, eligible-room lists and historical-record preservation. |
| Dashboard role checks | AdminDashboardChart; AdminListSorting; ListExportPdf | Role access alone does not assert metric amounts, severity ordering, capped alert counts, KPI consistency or document layout. |
| Customer ownership checks | EqualContractPartyPortal; CustomerResourceNotFound; CustomerNotificationRead | Equal second-party entitlements, safe 404 response copy and durable notification read state are additional outcomes. |
| Other tests | ApplicationSmokeTest | The recovered scaffold asserts the root HTTP response; no surviving test asserts this specific boot/routing outcome. It is retained as a smoke check, not represented as business-workflow coverage. |

No whole deleted test file was proven genuinely redundant. Repeated happy paths remain where fixtures, asserted fields, transitions or security boundaries differ. Exact duplicate-body comparison found none; absence of textual identity alone was not treated as proof of unique behaviour. No tests were excluded merely to improve results.

Some inherited concurrency tests issue sequential requests or explicitly open a transaction without demonstrating simultaneous competing sessions. Keep their assertions, but describe them as conflict/idempotency checks unless actual parallel execution is demonstrated. Likewise, mocked PDF converters verify application integration and response contracts rather than browser rendering fidelity.

## Final directory tree

```text
tests/
  Unit/
    Timebox1/
      .gitkeep
    Timebox2/
      DocumentFilenameTest.php
      InstallmentCalculationsTest.php
    Timebox3/
      UtilityCalculationsTest.php
    Timebox4/
      InvoiceBalanceCalculationsTest.php
      PaymentFinancialSummaryTest.php
    Timebox5/
      .gitkeep
  Integration/
    Timebox1/
      AccountOnboardingTest.php
      AuthTest.php
      BuildingRoomCrudTest.php
      ChangePasswordTest.php
      PasswordResetTest.php
      PropertyManagementTest.php
      PublicPropertyAssistantTest.php
      PublicPropertyDataRuleTest.php
    Timebox2/
      CustomerContractTest.php
      CustomerPortalTest.php
      CustomerRentAssistantTest.php
      CustomerResourceNotFoundTest.php
      EqualContractPartyPortalTest.php
      PhoneValidationTest.php
      RentContractDraftTest.php
      SaleApprovalTest.php
      SaleContractDocumentTest.php
      SaleContractDraftTest.php
      UserEmailPolicyTest.php
    Timebox3/
      ConsolidatedBillingTest.php
      InvoiceScheduleTest.php
      InvoiceSourceMixTest.php
      LateFeeSeedSupportTest.php
      SeedInvoiceNumberTest.php
      UtilityApprovalTest.php
      UtilityBillingPeriodTest.php
      UtilityBulkImportTest.php
      UtilityCreationTest.php
      UtilityInvoiceTest.php
      UtilityListTest.php
      UtilitySeederTest.php
      UtilityTypesAndRatesTest.php
    Timebox4/
      BillingDisplayResourceTest.php
      BillingWorkflowTest.php
      ConcurrencyWorkflowTest.php
      CustomerNotificationRecipientTest.php
      DocumentPdfDownloadTest.php
      InvoiceLateFeeApprovalTest.php
      PaymentConcurrencyTest.php
      PaymentMethodArchitectureTest.php
      PaymentNotificationWorkflowTest.php
      PaymentReceiptTest.php
      ReceiptDeliveryWorkflowTest.php
      ReceiptListPaymentMethodFilterTest.php
    Timebox5/
      AdminDashboardChartTest.php
      AdminListSortingTest.php
      ApplicationSmokeTest.php
      ApplicationTimezoneTest.php
      ContactInquiryTest.php
      CustomerMaintenanceRequestTest.php
      CustomerNotificationReadTest.php
      DemoDataIntegrityTest.php
      LifecycleSafetyTest.php
      ListExportPdfTest.php
      MaintenanceAdminTest.php
      MaintenanceCategoryTest.php
      MaintenanceFeeInvoiceTest.php
  Support/
    UtilityFixtures.php
  Pest.php
  TestCase.php
```

The former Feature folders were removed only after the byte-preservation audit and actual-project discovery both confirmed all 354 tests. The shared Timebox3 helper now lives in tests/Support/UtilityFixtures.php.

## Relocated surviving files

| Original path | Final path | Tests | Reason |
|---|---|---:|---|
| `tests/Feature/Admin/UserEmailPolicyTest.php` | `tests/Integration/Timebox2/UserEmailPolicyTest.php` | 5 | Resident/customer email policy is primarily customer management; staff restrictions are secondary. |
| `tests/Feature/Timebox1/AuthTest.php` | `tests/Integration/Timebox1/AuthTest.php` | 9 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox1/PropertyManagementTest.php` | `tests/Integration/Timebox1/PropertyManagementTest.php` | 9 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox2/CustomerContractTest.php` | `tests/Integration/Timebox2/CustomerContractTest.php` | 9 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityApprovalTest.php` | `tests/Integration/Timebox3/UtilityApprovalTest.php` | 4 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityBillingPeriodTest.php` | `tests/Integration/Timebox3/UtilityBillingPeriodTest.php` | 4 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityBulkImportTest.php` | `tests/Integration/Timebox3/UtilityBulkImportTest.php` | 6 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityCreationTest.php` | `tests/Integration/Timebox3/UtilityCreationTest.php` | 1 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityInvoiceTest.php` | `tests/Integration/Timebox3/UtilityInvoiceTest.php` | 4 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityListTest.php` | `tests/Integration/Timebox3/UtilityListTest.php` | 2 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox3/UtilityTypesAndRatesTest.php` | `tests/Integration/Timebox3/UtilityTypesAndRatesTest.php` | 1 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox4/PaymentReceiptTest.php` | `tests/Integration/Timebox4/PaymentReceiptTest.php` | 13 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox5/MaintenanceAdminTest.php` | `tests/Integration/Timebox5/MaintenanceAdminTest.php` | 6 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Feature/Timebox5/MaintenanceFeeInvoiceTest.php` | `tests/Integration/Timebox5/MaintenanceFeeInvoiceTest.php` | 11 | Existing timebox workflow; API, middleware, model and database integration. |
| `tests/Unit/PaymentFinancialSummaryTest.php` | `tests/Unit/Timebox4/PaymentFinancialSummaryTest.php` | 3 | Production payment-summary helper with unsaved models and no database or application bootstrap. |
| `tests/Unit/BillingCalculationsTest.php` | `tests/Unit/Timebox3/UtilityCalculationsTest.php` | 2 | Pure utility usage and amount arithmetic. These preserve illustrative formula checks; they do not invoke production calculation code. |
| `tests/Unit/BillingCalculationsTest.php` | `tests/Unit/Timebox4/InvoiceBalanceCalculationsTest.php` | 3 | Pure invoice due/balance and approved-payment arithmetic. These preserve illustrative formula checks; they do not invoke production calculation code. |
| `tests/Unit/BillingCalculationsTest.php` | `tests/Unit/Timebox2/InstallmentCalculationsTest.php` | 1 | Pure contract installment-interest arithmetic. These preserve illustrative formula checks; they do not invoke production calculation code. |

## Recovered files and primary timebox ownership

| Deleted source | Final path | Tests | Missing coverage / primary workflow |
|---|---|---:|---|
| `tests/Feature/Admin/AdminDashboardChartTest.php` | `tests/Integration/Timebox5/AdminDashboardChartTest.php` | 5 | Administration dashboard metrics, alerts and approval KPI consistency. |
| `tests/Feature/Admin/AdminListSortingTest.php` | `tests/Integration/Timebox5/AdminListSortingTest.php` | 4 | Shared administration sorting rules span property, utilities and maintenance. |
| `tests/Feature/Admin/BillingDisplayResourceTest.php` | `tests/Integration/Timebox4/BillingDisplayResourceTest.php` | 12 | Billing display resources and joint-party payment/receipt attribution; contract fields support billing. |
| `tests/Feature/Admin/BillingWorkflowTest.php` | `tests/Integration/Timebox4/BillingWorkflowTest.php` | 7 | Payment settlement drives invoice, receipt and contract completion transitions. |
| `tests/Feature/Admin/BuildingRoomCrudTest.php` | `tests/Integration/Timebox1/BuildingRoomCrudTest.php` | 8 | Property deletion, bulk safeguards and within-building room uniqueness are absent from surviving CRUD checks. |
| `tests/Feature/Admin/ConcurrencyWorkflowTest.php` | `tests/Integration/Timebox4/ConcurrencyWorkflowTest.php` | 7 | Primary transaction-consistency workflow covers payment approval; contract, utility and maintenance safeguards remain together with shared fixtures. |
| `tests/Feature/Admin/ConsolidatedBillingTest.php` | `tests/Integration/Timebox3/ConsolidatedBillingTest.php` | 10 | Combines approved utility charges and recurring contract charges into one bill, including idempotency and finalized-invoice protection. |
| `tests/Feature/Admin/CustomerNotificationRecipientTest.php` | `tests/Integration/Timebox4/CustomerNotificationRecipientTest.php` | 3 | Billing document delivery and notification recipients use current joint-party email addresses; contract delivery is secondary. |
| `tests/Feature/Admin/DemoDataIntegrityTest.php` | `tests/Integration/Timebox5/DemoDataIntegrityTest.php` | 11 | Cross-system demonstration seed integrity, image assets and repeatability are administration/data-quality checks. |
| `tests/Feature/Admin/DocumentPdfDownloadTest.php` | `tests/Integration/Timebox4/DocumentPdfDownloadTest.php` | 6 | Invoice, utility and receipt document rendering/download integration, with converter doubles. |
| `tests/Feature/Admin/InvoiceLateFeeApprovalTest.php` | `tests/Integration/Timebox4/InvoiceLateFeeApprovalTest.php` | 14 | Invoice approval snapshots, metered review edits and financial document policy. |
| `tests/Feature/Admin/InvoiceScheduleTest.php` | `tests/Integration/Timebox3/InvoiceScheduleTest.php` | 6 | Recurring utility/contract billing schedule generation and issue visibility. |
| `tests/Feature/Admin/InvoiceSourceMixTest.php` | `tests/Integration/Timebox3/InvoiceSourceMixTest.php` | 7 | Billing source aggregation, period uniqueness and deterministic financial seeding. |
| `tests/Feature/Admin/LateFeeSeedSupportTest.php` | `tests/Integration/Timebox3/LateFeeSeedSupportTest.php` | 3 | Late-fee configuration seeding and billing-policy snapshot attributes. |
| `tests/Feature/Admin/LifecycleSafetyTest.php` | `tests/Integration/Timebox5/LifecycleSafetyTest.php` | 15 | Shared administrative lifecycle rules preserve historical property, customer and financial records. |
| `tests/Feature/Admin/ListExportPdfTest.php` | `tests/Integration/Timebox5/ListExportPdfTest.php` | 3 | Shared administration list-export layout and converter integration. |
| `tests/Feature/Admin/MaintenanceCategoryTest.php` | `tests/Integration/Timebox5/MaintenanceCategoryTest.php` | 5 | Maintenance-category administration and historical-category availability. |
| `tests/Feature/Admin/PaymentConcurrencyTest.php` | `tests/Integration/Timebox4/PaymentConcurrencyTest.php` | 8 | Payment conflict handling, settlement invariants and duplicate-receipt prevention. |
| `tests/Feature/Admin/PaymentMethodArchitectureTest.php` | `tests/Integration/Timebox4/PaymentMethodArchitectureTest.php` | 10 | Payment-method administration, QR uploads and customer method restrictions. |
| `tests/Feature/Admin/PaymentNotificationWorkflowTest.php` | `tests/Integration/Timebox4/PaymentNotificationWorkflowTest.php` | 2 | Payment approval/rejection notifications and receipt cardinality. |
| `tests/Feature/Admin/PhoneValidationTest.php` | `tests/Integration/Timebox2/PhoneValidationTest.php` | 2 | Resident phone validation is customer-profile management; staff reuse is secondary. |
| `tests/Feature/Admin/ReceiptDeliveryWorkflowTest.php` | `tests/Integration/Timebox4/ReceiptDeliveryWorkflowTest.php` | 9 | Receipt delivery, retry/resend state, current-recipient security and visibility. |
| `tests/Feature/Admin/ReceiptListPaymentMethodFilterTest.php` | `tests/Integration/Timebox4/ReceiptListPaymentMethodFilterTest.php` | 1 | Receipt filter traverses related payment method. |
| `tests/Feature/Admin/RentContractDraftTest.php` | `tests/Integration/Timebox2/RentContractDraftTest.php` | 4 | Rent draft defaults, installment duration and recalculation. |
| `tests/Feature/Admin/SaleApprovalTest.php` | `tests/Integration/Timebox2/SaleApprovalTest.php` | 3 | Sale contract approval/rejection and resulting listing visibility. |
| `tests/Feature/Admin/SaleContractDocumentTest.php` | `tests/Unit/Timebox2/DocumentFilenameTest.php` | 1 | Canonical document filename helper takes scalar values and requires neither Laravel bootstrapping nor database access. Primary ownership is contract documents; invoice/receipt/utility cases remain intact. |
| `tests/Feature/Admin/SaleContractDocumentTest.php` | `tests/Integration/Timebox2/SaleContractDocumentTest.php` | 6 | Contract document download/export/email authorization; pure filename test extracted separately. |
| `tests/Feature/Admin/SaleContractDraftTest.php` | `tests/Integration/Timebox2/SaleContractDraftTest.php` | 9 | Sale contract number lifecycle, financial validation, filters and joint-customer search. |
| `tests/Feature/Admin/SeedInvoiceNumberTest.php` | `tests/Integration/Timebox3/SeedInvoiceNumberTest.php` | 5 | Financial billing seed idempotency, canonical numbering and legacy normalization. |
| `tests/Feature/Admin/UtilitySeederTest.php` | `tests/Integration/Timebox3/UtilitySeederTest.php` | 1 | Utility meter continuity, unique billing periods and importable demonstration month. |
| `tests/Feature/Ai/CustomerRentAssistantTest.php` | `tests/Integration/Timebox2/CustomerRentAssistantTest.php` | 4 | Authenticated tenant contract context and rent-assistant proxy integration; AI HTTP calls are faked. |
| `tests/Feature/Ai/PublicPropertyAssistantTest.php` | `tests/Integration/Timebox1/PublicPropertyAssistantTest.php` | 3 | Public property assistant proxy, validation and upstream failure handling; AI HTTP calls are faked. |
| `tests/Feature/ApplicationTimezoneTest.php` | `tests/Integration/Timebox5/ApplicationTimezoneTest.php` | 2 | Application-wide timezone configuration and its payment calendar-day consequence. |
| `tests/Feature/Auth/AccountOnboardingTest.php` | `tests/Integration/Timebox1/AccountOnboardingTest.php` | 3 | Authentication onboarding, hashed temporary passwords, email delivery and forced first-login change. |
| `tests/Feature/Auth/ChangePasswordTest.php` | `tests/Integration/Timebox1/ChangePasswordTest.php` | 4 | Password security, user isolation and token revocation. |
| `tests/Feature/Auth/PasswordResetTest.php` | `tests/Integration/Timebox1/PasswordResetTest.php` | 11 | Password recovery token ownership, anti-enumeration, delivery gating and branding. |
| `tests/Feature/ContactInquiryTest.php` | `tests/Integration/Timebox5/ContactInquiryTest.php` | 4 | Public contact intake and administrative inbox/read-state integration. |
| `tests/Feature/Customer/CustomerMaintenanceRequestTest.php` | `tests/Integration/Timebox5/CustomerMaintenanceRequestTest.php` | 9 | Maintenance eligibility, role/ownership isolation, rejection detail and attachment validation. |
| `tests/Feature/Customer/CustomerNotificationReadTest.php` | `tests/Integration/Timebox5/CustomerNotificationReadTest.php` | 4 | Persistent system notification read state and role authorization. |
| `tests/Feature/Customer/CustomerPortalTest.php` | `tests/Integration/Timebox2/CustomerPortalTest.php` | 15 | Primary customer/contract portal integration includes related financial views and contract documents. |
| `tests/Feature/Customer/CustomerResourceNotFoundTest.php` | `tests/Integration/Timebox2/CustomerResourceNotFoundTest.php` | 3 | Customer tenancy/access boundaries and safe 404 responses across linked resources. |
| `tests/Feature/Customer/EqualContractPartyPortalTest.php` | `tests/Integration/Timebox2/EqualContractPartyPortalTest.php` | 9 | Joint-contract entitlements govern shared billing, maintenance and AI context. |
| `tests/Feature/ExampleTest.php` | `tests/Integration/Timebox5/ApplicationSmokeTest.php` | 1 | HTTP application-root smoke test; retained because no surviving test asserts that boot/routing response. |
| `tests/Feature/Public/PublicPropertyDataRuleTest.php` | `tests/Integration/Timebox1/PublicPropertyDataRuleTest.php` | 2 | Public property field provenance, gallery exposure and inventory statistics. |

## Configuration and commands

- README.md test paths and focused execution examples now match the final structure.
- phpunit.xml now declares exactly Unit and Integration suites. The five timebox subdirectories are discovered recursively.
- Pest binds Tests\TestCase to Integration only; Unit retains the plain PHPUnit base class. The shared utility fixture include points to tests/Support/UtilityFixtures.php.
- All tests are Pest closures; no moved test namespaces or imports required changes. Composer already maps Tests\ to tests/.
- tests/TestCase.php retains its existing dedicated-local-MySQL guard; only its category comment changes.
- The project already configured MySQL rosewood_test in phpunit.xml and .env.testing. No SQLite in-memory setup existed, and no database-driver setting was changed.

Normal commands, from this backend only, after deliberately selecting an isolated disposable test database:

```sh
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test
php artisan test tests/Integration/Timebox1
php artisan test tests/Unit/Timebox2
```

Integration tests use RefreshDatabase and fixture seeders. For this investigation, tests ran against a newly initialized standalone MySQL instance on 127.0.0.1:13316 with its own rosewood_test database, not the existing server on port 3306. A copied Laravel base path kept storage, public files, caches and logs inside backend/.test-reorganisation/runtime. Original uploads and existing database files were not copied into that runtime. Application source/configuration copies were unchanged.

A recovered public-gallery test had a hidden dependency on an image file created by unrelated tests. Its new file-scoped beforeEach uses fake public storage and creates rooms/test.jpg. This removes test-order dependence without changing either original test body or assertion, and never writes a real upload.

Direct discovery was also run against the actual reorganised project (without executing test bodies) and found all 354 tests. Verification results below are populated from JUnit outputs. Both disposable database servers were shut down and the generated verification runtimes/datadirs were removed after evidence was saved.

## Results

| Run | Discovered | Passed | Failed | Skipped | Errors |
|---|---:|---:|---:|---:|---:|
| Surviving suite before reorganisation | 93 | 93 | 0 | 0 | 0 |
| Initial standalone Integration | 344 | 340 | 4 | 0 | 0 |
| Original-layout recheck of four initial failures | 4 | 1 | 3 | 0 | 0 |
| Final complete Laravel suite | 354 | 351 | 3 | 0 | 0 |
| Actual-project standalone Unit | 10 | 10 | 0 | 0 | 0 |

The initial Integration run preceded the independent gallery fixture. The final complete run exercises both final suites; its Integration subset is the final Integration result below. Standalone Unit was also verified directly in the actual project without a Laravel/database bootstrap.

| Category | Timebox | Discovered | Passed | Failed | Skipped | Errors |
|---|---:|---:|---:|---:|---:|---:|
| Unit | 1 | 0 | 0 | 0 | 0 | 0 |
| Unit | 2 | 2 | 2 | 0 | 0 | 0 |
| Unit | 3 | 2 | 2 | 0 | 0 | 0 |
| Unit | 4 | 6 | 6 | 0 | 0 | 0 |
| Unit | 5 | 0 | 0 | 0 | 0 | 0 |
| Integration | 1 | 49 | 49 | 0 | 0 | 0 |
| Integration | 2 | 69 | 69 | 0 | 0 | 0 |
| Integration | 3 | 54 | 53 | 1 | 0 | 0 |
| Integration | 4 | 92 | 92 | 0 | 0 | 0 |
| Integration | 5 | 80 | 78 | 2 | 0 | 0 |

Final failures (all reproduced with the recovered tests in their original Feature layout):

- `Integration/Timebox3/UtilitySeederTest.php:13`: expects no August 2026 utility rows; the current seeder creates such rows. Test/import-month expectation and current demo seeding policy disagree.
- `Integration/Timebox5/DemoDataIntegrityTest.php:56`: expects every customer email to be derived from its current display name; at least one pre-existing sample account uses a different fixed email. No personal values are included here.
- `Integration/Timebox5/DemoDataIntegrityTest.php:156`: repeated seeding changes invoice/payment/receipt counts, violating the existing idempotency assertion.

No production code or seeder was changed, and none of these assertions was weakened, skipped or removed. These are follow-up data-policy/seed-idempotency issues, not relocation/import/discovery errors. The gallery check passes in the original layout after demo seeding has populated fallback images, confirming its test-order dependency. Its isolated fixture now resolves that dependency without changing assertions.

Coverage retained: all 93 surviving and all 261 recovered tests remain discovered. No unique test or assertion was discarded. The recovered root-route smoke test is retained as a smoke check, not claimed as a business-workflow test.

Environment: PHP 8.4.20; Laravel 12.52.0; Pest 4.4.1; PHPUnit 12.5.12; disposable MySQL 8.0.45. Production database settings remain unchanged. Temporary durability flush settings were relaxed only on the disposable servers to reduce fixture overhead; no crash-durability behavior is asserted.

Evidence: coverage-audit.json contains every original/final path and test-statement hash; verification-results.json contains per-test outcomes and original-layout comparisons; *-results.xml files contain JUnit evidence with fixture email values redacted. No line/branch coverage measurement was performed.

## Necessary UOG report updates

1. Replace Feature as a test category with Integration and explain the API/controller/middleware/service/database boundaries exercised. Keep exactly Unit and Integration as automated backend categories.
2. Use the five report timebox names: Authentication and Property Management; Customer and Contract Management; Utility and Billing Management; Invoice and Payment Management; Maintenance and System Administration.
3. Include the cross-timebox ownership rationales above; the location of a test reflects its primary business workflow, not exclusive coverage.
4. Report the 93-test surviving baseline separately from 261 recovered tests; do not count file moves as newly written tests.
5. Distinguish test counts/assertions from code-coverage percentages. No line/branch coverage percentage was measured.
6. State the empty Unit timebox coverage gaps, illustrative formula-test limitation, concurrency limitation, mocked external-service boundaries and any remaining restored-test failures.
7. Use the final verified counts and outcomes below; do not claim a fully passing recovered suite unless the results support that statement.

## Unrelated working-tree state

The pre-existing public/favicon.ico deletion was preserved. A public/storage symlink change appeared during verification, outside the test edits, and was left untouched. Neither symlink target was used by the isolated integration runtimes.

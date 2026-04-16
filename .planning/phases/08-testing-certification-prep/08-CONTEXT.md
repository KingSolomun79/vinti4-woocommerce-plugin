# Phase 8: Testing & Certification Prep - Context

**Gathered:** 2026-04-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Unit tests that verify fingerprint correctness and callback handling, plus a certification checklist mapping all SISP-required behaviors to test cases. This phase produces the testing infrastructure, test files, test data, and certification documentation.

</domain>

<decisions>
## Implementation Decisions

### Test Framework & Bootstrap
- Create `composer.json` with PHPUnit as a dev dependency (plugin itself has zero runtime Composer dependencies)
- Pure PHP tests for fingerprint functions — no WordPress installation needed, just include the PHP files directly
- WordPress integration tests delivered as a **built-in admin diagnostic panel** inside the plugin (not a separate WP test suite requiring CLI)
- The admin panel gives a "Run Tests" capability accessible from WooCommerce settings — usable on any live/staging site where the plugin is installed
- Legacy plugin's `testing/` folder (worker-claude, worker-codex) was NOT official Vinti4 material — disregard completely

### Test Vectors & Data Source
- No SISP-provided test vectors available
- No existing production transactions from legacy plugin to extract from
- Test fixtures must be **manually calculated** — follow the SISP specification step by step to compute expected fingerprint outputs
- Fixtures stored in `tests/fixtures/` as PHP files returning known input/output arrays

### Certification Checklist
- Create as a markdown document (`docs/certification-checklist.md`)
- Maps every SISP-required behavior to the specific test case that verifies it
- Serves as proof documentation for SISP certification submission

### Test Delivery Format
- Two complementary testing mechanisms:
  1. **PHPUnit test files** (`tests/Test_Fingerprint.php`, etc.) — run via `vendor/bin/phpunit`, pure PHP, no WP needed
  2. **Admin diagnostic panel** — integrated into plugin, runs WP-dependent tests (callback handling, order status transitions, duplicate detection) on a live WordPress + WooCommerce installation

### Claude's Discretion
- Exact admin panel UI layout and styling
- Test result display format (pass/fail list, detailed output, etc.)
- PHPUnit configuration details (phpunit.xml structure, test directory layout)
- Bootstrap file structure for pure PHP tests

</decisions>

<specifics>
## Specific Ideas

- Admin diagnostic panel is intended for future reuse — "nice option for future use on other sites"
- Plugin should be self-contained for testing — merchant or developer can verify integration without external tools

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-testing-certification-prep*
*Context gathered: 2026-04-16*

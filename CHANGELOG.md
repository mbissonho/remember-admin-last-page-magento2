# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-06-08

### Added
- Optional opt-in entity details preview on the resume notification: it can now hint which record (order, customer, product, …) the saved page was about. 
  Values shown on entity details preview are partially masked (interleaved character hiding, e-mail-aware masking) and 
  only disclosed to users holding the entity's own ACL resource. The entity reference is sealed into an opaque, 
  installation-keyed token before it ever reaches the browser (confidential and tamper-evident).
- Admin setting `Show entity details on notification` (off by default).
- Unit and integration tests for the tokenizer, masking strategies and pool wiring.
- Enforced-2FA awareness: the post-login resume and the login-page entity preview
  now respect `Magento_TwoFactorAuth`. An admin only counts as authenticated after
  the second factor is passed, and the resume redirect survives the 2FA detour
  instead of being consumed by it.
- Pluggable second-factor guards behind `CompletedAdminAuthenticationInterface`, so
  the "is this admin fully authenticated" check can be extended to other providers
  without touching the module's code.

### Changed
- Supported PHP range broadened to **7.4 – 8.5** (PHP 8 constructor property
  promotion and `readonly` removed) so the module installs on legacy 7.4 code bases;
  the range is now declared as a `php` constraint in `composer.json`. PHP 8.2+ is
  recommended.
- The entity preview only resolves and shows record details when the admin is
  already authenticated in another session/tab; nothing is disclosed to an
  unauthenticated visitor.

### Fixed
- The resume notification no longer polls during the 2FA redirect, avoiding stray
  requests while the second factor is being completed.

## [1.0.0] - 2025-02-04

### Added
- Automated Tests

### Fixed
- Improve code quality

## [0.1.0] - 2024-02-01

### Added
- Initial module structure and functionality

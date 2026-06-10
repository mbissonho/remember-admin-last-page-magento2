# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-06-08

### Added
- Optional, opt-in entity details on the resume notification: it can now hint
  which record (order, customer, product, …) the saved page was about.
- Extensible architecture for it: per-`entity_type` resolver and formatter pools,
  a route → entity map detector pool, and pluggable masking strategies — all
  wired through `di.xml`, so new entity types are added without touching the
  module's code.
- Values shown are partially masked (interleaved character hiding, e-mail-aware
  masking) and only disclosed to users holding the entity's own ACL resource.
- The entity reference is sealed into an opaque, installation-keyed token before
  it ever reaches the browser (confidential and tamper-evident).
- Admin setting `Show entity details on notification` (off by default).
- Unit and integration tests for the tokenizer, masking strategies and pool wiring.

## [1.0.0] - 2025-02-04

### Added
- Automated Tests

### Fixed
- Improve code quality

## [0.1.0] - 2024-02-01

### Added
- Initial module structure and functionality

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Phone number validation wired into the send pipeline
- Message length validation before dispatch
- Webhook signature validation middleware (per-provider HMAC verification)
- Database indexes on `sms_logs` table for query performance
- Config validation on boot for early misconfiguration detection
- Defensive response parsing in Advanta and Onfon provider actions
- Job retry strategy with exponential backoff for failed sends
- `SmsSent` and `SmsFailed` events for application-level hooks
- Structured logging with credential scrubbing to prevent secret leakage
- Configurable base URLs per provider for sandbox/production switching
- Delivery status polling for Africa's Talking and Nexmo providers
- Fallback provider chain for automatic failover
- Per-provider rate limiting
- SMS templating with variable interpolation
- Cost estimation with GSM-7 and UCS-2 segment calculation
- Analytics and success rate tracking
- `sms:check-delivery` artisan command for manual delivery status checks
- `sms:stats` artisan command for sending statistics overview
- Query scopes on `SmsLog` model for convenient filtering
- Model factories for consumer testing

### Fixed
- SMS sending issues (multiple fixes)
- Duplicate bulk SMS logic refactored into `BaseProvider` to eliminate code duplication
- Hardcoded API URLs moved to configuration for environment flexibility
- Lint fixes

## [v0.0.16] - 2025-12-16

_No changes to this package (tag only)._

## [v0.0.15] - 2025-12-16

### Fixed
- Lint fixes

## [v0.0.14] - 2025-12-16

### Fixed
- Type fixes and general usage improvements
- Lint fixes

## [v0.0.13] - 2025-12-15

### Fixed
- Fixed file location issue

## [v0.0.12] - 2025-12-11

### Added
- Twilio and Nexmo provider support
- Onfon media handler
- Documentation updates

### Changed
- Modified phone formatter
- Updated packages and dependencies
- General cleanup and package updates

### Fixed
- Test fixes
- Dependency fixes
- Lint fixes

## [0.0.11] - 2024-09-06

### Fixed
- Bug fixes

## [0.0.10] - 2024-09-06

### Fixed
- Bug fixes

## [0.0.9] - 2024-09-06

### Fixed
- Bug fixes

## [0.0.8] - 2024-09-06

### Fixed
- Bug fixes

## [0.0.7] - 2024-09-06

### Fixed
- Bug fixes

## [0.0.6] - 2024-09-06

### Fixed
- Namespace fixes
- Lint fixes

## [0.0.5] - 2024-09-06

### Added
- Tests for SMS sending

### Changed
- General updates and improvements

## [0.0.4] - 2024-09-05

### Fixed
- Fixed missing key on object
- Lint fixes

## [0.0.3] - 2024-09-05

### Changed
- Removed `app` variable dependency

## [0.0.2] - 2024-09-05

_No changes to this package (tag only)._

## [0.0.1] - 2024-09-05

### Added
- Initial release: SMS handler with Advanta provider
- Test setup
- README documentation

### Fixed
- Lint fixes

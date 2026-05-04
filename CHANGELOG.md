# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-05-04

### Added

- Added support for Laravel log events so direct `Log::debug()`, `Log::info()`, `Log::notice()`, `Log::warning()`, `Log::error()`, `Log::critical()`, `Log::alert()`, and `Log::emergency()` calls can be reported to Telegram.
- Added `ERROR_MONITORING_CAPTURE_LOGS` configuration to enable or disable forwarding Laravel log events to Telegram.
- Added log event formatting that includes log level, message, optional context, and exception details when present in the log context.
- Added README guidance for log level ordering and threshold behavior.

### Changed

- Applied `ERROR_MONITORING_LEVEL` as the threshold for both reported exceptions and Laravel log events.
- Updated README installation instructions to use Packagist as the primary installation method.
- Improved notification throttling so duplicate exceptions and duplicate log events are deduplicated independently.

### Fixed

- Prevented notification fallback failures from re-entering Laravel logging by using `error_log()` instead of writing another Laravel log entry when Telegram delivery fails.

## [1.0.0] - 2026-05-02

### Added

- Initial release with Telegram notifications for reportable Laravel exceptions.
- Configurable Telegram bot token, chat ID, enable flag, and minimum reporting level.
- Duplicate exception throttling to reduce alert spam.

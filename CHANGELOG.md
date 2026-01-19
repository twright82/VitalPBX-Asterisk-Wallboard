# Changelog

## [1.1.0] - 2025-01-19

### Added
- VitalPBX API integration for automatic extension name sync
- "Sync from VitalPBX" button in admin extensions page
- Hourly cron job for automatic name synchronization
- Caller name display when agent is on call

### Changed
- First name field now optional when adding extensions (auto-syncs from VitalPBX)
- Agent status display shows caller name instead of just phone number

### Fixed
- Fixed NaN display issue when caller info was missing
- Fixed talking_to_name not being saved in onAgentConnect event

## [1.0.0] - 2025-01-18

### Added
- Initial wallboard implementation
- Real-time agent status display
- Queue statistics
- Call tracking (waiting, answered, abandoned)
- Admin panel for extensions, queues, settings
- AMI integration with VitalPBX

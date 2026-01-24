# Changelog

## [2.0.0] - 2026-01-24

### Major New Features
- **Easter Eggs** - Click company name 7x for trophy with victory fanfare; click SLA 5x for "Eric is typing..." joke
- **Team Extensions Panel** - Display non-queue staff (managers, NOC, back-office) with real-time status
- **Real-Time Alerts** - Notifications via Email, Slack, Teams, and browser popups with configurable thresholds
- **Daily Email Reports** - Automated end-of-day email with summary stats, hourly chart, queue breakdown, agent performance

### Added
- Queue badges on agent cards (green = signed in, gray = signed out)
- Calls Today box showing total daily inbound calls
- Abandoned calls counter with red styling
- Avg wait time display on queue cards
- Avg call time display on agent cards
- Phone number formatting for brand prefixes (#31 BW, #32 OL, #33 XL, #34 DCB)
- Status labels visible on agent cards (Avail, On Call, Wrap, Paused)
- Daily reset script for midnight stats cleanup
- SSL production deployment at bertram-wallboard.as36001.net
- Manager view (manager.html) for secondary displays

### Fixed
- **Critical:** Daemon crash every 60 seconds (PDO query() vs prepare()/execute())
- Orphaned waiting call records when caller rejoins with new unique_id
- Manager.html stuck on "Connecting" screen (missing errorOverlay element)
- Caller name display on agent cards when ringing
- Caller name display in WAITING panel
- Stuck waiting calls (now matched by caller+queue, not unique_id)
- API totals calculation (now queries calls table directly)

### Changed
- Removed wrapup tracking (VitalPBX handles it natively)
- Queue cards always show daily total (no longer switch to waiting count)
- Waiting calls shown separately with yellow highlight
- Real-time queue stats increment on events (not just polling)

### Security
- Removed debug.php and test.php from production

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

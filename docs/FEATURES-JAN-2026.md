# Wallboard Features - January 2026

## Overview
Four major features were added to the VitalPBX Asterisk Wallboard on January 22-23, 2026.

| Feature | Status | Tested |
|---------|--------|--------|
| Easter Eggs | Complete | Yes |
| Team Extensions Panel | Complete | Yes |
| Real-Time Alerts | Complete | Yes |
| Daily Email Reports | Complete | Yes |

---

## 1. Easter Eggs

### Trophy Easter Egg
**Trigger:** Click the company name (bottom-left of wallboard) **7 times** within 3 seconds.

**Result:** Full-screen overlay appears with:
- Victory fanfare sound (bass drop + C-E-G-C chord + airhorn)
- Gold trophy icon with glowing animation
- Confetti falling animation (150 pieces, multiple colors)
- Message: "Built by Tim Wright"
- Subtitle: "The Greatest Wallboard Ever Created. Tremendous."
- Zinger: "Other wallboards wish they had this energy." (orange, pulsing)

**Dismiss:** Click anywhere or wait 6 seconds.

```
┌─────────────────────────────────────────────┐
│                                             │
│              ✨  ✨  ✨  ✨  ✨               │
│                   🏆                        │
│            (glowing gold)                   │
│                                             │
│          Built by Tim Wright                │
│                                             │
│   The Greatest Wallboard Ever Created.      │
│              Tremendous.                    │
│                                             │
│              ✨  ✨  ✨  ✨  ✨               │
│                                             │
│         (click anywhere to close)           │
└─────────────────────────────────────────────┘
```

### Eric Easter Egg
**Trigger:** Click the SLA percentage box **5 times** within 3 seconds.

**Result:** Chat bubble overlay with animated typing sequence:
1. "Eric is typing..." (with animated dots, 2 sec)
2. "Eric is still typing..." (with animated dots, 2.5 sec)
3. "Eric has gone to lunch. Again." (final message, 3 sec then closes)

**Dismiss:** Click anywhere or wait for sequence to complete.

```
┌─────────────────────────────────────────────┐
│                                             │
│         ┌─────────────────────────┐         │
│         │                         │         │
│         │  Eric is typing...      │         │
│         │                         │         │
│         └─────────────────────────┘         │
│                                             │
│         (click anywhere to close)           │
└─────────────────────────────────────────────┘
```

### Files Modified
- `src/public/js/wallboard.js` - Click handlers, easter egg functions
- `src/public/css/wallboard.css` - Overlay styles, animations, confetti

### Testing
Verified via curl that code is deployed. Manual browser testing required.

---

## 2. Team Extensions Panel

### Purpose
Display non-queue staff (managers, NOC, back-office) below the Agents panel with real-time status.

### Features
- Shows team members who are NOT queue agents
- Same card style as agent cards (compact version)
- Status colors: Available (green), On Call (orange), Ringing (yellow), Paused (gray), Offline (dim)
- Call timer when on call
- Department/team label badge
- Status counts in header (Available / On Call)

### Mockup
```
┌─────────────────────────────────────────────────────────────────┐
│ TEAM                              ● 2 Available  ● 1 On Call    │
├─────────────────────────────────────────────────────────────────┤
│ ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐  │
│ │ █ John Smith     │ │ █ Jane Doe    2:34│ │ █ Bob Wilson     │  │
│ │   Ext 501        │ │   Ext 502        │ │   Ext 503        │  │
│ │   Available      │ │   📞 Customer    │ │   Offline        │  │
│ │   MANAGEMENT     │ │   NOC            │ │   SUPPORT        │  │
│ └──────────────────┘ └──────────────────┘ └──────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Admin Page
**URL:** `https://bertram-wallboard.as36001.net/admin/team.php`

**Features:**
- Add team members (select from extensions not already queue agents)
- Set team/department name
- Toggle active/inactive
- Delete team members

### Database
- Added `is_team_member` column to `extensions` table
- Added `team` column for department name

### Files Modified/Created
- `src/database/schema.sql` - Added columns
- `src/api/realtime.php` - Added `team_members` query
- `src/public/index.html` - Added team panel HTML
- `src/public/js/wallboard.js` - Added `renderTeamMembers()` method
- `src/public/css/wallboard.css` - Added team panel styles
- `src/admin/team.php` - NEW admin page
- `src/admin/templates/header.php` - Added nav link

### Testing
- API returns `team_members` array: `curl .../api/realtime.php | jq '.team_members'`
- Panel hidden when no team members configured

---

## 3. Real-Time Alerts

### Purpose
Notify staff immediately when queue metrics exceed thresholds via multiple channels.

### Alert Triggers

| Alert Type | Description | Default Threshold |
|------------|-------------|-------------------|
| Calls Waiting High | Too many calls in queue | 5 calls |
| Longest Wait High | Caller waiting too long | 120 seconds |
| SLA Below | Service level dropped | 80% |
| Abandoned Rate High | Too many hangups | 5/hour |
| No Agents | Queue has no available agents | 0 |

### Notification Channels

1. **Browser Popups** - Slide in from top-right of wallboard
2. **Email** - Via configured SMTP
3. **Slack** - Via incoming webhook
4. **Microsoft Teams** - Via incoming webhook

### Browser Popup Mockup
```
                                    ┌─────────────────────────────┐
                                    │ ⚠️ CALLS WAITING HIGH    ✕ │
                                    │                             │
                                    │ Sales: 5 calls waiting      │
                                    │ (threshold: 3)              │
                                    │                             │
                                    │ 2m ago                      │
                                    └─────────────────────────────┘
                                    ┌─────────────────────────────┐
                                    │ 🚨 SLA BREACH            ✕ │
                                    │                             │
                                    │ SLA dropped to 75%          │
                                    │ (target: 80%)               │
                                    │                             │
                                    │ 5m ago                      │
                                    └─────────────────────────────┘
```

- **Warning alerts:** Yellow border, ⚠️ icon
- **Critical alerts:** Red border with pulse animation, 🚨 icon
- Click ✕ to dismiss (acknowledge)

### Features
- **15-minute cooldown** per rule to prevent alert spam
- **Quiet hours** - Suppress alerts during configured times (e.g., 9pm-7am)
- **Severity levels** - Info, Warning, Critical
- **Auto-resolve** - Alerts clear when condition returns to normal
- **Alert history** - All alerts logged for review

### Admin Configuration
**URL:** `https://bertram-wallboard.as36001.net/admin/alerts.php`

**Sections:**
1. **Alert Rules** - Enable/disable, set thresholds, severity, cooldown
2. **Alert Settings & Quiet Hours** - Master toggle, quiet hours schedule
3. **Webhook Integrations** - Add Slack/Teams webhook URLs
4. **Alert Recipients** - Email addresses for notifications
5. **Recent Alerts** - History of triggered alerts

### Admin UI Mockup
```
┌─────────────────────────────────────────────────────────────────┐
│ 🚨 Alert Rules                                                  │
├─────────────────────────────────────────────────────────────────┤
│ Alert Type          │ Threshold │ Severity │ Cooldown │ Enabled │
│─────────────────────│───────────│──────────│──────────│─────────│
│ Calls Waiting High  │ 5 calls   │ Warning  │ 15 min   │ [✓]     │
│ Longest Wait High   │ 120 sec   │ Warning  │ 15 min   │ [✓]     │
│ SLA Below           │ 80 %      │ Critical │ 15 min   │ [✓]     │
│ Abandoned Rate      │ 5 /hour   │ Warning  │ 30 min   │ [✓]     │
│ No Agents           │ 0         │ Critical │ 5 min    │ [✓]     │
├─────────────────────────────────────────────────────────────────┤
│                                          [Save Rules]           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ ⏰ Alert Settings & Quiet Hours                                 │
├─────────────────────────────────────────────────────────────────┤
│ [✓] Alerts Enabled          [✓] Quiet Hours Enabled            │
│                                                                 │
│ Quiet Hours Start: [21:00]   Quiet Hours End: [07:00]          │
├─────────────────────────────────────────────────────────────────┤
│                                          [Save Settings]        │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ 🔗 Webhook Integrations                        [+ Add Webhook]  │
├─────────────────────────────────────────────────────────────────┤
│ Type   │ Name           │ Channel    │ Status │ Actions        │
│────────│────────────────│────────────│────────│────────────────│
│ Slack  │ #alerts        │ #alerts    │ Active │ [Disable][Del] │
│ Teams  │ NOC Channel    │ -          │ Active │ [Disable][Del] │
└─────────────────────────────────────────────────────────────────┘
```

### Files Created
- `src/daemon/alert-processor.php` - Checks conditions every 30 sec
- `src/daemon/alert-sender.php` - Sends email/Slack/Teams
- `src/api/acknowledge-alert.php` - Dismiss popup endpoint

### Files Modified
- `src/daemon/wallboard-daemon.php` - Integrated alert processor
- `src/api/realtime.php` - Added `active_alerts` to response
- `src/admin/alerts.php` - Added webhook UI, quiet hours, severity
- `src/public/js/wallboard.js` - Added `renderAlertPopups()`, `acknowledgeAlert()`
- `src/public/css/wallboard.css` - Added popup styles

### Database Tables
```sql
-- New table for webhooks
CREATE TABLE webhook_config (
    id INT PRIMARY KEY,
    webhook_type ENUM('slack', 'teams'),
    webhook_name VARCHAR(100),
    webhook_url VARCHAR(500),
    channel_name VARCHAR(100),
    is_active TINYINT(1)
);

-- New table for active alerts
CREATE TABLE active_alerts (
    id INT PRIMARY KEY,
    alert_rule_id INT,
    alert_type VARCHAR(50),
    queue_number VARCHAR(20),
    current_value VARCHAR(50),
    threshold VARCHAR(50),
    message TEXT,
    severity ENUM('info', 'warning', 'critical'),
    triggered_at TIMESTAMP,
    acknowledged_at TIMESTAMP,
    acknowledged_by VARCHAR(100),
    resolved_at TIMESTAMP,
    is_active TINYINT(1)
);

-- Added to company_config
ALTER TABLE company_config ADD COLUMN alerts_enabled TINYINT(1);
ALTER TABLE company_config ADD COLUMN quiet_hours_enabled TINYINT(1);
ALTER TABLE company_config ADD COLUMN quiet_hours_start TIME;
ALTER TABLE company_config ADD COLUMN quiet_hours_end TIME;

-- Added to alert_rules
ALTER TABLE alert_rules ADD COLUMN severity ENUM('info','warning','critical');
ALTER TABLE alert_rules ADD COLUMN last_triggered_at TIMESTAMP;
```

### Testing Performed
1. **API Test:** `curl .../api/realtime.php | jq '.active_alerts'` - Returns array
2. **Insert Test Alert:** Created warning alert via PHP - Appeared in API
3. **Insert Critical Alert:** Created critical alert - Appeared with correct severity
4. **Acknowledge Test:** `POST /api/acknowledge-alert.php` - Returned success, alert marked acknowledged
5. **Cleanup:** Removed test alerts

---

## Git Commits

```
1b203bb - Add real-time alerts with Email, Slack, Teams, and browser popups
fdf35e3 - Fix avg wait time and add avg call time to agent cards
0c94d17 - Jan 22, 2026 - Wallboard updates complete (Team Extensions, Easter Eggs)
```

---

## 4. Daily Email Reports

### Purpose
Automated end-of-day email with call center stats, charts, and agent performance.

### Features
- **Summary Stats:** Total Calls, Answered, Abandoned, SLA %
- **Hourly Call Volume:** Bar chart showing calls from 8am-5pm
- **Calls by Queue:** Breakdown with percentages and progress bars
- **Agent Performance:** Table with calls, talk time, avg handle, missed
- **Configurable Sections:** Enable/disable each section
- **Scheduled Sending:** Set send time (default 5:30pm)
- **Test Send:** Send immediately for testing

### Sample Email
```
■ Bertram Communications
Daily Call Center Report — January 23, 2026

┌────────────┬────────────┬────────────┬────────────┐
│     37     │     32     │      4     │   96.9%    │
│ Total Calls│  Answered  │ Abandoned  │    SLA     │
└────────────┴────────────┴────────────┴────────────┘

■ Hourly Call Volume
[Bar chart: 8am-5pm]

■ Calls by Queue
Support     ████████████████████  15 (41%)
Billing     ████████████████████  15 (41%)
Sales       █████████             7 (19%)

■ Agent Performance
┌──────────┬───────┬───────────┬────────────┬────────┐
│ Agent    │ Calls │ Talk Time │ Avg Handle │ Missed │
├──────────┼───────┼───────────┼────────────┼────────┤
│ Rachel L │   7   │   31:07   │    4:27    │   0    │
│ Joel V   │   6   │   23:19   │    3:53    │   0    │
│ Chris B  │   6   │   35:46   │    5:58    │   0    │
└──────────┴───────┴───────────┴────────────┴────────┘

Generated by Bertram Wallboard
```

### Admin Configuration
**URL:** `https://bertram-wallboard.as36001.net/admin/email-reports.php`

**Settings:**
- Enable/disable daily reports
- Set send time
- Toggle sections (hourly chart, queue breakdown, agent table)
- Add/remove recipients

### Cron Setup
Add to server crontab:
```bash
* * * * * php /var/www/html/scripts/daily-report.php >> /var/log/wallboard/reports.log 2>&1
```

### Files Created
- `src/scripts/daily-report.php` - Report generator and sender
- `src/admin/email-reports.php` - Admin configuration page

### Database Tables
```sql
-- Added columns to report_config
ALTER TABLE report_config ADD COLUMN include_hourly_chart TINYINT(1) DEFAULT 1;
ALTER TABLE report_config ADD COLUMN include_queue_breakdown TINYINT(1) DEFAULT 1;
ALTER TABLE report_config ADD COLUMN include_agent_table TINYINT(1) DEFAULT 1;
ALTER TABLE report_config ADD COLUMN include_pdf TINYINT(1) DEFAULT 0;

-- report_recipients table
CREATE TABLE report_recipients (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(255),
    is_active TINYINT(1),
    created_at TIMESTAMP
);
```

### Testing
- Preview button shows text version of report
- Test Send button sends to all active recipients immediately
- Verified report generates with correct data

---

## Quick Reference URLs

| Page | URL |
|------|-----|
| Wallboard | https://bertram-wallboard.as36001.net |
| Admin Login | https://bertram-wallboard.as36001.net/admin/ |
| Team Members | https://bertram-wallboard.as36001.net/admin/team.php |
| Alerts Config | https://bertram-wallboard.as36001.net/admin/alerts.php |
| Email Reports | https://bertram-wallboard.as36001.net/admin/email-reports.php |
| API Endpoint | https://bertram-wallboard.as36001.net/api/realtime.php |

---

## Support

Created by Claude Code (Claude Opus 4.5)
January 22-23, 2026

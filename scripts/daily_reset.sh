#!/bin/bash
# Daily reset script for wallboard - runs at midnight

cd /opt/wallboard/bertram-communications/docker

# Reset queue stats for new day
docker compose exec -T db mysql -u wallboard -pVHwrblDH80xt10451 wallboard -e "
UPDATE queue_stats_realtime SET 
    calls_today = 0,
    answered_today = 0,
    abandoned_today = 0,
    sla_percent_today = 100,
    avg_wait_today = 0,
    avg_talk_today = 0,
    calls_waiting = 0,
    longest_wait_seconds = 0;

UPDATE agent_status SET 
    calls_today = 0,
    talk_time_today = 0,
    missed_today = 0,
    avg_handle_time = 0;

-- Clear any stuck waiting calls
UPDATE calls SET status = 'abandoned', ended_at = NOW() 
WHERE status = 'waiting';

-- Optional: Archive old calls (keep last 30 days)
DELETE FROM calls WHERE ended_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
"

echo "Daily reset completed at $(date)"

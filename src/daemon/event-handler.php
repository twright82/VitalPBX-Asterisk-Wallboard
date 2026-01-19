<?php
/**
 * Event Handler Class - Bulletproof Edition
 * 
 * Processes AMI events with flexible field extraction and comprehensive error handling
 * 
 * @package VitalPBX-Asterisk-Wallboard
 * @version 1.1.0
 */

class EventHandler {
    private $db;
    private $debug = false;
    private $logFile = null;
    private $rawLogFile = null;
    private $monitoredQueues = [];
    private $monitoredExtensions = [];
    private $eventCounts = [];
    private $unknownEvents = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->loadConfig();
    }
    
    public function enableDebug($logFile = null) {
        $this->debug = true;
        $this->logFile = $logFile ?: '/var/log/wallboard/events.log';
        $this->rawLogFile = '/var/log/wallboard/raw-events.log';
        @mkdir(dirname($this->logFile), 0755, true);
    }
    
    private function log($msg, $level = 'DEBUG', $data = null) {
        $ts = date('Y-m-d H:i:s');
        $line = "[$ts] [$level] $msg";
        if ($data) {
            $clean = $data;
            unset($clean['_raw']);
            $line .= " | " . json_encode($clean);
        }
        $line .= "\n";
        
        if ($this->debug) echo $line;
        if ($this->logFile) @file_put_contents($this->logFile, $line, FILE_APPEND);
    }
    
    private function logRaw($event) {
        if ($this->rawLogFile && isset($event['_raw'])) {
            $entry = "=== [" . date('Y-m-d H:i:s') . "] " . ($event['Event'] ?? 'Unknown') . " ===\n" . $event['_raw'] . "\n";
            @file_put_contents($this->rawLogFile, $entry, FILE_APPEND);
        }
    }
    
    public function loadConfig() {
        try {
            $stmt = $this->db->query("SELECT queue_number, queue_name, display_name FROM queues WHERE is_active = 1");
            $this->monitoredQueues = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->monitoredQueues[$row['queue_number']] = $row;
            }
            
            $stmt = $this->db->query("SELECT extension, display_name FROM extensions WHERE is_active = 1");
            $this->monitoredExtensions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->monitoredExtensions[$row['extension']] = $row;
            }
            
            $this->log("Loaded " . count($this->monitoredQueues) . " queues, " . count($this->monitoredExtensions) . " extensions", 'INFO');
        } catch (Exception $e) {
            $this->log("Config load failed: " . $e->getMessage(), 'ERROR');
        }
    }
    
    // Flexible field extraction - tries multiple possible field names
    private function getField($event, $names, $default = null) {
        foreach ((array)$names as $name) {
            if (isset($event[$name]) && $event[$name] !== '') return $event[$name];
        }
        return $default;
    }
    
    // Extract extension from PJSIP/1201, SIP/1201-00001, Local/1201@ctx, etc
    private function extractExt($value) {
        if (!$value) return null;
        if (preg_match('/^\d{3,5}$/', $value)) return $value;
        if (preg_match('/(?:PJSIP|SIP|IAX2)\/(\d{3,5})(?:-|$)/i', $value, $m)) return $m[1];
        if (preg_match('/Local\/(\d{3,5})@/i', $value, $m)) return $m[1];
        if (preg_match('/Agent\/(\d{3,5})/i', $value, $m)) return $m[1];
        if (preg_match('/(\d{3,5})/', $value, $m)) return $m[1];
        return null;
    }
    
    private function getQueue($e) { return $this->getField($e, ['Queue', 'QueueName', 'queue']); }
    private function getMember($e) { return $this->getField($e, ['Member', 'MemberName', 'Interface', 'Agent', 'StateInterface']); }
    private function getCallerNum($e) { return $this->getField($e, ['CallerIDNum', 'CallerID', 'ConnectedLineNum']); }
    private function getCallerName($e) { return $this->getField($e, ['CallerIDName', 'ConnectedLineName']); }
    private function getUniqueId($e) { return $this->getField($e, ['UniqueID', 'Uniqueid', 'LinkedID']); }
    private function getChannel($e) { return $this->getField($e, ['Channel', 'channel']); }
    
    public function handleEvent($event) {
        if (!isset($event['Event'])) return;
        
        $type = $event['Event'];
        if (!isset($this->eventCounts[$type])) $this->eventCounts[$type] = 0;
        $this->eventCounts[$type]++;
        
        // Log first few of each type for debugging
        if ($this->eventCounts[$type] <= 3) $this->logRaw($event);
        
        try {
            switch ($type) {
                case 'QueueCallerJoin': $this->onQueueCallerJoin($event); break;
                case 'QueueCallerLeave': $this->onQueueCallerLeave($event); break;
                case 'QueueCallerAbandon': $this->onQueueCallerAbandon($event); break;
                case 'AgentCalled': $this->onAgentCalled($event); break;
                case 'AgentConnect': $this->onAgentConnect($event); break;
                case 'AgentComplete': $this->onAgentComplete($event); break;
                case 'AgentRingNoAnswer': $this->onAgentRingNoAnswer($event); break;
                case 'QueueMemberStatus': $this->onQueueMemberStatus($event); break;
                case 'QueueMemberAdded': $this->onQueueMemberAdded($event); break;
                case 'QueueMemberRemoved': $this->onQueueMemberRemoved($event); break;
                case 'QueueMemberPause':
                case 'QueueMemberPaused': $this->onQueueMemberPause($event); break;
                case 'QueueSummary': $this->onQueueSummary($event); break;
                case 'QueueMember': $this->onQueueMemberResponse($event); break;
                case 'QueueEntry': $this->onQueueEntry($event); break;
                case 'Hangup': $this->onHangup($event); break;
                case 'DialBegin': $this->onDialBegin($event); break;
                case 'Newchannel': $this->onNewchannel($event); break;
                case 'DialEnd': $this->onDialEnd($event); break;
                case 'DeviceStateChange':
                case 'ExtensionStatus': $this->onDeviceState($event); break;
                // Ignore noisy events
                case 'VarSet': case 'Newexten': case 'RTCPSent': case 'RTCPReceived':
                case 'NewConnectedLine': case 'NewCallerid': case 'Cdr': case 'CEL':
                    break;
                default:
                    if (!isset($this->unknownEvents[$type])) {
                        $this->unknownEvents[$type] = true;
                        $this->log("Unknown event: $type", 'WARN');
                        $this->logRaw($event);
                    }
            }
        } catch (Exception $e) {
            $this->log("Error handling $type: " . $e->getMessage(), 'ERROR');
        }
    }
    
    // === QUEUE CALLER EVENTS ===
    
    private function onQueueCallerJoin($e) {
        $queue = $this->getQueue($e);
        $uid = $this->getUniqueId($e);
        $callerNum = $this->getCallerNum($e);
        $callerName = $this->getCallerName($e);
        
        if (!$queue) { $this->log("QueueCallerJoin missing queue", 'WARN', $e); return; }
        
        // Only track queues in our whitelist
        $tracked = $this->db->query("SELECT queue_number FROM queues WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($queue, $tracked)) {
            $this->log("Skipping untracked queue: $queue", 'DEBUG');
            return;
        }
        
        $this->log("Caller joined: $queue, Caller: $callerNum", 'EVENT');
        
        $stmt = $this->db->prepare("
            INSERT INTO calls (unique_id, call_type, caller_number, caller_name, queue_number, status, entered_queue_at)
            VALUES (?, 'inbound', ?, ?, ?, 'waiting', NOW())
            ON DUPLICATE KEY UPDATE queue_number = ?, status = 'waiting', entered_queue_at = NOW()
        ");
        $stmt->execute([$uid, $callerNum, $callerName, $queue, $queue]);
        
        $this->updateQueueStats($queue);
        if ($callerNum) $this->trackRepeatCaller($callerNum, $callerName, $queue);
    }
    
    private function onQueueCallerLeave($e) {
        $queue = $this->getQueue($e);
        $this->log("Caller left queue: $queue", 'EVENT');
        if ($queue) $this->updateQueueStats($queue);
    }
    
    private function onQueueCallerAbandon($e) {
        $queue = $this->getQueue($e);
        // Skip untracked queues
        $tracked = $this->db->query("SELECT queue_number FROM queues WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        if ($queue && !in_array($queue, $tracked)) return;
        $uid = $this->getUniqueId($e);
        $wait = $this->getField($e, ['HoldTime', 'Wait', 'WaitTime'], 0);
        
        $this->log("Caller abandoned: $queue after {$wait}s", 'EVENT');
        
        $stmt = $this->db->prepare("UPDATE calls SET status = 'abandoned', wait_time = ?, ended_at = NOW() WHERE unique_id = ?");
        $stmt->execute([$wait, $uid]);
        if ($queue) $this->updateQueueStats($queue);
    }
    
    // === AGENT EVENTS ===
    
    private function onAgentCalled($e) {
        $ext = $this->extractExt($this->getMember($e));
        $caller = $this->getCallerNum($e);
        $callerName = $this->getCallerName($e);
        if (!$ext) return;
        
        $this->log("Agent $ext ringing for $caller", 'EVENT');
        $stmt = $this->db->prepare("UPDATE agent_status SET status = 'ringing', status_since = NOW(), talking_to = ? WHERE extension = ?");
        $stmt->execute([$caller, $ext]);
    }
    
    private function onAgentConnect($e) {
        $ext = $this->extractExt($this->getMember($e));
        $uid = $this->getUniqueId($e);
        $wait = $this->getField($e, ['HoldTime', 'Wait', 'RingTime'], 0);
        $caller = $this->getCallerNum($e);
        $callerName = $this->getCallerName($e);
        $queue = $this->getQueue($e);
        if (!$ext) return;
        // Skip untracked queues
        $tracked = $this->db->query("SELECT queue_number FROM queues WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        if ($queue && !in_array($queue, $tracked)) return;
        // Skip untracked queues
        $tracked = $this->db->query("SELECT queue_number FROM queues WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        if ($queue && !in_array($queue, $tracked)) return;
        
        $this->log("Agent $ext connected to $caller (wait: {$wait}s)", 'EVENT');
        
        $stmt = $this->db->prepare("
            UPDATE calls SET status = 'answered', agent_extension = ?, 
            agent_name = (SELECT display_name FROM extensions WHERE extension = ?),
            wait_time = ?, answered_at = NOW() WHERE unique_id = ?
        ");
        $stmt->execute([$ext, $ext, $wait, $uid]);
        
        $stmt = $this->db->prepare("
            UPDATE agent_status SET status = 'on_call', status_since = NOW(), 
            current_call_id = ?, talking_to = ?, talking_to_name = ?, current_call_type = 'inbound', call_started_at = NOW() WHERE extension = ?
        ");
        $stmt->execute([$uid, $caller, $callerName, $ext]);
        
        if ($queue) $this->updateQueueStats($queue);
    }
    
    private function onAgentComplete($e) {
        $ext = $this->extractExt($this->getMember($e));
        $uid = $this->getUniqueId($e);
        $talk = $this->getField($e, ['TalkTime', 'Duration'], 0);
        if (!$ext) return;
        
        $this->log("Agent $ext completed call (talk: {$talk}s)", 'EVENT');
        
        $stmt = $this->db->prepare("UPDATE calls SET status = 'completed', talk_time = ?, ended_at = NOW() WHERE unique_id = ?");
        $stmt->execute([$talk, $uid]);
        
        $stmt = $this->db->prepare("
            UPDATE agent_status SET status = 'wrapup', status_since = NOW(), 
            current_call_id = NULL, talking_to = NULL, call_started_at = NULL,
            calls_today = calls_today + 1, talk_time_today = talk_time_today + ? WHERE extension = ?
        ");
        $stmt->execute([$talk, $ext]);
    }
    
    private function onAgentRingNoAnswer($e) {
        $ext = $this->extractExt($this->getMember($e));
        $queue = $this->getQueue($e);
        $ring = $this->getField($e, ['RingTime', 'Duration'], 0);
        if (!$ext) return;
        
        $this->log("Agent $ext ring-no-answer after {$ring}s", 'EVENT');
        
        $stmt = $this->db->prepare("UPDATE agent_status SET status = 'available', status_since = NOW(), talking_to = NULL, missed_today = missed_today + 1 WHERE extension = ?");
        $stmt->execute([$ext]);
        
        $stmt = $this->db->prepare("INSERT INTO missed_calls (extension, agent_name, queue_number, ring_time, missed_at) VALUES (?, (SELECT display_name FROM extensions WHERE extension = ?), ?, ?, NOW())");
        $stmt->execute([$ext, $ext, $queue, $ring]);
    }
    
    // === QUEUE MEMBER EVENTS ===
    
    private function onQueueMemberStatus($e) {
        $ext = $this->extractExt($this->getMember($e));
        $status = $this->getField($e, ['Status', 'MemberStatus'], 0);
        $paused = $this->getField($e, ['Paused'], '0');
        if (!$ext) return;
        
        $map = [0=>'unknown', 1=>'available', 2=>'on_call', 3=>'busy', 4=>'unavailable', 5=>'unavailable', 6=>'ringing', 7=>'on_call', 8=>'on_hold'];
        $agentStatus = $map[(int)$status] ?? 'unknown';
        if ($paused === '1' || $paused === 'true') $agentStatus = 'paused';
        
        $stmt = $this->db->prepare("
            INSERT INTO agent_status (extension, agent_name, status, status_since) 
            VALUES (?, (SELECT display_name FROM extensions WHERE extension = ?), ?, NOW())
            ON DUPLICATE KEY UPDATE status = IF(status != ?, ?, status), status_since = IF(status != ?, NOW(), status_since)
        ");
        $stmt->execute([$ext, $ext, $agentStatus, $agentStatus, $agentStatus, $agentStatus]);
    }
    
    private function onQueueMemberAdded($e) {
        $queue = $this->getQueue($e);
        $ext = $this->extractExt($this->getMember($e));
        if (!$ext || !$queue) return;
        
        $this->log("Member $ext added to queue $queue", 'EVENT');
        $stmt = $this->db->prepare("INSERT IGNORE INTO agent_queue_membership (extension, queue_number, joined_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ext, $queue]);
    }
    
    private function onQueueMemberRemoved($e) {
        $queue = $this->getQueue($e);
        $ext = $this->extractExt($this->getMember($e));
        if (!$ext || !$queue) return;
        
        $this->log("Member $ext removed from queue $queue", 'EVENT');
        $stmt = $this->db->prepare("DELETE FROM agent_queue_membership WHERE extension = ? AND queue_number = ?");
        $stmt->execute([$ext, $queue]);
    }
    
    private function onQueueMemberPause($e) {
        $ext = $this->extractExt($this->getMember($e));
        $paused = $this->getField($e, ['Paused'], '0');
        $reason = $this->getField($e, ['PausedReason', 'Reason'], '');
        if (!$ext) return;
        
        $status = ($paused === '1' || $paused === 'true') ? 'paused' : 'available';
        $this->log("Member $ext paused: $paused", 'EVENT');
        
        $stmt = $this->db->prepare("UPDATE agent_status SET status = ?, status_since = NOW(), pause_reason = ? WHERE extension = ?");
        $stmt->execute([$status, $reason, $ext]);
    }
    
    // === STATUS RESPONSE EVENTS ===
    
    private function onQueueSummary($e) {
        $queue = $this->getQueue($e);
        if (!$queue) return;
        
        $waiting = $this->getField($e, ['Callers', 'Waiting'], 0);
        $available = $this->getField($e, ['Available'], 0);
        $members = $this->getField($e, ['LoggedIn', 'Members'], 0);
        
        $stmt = $this->db->prepare("
            INSERT INTO queue_stats_realtime (queue_number, queue_name, calls_waiting, agents_available, total_agents, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE calls_waiting = ?, agents_available = ?, total_agents = ?, updated_at = NOW()
        ");
        $stmt->execute([$queue, $this->monitoredQueues[$queue]['display_name'] ?? $queue, $waiting, $available, $members, $waiting, $available, $members]);
    }
    
    private function onQueueMemberResponse($e) {
        // Same handling as QueueMemberStatus
        $this->onQueueMemberStatus($e);
    }
    
    private function onQueueEntry($e) {
        // Caller waiting in queue - already tracked via QueueCallerJoin
    }
    
    private function onHangup($e) {
        $ext = $this->extractExt($this->getChannel($e));
        if (!$ext || !isset($this->monitoredExtensions[$ext])) return;
        
        // Reset agent to available after brief delay (handled by wrapup timer)
    }
    
    private function onDeviceState($e) {
        $device = $this->getField($e, ['Device', 'Exten']);
        $ext = $this->extractExt($device);
        $state = $this->getField($e, ['State', 'Status']);
        if (!$ext) return;
        
        // Map device states
        $map = ['NOT_INUSE' => 'available', 'INUSE' => 'on_call', 'BUSY' => 'busy', 'UNAVAILABLE' => 'offline', 'RINGING' => 'ringing', 'ONHOLD' => 'on_hold'];
        $status = $map[$state] ?? null;
        if (!$status) return;
        
        $stmt = $this->db->prepare("UPDATE agent_status SET status = ? WHERE extension = ? AND status NOT IN ('paused', 'wrapup')");
        $stmt->execute([$status, $ext]);
    }
    
    // === HELPER METHODS ===
    
    private function updateQueueStats($queue) {
        // Count waiting calls from active calls table
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM calls WHERE queue_number = ? AND status = 'waiting'");
        $stmt->execute([$queue]);
        $waiting = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("
            UPDATE queue_stats_realtime SET calls_waiting = ?, updated_at = NOW() WHERE queue_number = ?
        ");
        $stmt->execute([$waiting, $queue]);
    }
    
    private function trackRepeatCaller($number, $name, $queue) {
        $stmt = $this->db->prepare("
            INSERT INTO repeat_callers (caller_number, caller_name, call_count, first_call_at, last_call_at, last_queue)
            VALUES (?, ?, 1, NOW(), NOW(), ?)
            ON DUPLICATE KEY UPDATE call_count = call_count + 1, last_call_at = NOW(), last_queue = ?, caller_name = COALESCE(?, caller_name)
        ");
        $stmt->execute([$number, $name, $queue, $queue, $name]);
    }
    
    public function getEventCounts() { return $this->eventCounts; }
    public function getUnknownEvents() { return array_keys($this->unknownEvents); }

    // === OUTBOUND CALL TRACKING ===
    
    
    // === OUTBOUND DETECTION VIA NEWCHANNEL ===
    
    private function onNewchannel($e) {
        $channel = $this->getChannel($e);
        // Only track PJSIP channels from agent extensions (4 digits)
        if (!preg_match("/PJSIP\/(\d{4})-/", $channel, $m)) return;
        $ext = $m[1];
        
        $exten = $this->getField($e, ["Exten"]); // What they are dialing
        $context = $this->getField($e, ["Context"]);
        
        // Skip if not dialing an external number (7+ digits or starts with +)
        if (!$exten) return;
        if (strlen($exten) < 7 && !preg_match("/^\+/", $exten)) return;
        // Skip queue contexts
        if (strpos($context, "queue") !== false) return;
        
        $this->log("Newchannel outbound: $ext dialing $exten", "EVENT");
        
        $stmt = $this->db->prepare("
            UPDATE agent_status SET 
                status = \"ringing\",
                current_call_type = \"outbound\",
                talking_to = ?,
                call_started_at = NOW(),
                status_since = NOW()
            WHERE extension = ?
        ");
        $stmt->execute([$exten, $ext]);
    }

    private function onDialBegin($e) {
        $channel = $this->getChannel($e);
        if (!preg_match("/PJSIP\/(\d+)/", $channel, $m)) return;
        $ext = $m[1];
        
        $dialedNum = $this->getField($e, ["DestCallerIDNum", "DialString", "Exten"]);
        $dialedName = $this->getField($e, ["DestCallerIDName"]);
        $uid = $this->getUniqueId($e);
        
        // Skip internal calls (extension to extension) and queue calls
        if (preg_match("/^\d{4}$/", $dialedNum)) return;
        if (strpos($dialedNum, "Local/") !== false) return;
        
        $this->log("Outbound: $ext dialing $dialedNum", "EVENT");
        
        $stmt = $this->db->prepare("
            UPDATE agent_status SET 
                status = \"ringing\",
                current_call_type = \"outbound\",
                talking_to = ?,
                talking_to_name = ?,
                current_call_id = ?,
                call_started_at = NOW(),
                status_since = NOW()
            WHERE extension = ?
        ");
        $stmt->execute([$dialedNum, $dialedName, $uid, $ext]);
    }
    
    private function onDialEnd($e) {
        $channel = $this->getChannel($e);
        if (!preg_match("/PJSIP\/(\d+)/", $channel, $m)) return;
        $ext = $m[1];
        
        $dialStatus = $this->getField($e, ["DialStatus"]);
        
        if ($dialStatus === "ANSWER") {
            $stmt = $this->db->prepare("
                UPDATE agent_status SET 
                    status = \"on_call\",
                    call_started_at = NOW(),
                    status_since = NOW()
                WHERE extension = ? AND current_call_type = \"outbound\"
            ");
            $stmt->execute([$ext]);
            $this->log("Outbound answered: $ext", "EVENT");
        } else {
            $stmt = $this->db->prepare("
                UPDATE agent_status SET 
                    status = \"available\",
                    current_call_type = NULL,
                    talking_to = NULL,
                    talking_to_name = NULL,
                    current_call_id = NULL,
                    call_started_at = NULL,
                    status_since = NOW()
                WHERE extension = ? AND current_call_type = \"outbound\"
            ");
            $stmt->execute([$ext]);
        }
    }
}

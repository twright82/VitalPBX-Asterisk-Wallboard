<?php
/**
 * AMI Connector Class - Bulletproof Edition
 * 
 * Handles connection to Asterisk Manager Interface with:
 * - Automatic reconnection with exponential backoff
 * - Comprehensive error handling and logging
 * - Flexible event parsing that adapts to VitalPBX variations
 * - Field name normalization for cross-version compatibility
 * - Connection health monitoring
 * 
 * @package VitalPBX-Asterisk-Wallboard
 * @version 1.1.0
 */

class AMIConnector {
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket = null;
    private $connected = false;
    private $lastError = '';
    private $debug = false;
    private $logFile = null;
    private $eventCallbacks = [];
    private $buffer = '';
    private $reconnectAttempts = 0;
    private $maxReconnectAttempts = 10;
    private $reconnectDelay = 5;
    private $lastActivity = 0;
    private $connectionTimeout = 30;
    private $readTimeout = 5;
    
    // Track discovered fields for debugging
    private $discoveredFields = [];
    private $discoveredEventTypes = [];
    
    // Field name aliases for normalization
    private static $fieldAliases = [
        // Queue variations
        'Queue' => 'Queue',
        'QueueName' => 'Queue',
        'queue' => 'Queue',
        
        // Member/Agent variations
        'Member' => 'Member',
        'MemberName' => 'MemberName',
        'Interface' => 'Interface',
        'Agent' => 'Member',
        'agent' => 'Member',
        'StateInterface' => 'StateInterface',
        
        // Caller ID variations
        'CallerID' => 'CallerIDNum',
        'CallerIDNum' => 'CallerIDNum',
        'Callerid' => 'CallerIDNum',
        'callerid' => 'CallerIDNum',
        'CallerIdNum' => 'CallerIDNum',
        'ConnectedLineNum' => 'ConnectedLineNum',
        
        // Caller Name variations
        'CallerIDName' => 'CallerIDName',
        'CallerIdName' => 'CallerIDName',
        'ConnectedLineName' => 'ConnectedLineName',
        
        // Channel variations
        'Channel' => 'Channel',
        'channel' => 'Channel',
        'DestChannel' => 'DestChannel',
        'Destchannel' => 'DestChannel',
        'DestinationChannel' => 'DestChannel',
        
        // Unique ID variations
        'UniqueID' => 'UniqueID',
        'Uniqueid' => 'UniqueID',
        'uniqueid' => 'UniqueID',
        'LinkedID' => 'LinkedID',
        'Linkedid' => 'LinkedID',
        'DestUniqueID' => 'DestUniqueID',
        'DestUniqueid' => 'DestUniqueID',
        
        // Status variations  
        'Status' => 'Status',
        'MemberStatus' => 'Status',
        'ChannelState' => 'ChannelState',
        'ChannelStateDesc' => 'ChannelStateDesc',
        'DeviceStatus' => 'DeviceStatus',
        'Paused' => 'Paused',
        'PausedReason' => 'PausedReason',
        
        // Time variations
        'Wait' => 'Wait',
        'WaitTime' => 'Wait',
        'Holdtime' => 'HoldTime',
        'HoldTime' => 'HoldTime',
        'TalkTime' => 'TalkTime',
        'Talktime' => 'TalkTime',
        'RingTime' => 'RingTime',
        'Ringtime' => 'RingTime',
        'Duration' => 'Duration',
        
        // Count variations
        'Count' => 'Count',
        'Calls' => 'Calls',
        'Position' => 'Position',
        'CallsTaken' => 'CallsTaken',
        'LastCall' => 'LastCall',
        
        // Event variations
        'Event' => 'Event',
        'Privilege' => 'Privilege',
        'Response' => 'Response',
        'Message' => 'Message',
    ];
    
    public function __construct($host, $port = 5038, $username = '', $password = '') {
        $this->host = $host;
        $this->port = (int) $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    /**
     * Enable debug logging
     */
    public function enableDebug($logFile = null) {
        $this->debug = true;
        $this->logFile = $logFile ?: '/var/log/wallboard/ami-debug.log';
        
        // Ensure log directory exists
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        $this->log("=== AMI Debug Logging Started ===");
        $this->log("Host: {$this->host}:{$this->port}");
        $this->log("User: {$this->username}");
    }
    
    /**
     * Log message with timestamp and optional data
     */
    private function log($message, $level = 'DEBUG', $data = null) {
        $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
        $logLine = "[$timestamp] [AMI] [$level] $message";
        
        if ($data !== null) {
            if (is_array($data)) {
                // Don't log sensitive data
                $safeData = $data;
                if (isset($safeData['Secret'])) $safeData['Secret'] = '***';
                if (isset($safeData['_raw'])) unset($safeData['_raw']);
                $logLine .= " | " . json_encode($safeData, JSON_UNESCAPED_SLASHES);
            } else {
                $logLine .= " | $data";
            }
        }
        
        $logLine .= "\n";
        
        if ($this->debug) {
            // Color output for terminal
            $colors = [
                'ERROR' => "\033[31m",
                'WARN' => "\033[33m",
                'INFO' => "\033[32m",
                'DEBUG' => "\033[36m",
            ];
            $reset = "\033[0m";
            $color = $colors[$level] ?? '';
            echo $color . $logLine . $reset;
        }
        
        if ($this->logFile) {
            @file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Connect to AMI
     */
    public function connect() {
        $this->log("Connecting to {$this->host}:{$this->port}...", 'INFO');
        
        // Close existing connection
        if ($this->socket) {
            $this->disconnect();
        }
        
        $this->lastError = '';
        $errno = 0;
        $errstr = '';
        
        // Attempt connection
        $this->socket = @fsockopen(
            $this->host, 
            $this->port, 
            $errno, 
            $errstr, 
            $this->connectionTimeout
        );
        
        if (!$this->socket) {
            $this->lastError = "Connection failed: $errstr (errno: $errno)";
            $this->log($this->lastError, 'ERROR');
            $this->logTroubleshooting('connection');
            return false;
        }
        
        // Configure socket
        stream_set_timeout($this->socket, $this->readTimeout);
        stream_set_blocking($this->socket, true);
        
        // Read banner
        $banner = $this->readLine();
        if ($banner === false) {
            $this->lastError = "No banner received from AMI";
            $this->log($this->lastError, 'ERROR');
            $this->disconnect();
            return false;
        }
        
        $this->log("Connected! Banner: " . trim($banner), 'INFO');
        $this->lastActivity = time();
        
        return true;
    }
    
    /**
     * Login to AMI
     */
    public function login() {
        if (!$this->socket) {
            $this->lastError = "Not connected";
            return false;
        }
        
        $this->log("Authenticating as '{$this->username}'...", 'INFO');
        
        $response = $this->sendAction('Login', [
            'Username' => $this->username,
            'Secret' => $this->password
        ]);
        
        if ($response === false) {
            $this->lastError = "Login failed: No response from server";
            $this->log($this->lastError, 'ERROR');
            return false;
        }
        
        $responseStatus = strtolower($response['Response'] ?? '');
        
        if ($responseStatus !== 'success') {
            $this->lastError = "Login failed: " . ($response['Message'] ?? 'Unknown error');
            $this->log($this->lastError, 'ERROR');
            $this->logTroubleshooting('auth');
            return false;
        }
        
        $this->connected = true;
        $this->reconnectAttempts = 0;
        $this->log("Authentication successful!", 'INFO');
        
        return true;
    }
    
    /**
     * Connect and login in one call
     */
    public function connectAndLogin() {
        if (!$this->connect()) {
            return false;
        }
        
        if (!$this->login()) {
            $this->disconnect();
            return false;
        }
        
        return true;
    }
    
    /**
     * Disconnect from AMI
     */
    public function disconnect() {
        if ($this->socket) {
            $this->log("Disconnecting...");
            
            // Try graceful logout
            if ($this->connected) {
                @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            }
            
            @fclose($this->socket);
            $this->socket = null;
        }
        
        $this->connected = false;
        $this->buffer = '';
    }
    
    /**
     * Check if connected and connection is healthy
     */
    public function isConnected() {
        if (!$this->socket || !$this->connected) {
            return false;
        }
        
        // Check socket status
        $info = @stream_get_meta_data($this->socket);
        if (!$info || !empty($info['eof'])) {
            $this->log("Connection lost (EOF)", 'WARN');
            $this->connected = false;
            return false;
        }
        
        // Check for stale connection (no activity for 2 minutes)
        if ($this->lastActivity > 0 && (time() - $this->lastActivity) > 120) {
            $this->log("Connection may be stale, last activity: " . (time() - $this->lastActivity) . "s ago", 'WARN');
        }
        
        return true;
    }
    
    /**
     * Send an AMI action and get response
     */
    public function sendAction($action, $params = []) {
        if (!$this->socket) {
            $this->log("Cannot send '$action': not connected", 'ERROR');
            return false;
        }
        
        // Build command
        $cmd = "Action: $action\r\n";
        foreach ($params as $key => $value) {
            if ($key !== 'Secret') {
                $cmd .= "$key: $value\r\n";
            } else {
                $cmd .= "$key: ***\r\n"; // Log safely
            }
        }
        
        $this->log("Sending: $action", 'DEBUG', $params);
        
        // Build actual command with real secret
        $realCmd = "Action: $action\r\n";
        foreach ($params as $key => $value) {
            $realCmd .= "$key: $value\r\n";
        }
        $realCmd .= "\r\n";
        
        // Send
        $written = @fwrite($this->socket, $realCmd);
        if ($written === false || $written === 0) {
            $this->lastError = "Failed to write to socket";
            $this->log($this->lastError, 'ERROR');
            $this->connected = false;
            return false;
        }
        
        $this->lastActivity = time();
        
        // Read response
        return $this->readResponse();
    }
    
    /**
     * Read a response (blocks until complete)
     */
    private function readResponse() {
        $response = [];
        $timeout = time() + 10;
        $raw = '';
        
        while (time() < $timeout) {
            $line = $this->readLine();
            
            if ($line === false) {
                // Check if connection lost
                if (!$this->isConnected()) {
                    return false;
                }
                continue;
            }
            
            $raw .= $line;
            $line = trim($line);
            
            // Empty line = end of response
            if ($line === '') {
                break;
            }
            
            // Parse key: value
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $key = trim(substr($line, 0, $colonPos));
                $value = trim(substr($line, $colonPos + 1));
                $response[$key] = $value;
            }
        }
        
        if (!empty($response)) {
            $this->log("Response: " . ($response['Response'] ?? 'N/A'), 'DEBUG', $response);
        }
        
        return !empty($response) ? $response : false;
    }
    
    /**
     * Read a single line from socket
     */
    private function readLine() {
        if (!$this->socket) {
            return false;
        }
        
        $line = @fgets($this->socket, 4096);
        
        if ($line === false) {
            $info = @stream_get_meta_data($this->socket);
            if ($info && !empty($info['eof'])) {
                $this->log("Socket EOF", 'WARN');
                $this->connected = false;
            }
            // Timeout is OK, just return false
        }
        
        return $line;
    }
    
    /**
     * Read next event from AMI (non-blocking with timeout)
     */
    public function readEvent($timeout = 1) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $startTime = microtime(true);
        
        while ((microtime(true) - $startTime) < $timeout) {
            $line = @fgets($this->socket, 4096);
            
            if ($line === false) {
                $info = @stream_get_meta_data($this->socket);
                if ($info && !empty($info['eof'])) {
                    $this->log("Connection lost during read", 'WARN');
                    $this->connected = false;
                    return false;
                }
                // Timeout, sleep briefly and continue
                usleep(10000); // 10ms
                continue;
            }
            
            $this->buffer .= $line;
            $this->lastActivity = time();
            
            // Check for complete event (double newline)
            if (trim($line) === '' && trim($this->buffer) !== '') {
                $event = $this->parseEvent($this->buffer);
                $this->buffer = '';
                
                if ($event && isset($event['Event'])) {
                    $this->trackDiscovery($event);
                    return $event;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Parse raw event data with field normalization
     */
    private function parseEvent($raw) {
        $event = [];
        $lines = explode("\n", $raw);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $key = trim(substr($line, 0, $colonPos));
                $value = trim(substr($line, $colonPos + 1));
                
                // Normalize field name
                $normalizedKey = self::$fieldAliases[$key] ?? $key;
                
                // Store both original and normalized if different
                $event[$normalizedKey] = $value;
                if ($normalizedKey !== $key) {
                    $event['_orig_' . $key] = $value;
                }
            }
        }
        
        // Store raw for debugging
        $event['_raw'] = $raw;
        
        return $event;
    }
    
    /**
     * Track discovered event types and fields
     */
    private function trackDiscovery($event) {
        $eventType = $event['Event'] ?? 'Unknown';
        
        // Track event type
        if (!isset($this->discoveredEventTypes[$eventType])) {
            $this->discoveredEventTypes[$eventType] = 0;
            $this->log("NEW EVENT TYPE DISCOVERED: $eventType", 'INFO');
        }
        $this->discoveredEventTypes[$eventType]++;
        
        // Track fields for this event type
        if (!isset($this->discoveredFields[$eventType])) {
            $this->discoveredFields[$eventType] = [];
        }
        
        foreach (array_keys($event) as $field) {
            if (strpos($field, '_') === 0) continue; // Skip internal fields
            
            if (!isset($this->discoveredFields[$eventType][$field])) {
                $this->discoveredFields[$eventType][$field] = true;
                $this->log("New field in $eventType: $field = " . substr($event[$field], 0, 50), 'DEBUG');
            }
        }
    }
    
    /**
     * Get discovered event types and their fields
     */
    public function getDiscoveredSchema() {
        return [
            'event_types' => $this->discoveredEventTypes,
            'fields_by_type' => $this->discoveredFields
        ];
    }
    
    /**
     * Register callback for specific event type
     */
    public function onEvent($eventType, $callback) {
        if (!isset($this->eventCallbacks[$eventType])) {
            $this->eventCallbacks[$eventType] = [];
        }
        $this->eventCallbacks[$eventType][] = $callback;
    }
    
    /**
     * Register callback for all events
     */
    public function onAnyEvent($callback) {
        $this->onEvent('*', $callback);
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Ping to keep alive
     */
    public function ping() {
        $response = $this->sendAction('Ping');
        $success = $response && isset($response['Ping']) && $response['Ping'] === 'Pong';
        if (!$success) {
            $this->log("Ping failed", 'WARN');
        }
        return $success;
    }
    
    /**
     * Request queue status
     */
    public function getQueueStatus($queue = null) {
        $params = [];
        if ($queue) {
            $params['Queue'] = $queue;
        }
        return $this->sendAction('QueueStatus', $params);
    }
    
    /**
     * Request queue summary
     */
    public function getQueueSummary($queue = null) {
        $params = [];
        if ($queue) {
            $params['Queue'] = $queue;
        }
        return $this->sendAction('QueueSummary', $params);
    }
    
    /**
     * Get SIP/PJSIP endpoint status
     */
    public function getEndpointStatus() {
        // VitalPBX uses PJSIP by default
        $this->sendAction('PJSIPShowEndpoints');
        // Also try classic SIP for compatibility
        $this->sendAction('SIPpeers');
    }
    
    /**
     * Get active channels
     */
    public function getActiveChannels() {
        return $this->sendAction('CoreShowChannels');
    }
    
    /**
     * Attempt reconnection with exponential backoff
     */
    public function reconnect() {
        $this->reconnectAttempts++;
        
        if ($this->reconnectAttempts > $this->maxReconnectAttempts) {
            $this->log("Max reconnection attempts ({$this->maxReconnectAttempts}) reached", 'ERROR');
            return false;
        }
        
        // Exponential backoff: 5, 10, 20, 40... capped at 60
        $delay = min($this->reconnectDelay * pow(2, $this->reconnectAttempts - 1), 60);
        
        $this->log("Reconnection attempt {$this->reconnectAttempts}/{$this->maxReconnectAttempts} in {$delay}s...", 'WARN');
        
        sleep($delay);
        
        return $this->connectAndLogin();
    }
    
    /**
     * Reset reconnection counter (call after successful operations)
     */
    public function resetReconnectCounter() {
        $this->reconnectAttempts = 0;
    }
    
    /**
     * Log troubleshooting tips
     */
    private function logTroubleshooting($type) {
        $tips = [
            'connection' => [
                "=== CONNECTION TROUBLESHOOTING ===",
                "1. Verify AMI is enabled on VitalPBX",
                "   - VitalPBX Admin > Settings > PBX Settings > AMI",
                "2. Check firewall allows port {$this->port}",
                "   - On VitalPBX: firewall-cmd --list-all",
                "   - Should show {$this->port}/tcp allowed",
                "3. Test from this server:",
                "   - telnet {$this->host} {$this->port}",
                "4. Check VitalPBX is running:",
                "   - systemctl status asterisk",
                "5. Check network connectivity:",
                "   - ping {$this->host}",
            ],
            'auth' => [
                "=== AUTHENTICATION TROUBLESHOOTING ===",
                "1. Verify AMI user exists in VitalPBX",
                "   - VitalPBX Admin > Settings > PBX Settings > AMI",
                "   - Check username: {$this->username}",
                "2. Verify AMI secret/password is correct",
                "3. Check permit list includes this server's IP",
                "   - Get this server's IP: curl ifconfig.me",
                "   - Add to AMI user's permit list",
                "4. Check Asterisk logs:",
                "   - tail -f /var/log/asterisk/messages",
                "5. Try manual login:",
                "   - telnet {$this->host} {$this->port}",
                "   - Action: Login",
                "   - Username: {$this->username}",
                "   - Secret: <your-password>",
            ]
        ];
        
        if (isset($tips[$type])) {
            foreach ($tips[$type] as $tip) {
                $this->log($tip, 'INFO');
            }
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->disconnect();
    }
}

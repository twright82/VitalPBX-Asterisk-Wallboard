/**
 * Wallboard JavaScript
 * VitalPBX Asterisk Wallboard
 */

class Wallboard {
    constructor() {
        this.refreshRate = 5000; // 5 seconds
        this.queues = {};
        this.data = null;
        this.connected = false;
        this.retryCount = 0;
        this.maxRetries = 5;

        // Easter egg counters
        this.companyNameClicks = 0;
        this.slaBoxClicks = 0;
        this.companyNameClickTimer = null;
        this.slaBoxClickTimer = null;

        this.init();
    }
    
    async init() {
        // Start clock
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);

        // Start timer updates
        setInterval(() => this.updateTimers(), 1000);

        // Setup easter eggs
        this.setupEasterEggs();

        // Initial fetch
        await this.fetchData();

        // Start refresh loop
        setInterval(() => this.fetchData(), this.refreshRate);
    }

    setupEasterEggs() {
        // Easter egg 1: Click company name 7 times for trophy message
        const companyName = document.getElementById('companyName');
        if (companyName) {
            companyName.style.cursor = 'pointer';
            companyName.addEventListener('click', () => {
                clearTimeout(this.companyNameClickTimer);
                this.companyNameClicks++;

                if (this.companyNameClicks >= 7) {
                    this.showTrophyEasterEgg();
                    this.companyNameClicks = 0;
                }

                // Reset counter after 3 seconds of no clicks
                this.companyNameClickTimer = setTimeout(() => {
                    this.companyNameClicks = 0;
                }, 3000);
            });
        }

        // Easter egg 2: Click SLA box 5 times for Eric typing message
        const slaBox = document.querySelector('.sla-box');
        if (slaBox) {
            slaBox.style.cursor = 'pointer';
            slaBox.addEventListener('click', () => {
                clearTimeout(this.slaBoxClickTimer);
                this.slaBoxClicks++;

                if (this.slaBoxClicks >= 5) {
                    this.showEricEasterEgg();
                    this.slaBoxClicks = 0;
                }

                // Reset counter after 3 seconds of no clicks
                this.slaBoxClickTimer = setTimeout(() => {
                    this.slaBoxClicks = 0;
                }, 3000);
            });
        }
    }

    showTrophyEasterEgg() {
        // Play triumphant fanfare
        this.playVictoryFanfare();

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'easter-egg-overlay trophy-overlay';
        overlay.innerHTML = `
            <div class="confetti-container" id="confettiContainer"></div>
            <div class="trophy-content">
                <div class="trophy-icon">🏆</div>
                <div class="trophy-message">Built by Tim Wright</div>
                <div class="trophy-subtitle">The Greatest Wallboard Ever Created. Tremendous.</div>
                <div class="trophy-zinger">Other wallboards wish they had this energy.</div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Create confetti
        this.createConfetti();

        // Close on click
        overlay.addEventListener('click', () => {
            overlay.classList.add('fade-out');
            setTimeout(() => overlay.remove(), 500);
        });

        // Auto-close after 6 seconds
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.classList.add('fade-out');
                setTimeout(() => overlay.remove(), 500);
            }
        }, 6000);
    }

    playVictoryFanfare() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const masterGain = audioCtx.createGain();
            masterGain.gain.value = 0.3;
            masterGain.connect(audioCtx.destination);

            // Epic bass drop
            const bassOsc = audioCtx.createOscillator();
            const bassGain = audioCtx.createGain();
            bassOsc.type = 'sawtooth';
            bassOsc.frequency.setValueAtTime(80, audioCtx.currentTime);
            bassOsc.frequency.exponentialRampToValueAtTime(40, audioCtx.currentTime + 0.5);
            bassGain.gain.setValueAtTime(0.8, audioCtx.currentTime);
            bassGain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 1);
            bassOsc.connect(bassGain);
            bassGain.connect(masterGain);
            bassOsc.start();
            bassOsc.stop(audioCtx.currentTime + 1);

            // Triumphant fanfare notes (C-E-G-C chord progression)
            const notes = [
                { freq: 523.25, start: 0.1, dur: 0.3 },    // C5
                { freq: 659.25, start: 0.2, dur: 0.3 },    // E5
                { freq: 783.99, start: 0.3, dur: 0.4 },    // G5
                { freq: 1046.50, start: 0.5, dur: 0.8 },   // C6 (big finish)
            ];

            notes.forEach(note => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.value = note.freq;
                gain.gain.setValueAtTime(0, audioCtx.currentTime + note.start);
                gain.gain.linearRampToValueAtTime(0.4, audioCtx.currentTime + note.start + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + note.start + note.dur);
                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(audioCtx.currentTime + note.start);
                osc.stop(audioCtx.currentTime + note.start + note.dur);
            });

            // Airhorn-style accent
            const hornOsc = audioCtx.createOscillator();
            const hornGain = audioCtx.createGain();
            hornOsc.type = 'square';
            hornOsc.frequency.setValueAtTime(440, audioCtx.currentTime + 0.6);
            hornOsc.frequency.linearRampToValueAtTime(880, audioCtx.currentTime + 0.7);
            hornGain.gain.setValueAtTime(0, audioCtx.currentTime + 0.6);
            hornGain.gain.linearRampToValueAtTime(0.3, audioCtx.currentTime + 0.65);
            hornGain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 1.2);
            hornOsc.connect(hornGain);
            hornGain.connect(masterGain);
            hornOsc.start(audioCtx.currentTime + 0.6);
            hornOsc.stop(audioCtx.currentTime + 1.2);

        } catch (e) {
            console.log('Audio not supported');
        }
    }

    createConfetti() {
        const container = document.getElementById('confettiContainer');
        if (!container) return;

        const colors = ['#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7', '#dfe6e9', '#ff7675'];

        for (let i = 0; i < 150; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 3 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';

            // Random shapes
            if (Math.random() > 0.5) {
                confetti.style.borderRadius = '50%';
            }

            container.appendChild(confetti);
        }
    }

    showEricEasterEgg() {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'easter-egg-overlay eric-overlay';
        overlay.innerHTML = `
            <div class="eric-content">
                <div class="eric-bubble">
                    <span class="eric-text"></span>
                    <span class="typing-dots"><span>.</span><span>.</span><span>.</span></span>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const textEl = overlay.querySelector('.eric-text');
        const dotsEl = overlay.querySelector('.typing-dots');

        // Message sequence
        const messages = [
            { text: 'Eric is typing', delay: 2000 },
            { text: 'Eric is still typing', delay: 2500 },
            { text: 'Eric has gone to lunch. Again.', final: true, delay: 3000 }
        ];

        let currentIndex = 0;

        const showNextMessage = () => {
            if (currentIndex >= messages.length) return;

            const msg = messages[currentIndex];
            textEl.textContent = msg.text;

            if (msg.final) {
                dotsEl.style.display = 'none';
                // Auto-close after showing final message
                setTimeout(() => {
                    if (overlay.parentNode) {
                        overlay.classList.add('fade-out');
                        setTimeout(() => overlay.remove(), 500);
                    }
                }, msg.delay);
            } else {
                dotsEl.style.display = 'inline';
                currentIndex++;
                setTimeout(showNextMessage, msg.delay);
            }
        };

        showNextMessage();

        // Close on click
        overlay.addEventListener('click', () => {
            overlay.classList.add('fade-out');
            setTimeout(() => overlay.remove(), 500);
        });
    }
    
    async fetchData() {
        try {
            const response = await fetch('/api/realtime.php');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown error');
            }
            
            this.data = data;
            this.connected = true;
            this.retryCount = 0;
            
            // Update config
            if (data.config) {
                this.refreshRate = (data.config.refresh_rate || 5) * 1000;
            }
            
            // Store queue mapping
            if (data.queues) {
                data.queues.forEach(q => {
                    this.queues[q.queue_number] = q;
                });
            }
            
            this.render();
            this.hideLoading();
            this.hideError();
            
        } catch (error) {
            console.error('Fetch error:', error);
            this.retryCount++;
            
            if (this.retryCount >= this.maxRetries) {
                this.showError();
            }
        }
    }
    
    render() {
        if (!this.data) return;

        this.renderSummary();
        this.renderQueueCards();
        this.renderAgents();
        this.renderTeamMembers();
        this.renderWaitingCalls();
        this.renderLeaderboard();
        this.renderBottomBar();
        this.renderAlertPopups();
    }
    
    renderSummary() {
        const summary = this.data.summary;
        
        // Total waiting
        document.getElementById('totalWaiting').textContent = summary.total_waiting;
        
        // Longest wait
        const longestWait = document.getElementById('longestWait');
        longestWait.textContent = summary.longest_wait_formatted;
        longestWait.className = `wait-time ${summary.longest_wait_class}`;
        
        // Alert state
        const queueTotal = document.getElementById('queueTotal');
        if (summary.longest_wait > 120) { // 2 minutes
            queueTotal.classList.add('alert');
        } else {
            queueTotal.classList.remove('alert');
        }
        
        // SLA
        const slaPercent = document.getElementById('slaPercent');
        slaPercent.textContent = `${summary.sla_percent}%`;
        slaPercent.className = `value ${summary.sla_class}`;
        
        // Callbacks
        const callbackEl = document.getElementById('callbackCount');
        const abandonedEl = document.getElementById('abandonedCount');
        const totalCallsEl = document.getElementById('totalCallsCount');
        if (callbackEl) callbackEl.textContent = summary.callbacks_waiting || 0;
        if (abandonedEl) abandonedEl.textContent = summary.abandoned_today || 0;
        if (totalCallsEl) totalCallsEl.textContent = summary.total_calls_today || 0;
        
        // Agent counts
        const counts = this.data.agent_counts;
        document.getElementById('countAvailable').textContent = counts.available || 0;
        document.getElementById('countOnCall').textContent = counts.on_call || 0;
        document.getElementById('countPaused').textContent = counts.paused || 0;
    }
    
    renderQueueCards() {
        const container = document.getElementById('queueCards');
        const queues = this.data.queues;
        
        if (!queues || queues.length === 0) {
            container.innerHTML = '<div class="empty-message">No queues configured</div>';
            return;
        }
        
        container.innerHTML = queues.map(queue => {
            let cardClass = 'queue-card';
            if (queue.calls_waiting > 3) {
                cardClass += ' critical';
            } else if (queue.calls_waiting > 0) {
                cardClass += ' has-calls';
            }
            
            // Always show calls today as main number, waiting shown separately
            const avgWait = this.formatDuration(queue.avg_wait || 0);
            const abandoned = queue.abandoned_today || 0;
            const answered = queue.answered_today || 0;
            const statsLine = `Ans: ${answered}` + (abandoned > 0 ? ` · Abd: ${abandoned}` : '');
            const waitingLine = queue.calls_waiting > 0 
                ? `⏳ ${queue.calls_waiting} waiting (${this.formatDuration(queue.longest_wait)})` 
                : `Avg: ${avgWait}`;
            
            return `
                <div class="${cardClass}" data-queue="${queue.queue_number}" title="${queue.display_name} - Waiting: ${queue.calls_waiting}, Today: ${queue.calls_today}, Answered: ${queue.answered_today}, Abandoned: ${queue.abandoned_today}, Avg Wait: ${avgWait}">
                    <div class="name">${this.escapeHtml(queue.display_name)}</div>
                    <div class="count">${queue.calls_today}</div>
                    <div class="queue-label">today</div>
                    <div class="queue-stats">${statsLine}</div>
                    <div class="wait">${waitingLine}</div>
                </div>
            `;
        }).join('');
    }
    
    renderAgents() {
        const container = document.getElementById('agentsGrid');
        const agents = this.data.agents;
        
        if (!agents || agents.length === 0) {
            container.innerHTML = '<div class="empty-message">No agents configured</div>';
            return;
        }
        
        container.innerHTML = agents.map(agent => {
            const cardClass = `agent-card ${agent.status}`;
            
            // Build timer
            let timerHtml = '';
            if ((agent.status === 'on_call' || agent.status === 'ringing') && agent.call_started_at) {
                timerHtml = `<div class="agent-timer" data-start="${agent.call_started_at}">${this.formatDuration(agent.call_duration)}</div>`;
            }
            
            // Build status text
            let statusHtml = '';
            if (agent.status === 'available') {
                statusHtml = '<div class="agent-status available">Available</div>';
            } else if (agent.status === 'on_call') {
                const callIcon = agent.current_call_type === 'outbound' ? '📤 ' : '📞 ';
                const phoneNum = this.extractPhone(agent.talking_to);
                const formattedPhone = phoneNum ? this.formatPhone(phoneNum) : '';
                let queuePrefix = agent.current_queue_name ? agent.current_queue_name + ' ' : ''; let callerDisplay = queuePrefix + (agent.talking_to_name || '') + (formattedPhone ? ' ' + formattedPhone : ''); if (!callerDisplay.trim()) callerDisplay = 'On call';
                statusHtml = `<div class="agent-status talking">${callIcon}${callerDisplay}</div>`;
            } else if (agent.status === 'ringing') {
                const ringIcon = agent.current_call_type === 'outbound' ? '📤 Dialing: ' : '📞 ';
                const ringInfo = agent.talking_to_name || this.formatPhone(agent.talking_to) || 'Incoming';
                statusHtml = `<div class="agent-status ringing">${ringIcon}${ringInfo}</div>`;
            } else if (agent.status === 'paused') {
                const reason = agent.pause_reason || 'Paused';
                statusHtml = `<div class="agent-status paused">☕ ${this.escapeHtml(reason)}</div>`;
            } else {
                statusHtml = `<div class="agent-status offline">${agent.status}</div>`;
            }
            
            // Build queue badges
            // Show all tracked queues - green if signed in, gray if not
            const allQueues = Object.values(this.queues || {});
            const queueBadges = allQueues.map(queue => {
                const membership = (agent.queues || {})[queue.queue_number];
                const isSignedIn = membership && membership.signed_in;
                const badgeClass = isSignedIn ? 'q-badge in' : 'q-badge out';
                return `<span class="${badgeClass}">${this.escapeHtml(queue.display_name)}</span>`;
            }).join('');
            
            // Build stats line
            const talkTimeFormatted = this.formatDuration(agent.talk_time_today || 0);
            const avgHandleFormatted = this.formatDuration(agent.avg_handle_time || 0);
            const statsHtml = `<div class="agent-stats">📞 ${agent.calls_today || 0} calls · ⏱️ ${talkTimeFormatted} · 📊 ${avgHandleFormatted} avg${agent.missed_today ? ` · ❌ ${agent.missed_today} missed` : ""}</div>`;
            return `
                <div class="${cardClass}" data-extension="${agent.extension}">
                    <div class="agent-row-top">
                        <div>
                            <div class="agent-name">${this.escapeHtml(agent.name)}</div>
                            <div class="agent-ext">Ext ${agent.extension}</div>
                        </div>
                        ${timerHtml}
                    </div>
                    ${statusHtml}
                    ${statsHtml}
                    <div class="queue-badges">${queueBadges}</div>
                </div>
            `;
        }).join('');
    }

    renderTeamMembers() {
        const container = document.getElementById('teamGrid');
        const panel = document.getElementById('teamPanel');
        const members = this.data.team_members;

        // Hide panel if no team members
        if (!members || members.length === 0) {
            if (panel) panel.style.display = 'none';
            return;
        }

        panel.style.display = 'block';

        // Count statuses
        let available = 0, onCall = 0;
        members.forEach(m => {
            if (m.status === 'available') available++;
            if (m.status === 'on_call' || m.status === 'ringing') onCall++;
        });

        document.getElementById('teamCountAvailable').textContent = available;
        document.getElementById('teamCountOnCall').textContent = onCall;

        container.innerHTML = members.map(member => {
            const cardClass = `team-card ${member.status}`;

            // Build timer
            let timerHtml = '';
            if ((member.status === 'on_call' || member.status === 'ringing') && member.call_started_at) {
                timerHtml = `<div class="team-timer" data-start="${member.call_started_at}">${this.formatDuration(member.call_duration)}</div>`;
            }

            // Build status text
            let statusHtml = '';
            if (member.status === 'available') {
                statusHtml = '<div class="team-status available">Available</div>';
            } else if (member.status === 'on_call') {
                const callIcon = member.current_call_type === 'outbound' ? '📤 ' : '📞 ';
                const callerDisplay = member.talking_to_name || this.formatPhone(member.talking_to) || 'On call';
                statusHtml = `<div class="team-status talking">${callIcon}${callerDisplay}</div>`;
            } else if (member.status === 'ringing') {
                const ringIcon = member.current_call_type === 'outbound' ? '📤 Dialing: ' : '📞 ';
                const ringInfo = member.talking_to_name || this.formatPhone(member.talking_to) || 'Incoming';
                statusHtml = `<div class="team-status ringing">${ringIcon}${ringInfo}</div>`;
            } else if (member.status === 'paused') {
                statusHtml = `<div class="team-status paused">☕ ${this.escapeHtml(member.pause_reason || 'Away')}</div>`;
            } else {
                statusHtml = `<div class="team-status offline">${member.status || 'Offline'}</div>`;
            }

            // Team/department badge
            const deptHtml = member.team ? `<div class="team-dept">${this.escapeHtml(member.team)}</div>` : '';

            return `
                <div class="${cardClass}" data-extension="${member.extension}">
                    <div class="team-row-top">
                        <div>
                            <div class="team-name">${this.escapeHtml(member.name)}</div>
                            <div class="team-ext">Ext ${member.extension}</div>
                        </div>
                        ${timerHtml}
                    </div>
                    ${statusHtml}
                    ${deptHtml}
                </div>
            `;
        }).join('');
    }

    renderWaitingCalls() {
        const container = document.getElementById('waitingList');
        const calls = this.data.calls_waiting;
        
        if (!calls || calls.length === 0) {
            container.innerHTML = '<div class="empty-message">No calls waiting</div>';
            return;
        }
        
        container.innerHTML = calls.map(call => {
            const itemClass = `waiting-item ${call.wait_class}`;
            const queue = this.queues[call.queue_number];
            const queueName = queue ? queue.display_name : call.queue_number;
            
            return `
                <div class="${itemClass}" data-unique-id="${call.unique_id}">
                    <div>
                        <div class="queue">${this.escapeHtml(queueName)}</div>
                        <div class="caller">${this.escapeHtml(call.caller_formatted)}</div>
                    </div>
                    <div class="time ${call.wait_class}" data-entered="${call.entered_queue_at}">${call.wait_formatted}</div>
                </div>
            `;
        }).join('');
    }
    
    renderLeaderboard() {
        const container = document.getElementById('leaderboard');
        const leaders = this.data.leaderboard;
        
        if (!leaders || leaders.length === 0) {
            container.innerHTML = '<div class="empty-message">No calls yet today</div>';
            return;
        }
        
        container.innerHTML = leaders.map(leader => {
            return `
                <div class="leader-item">
                    <div class="leader-medal">${leader.medal}</div>
                    <div class="leader-info">
                        <div class="leader-name">${this.escapeHtml(leader.name)}</div>
                        <div class="leader-title">${leader.title.emoji} ${leader.title.title}</div>
                    </div>
                    <div class="leader-stats">
                        <div class="leader-calls">${leader.calls}</div>
                        <div class="leader-avg">avg ${leader.avg_formatted}</div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    renderBottomBar() {
        // Company name
        document.getElementById('companyName').textContent = this.data.company || 'Call Center';

        // Queue numbers
        const queueNums = document.getElementById('queueNumbers');
        if (this.data.queues) {
            queueNums.innerHTML = this.data.queues.map(q =>
                `<span>${this.escapeHtml(q.display_name)}: <span class="queue-num">${q.queue_number}</span></span>`
            ).join('');
        }
    }

    renderAlertPopups() {
        const alerts = this.data.active_alerts;

        // Get or create container
        let container = document.getElementById('alertPopupContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'alertPopupContainer';
            container.className = 'alert-popup-container';
            document.body.appendChild(container);
        }

        // No alerts - hide container
        if (!alerts || alerts.length === 0) {
            container.innerHTML = '';
            return;
        }

        // Only show unacknowledged alerts
        const unacknowledged = alerts.filter(a => !a.is_acknowledged);

        if (unacknowledged.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = unacknowledged.map(alert => {
            const severityClass = alert.severity === 'critical' ? 'critical' : 'warning';
            const icon = alert.severity === 'critical' ? '🚨' : '⚠️';
            const timeAgo = this.formatTimeAgo(alert.triggered_at);

            return `
                <div class="alert-popup ${severityClass}" data-alert-id="${alert.id}">
                    <div class="alert-popup-header">
                        <span class="alert-popup-icon">${icon}</span>
                        <span class="alert-popup-type">${this.escapeHtml(alert.alert_type.replace(/_/g, ' '))}</span>
                        <button class="alert-popup-dismiss" onclick="wallboard.acknowledgeAlert(${alert.id})">&times;</button>
                    </div>
                    <div class="alert-popup-message">${this.escapeHtml(alert.message)}</div>
                    <div class="alert-popup-time">${timeAgo}</div>
                </div>
            `;
        }).join('');
    }

    async acknowledgeAlert(alertId) {
        try {
            const response = await fetch('/api/acknowledge-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `alert_id=${alertId}`
            });

            if (response.ok) {
                // Remove the popup immediately
                const popup = document.querySelector(`.alert-popup[data-alert-id="${alertId}"]`);
                if (popup) {
                    popup.classList.add('fade-out');
                    setTimeout(() => popup.remove(), 300);
                }
            }
        } catch (error) {
            console.error('Failed to acknowledge alert:', error);
        }
    }

    formatTimeAgo(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        const now = new Date();
        const diffSeconds = Math.floor((now - date) / 1000);

        if (diffSeconds < 60) return 'Just now';
        if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)}m ago`;
        if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)}h ago`;
        return date.toLocaleTimeString();
    }
    
    updateTimers() {
        // Update call duration timers for agents
        document.querySelectorAll('.agent-timer[data-start]').forEach(el => {
            const start = el.dataset.start;
            if (start) {
                const startTime = new Date(start).getTime();
                const duration = Math.floor((Date.now() - startTime) / 1000);
                el.textContent = this.formatDuration(duration);
            }
        });

        // Update call duration timers for team members
        document.querySelectorAll('.team-timer[data-start]').forEach(el => {
            const start = el.dataset.start;
            if (start) {
                const startTime = new Date(start).getTime();
                const duration = Math.floor((Date.now() - startTime) / 1000);
                el.textContent = this.formatDuration(duration);
            }
        });
    }
    
    updateClock() {
        const clock = document.getElementById('clock');
        const now = new Date();
        clock.textContent = now.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
    
    formatDuration(seconds) {
        if (typeof seconds !== 'number' || seconds < 0) seconds = 0;
        
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    extractPhone(accountNum) {
        if (!accountNum) return null;
        const match = accountNum.match(/^#\d{2}(\d{10})$/);
        if (match) return match[1];
        if (accountNum.startsWith('+')) return accountNum.replace('+1', '');
        if (/^\d{10,11}$/.test(accountNum)) return accountNum;
        return null;
    }

    formatPhone(number) {
        if (!number) return '';
        
        // Remove non-digits
        const digits = number.replace(/\D/g, '');
        
        // Format US numbers
        if (digits.length === 10) {
            return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
        }
        if (digits.length === 11 && digits[0] === '1') {
            return `+1 (${digits.slice(1,4)}) ${digits.slice(4,7)}-${digits.slice(7)}`;
        }
        
        return number;
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
    
    showError() {
        document.getElementById('errorOverlay').style.display = 'flex';
    }
    
    hideError() {
        document.getElementById('errorOverlay').style.display = 'none';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.wallboard = new Wallboard();
});

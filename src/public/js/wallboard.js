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
        
        this.init();
    }
    
    async init() {
        // Start clock
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);
        
        // Start timer updates
        setInterval(() => this.updateTimers(), 1000);
        
        // Initial fetch
        await this.fetchData();
        
        // Start refresh loop
        setInterval(() => this.fetchData(), this.refreshRate);
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
        this.renderWaitingCalls();
        this.renderLeaderboard();
        this.renderBottomBar();
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
        document.getElementById('callbackCount').textContent = summary.callbacks_waiting || 0;
        document.getElementById('abandonedCount').textContent = summary.abandoned_today || 0;
        
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
            const queueBadges = Object.entries(agent.queues || {}).map(([queueNum, membership]) => {
                const queue = this.queues[queueNum];
                const name = queue ? queue.display_name : queueNum;
                const badgeClass = membership.signed_in ? 'q-badge in' : 'q-badge out';
                return `<span class="${badgeClass}">${this.escapeHtml(name)}</span>`;
            }).join('');
            
            // Build stats line
            const talkTimeFormatted = this.formatDuration(agent.talk_time_today || 0);
            const statsHtml = `<div class="agent-stats">📞 ${agent.calls_today || 0} calls · ⏱️ ${talkTimeFormatted}${agent.missed_today ? ` · ❌ ${agent.missed_today} missed` : ""}</div>`;
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
    
    updateTimers() {
        // Update call duration timers
        document.querySelectorAll('.agent-timer[data-start]').forEach(el => {
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

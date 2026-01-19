/**
 * Manager Dashboard JavaScript
 * VitalPBX Asterisk Wallboard
 * 
 * Extends wallboard.js with manager-specific features
 */

class ManagerDashboard extends Wallboard {
    constructor() {
        super();
        this.loadManagerData();
    }
    
    render() {
        super.render();
        this.renderManagerSection();
    }
    
    async loadManagerData() {
        // Additional data for manager view
        try {
            const [missedData, repeatData] = await Promise.all([
                this.fetchMissedCalls(),
                this.fetchRepeatCallers()
            ]);
            
            this.missedCalls = missedData;
            this.repeatCallers = repeatData;
            this.renderManagerSection();
        } catch (e) {
            console.error('Error loading manager data:', e);
        }
    }
    
    async fetchMissedCalls() {
        try {
            const response = await fetch('/api/stats.php?type=missed_today');
            if (response.ok) {
                const data = await response.json();
                return data.data || [];
            }
        } catch (e) {
            console.error('Error fetching missed calls:', e);
        }
        return [];
    }
    
    async fetchRepeatCallers() {
        try {
            const response = await fetch('/api/stats.php?type=repeat_callers');
            if (response.ok) {
                const data = await response.json();
                return data.data || [];
            }
        } catch (e) {
            console.error('Error fetching repeat callers:', e);
        }
        return [];
    }
    
    renderManagerSection() {
        this.renderAlerts();
        this.renderMissedCalls();
        this.renderRepeatCallers();
        this.renderDailyStats();
    }
    
    renderAlerts() {
        const container = document.getElementById('alertList');
        if (!container) return;
        
        const alerts = this.data?.alerts || [];
        
        if (alerts.length === 0) {
            container.innerHTML = '<div class="empty-message">No alerts today</div>';
            return;
        }
        
        container.innerHTML = alerts.slice(0, 5).map(alert => {
            const isCritical = ['sla_breach', 'no_agents'].includes(alert.alert_type);
            return `
                <div class="alert-item ${isCritical ? 'critical' : ''}">
                    <div class="time">${this.formatTime(alert.sent_at)}</div>
                    <div class="message">${this.escapeHtml(alert.alert_message)}</div>
                </div>
            `;
        }).join('');
    }
    
    renderMissedCalls() {
        const container = document.getElementById('missedList');
        if (!container) return;
        
        // Get missed counts from agent data
        const agents = this.data?.agents || [];
        const missedByAgent = agents
            .filter(a => (a.missed_today || 0) > 0)
            .sort((a, b) => b.missed_today - a.missed_today);
        
        if (missedByAgent.length === 0) {
            container.innerHTML = '<div class="empty-message">No missed calls today</div>';
            return;
        }
        
        container.innerHTML = missedByAgent.map(agent => `
            <div class="missed-item">
                <span class="agent">${this.escapeHtml(agent.name)}</span>
                <span class="count">${agent.missed_today}</span>
            </div>
        `).join('');
    }
    
    renderRepeatCallers() {
        const container = document.getElementById('repeatList');
        if (!container) return;
        
        const callers = this.repeatCallers || [];
        
        if (callers.length === 0) {
            container.innerHTML = '<div class="empty-message">No repeat callers flagged</div>';
            return;
        }
        
        container.innerHTML = callers.slice(0, 5).map(caller => `
            <div class="repeat-item">
                <span class="number">${this.formatPhone(caller.caller_number)}</span>
                <div class="calls">
                    <span class="badge">${caller.call_count}x</span>
                    <span class="last">${this.timeAgo(caller.last_call_at)}</span>
                </div>
            </div>
        `).join('');
    }
    
    renderDailyStats() {
        if (!this.data) return;
        
        const summary = this.data.summary;
        
        // Update stat values
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        
        setVal('statTotal', summary.total_calls_today || 0);
        setVal('statAnswered', summary.answered_today || 0);
        setVal('statAbandoned', summary.abandoned_today || 0);
        setVal('statAbandonRate', (summary.abandon_rate || 0) + '%');
        
        // Calculate avg handle time from agents
        const agents = this.data.agents || [];
        let totalHandle = 0;
        let handleCount = 0;
        agents.forEach(a => {
            if (a.avg_handle_time > 0) {
                totalHandle += a.avg_handle_time;
                handleCount++;
            }
        });
        const avgHandle = handleCount > 0 ? Math.round(totalHandle / handleCount) : 0;
        setVal('statAvgHandle', this.formatDuration(avgHandle));
        
        // Avg wait from queues
        const queues = this.data.queues || [];
        let totalWait = 0;
        let waitCount = 0;
        queues.forEach(q => {
            if (q.avg_wait > 0) {
                totalWait += q.avg_wait;
                waitCount++;
            }
        });
        const avgWait = waitCount > 0 ? Math.round(totalWait / waitCount) : 0;
        setVal('statAvgWait', this.formatDuration(avgWait));
        
        // Color the abandon rate
        const abandonEl = document.getElementById('statAbandoned');
        if (abandonEl) {
            abandonEl.classList.remove('good', 'warning', 'bad');
            if (summary.abandoned_today > 5) {
                abandonEl.classList.add('bad');
            } else if (summary.abandoned_today > 2) {
                abandonEl.classList.add('warning');
            }
        }
        
        // Also update bottom company name
        const bottomName = document.getElementById('companyNameBottom');
        if (bottomName) {
            bottomName.textContent = this.data.company || 'Call Center';
        }
    }
    
    formatTime(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
    
    timeAgo(timestamp) {
        if (!timestamp) return '';
        
        const now = Date.now();
        const then = new Date(timestamp).getTime();
        const diff = Math.floor((now - then) / 1000);
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }
}

// Override wallboard with manager dashboard
document.addEventListener('DOMContentLoaded', () => {
    window.wallboard = new ManagerDashboard();
    
    // Refresh manager data every 30 seconds
    setInterval(() => {
        if (window.wallboard.loadManagerData) {
            window.wallboard.loadManagerData();
        }
    }, 30000);
});

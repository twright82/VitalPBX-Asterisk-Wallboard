/**
 * Admin Panel JavaScript
 * VitalPBX Asterisk Wallboard
 */

// Flash messages auto-hide
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// Confirm dangerous actions
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});

// Mobile sidebar toggle
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
}

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let valid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                valid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please fill in all required fields');
        }
    });
});

// Test AMI connection
async function testAMIConnection() {
    const host = document.getElementById('ami_host').value;
    const port = document.getElementById('ami_port').value;
    const user = document.getElementById('ami_username').value;
    const pass = document.getElementById('ami_password').value;
    
    if (!host || !user || !pass) {
        alert('Please fill in all AMI fields');
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Testing...';
    
    try {
        const response = await fetch('/api/test-ami.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({host, port, username: user, password: pass})
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✓ Connection successful!');
        } else {
            alert('✗ Connection failed: ' + result.error);
        }
    } catch (err) {
        alert('✗ Test failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
    }
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show brief notification
        const note = document.createElement('div');
        note.textContent = 'Copied!';
        note.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#14532d;color:#4ade80;padding:10px 20px;border-radius:8px;z-index:9999;';
        document.body.appendChild(note);
        setTimeout(() => note.remove(), 2000);
    });
}

// Date range quick select
function setDateRange(range) {
    const end = new Date();
    let start = new Date();
    
    switch (range) {
        case 'today':
            break;
        case 'yesterday':
            start.setDate(start.getDate() - 1);
            end.setDate(end.getDate() - 1);
            break;
        case 'week':
            start.setDate(start.getDate() - 7);
            break;
        case 'month':
            start.setMonth(start.getMonth() - 1);
            break;
    }
    
    document.getElementById('start').value = start.toISOString().split('T')[0];
    document.getElementById('end').value = end.toISOString().split('T')[0];
}

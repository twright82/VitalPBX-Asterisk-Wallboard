# Deployment & Testing Guide

## Quick Deploy (5 Minutes)

### 1. Get the code onto your server

**Option A: Clone from GitHub**
```bash
git clone https://github.com/YOUR_USERNAME/VitalPBX-Asterisk-Wallboard.git
cd VitalPBX-Asterisk-Wallboard
```

**Option B: Upload the zip**
```bash
# Upload VitalPBX-Asterisk-Wallboard.zip to your server
unzip VitalPBX-Asterisk-Wallboard.zip
cd VitalPBX-Asterisk-Wallboard
```

### 2. Run the installer

```bash
chmod +x install.sh
./install.sh
```

The installer will prompt for:
- Instance name (e.g., "bertram")
- Company name
- Port (default 8080)
- AMI credentials (from VitalPBX)
- Admin username/password

### 3. Access your dashboard

- **Wallboard**: `http://your-server:8080/`
- **Manager View**: `http://your-server:8080/manager.html`
- **Admin Panel**: `http://your-server:8080/admin/`

---

## Manual Deploy (If install.sh doesn't work)

### 1. Copy environment file
```bash
cd docker
cp .env.example .env
nano .env  # Edit with your settings
```

### 2. Start containers
```bash
docker-compose up -d
```

### 3. Wait for MySQL to initialize (30 seconds)
```bash
docker-compose logs -f db  # Watch for "ready for connections"
# Ctrl+C to stop watching
```

### 4. Insert initial admin user
```bash
docker-compose exec db mysql -u root -p$DB_ROOT_PASS wallboard

# In MySQL prompt:
INSERT INTO users (username, password_hash, role, is_active) 
VALUES ('admin', '$2y$10$YourHashHere', 'admin', 1);

# Generate hash: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
```

### 5. Configure AMI in admin panel
- Go to `http://your-server:8080/admin/`
- Login with admin credentials
- Go to Settings > AMI Configuration
- Enter VitalPBX AMI details

---

## Testing the Installation

### Test 1: Check containers are running
```bash
docker-compose ps
```
Expected: All 3 containers (app, db, daemon) should show "Up"

### Test 2: Check web server
```bash
curl http://localhost:8080/
```
Expected: HTML content returned

### Test 3: Check API
```bash
curl http://localhost:8080/api/health.php
```
Expected: `{"status":"ok",...}`

### Test 4: Check database connection
```bash
curl http://localhost:8080/api/realtime.php
```
Expected: JSON with queues and agents (possibly empty)

### Test 5: Check daemon logs
```bash
docker-compose logs -f daemon
```
Expected: Should show "Connecting to AMI..." messages

---

## VitalPBX AMI Setup

### Create AMI User in VitalPBX:

1. Login to VitalPBX admin
2. Go to **Settings > PBX Settings > AMI**
3. Click **Add AMI Account**
4. Fill in:
   - **AMI Name**: wallboard
   - **Secret**: (generate a secure password)
   - **Permit**: Add your wallboard server IP (e.g., 173.242.94.14/255.255.255.255)
   - **Read**: system, call, log, verbose, command, agent, user
   - **Write**: system, call, log, verbose, command, agent, user
5. Click **Save** and **Apply Changes**

### Test AMI Connection:
```bash
# From your wallboard server
telnet pbx1.as36001.net 5038

# Should see:
# Asterisk Call Manager/...

# Type:
Action: Login
Username: wallboard
Secret: yourpassword

# Should see:
# Response: Success
```

---

## Adding Your Queues

After installation, add your queues in Admin Panel:

| Queue Number | Name | Display Name |
|--------------|------|--------------|
| 1293 | ISP Support Queue | Support |
| 1292 | ISP Billing Queue | Billing |
| 1291 | ISP Sales Queue | Sales |
| 1201 | Bertram Fiber | Fiber |
| 1202 | Fiber In Home Install | Install |
| 1330 | Powercode Queue | Powercode |

---

## Adding Extensions

Add your agent extensions:

1. Go to Admin > Extensions
2. Click "Bulk Add"
3. Enter range: 1200-1220
4. Click Add
5. Edit each extension to add proper names

---

## Troubleshooting

### Daemon not connecting to AMI

**Check firewall:**
```bash
# On VitalPBX server
firewall-cmd --list-all
# Should allow 5038 from your wallboard IP
```

**Check AMI permit:**
```bash
# On VitalPBX server
cat /etc/asterisk/manager.conf
# Look for [wallboard] section
# permit= should include your wallboard IP
```

**Test manually:**
```bash
docker-compose exec daemon php /var/www/html/daemon/wallboard-daemon.php start --debug
```

### No data on dashboard

1. Make sure queues are added in Admin > Queues
2. Make sure extensions are added in Admin > Extensions
3. Make test calls to generate data
4. Check daemon logs: `docker-compose logs daemon`

### Database errors

```bash
# Rebuild database
docker-compose down -v
docker-compose up -d
# Wait 30 seconds for DB init
```

### Permission errors

```bash
# Fix permissions
docker-compose exec app chown -R www-data:www-data /var/www/html
docker-compose exec app chmod 777 /var/log/wallboard
```

---

## Making Test Calls

To see the dashboard in action:

1. Call one of your queue numbers
2. Let it ring (watch "Waiting" count go up)
3. Answer from an agent extension
4. Watch the agent card update
5. Hang up and see wrap-up timer
6. Check reports after a few calls

---

## Production Checklist

Before going live:

- [ ] Change default admin password
- [ ] Configure all queues
- [ ] Configure all extensions with real names
- [ ] Set correct timezone in Settings
- [ ] Configure alert thresholds
- [ ] Add alert recipients (email)
- [ ] Test with real calls
- [ ] Set up SSL (optional but recommended)

---

## Getting Help

If you encounter issues:

1. Check daemon logs: `docker-compose logs daemon`
2. Check app logs: `docker-compose logs app`
3. Check PHP logs: `docker-compose exec app cat /var/log/wallboard/php_errors.log`
4. Check database: `docker-compose exec db mysql -u root -p wallboard`

---

Good luck with your deployment! 🚀

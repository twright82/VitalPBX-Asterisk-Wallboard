# VitalPBX Asterisk Wallboard

Real-time call center wallboard for VitalPBX/Asterisk systems.

## Features

- **Real-time agent status** - See who's available, on call, paused, or ringing
- **Queue statistics** - Calls waiting, longest wait time, agents available
- **Call tracking** - Track answered, abandoned, and SLA metrics
- **VitalPBX integration** - Automatic extension name sync via API
- **Admin panel** - Manage extensions, queues, alerts, and settings

## Requirements

- Docker & Docker Compose
- VitalPBX with AMI access
- Network access to VitalPBX (ports 5038 for AMI, 3500 for API)

## Installation

1. Clone the repository:
```bash
   git clone https://github.com/twright82/VitalPBX-Asterisk-Wallboard.git
   cd VitalPBX-Asterisk-Wallboard
```

2. Configure environment:
```bash
   cp docker/.env.example docker/.env
   # Edit docker/.env with your settings
```

3. Start the containers:
```bash
   cd docker
   docker compose up -d
```

4. Access the wallboard at `http://your-server:8081`

## Configuration

### AMI Connection
Edit `docker/.env`:
```
AMI_HOST=your-pbx-server
AMI_PORT=5038
AMI_USER=wallboard
AMI_PASS=your-ami-password
```

### VitalPBX API Sync
The wallboard can automatically sync extension names from VitalPBX.

1. In VitalPBX, go to Admin → Application Keys
2. Create a new key or use existing
3. Update `scripts/sync_vitalpbx.php` with your API key
4. Set up hourly cron:
```bash
   0 * * * * cd /opt/wallboard/bertram-communications/docker && docker compose exec -T daemon php /var/www/html/scripts/sync_vitalpbx.php
```

### Adding Extensions
1. Go to Admin → Extensions
2. Click "Add Extension"
3. Enter the 4-digit extension number
4. Click "Sync from VitalPBX" to auto-populate the name

## Admin Panel

Access at `http://your-server:8081/admin`

- **Extensions** - Manage agent extensions
- **Queues** - Configure queue display settings
- **Alerts** - Set up SLA and wait time alerts
- **Settings** - General wallboard configuration

## Troubleshooting

### Check daemon logs
```bash
cd docker
docker compose logs daemon
```

### Verify AMI connection
```bash
docker compose logs daemon | grep -i "connected\|login"
```

### Manual sync from VitalPBX
```bash
docker exec bertram-communications-daemon php /var/www/html/scripts/sync_vitalpbx.php
```

## License

MIT License

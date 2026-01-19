# VitalPBX Asterisk Wallboard

Real-time call center dashboard for VitalPBX and Asterisk-based phone systems.

![Dashboard Preview](docs/preview.png)

## Features

- **Real-time Queue Monitoring** - See calls waiting, longest wait time, calls per queue
- **Agent Status Tracking** - Available, on call, ringing, wrap-up, paused states
- **Queue Membership** - See which queues each agent is signed into
- **Inbound & Outbound Call Tracking** - Track all calls through your system
- **Leaderboard** - Gamified agent performance (King/Queen/Princess)
- **SLA Monitoring** - Service level tracking with configurable targets
- **Alerts** - Email/SMS notifications when SLA breaches or queues overflow
- **Callbacks** - Track callback queue
- **Missed Calls** - Ring-no-answer tracking per agent
- **Repeat Callers** - Flag customers who call multiple times
- **Reports** - Daily, weekly, agent performance reports
- **Multi-instance Support** - Run multiple dashboards on one server
- **Docker Deployment** - Easy installation and updates

## Requirements

- Ubuntu 22.04/24.04 (or any Linux with Docker)
- Docker & Docker Compose
- VitalPBX or Asterisk with AMI access
- Network access from dashboard server to PBX AMI port (5038)

## Quick Install

```bash
git clone https://github.com/yourusername/VitalPBX-Asterisk-Wallboard.git
cd VitalPBX-Asterisk-Wallboard
chmod +x install.sh
./install.sh
```

The installer will prompt for:
- Instance name (for multiple installations)
- Company name
- Web port
- AMI credentials
- Admin username/password

## Manual Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/VitalPBX-Asterisk-Wallboard.git
cd VitalPBX-Asterisk-Wallboard
```

2. Copy and edit environment file:
```bash
cp docker/.env.example docker/.env
nano docker/.env
```

3. Start containers:
```bash
cd docker
docker-compose up -d
```

4. Access the admin panel at `http://your-server:8080/admin`

5. Configure AMI settings, queues, and extensions

## Configuration

### AMI Setup in VitalPBX

1. Go to **Settings > PBX Settings > AMI**
2. Create a new AMI user:
   - Username: `wallboard`
   - Secret: (generate a secure password)
   - Permit: Add your wallboard server IP
   - Read Permissions: system, call, log, verbose, command, agent, user
   - Write Permissions: system, call, log, verbose, command, agent, user

### Dashboard Configuration

All configuration is done through the Admin GUI:

- **AMI Settings** - Connection to your PBX
- **Queues** - Which queues to monitor
- **Extensions** - Which agents to track
- **Alerts** - Thresholds and recipients
- **Company** - Branding, timezone, business hours

## Views

### Wallboard (`/`)
Large display for TVs in call center. Shows:
- Total calls waiting
- Per-queue breakdown
- Agent status cards
- Leaderboard
- Calls waiting list

### Manager Dashboard (`/manager`)
Detailed view for supervisors. Adds:
- Alerts panel
- Missed calls tracking
- Repeat callers
- Detailed statistics
- Settings access

## API Endpoints

- `GET /api/realtime.php` - Current state of all queues and agents
- `GET /api/stats.php` - Historical statistics
- `GET /api/export.php` - CSV export

## Multiple Instances

To run multiple dashboards (e.g., different departments):

```bash
# First instance
INSTANCE_NAME=sales APP_PORT=8080 ./install.sh

# Second instance
INSTANCE_NAME=support APP_PORT=8081 ./install.sh
```

Each instance has its own database and configuration.

## Troubleshooting

### Daemon not connecting to AMI

1. Check AMI credentials in Admin > Settings > AMI
2. Verify network connectivity: `telnet pbx-host 5038`
3. Check AMI permit list includes your server IP
4. View daemon logs: `docker-compose logs -f daemon`

### No data showing on dashboard

1. Verify daemon is running: `docker-compose ps`
2. Check if queues are configured in Admin > Queues
3. Check if extensions are configured in Admin > Extensions
4. Make test calls to populate data

### Dashboard not loading

1. Check container status: `docker-compose ps`
2. View app logs: `docker-compose logs -f app`
3. Verify port is not in use: `netstat -tlnp | grep 8080`

## Development

### Local Development

```bash
# Start with live code reloading
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up
```

### Project Structure

```
├── docker/                 # Docker configuration
├── src/
│   ├── admin/             # Admin panel pages
│   ├── api/               # API endpoints
│   ├── config/            # Configuration files
│   ├── daemon/            # AMI daemon
│   ├── database/          # SQL schema
│   ├── includes/          # Shared PHP includes
│   └── public/            # Frontend (wallboard, manager)
├── logs/                   # Log files
├── install.sh             # Installation script
└── README.md
```

## Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Submit a pull request

## License

MIT License - see [LICENSE](LICENSE) file

## Credits

Built for VitalPBX and Asterisk-based phone systems.

# Expy

An open-source domain expiration monitoring tool. Keep track of your domains, registrars, and renewal costs in one place.

## Features

- **🌐 Domain Lifecycle**
    - Track expiration dates automatically
    - Sync details via Whois/RDAP
    - Monitor domain statuses

- **🏢 Registrar Management**
    - Organize domains by registrar
    - Integration with popular registrars (Porkbun, Dynadot, Idwebhost)

- **💰 Fee Tracking**
    - Monitor renewal costs and currency conversions
    - Financial overview of your domain portfolio

## Quick Start

### Docker (Recommended)

```bash
docker run -d \
  -p 80:80 \
  -e APP_KEY=base64:... \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=your-db-host \
  ghcr.io/sooluh/expy:latest
```

### Installation Methods

- **Docker** - Best for production
- **Local Source** - For development (`composer install && pnpm install`)

## License

The MIT License (MIT).

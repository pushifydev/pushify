# 🚀 Pushify

<div align="center">

[![Version](https://img.shields.io/badge/version-0.1.0--beta-blue.svg)](https://github.com/pushifydev/pushify/releases)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.0-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![Docker](https://img.shields.io/badge/Docker-required-2496ED?logo=docker&logoColor=white)](https://docker.com)

**Open-source Platform-as-a-Service (PaaS) for deploying full-stack applications with managed databases, automatic SSL, and custom domains.**

[Features](#-features) • [Quick Start](#-quick-start) • [Documentation](#-documentation) • [Contributing](#-contributing) • [Community](#-community)

</div>

---

## 📋 Table of Contents

- [About](#about)
- [Features](#-features)
- [Quick Start](#-quick-start)
- [Installation](#-installation)
- [Usage](#-usage)
- [Supported Frameworks](#-supported-frameworks)
- [Documentation](#-documentation)
- [Contributing](#-contributing)
- [Versioning](#-versioning)
- [Community](#-community)
- [License](#-license)

---

## About

**Pushify** is a self-hosted, open-source Platform-as-a-Service (PaaS) that makes deploying and managing full-stack applications simple and efficient. Think Heroku + Vercel + Railway, but **fully open-source** and **self-hosted**.

### Why Pushify?

- 🔓 **Fully Open Source** - No vendor lock-in, full control
- 💰 **Cost-Effective** - Run on your own infrastructure
- 🔐 **Privacy-First** - Your data stays on your servers
- 🎯 **Developer-Friendly** - Simple, intuitive interface
- ⚡ **Fast Deployments** - Git push to deploy in seconds
- 🔧 **Flexible** - Custom Dockerfiles, build commands, and more

---

## ✨ Features

### 🚀 Deployment

- **One-Click Deployment**: Deploy from GitHub with a single click
- **Auto-Deploy**: Automatic deployments on git push
- **Preview Deployments**: Automatic preview environments for pull requests
- **Custom Dockerfiles**: Full control with your own Dockerfiles
- **Flexible Build Commands**: Customize install, build, and start commands
- **Rollback Support**: Instantly roll back to previous deployments
- **Zero-Downtime Deployments**: Seamless updates

### 🗄️ Database Management

- **Multiple Databases**: PostgreSQL, MySQL, MariaDB, MongoDB, Redis
- **One-Click Creation**: Create databases in seconds
- **Remote Access**: Secure remote database connections
- **Automated Backups**: Daily automated backups with retention
- **Resource Management**: Configure CPU and memory limits

### 🔒 Security & SSL

- **Automatic SSL**: Free Let's Encrypt SSL certificates
- **Custom Domains**: Connect your own domains
- **Secure Environment Variables**: Encrypted storage
- **SSH Key Management**: Secure server access
- **Input Validation**: Comprehensive security checks

### 📊 Monitoring & Logs

- **Real-Time Logs**: Live container log streaming
- **Health Checks**: Automated application health monitoring
- **Alerts**: Email notifications for critical events
- **Activity Logs**: Track all project activities
- **Resource Monitoring**: CPU, memory, and disk usage

### 👥 Team Collaboration

- **Team Management**: Invite team members
- **Role-Based Access**: Owner, admin, member roles
- **Shared Projects**: Collaborate on deployments
- **Activity Tracking**: See who did what and when

### 🔧 Developer Experience

- **GitHub Integration**: Seamless GitHub OAuth and webhooks
- **Custom Build Settings**: UI-based configuration
- **Environment Variables**: Manage envs through UI
- **Container Logs**: Real-time log access
- **API Ready**: RESTful API (coming soon)

---

## 🚀 Quick Start

### Prerequisites

- **Server**: Ubuntu 22.04 LTS (2 vCPU, 4GB RAM minimum)
- **Docker**: 24.0.5+
- **PHP**: 8.2+
- **Database**: PostgreSQL 15+ or MySQL 8.0+
- **Node.js**: 20.x
- **RabbitMQ**: 3.x (for notifications)

### Installation

```bash
# Clone the repository
git clone https://github.com/pushifydev/pushify.git
cd pushify

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env .env.local

# Configure your .env.local with database credentials
nano .env.local

# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Build frontend assets
npm run build

# Start development server
symfony server:start

# Start message consumer (in another terminal)
php bin/console messenger:consume async -vv
```

### 🎯 First Project

1. **Connect GitHub**: Go to Settings → GitHub Integration
2. **Import Repository**: Click "New Project" → Import from GitHub
3. **Configure**: Select framework and build settings
4. **Deploy**: Click "Deploy" and watch your app go live! 🎉

---

## 📦 Supported Frameworks

| Framework | Support | Auto-Detect | Custom Dockerfile |
|-----------|---------|-------------|-------------------|
| **Next.js** | ✅ Full | ✅ Yes | ✅ Yes |
| **React** | ✅ Full | ✅ Yes | ✅ Yes |
| **Vue** | ✅ Full | ✅ Yes | ✅ Yes |
| **Nuxt** | ✅ Full | ✅ Yes | ✅ Yes |
| **Svelte** | ✅ Full | ✅ Yes | ✅ Yes |
| **Laravel** | ✅ Full | ✅ Yes | ✅ Yes |
| **Symfony** | ✅ Full | ✅ Yes | ✅ Yes |
| **Node.js** | ✅ Full | ✅ Yes | ✅ Yes |
| **Python/Django** | ⚙️ Custom Dockerfile | ❌ No | ✅ Yes |
| **Go** | ⚙️ Custom Dockerfile | ❌ No | ✅ Yes |
| **Rust** | ⚙️ Custom Dockerfile | ❌ No | ✅ Yes |
| **Static Sites** | ✅ Full | ✅ Yes | ✅ Yes |

**Legend:**
- ✅ **Full**: Automatic detection and deployment
- ⚙️ **Custom Dockerfile**: Requires custom Dockerfile
- 🔄 **Coming Soon**: Planned support

---

## 📚 Documentation

### Guides

- **[Production Deployment Guide](PRODUCTION_DEPLOYMENT_GUIDE.md)** - Complete production setup
- **[Custom Build Configuration](CUSTOM_BUILD_GUIDE.md)** - Advanced build settings
- **[Open Source Setup](OPEN_SOURCE_SETUP.md)** - GitHub repository setup
- **[Contributing Guide](CONTRIBUTING.md)** - How to contribute
- **[Security Audit Report](SECURITY_AUDIT_REPORT.md)** - Security analysis
- **[SEO & Marketing Strategy](SEO_MARKETING_STRATEGY.md)** - Marketing guide

### Quick Links

- [Installation](#-installation)
- [Configuration](PRODUCTION_DEPLOYMENT_GUIDE.md#environment-variables)
- [Troubleshooting](PRODUCTION_DEPLOYMENT_GUIDE.md#troubleshooting)
- [API Documentation](#) (Coming Soon)
- [FAQ](#) (Coming Soon)

---

## 🛠️ Tech Stack

**Backend:**
- PHP 8.2+ with Symfony 7.0
- Doctrine ORM
- Symfony Messenger (with RabbitMQ)

**Frontend:**
- React 18
- Tailwind CSS
- Radix UI Components
- Symfony UX & Stimulus

**Infrastructure:**
- Docker & Docker Compose
- Nginx
- PostgreSQL / MySQL
- Redis
- RabbitMQ

**Deployment:**
- Git-based deployments
- Docker containerization
- Multi-server support

---

## 🤝 Contributing

We love contributions! Pushify is a community project and we welcome:

- 🐛 **Bug reports** and **feature requests** via [GitHub Issues](https://github.com/pushifydev/pushify/issues)
- 📝 **Code contributions** via [Pull Requests](https://github.com/pushifydev/pushify/pulls)
- 📚 **Documentation improvements**
- 🌍 **Translations** (coming soon)
- 💬 **Community support**

### Quick Contribution Steps

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'feat: add amazing feature'`)
4. **Push** to your branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

Please read our [Contributing Guide](CONTRIBUTING.md) for detailed guidelines.

### Code of Conduct

We are committed to providing a welcoming and inspiring community for all. Please read our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## 📊 Versioning

Pushify follows [Semantic Versioning](https://semver.org/):

- **Current Version**: `0.1.0-beta` (Beta Release)
- **Changelog**: See [CHANGELOG.md](CHANGELOG.md)
- **Releases**: [GitHub Releases](https://github.com/pushifydev/pushify/releases)

### Version Schema

```
MAJOR.MINOR.PATCH-LABEL

- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes
- LABEL: alpha, beta, rc1, stable
```

### Roadmap

#### v0.2.0-beta (Q1 2025)
- [ ] Kubernetes support
- [ ] Multi-region deployments
- [ ] Advanced monitoring dashboard
- [ ] Slack/Discord notifications

#### v1.0.0 (Q2 2025)
- [ ] Stable production release
- [ ] Full API documentation
- [ ] CLI tool
- [ ] Marketplace for extensions

[See full roadmap →](https://github.com/pushifydev/pushify/projects)

---

## 💬 Community

Join our community to get help, share ideas, and stay updated:

- **GitHub Discussions**: [Ask questions & share ideas](https://github.com/pushifydev/pushify/discussions)
- **Discord**: [Join our server](#) (Coming Soon)
- **Twitter/X**: [@pushify_dev](#) (Coming Soon)
- **Email**: support@pushify.dev

### Show Your Support

If you find Pushify useful, please consider:

- ⭐ **Star** this repository
- 🐦 **Share** on Twitter/X
- 📝 **Write** a blog post about your experience
- 🤝 **Contribute** to the project

---

## 📈 Project Stats

![GitHub stars](https://img.shields.io/github/stars/yourusername/pushify?style=social)
![GitHub forks](https://img.shields.io/github/forks/yourusername/pushify?style=social)
![GitHub watchers](https://img.shields.io/github/watchers/yourusername/pushify?style=social)
![GitHub contributors](https://img.shields.io/github/contributors/yourusername/pushify)
![GitHub issues](https://img.shields.io/github/issues/yourusername/pushify)
![GitHub pull requests](https://img.shields.io/github/issues-pr/yourusername/pushify)

---

## 📜 License

Pushify is open-source software licensed under the [MIT License](LICENSE).

---

## 🙏 Acknowledgments

Built with ❤️ by the Pushify community.

Special thanks to:
- [Symfony](https://symfony.com) - The PHP framework
- [Docker](https://docker.com) - Containerization platform
- [Heroku](https://heroku.com) & [Vercel](https://vercel.com) - Inspiration
- All our [contributors](https://github.com/pushifydev/pushify/graphs/contributors)

---

## 📞 Support

Need help?

- 📖 Check the [Documentation](PRODUCTION_DEPLOYMENT_GUIDE.md)
- 💬 Join [GitHub Discussions](https://github.com/pushifydev/pushify/discussions)
- 🐛 Report bugs via [GitHub Issues](https://github.com/pushifydev/pushify/issues)
- 📧 Email: support@pushify.dev
- 🔒 Security issues: security@pushify.dev

---

<div align="center">

**Made with ❤️ by developers, for developers.**

[⬆ Back to Top](#-pushify)

</div>

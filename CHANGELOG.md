# Changelog

All notable changes to Pushify will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial beta release preparation

---

## [0.1.0-beta] - 2025-01-25

### 🎉 Initial Beta Release

#### Core Features
- **Project Deployment**: Deploy Next.js, React, Vue, Nuxt, Laravel, Symfony, and static sites
- **GitHub Integration**: Import repositories and auto-deploy on push
- **Custom Build Commands**: Flexible build configuration via UI
- **Custom Dockerfile Support**: Full control with custom Dockerfiles
- **Environment Variables**: Secure environment variable management
- **Database Management**: Create and manage PostgreSQL, MySQL, MongoDB, Redis databases
- **Remote Database Access**: Enable remote connections to managed databases
- **SSL Certificates**: Automatic Let's Encrypt SSL setup
- **Custom Domains**: Connect custom domains to projects
- **Deployment History**: Track all deployments with logs
- **Rollback Support**: Roll back to previous deployments
- **Preview Deployments**: Automatic preview environments for pull requests
- **Webhooks**: GitHub webhook integration for auto-deploy
- **Activity Logs**: Track all project activities
- **Team Collaboration**: Multi-user team support
- **Email Notifications**: RabbitMQ-powered email notifications
- **Server Management**: Connect and manage multiple servers
- **Backup System**: Automated database backups
- **Health Monitoring**: Application health checks and alerts
- **Container Logs**: Real-time container log streaming

#### Security
- SQL injection protection with Doctrine ORM
- XSS protection with Twig auto-escaping
- CSRF protection
- Input validation service
- Secure SSH key management

#### Developer Experience
- Clean dashboard UI
- Real-time deployment logs
- Toast notifications
- Responsive design
- Dark theme

### Known Issues
- Drizzle ORM projects require custom Dockerfile or prebuild script
- Some edge cases in multi-framework monorepos
- Email notifications require RabbitMQ setup

### Breaking Changes
- None (initial release)

---

## Version Schema

Pushify follows [Semantic Versioning](https://semver.org/):

- **MAJOR.MINOR.PATCH-LABEL**
  - **MAJOR**: Breaking changes
  - **MINOR**: New features (backward compatible)
  - **PATCH**: Bug fixes (backward compatible)
  - **LABEL**: `alpha`, `beta`, `rc1`, `stable`

Examples:
- `0.1.0-beta` - First beta release
- `0.2.0-beta` - Second beta with new features
- `1.0.0-rc1` - Release candidate
- `1.0.0` - Stable production release
- `1.1.0` - Minor update with new features
- `1.1.1` - Patch/bugfix release

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to Pushify.

---

[Unreleased]: https://github.com/pushifydev/pushify/compare/v0.1.0-beta...HEAD
[0.1.0-beta]: https://github.com/pushifydev/pushify/releases/tag/v0.1.0-beta

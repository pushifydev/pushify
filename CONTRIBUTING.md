# Contributing to Pushify

First off, thank you for considering contributing to Pushify! 🎉

Pushify is an open-source platform-as-a-service (PaaS) that makes deploying full-stack applications simple and efficient.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Versioning](#versioning)

---

## Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code.

### Our Standards

- **Be respectful** and inclusive
- **Be collaborative** and helpful
- **Accept constructive criticism** gracefully
- **Focus on what is best** for the community

---

## How Can I Contribute?

### 🐛 Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates.

**When reporting a bug, include:**
- Clear, descriptive title
- Exact steps to reproduce
- Expected vs actual behavior
- Screenshots (if applicable)
- Environment details (OS, PHP version, Docker version)
- Relevant logs

**Use this template:**
```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce:
1. Go to '...'
2. Click on '...'
3. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- OS: [e.g., Ubuntu 22.04]
- PHP Version: [e.g., 8.2]
- Docker Version: [e.g., 24.0.5]
- Pushify Version: [e.g., 0.1.0-beta]

**Additional context**
Any other relevant information.
```

### 💡 Suggesting Features

We love feature suggestions! Please create an issue with:

- Clear, descriptive title
- Detailed description of the feature
- Why this feature would be useful
- Example use cases
- Mockups/diagrams (if applicable)

**Use this template:**
```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Any alternative solutions or features you've considered.

**Additional context**
Any other context, screenshots, or mockups.
```

### 🔧 Contributing Code

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Make your changes**
4. **Test thoroughly**
5. **Commit with conventional commits** (see below)
6. **Push to your fork** (`git push origin feature/amazing-feature`)
7. **Open a Pull Request**

---

## Development Setup

### Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js 20.x
- Docker & Docker Compose
- PostgreSQL 15+ or MySQL 8.0+
- Redis 7.x
- RabbitMQ 3.x

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/pushify.git  # Fork pushifydev/pushify first
cd pushify

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env .env.local

# Configure .env.local with your database credentials

# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Build assets
npm run build

# Start development server
symfony server:start -d

# Start worker (in another terminal)
php bin/console messenger:consume async -vv
```

### Running Tests

```bash
# Run PHP tests
php bin/phpunit

# Run JavaScript tests
npm test

# Run linting
composer run-script lint
npm run lint
```

---

## Pull Request Process

### Before Submitting

- [ ] Code follows our coding standards
- [ ] All tests pass
- [ ] New features include tests
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated
- [ ] Commit messages follow conventions

### PR Title Format

Use conventional commit format:

```
type(scope): brief description

Examples:
feat(deployment): add support for Go applications
fix(database): resolve remote access issue for PostgreSQL
docs(readme): update installation instructions
refactor(ui): improve dashboard performance
```

### PR Description Template

```markdown
## Description
Brief description of what this PR does.

## Type of Change
- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Code refactoring

## Related Issue
Closes #123

## How Has This Been Tested?
Describe the tests you ran and how to reproduce.

## Screenshots (if applicable)
Add screenshots to help explain your changes.

## Checklist
- [ ] My code follows the project's coding standards
- [ ] I have performed a self-review of my code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
- [ ] I have updated the CHANGELOG.md
```

### Review Process

1. **Automated checks** must pass (CI/CD)
2. **At least one maintainer approval** required
3. **No unresolved conversations**
4. **All tests passing**
5. **Documentation updated**

Maintainers may request changes or provide feedback. Please be patient and responsive.

---

## Coding Standards

### PHP

We follow **PSR-12** coding standards.

```php
// ✅ Good
class DeploymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function deploy(Project $project): void
    {
        // Implementation
    }
}

// ❌ Bad
class DeploymentService {
  public function deploy($project) {
    //Implementation
  }
}
```

**Key Points:**
- Use type hints everywhere
- Use property promotion for constructor params
- Use readonly when applicable
- Document complex logic
- Keep methods focused and small

### JavaScript/React

We use **ESLint** with Airbnb config.

```javascript
// ✅ Good
export default function DeploymentCard({ deployment }) {
  const [isExpanded, setIsExpanded] = useState(false);

  return (
    <div className="deployment-card">
      <h3>{deployment.name}</h3>
    </div>
  );
}

// ❌ Bad
export default function DeploymentCard(props) {
  return <div className="deployment-card">
    <h3>{props.deployment.name}</h3>
  </div>
}
```

### Database

- **Always use migrations** for schema changes
- **Use Doctrine ORM** - no raw SQL
- **Add indexes** for frequently queried columns
- **Use transactions** for multiple operations

### Security

- **Never trust user input** - always validate
- **Use parameterized queries** (Doctrine does this)
- **Escape shell arguments** with `escapeshellarg()`
- **Never commit secrets** to version control
- **Use environment variables** for sensitive data

---

## Commit Guidelines

We use [Conventional Commits](https://www.conventionalcommits.org/).

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, semicolons, etc)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Maintenance tasks
- `ci`: CI/CD changes

### Scopes

- `deployment`: Deployment service
- `database`: Database management
- `auth`: Authentication
- `ui`: User interface
- `api`: API endpoints
- `docker`: Docker/containerization
- `security`: Security improvements

### Examples

```bash
# Feature
git commit -m "feat(deployment): add Python Django support"

# Bug fix
git commit -m "fix(database): resolve PostgreSQL remote access issue"

# Documentation
git commit -m "docs(readme): add Docker installation instructions"

# Breaking change
git commit -m "feat(api)!: change deployment endpoint response structure

BREAKING CHANGE: The deployment endpoint now returns {data, meta}
instead of flat response. Update your API clients accordingly."
```

---

## Versioning

Pushify follows [Semantic Versioning](https://semver.org/):

### Version Format: `MAJOR.MINOR.PATCH-LABEL`

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)
- **LABEL**: `alpha`, `beta`, `rc1`, `stable`

### Labels

- `alpha`: Early testing, unstable
- `beta`: Feature complete, testing phase
- `rc1, rc2`: Release candidates
- `stable` or no label: Production-ready

### Examples

- `0.1.0-beta` - Initial beta
- `0.2.0-beta` - Beta with new features
- `1.0.0-rc1` - First release candidate
- `1.0.0` - Stable release
- `1.1.0` - Minor version with new features
- `1.1.1` - Patch/bugfix

### When to Increment

**MAJOR (1.0.0 → 2.0.0)**
- Breaking API changes
- Database schema breaking changes
- Removed features
- Major architecture changes

**MINOR (1.0.0 → 1.1.0)**
- New features (backward compatible)
- New framework support
- New UI components
- Deprecations

**PATCH (1.0.0 → 1.0.1)**
- Bug fixes
- Security patches
- Documentation fixes
- Performance improvements

---

## Branch Strategy

### Main Branches

- **`main`**: Production-ready code
- **`develop`**: Development branch (default for PRs)
- **`beta`**: Beta releases

### Feature Branches

```bash
# Format: feature/short-description
git checkout -b feature/add-python-support

# Format: fix/issue-number-short-description
git checkout -b fix/123-database-connection

# Format: docs/what-you-are-documenting
git checkout -b docs/contributing-guide
```

### Workflow

1. Fork the repository
2. Create feature branch from `develop`
3. Make changes
4. Open PR to `develop`
5. After review and merge, changes go to `beta` for testing
6. When stable, merged to `main` and tagged

---

## Release Process

### Creating a Release

1. Update `VERSION` file
2. Update `CHANGELOG.md`
3. Create git tag: `git tag -a v1.0.0 -m "Release v1.0.0"`
4. Push tag: `git push origin v1.0.0`
5. GitHub Actions will create release automatically

### Release Checklist

- [ ] All tests passing
- [ ] CHANGELOG.md updated
- [ ] VERSION file updated
- [ ] Migration scripts tested
- [ ] Documentation updated
- [ ] Breaking changes documented
- [ ] Release notes prepared

---

## Questions?

- **Discord**: [Join our community](#)
- **GitHub Discussions**: [Ask a question](https://github.com/pushifydev/pushify/discussions)
- **Email**: support@pushify.dev

---

## Recognition

Contributors will be recognized in:
- CHANGELOG.md for their contributions
- README.md contributors section
- GitHub contributors page

Thank you for contributing to Pushify! 🚀

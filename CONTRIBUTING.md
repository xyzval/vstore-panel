# 🤝 Contributing to AlrelShop Panel

Terima kasih atas minat Anda untuk berkontribusi pada AlrelShop Panel! Panduan ini akan membantu Anda memulai.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Making Changes](#making-changes)
- [Submitting Changes](#submitting-changes)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Documentation](#documentation)

## 📜 Code of Conduct

Proyek ini mengikuti [Contributor Covenant Code of Conduct](https://www.contributor-covenant.org/). Dengan berpartisipasi, Anda diharapkan untuk menjunjung tinggi kode etik ini.

### 🤝 Our Pledge

- Menciptakan lingkungan yang ramah dan inklusif
- Menghormati perbedaan pendapat dan pengalaman
- Menerima kritik konstruktif dengan baik
- Fokus pada yang terbaik untuk komunitas

## 🚀 Getting Started

### Prerequisites

- PHP 7.4+ (Recommended: PHP 8.1+)
- MySQL 5.7+ atau MariaDB 10.3+
- Apache/Nginx web server
- Git
- Composer (optional)

### 🍴 Fork the Repository

1. Fork repository di GitHub
2. Clone fork Anda ke local machine:

```bash
git clone https://github.com/YOUR_USERNAME/alrelshop-panel.git
cd alrelshop-panel
```

3. Add upstream remote:

```bash
git remote add upstream https://github.com/ORIGINAL_OWNER/alrelshop-panel.git
```

## 🛠️ Development Setup

### 1. Install Dependencies

```bash
# Install PHP dependencies (if using Composer)
composer install

# Setup development environment
cp config.php.example config.php
```

### 2. Database Setup

```bash
# Create development database
mysql -u root -p
```

```sql
CREATE DATABASE alrelshop_panel_dev;
CREATE USER 'dev_user'@'localhost' IDENTIFIED BY 'dev_password';
GRANT ALL PRIVILEGES ON alrelshop_panel_dev.* TO 'dev_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configuration

Update `config.php` dengan database development:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'dev_user');
define('DB_PASS', 'dev_password');
define('DB_NAME', 'alrelshop_panel_dev');
```

### 4. Install Database Schema

Visit: `http://localhost/alrelshop-panel/install_db.php`

## 🔧 Making Changes

### 1. Create Feature Branch

```bash
# Update main branch
git checkout main
git pull upstream main

# Create new feature branch
git checkout -b feature/your-feature-name
```

### 2. Branch Naming Convention

- `feature/` - New features
- `bugfix/` - Bug fixes
- `hotfix/` - Critical fixes
- `docs/` - Documentation updates
- `refactor/` - Code refactoring

Examples:
- `feature/vps-auto-detection`
- `bugfix/ssh-connection-timeout`
- `docs/api-documentation`

### 3. Make Your Changes

- Write clean, readable code
- Follow coding standards (PSR-12)
- Add comments for complex logic
- Update documentation if needed

## 📤 Submitting Changes

### 1. Commit Guidelines

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```bash
# Format: <type>(<scope>): <description>

git commit -m "feat(vps): add auto-detection feature"
git commit -m "fix(auth): resolve login session timeout"
git commit -m "docs(readme): update installation guide"
```

**Commit Types:**
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation
- `style` - Code style changes
- `refactor` - Code refactoring
- `test` - Tests
- `chore` - Maintenance

### 2. Push Changes

```bash
git push origin feature/your-feature-name
```

### 3. Create Pull Request

1. Go to your fork on GitHub
2. Click "New Pull Request"
3. Select your feature branch
4. Fill out PR template:

```markdown
## 📝 Description
Brief description of changes

## 🎯 Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## ✅ Testing
- [ ] Unit tests pass
- [ ] Manual testing completed
- [ ] No breaking changes

## 📸 Screenshots (if applicable)
Add screenshots for UI changes

## 📋 Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No merge conflicts
```

## 📏 Coding Standards

### PHP Standards

Follow **PSR-12** coding standards:

```php
<?php

declare(strict_types=1);

namespace AlrelShop\Panel;

class ExampleClass
{
    private string $property;

    public function exampleMethod(string $parameter): string
    {
        if ($parameter === 'example') {
            return 'result';
        }

        return $this->property;
    }
}
```

### HTML/CSS Standards

```html
<!-- Use semantic HTML -->
<main class="content">
    <section class="dashboard">
        <h1>Dashboard</h1>
        <!-- Content -->
    </section>
</main>
```

```css
/* Use BEM methodology */
.dashboard {}
.dashboard__header {}
.dashboard__content {}
.dashboard--loading {}
```

### JavaScript Standards

```javascript
// Use modern ES6+ syntax
const handleClick = (event) => {
    event.preventDefault();
    // Handle click
};

// Use const/let instead of var
const API_URL = '/api/v1';
let isLoading = false;
```

### Database Standards

```sql
-- Use descriptive table names
CREATE TABLE vps_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_key VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Use proper indexing
CREATE INDEX idx_server_key ON vps_servers(server_key);
CREATE INDEX idx_user_id ON vpn_accounts(user_id);
```

## 🧪 Testing

### Manual Testing

1. **Test all affected features**
2. **Cross-browser testing** (Chrome, Firefox, Safari)
3. **Mobile responsiveness** testing
4. **Different PHP versions** (7.4, 8.0, 8.1)

### Testing Checklist

- [ ] Login/logout functionality
- [ ] VPS management (add/edit/delete)
- [ ] Account creation with billing
- [ ] SSH connectivity
- [ ] Database operations
- [ ] Security measures
- [ ] Error handling
- [ ] Mobile responsiveness

### Test Scenarios

```bash
# Test VPS auto-detection
curl -X POST http://localhost/admin-vps.php \
  -d "action=auto_detect&ip_address=1.2.3.4"

# Test account creation
curl -X POST http://localhost/create-account.php \
  -d "action=create_account&server=1&account_type=ssh&username=test&password=test123&duration=30"
```

## 📚 Documentation

### Code Documentation

```php
/**
 * Create VPN account on target VPS server
 *
 * @param array $vps_server VPS server configuration
 * @param string $type Account type (ssh, vmess, vless, etc.)
 * @param string $username Account username
 * @param string $password Account password
 * @param int $duration Account duration in days
 * @param string $uuid UUID for V2Ray protocols
 * @return array Result with success status and output
 */
function createVPNAccount($vps_server, $type, $username, $password, $duration, $uuid) {
    // Implementation
}
```

### API Documentation

Update API documentation untuk endpoint baru:

```markdown
## POST /admin-vps.php

Create new VPS server

**Parameters:**
- `action` (string): "add_vps"
- `server_key` (string): Unique server identifier
- `name` (string): Server display name
- `ip_address` (string): Server IP address

**Response:**
```json
{
  "success": true,
  "message": "VPS berhasil ditambahkan"
}
```

### README Updates

Update README.md jika ada:
- Fitur baru
- Perubahan instalasi
- Dependency baru
- Breaking changes

## 🐛 Reporting Issues

### Bug Reports

Gunakan template berikut untuk bug reports:

```markdown
**🐛 Bug Description**
Clear description of the bug

**🔄 Steps to Reproduce**
1. Go to '...'
2. Click on '...'
3. See error

**✅ Expected Behavior**
What should happen

**❌ Actual Behavior**
What actually happens

**🖥️ Environment**
- OS: [e.g. Ubuntu 20.04]
- PHP Version: [e.g. 8.1]
- MySQL Version: [e.g. 8.0]
- Browser: [e.g. Chrome 96]

**📸 Screenshots**
Add screenshots if applicable

**📋 Additional Context**
Any other relevant information
```

### Feature Requests

```markdown
**🚀 Feature Description**
Clear description of the feature

**💡 Motivation**
Why this feature would be useful

**📝 Detailed Description**
Detailed explanation of the feature

**🎨 Mockups/Examples**
Visual examples if applicable
```

## 🏷️ Release Process

### Version Numbering

Menggunakan [Semantic Versioning](https://semver.org/):

- `MAJOR.MINOR.PATCH`
- `1.0.0` - Initial release
- `1.1.0` - New features
- `1.0.1` - Bug fixes

### Release Checklist

- [ ] Update version number
- [ ] Update CHANGELOG.md
- [ ] Test on clean environment
- [ ] Create release notes
- [ ] Tag release in Git
- [ ] Update documentation

## 🙏 Recognition

Contributors akan diakui di:

- README.md contributors section
- CHANGELOG.md untuk setiap release
- GitHub contributors page
- Special thanks untuk major contributions

## 📞 Getting Help

Jika Anda membutuhkan bantuan:

1. **📖 Check Documentation** - README.md dan docs/
2. **🔍 Search Issues** - Mungkin sudah ada solusinya
3. **💬 Join Discussion** - GitHub Discussions
4. **📱 Contact Support** - Telegram: @alrelshop

## 📜 License

Dengan berkontribusi, Anda setuju bahwa kontribusi Anda akan dilisensikan di bawah [MIT License](LICENSE).

---

**Terima kasih telah berkontribusi pada AlrelShop Panel! 🎉**

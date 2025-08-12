#!/bin/bash

# AlrelShop Panel - SSH Setup Script
# Script untuk setup SSH keys dan koneksi ke VPS target

echo "=================================================="
echo "  AlrelShop Panel - SSH Setup Script"
echo "=================================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    print_error "Jangan jalankan script ini sebagai root!"
    print_status "Gunakan user www-data atau user web server"
    exit 1
fi

# Create SSH directory
SSH_DIR="$HOME/.ssh"
if [ ! -d "$SSH_DIR" ]; then
    print_status "Membuat direktori SSH..."
    mkdir -p "$SSH_DIR"
    chmod 700 "$SSH_DIR"
    print_success "Direktori SSH dibuat: $SSH_DIR"
fi

# Generate SSH key pair
KEY_FILE="$SSH_DIR/alrelshop_panel"
if [ ! -f "$KEY_FILE" ]; then
    print_status "Generating SSH key pair..."
    ssh-keygen -t rsa -b 4096 -f "$KEY_FILE" -N "" -C "alrelshop-panel@$(hostname)"
    chmod 600 "$KEY_FILE"
    chmod 644 "$KEY_FILE.pub"
    print_success "SSH key pair berhasil dibuat"
    print_status "Private key: $KEY_FILE"
    print_status "Public key: $KEY_FILE.pub"
else
    print_warning "SSH key sudah ada: $KEY_FILE"
fi

echo ""
print_status "Public Key Content:"
echo "=================================================="
cat "$KEY_FILE.pub"
echo "=================================================="
echo ""

# VPS Target List
print_status "Daftar VPS Target yang perlu dikonfigurasi:"
echo ""
echo "1. ANYM NETWORK (140.213.202.13)"
echo "2. ARGON DATA 1 (103.150.197.96)" 
echo "3. DIGITAL OCEAN 2 (128.199.104.75)"
echo ""

print_warning "LANGKAH SELANJUTNYA:"
echo "1. Copy public key di atas"
echo "2. Login ke setiap VPS target sebagai root"
echo "3. Jalankan: echo 'PUBLIC_KEY_CONTENT' >> ~/.ssh/authorized_keys"
echo "4. Atau gunakan: ssh-copy-id -i $KEY_FILE.pub root@VPS_IP"
echo ""

# Test connection function
test_connection() {
    local vps_ip=$1
    local vps_name=$2
    
    print_status "Testing connection to $vps_name ($vps_ip)..."
    
    if ssh -i "$KEY_FILE" -o ConnectTimeout=10 -o StrictHostKeyChecking=no root@$vps_ip "echo 'Connection successful'" 2>/dev/null; then
        print_success "✓ Connection to $vps_name OK"
        
        # Test menu command
        if ssh -i "$KEY_FILE" -o ConnectTimeout=10 -o StrictHostKeyChecking=no root@$vps_ip "command -v menu" 2>/dev/null; then
            print_success "✓ Menu command available on $vps_name"
        else
            print_error "✗ Menu command not found on $vps_name"
        fi
    else
        print_error "✗ Connection to $vps_name FAILED"
        print_status "Make sure public key is added to $vps_ip"
    fi
    echo ""
}

# Ask for testing connections
echo ""
read -p "Apakah Anda ingin test koneksi ke VPS target? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    print_status "Testing connections..."
    echo ""
    
    test_connection "140.213.202.13" "ANYM NETWORK"
    test_connection "103.150.197.96" "ARGON DATA 1"
    test_connection "128.199.104.75" "DIGITAL OCEAN 2"
fi

# Update config.php
CONFIG_FILE="$(dirname "$0")/config.php"
if [ -f "$CONFIG_FILE" ]; then
    print_status "Updating config.php dengan SSH key path..."
    
    # Backup original config
    cp "$CONFIG_FILE" "$CONFIG_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Update SSH key path in config
    sed -i "s|'ssh_key' => '/path/to/ssh/key'|'ssh_key' => '$KEY_FILE'|g" "$CONFIG_FILE"
    
    print_success "Config.php berhasil diupdate"
    print_status "Backup disimpan: $CONFIG_FILE.backup.*"
else
    print_warning "Config.php tidak ditemukan di direktori ini"
fi

echo ""
print_success "SSH setup selesai!"
print_status "SSH private key: $KEY_FILE"
print_status "Pastikan semua VPS target sudah dikonfigurasi dengan public key"
echo ""

# Create test script
TEST_SCRIPT="$(dirname "$0")/test-ssh.sh"
cat > "$TEST_SCRIPT" << EOF
#!/bin/bash
# Test SSH connections to all VPS targets

SSH_KEY="$KEY_FILE"

echo "Testing SSH connections..."
echo ""

# VPS List
declare -A VPS_LIST=(
    ["140.213.202.13"]="ANYM NETWORK"
    ["103.150.197.96"]="ARGON DATA 1" 
    ["128.199.104.75"]="DIGITAL OCEAN 2"
)

for ip in "\${!VPS_LIST[@]}"; do
    name="\${VPS_LIST[\$ip]}"
    echo "Testing \$name (\$ip)..."
    
    if ssh -i "\$SSH_KEY" -o ConnectTimeout=10 -o StrictHostKeyChecking=no root@\$ip "echo 'OK' && command -v menu" 2>/dev/null; then
        echo "✓ \$name - Connection and menu OK"
    else
        echo "✗ \$name - Connection or menu FAILED"
    fi
    echo ""
done
EOF

chmod +x "$TEST_SCRIPT"
print_status "Test script dibuat: $TEST_SCRIPT"
print_status "Jalankan ./test-ssh.sh untuk test semua koneksi"

echo ""
echo "=================================================="
print_success "Setup SSH selesai!"
echo "=================================================="

#!/usr/bin/env bash


set -e

echo "🔥 Installing Telegram Bot API Server..."
echo "-------------------------------------------"

PROJECT_DIR="/home/laraninjadown"
TDLIB_DIR="/home/tdlib/telegram-bot-api"
SERVICE_FILE="${PROJECT_DIR}/scripts/telegram_api_service.d"
SYSTEMD_PATH="/etc/systemd/system/telegram-bot-api.service"

echo "📦 Installing dependencies..."

sudo apt update -y
sudo apt install -y \
    git cmake g++ zlib1g zlib1g-dev openssl libssl-dev gperf build-essential

echo "✔ Dependencies installed."


sudo mkdir -p /var/lib/telegram-bot-api
sudo chown www-data:www-data /var/lib/telegram-bot-api


if [ ! -d "/home/tdlib" ]; then
  sudo mkdir -p /home/tdlib
  sudo chown $USER:$USER /home/tdlib
fi

if [ -d "$TDLIB_DIR" ]; then
  echo "♻ Repo already exists. Updating..."
  cd "$TDLIB_DIR"
  git pull
else
  echo "📥 Cloning Telegram Bot API Server..."
  git clone --recursive https://github.com/tdlib/telegram-bot-api.git "$TDLIB_DIR"
fi

echo "✔ Repository ready."

echo "🔨 Building source..."

cd "$TDLIB_DIR"
mkdir -p build
cd build

cmake -DCMAKE_BUILD_TYPE=Release ..
cmake --build . --target install -j$(nproc)

echo "✔ Build complete."

echo "⚙ Setting up systemd service..."

if [ ! -f "$SERVICE_FILE" ]; then
  echo "❌ ERROR: Service file not found at $SERVICE_FILE"
  exit 1
fi

sudo cp "$SERVICE_FILE" "$SYSTEMD_PATH"
sudo chmod 644 "$SYSTEMD_PATH"

echo "✔ Service file copied."

echo "🔁 Enabling service..."
sudo systemctl daemon-reload
sudo systemctl enable telegram-bot-api
sudo systemctl restart telegram-bot-api

echo "✔ Service started."

echo "🔎 Checking status..."
sleep 1
sudo systemctl status telegram-bot-api --no-pager

echo "🎉 DONE!"
echo "Your Telegram Bot API server is installed and running!"









































[Unit]
Description=Telegram Bot Api Server
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/telegram-bot-api/build
ExecStart=/usr/local/bin/telegram-bot-api --local --api-id=26793041 --api-hash=486b7819392544180db32a5202a94adc --http-port=8081 --max-webhook-connections=1000 --dir=/var/lib/telegram-bot-api
Restart=10
Restart=on-failure
RestartSec=always
User=www-data
Group=www-data
LimitNOFILE=100000

[Install]
WantedBy=multi-user.target













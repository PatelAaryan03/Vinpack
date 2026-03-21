#!/bin/bash

# Vinpack Development Server Starter
# Run this script to start the PHP server instantly

PROJECT_DIR="/Users/aaryanpatel/Desktop/Projects/Vinpack"
PORT=8000
HOST="127.0.0.1"

echo "🚀 Starting Vinpack Development Server..."
echo "📱 Server running at: http://$HOST:$PORT"
echo "👨‍💼 Admin login at: http://$HOST:$PORT/admin/login.php"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

cd "$PROJECT_DIR/public"
php -S $HOST:$PORT

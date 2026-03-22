#!/bin/bash

# Vinpack Development Server Starter
# Run this script to start the PHP server
# Works from any directory

PORT=${1:-8000}  # Default port 8000, or use argument
HOST="127.0.0.1"

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$SCRIPT_DIR"

echo "🚀 Starting Vinpack Development Server..."
echo "📍 Project path: $PROJECT_DIR"
echo "📱 Server running at: http://$HOST:$PORT"
echo "👨‍💼 Admin login at: http://$HOST:$PORT/admin/login.php"
echo "🏠 Website: http://$HOST:$PORT"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

cd "$PROJECT_DIR/public"
php -S $HOST:$PORT

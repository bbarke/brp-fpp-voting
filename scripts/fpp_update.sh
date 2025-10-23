#!/bin/bash
set -x

missing_packages=""

# Check for python3-requests
if ! dpkg -s python3-requests >/dev/null 2>&1; then
    missing_packages+=" python3-requests"
fi

# Check for python3-crontab
if ! dpkg -s python3-crontab >/dev/null 2>&1; then
    missing_packages+=" python3-crontab"
fi

# Check for python3-mutagen
if ! dpkg -s python3-mutagen >/dev/null 2>&1; then
    missing_packages+=" python3-mutagen"
fi

if [ -n "$missing_packages" ]; then
    sudo apt-get update
    sudo apt-get install -y$missing_packages
else
    echo "All required packages are already installed."
fi

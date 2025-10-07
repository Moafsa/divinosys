#!/bin/bash

# Fix npm vulnerabilities
echo "Fixing npm vulnerabilities..."

# Update npm to latest version
npm install -g npm@latest

# Fix vulnerabilities
npm audit fix --force

# Clean npm cache
npm cache clean --force

echo "NPM vulnerabilities fixed!"

#!/bin/bash
set -e

# Pass environment variables to cron (cron runs in a clean env)
printenv | grep -v "no_proxy" >> /etc/environment

# Start cron daemon in background
cron

# Start Apache in foreground (PID 1)
exec apache2-foreground

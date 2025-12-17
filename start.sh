#!/bin/bash
# Configure Apache to use Render's port
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/*.conf

# Start Apache in foreground
apache2-foreground

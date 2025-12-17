FROM php:8.2-apache

# Install necessary PHP extensions for your project
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite for URL routing
RUN a2enmod rewrite

# Copy all project files to web server directory
COPY . /var/www/html/

# Create uploads directory with proper permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads

# Configure Apache to use .htaccess files
RUN echo "<Directory /var/www/html>" >> /etc/apache2/apache2.conf && \
    echo "    AllowOverride All" >> /etc/apache2/apache2.conf && \
    echo "    Require all granted" >> /etc/apache2/apache2.conf && \
    echo "</Directory>"

# Set proper ownership for all files
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

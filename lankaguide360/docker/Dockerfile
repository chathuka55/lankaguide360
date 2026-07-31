# =====================================================================
# LankaGuide 360 — PHP application image
# php:8.2 + Apache with the PDO MySQL driver enabled.
# =====================================================================
FROM php:8.2-apache

LABEL org.opencontainers.image.title="LankaGuide 360" \
      org.opencontainers.image.description="Intelligent Sri Lanka tourism booking platform" \
      org.opencontainers.image.source="https://github.com/your-org/lankaguide360"

# Install PDO MySQL + mysqli extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite

# Recommended production-ish PHP settings
RUN { \
      echo "upload_max_filesize=12M"; \
      echo "post_max_size=14M"; \
      echo "memory_limit=256M"; \
      echo "expose_php=Off"; \
    } > /usr/local/etc/php/conf.d/lankaguide360.ini

# Copy the application (build context is the project root)
COPY . /var/www/html/

# Uploads directory for performer trip photos
RUN mkdir -p /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=5 \
  CMD curl -fsS http://localhost/index.php >/dev/null || exit 1

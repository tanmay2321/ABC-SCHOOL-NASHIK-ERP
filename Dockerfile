# Official lightweight PHP 8.2 image for Render cloud deployment
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Create upload directories and configure permissions for SQLite database and uploads
RUN mkdir -p /app/uploads/logo /app/uploads/assignments /app/uploads/submissions \
    && chmod -R 777 /app/uploads \
    && chmod 666 /app/database.sqlite 2>/dev/null || true \
    && chmod 777 /app

# Render provides the $PORT environment variable at runtime (defaults to 10000)
ENV PORT=10000
EXPOSE 10000

# Start the application using hardened router.php
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} router.php"]

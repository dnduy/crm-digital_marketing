# Sử dụng phiên bản PHP-FPM dựa trên Alpine cho kích thước nhỏ
FROM php:8.2-fpm-alpine

# Cài đặt các thư viện hệ thống cần thiết (ví dụ: cho GD, zip, git)
RUN apk add --no-cache \
    autoconf \
    g++ \
    make \
    libzip-dev \
    libpng-dev \
    git \
    zip \
    unzip

# Cài đặt Composer (Công cụ quản lý thư viện cho PHP)
# Sử dụng COPY --from để tải Composer từ image riêng biệt, sạch sẽ hơn
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cài đặt các extension PHP phổ biến (pdo_mysql rất quan trọng cho Database)
RUN docker-php-ext-install pdo pdo_mysql mysqli opcache

# Thiết lập thư mục làm việc bên trong container
WORKDIR /var/www/html

# Mặc định, PHP-FPM chạy trên port 9000
EXPOSE 9000

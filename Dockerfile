FROM php:7.4-apache

# 复制应用程序文件到容器中
COPY . /var/www/html/

# 安装PHP扩展和其他依赖项
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 暴露80端口
EXPOSE 80

# 启动Apache服务器并运行应用程序
CMD ["apache2-foreground"]
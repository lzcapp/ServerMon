FROM php:7.4-apache-buster

# 复制应用程序文件到容器中
COPY . /var/www/html/

# 暴露80端口
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 CMD curl http://localhost/ || exit 1

# 启动Apache服务器并运行应用程序
CMD ["apache2-foreground"]

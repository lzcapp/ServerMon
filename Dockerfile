FROM php:7.4.33-alpine3.15

# 复制应用程序文件到容器中
COPY . /var/www/html/

# 暴露80端口
EXPOSE 80

# 启动Apache服务器并运行应用程序
CMD ["apache2-foreground"]
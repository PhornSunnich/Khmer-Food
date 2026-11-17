FROM php:8.3-cli

# ដំឡើង mysqli និង pdo_mysql សម្រាប់ភ្ជាប់ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# កំណត់ Working Directory
WORKDIR /var/www/html

# ចម្លង Source Code ទាំងអស់ចូល
COPY . /var/www/html/

# បើក PHP Built-in Server តាម Railway Dynamic PORT
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-80}"]

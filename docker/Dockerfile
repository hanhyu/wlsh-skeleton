FROM phpswoole/swoole:5.0.2-php8.1

LABEL maintainer="hanhyu <hanhyu@qq.com>" version="1.0"

ENV DEBIAN_FRONTEND noninteractive
ENV LANG C.UTF-8

RUN docker-php-ext-install -j4 bcmath calendar exif iconv pcntl sockets sysvmsg sysvsem sysvshm

RUN set -ex \
    && pecl update-channels \
    && pecl install msgpack \
    && docker-php-ext-enable msgpack \
    && rm -rf /tmp/* /usr/share/man /usr/src/php.tar.xz* \
    && apt-get clean

# RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
RUN ln -snf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && echo 'Asia/Shanghai' > /etc/timezone \
    && dpkg-reconfigure --frontend noninteractive tzdata \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && sed -i '/post_max_size = 8M/c post_max_size = 300M' /usr/local/etc/php/php.ini \
    && sed -i '/upload_max_filesize = 2M/c upload_max_filesize = 300M' /usr/local/etc/php/php.ini \
    && sed -i '/max_input_time = 60/c max_input_time = 300' /usr/local/etc/php/php.ini \
    && sed -i '/;max_input_vars = 1000/c max_input_vars = 3000' /usr/local/etc/php/php.ini \
    && sed -i '/;date.timezone =/c date.timezone = Asia/Shanghai' /usr/local/etc/php/php.ini

COPY supervisord.conf /etc/supervisor/conf.d/
# add php-fpm-exporter

# CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

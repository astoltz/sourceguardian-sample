FROM php:8.4-apache

# https://www.sourceguardian.com/loaders.html
# php -i | grep extension_dir
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
        mkdir /loaders && \
        cd /loaders && \
        curl -o loaders.linux-x86_64.tar.gz https://www.sourceguardian.com/loaders/download/loaders.linux-x86_64.tar.gz && \
        tar -xzf loaders.linux-x86_64.tar.gz && \
        cp /loaders/ixed.8.4.lin /usr/local/lib/php/extensions/no-debug-non-zts-20240924 && \
        sed -i '1s/^/zend_extension=ixed.8.4.lin\n/' /usr/local/etc/php/php.ini && \
        echo 'sourceguardian.enable_vm_hybrid=1' >> /usr/local/etc/php/php.ini && \
        rm -Rf /loaders

ADD src/ /var/www/html/

# PHP variant of the site image — use this INSTEAD of the nginx Dockerfile once
# the forms go live: the /api/*.php handlers need PHP, which nginx-alpine lacks.
# (To switch CI: add `file: Dockerfile.php` to the build-push-action step.)
#
#   docker build -f Dockerfile.php -t impactfund-php .
#   docker run -d -p 8777:8777 --restart unless-stopped \
#     -v impactfund_submissions:/var/www/_submissions \
#     -v /path/on/server/config.php:/var/www/html/api/config.php:ro \
#     impactfund-php
#
# ALWAYS mount config.php — never bake it into the image (it holds secrets,
# and image layers are forever). Same for _submissions (mounted volume above).
#
# MAIL: php:8.3-apache has no sendmail, so delivery_mode 'email' needs msmtp
# (installed below) pointed at a real SMTP relay — mount /etc/msmtprc:
#   -v /path/on/server/msmtprc:/etc/msmtprc:ro
# (account default; host/port/user/password of e.g. an M365 mailbox).
# Without an msmtprc, use delivery_mode 'graph' or 'off'.
# On GoDaddy none of this applies — PHP mail() works there natively.

FROM php:8.3-apache

# Mail relay for PHP mail() (configure via mounted /etc/msmtprc)
RUN apt-get update && apt-get install -y --no-install-recommends msmtp-mta \
 && rm -rf /var/lib/apt/lists/* \
 && echo 'sendmail_path = "/usr/sbin/sendmail -t -i"' > /usr/local/etc/php/conf.d/mail.ini

# Serve on 8777 to match the existing deployment
RUN sed -i 's/^Listen 80$/Listen 8777/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:8777>/' /etc/apache2/sites-available/000-default.conf
EXPOSE 8777

# Site content (root-relative URLs -> must be the document root)
COPY . /var/www/html/

# Never ship these to the container image (.dockerignore is the first line of
# defense; this is belt-and-braces for stale build contexts)
RUN rm -rf /var/www/html/_source /var/www/html/_tools \
           /var/www/html/.git /var/www/html/Dockerfile* \
           /var/www/html/docker-compose.yml /var/www/html/.github \
           /var/www/html/_submissions /var/www/html/api/config.php

# Submissions land outside the webroot
RUN mkdir -p /var/www/_submissions && chown www-data:www-data /var/www/_submissions

# Modest upload limits (application form caps files at 10 MB each)
RUN { \
  echo 'upload_max_filesize = 12M'; \
  echo 'post_max_size = 60M'; \
  echo 'max_file_uploads = 10'; \
} > /usr/local/etc/php/conf.d/uploads.ini

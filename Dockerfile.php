# PHP variant of the site image — use this INSTEAD of the nginx Dockerfile once
# the forms go live: the /api/*.php handlers need PHP, which nginx-alpine lacks.
# Behavior matches GoDaddy shared hosting (Apache + PHP), so what works here
# works after the GoDaddy cutover unchanged.
#
#   docker build -f Dockerfile.php -t impactfund-php .
#   docker run -d -p 8777:8777 --restart unless-stopped \
#     -v impactfund_submissions:/var/www/_submissions impactfund-php
#
# Provide the real config by baking it in or mounting it:
#   -v /path/on/server/config.php:/var/www/html/api/config.php:ro

FROM php:8.3-apache

# Serve on 8777 to match the existing deployment
RUN sed -i 's/^Listen 80$/Listen 8777/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:8777>/' /etc/apache2/sites-available/000-default.conf
EXPOSE 8777

# Site content (root-relative URLs -> must be the document root)
COPY . /var/www/html/

# Never ship these to the container image
RUN rm -rf /var/www/html/_source /var/www/html/_tools \
           /var/www/html/.git /var/www/html/Dockerfile* \
           /var/www/html/docker-compose.yml /var/www/html/.github

# Submissions land outside the webroot
RUN mkdir -p /var/www/_submissions && chown www-data:www-data /var/www/_submissions

# Modest upload limits (application form caps files at 10 MB each)
RUN { \
  echo 'upload_max_filesize = 12M'; \
  echo 'post_max_size = 60M'; \
  echo 'max_file_uploads = 10'; \
} > /usr/local/etc/php/conf.d/uploads.ini

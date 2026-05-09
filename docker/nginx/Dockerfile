# syntax=docker/dockerfile:1.7
# API-only nginx image. The application's public/ directory is small
# (favicon.ico, robots.txt, index.php) so we copy it straight from the build
# context — no cross-image dependency on the PHP-FPM app image, which means
# compose can build both services in parallel.

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public

EXPOSE 80

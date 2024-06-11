FROM php:8.2-cli-alpine

LABEL org.opencontainers.image.description = "Experimental Docker image for the HydePHP standalone executable."

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions zlib

COPY builds/hyde /hyde.phar

ENTRYPOINT ["/hyde.phar"]

# The HydePHP CLI needs nothing from the base image: the executable carries its own
# PHP runtime. This image exists to make that easy to verify and easy to consume.
FROM alpine:3.20

LABEL org.opencontainers.image.title="HydePHP CLI"
LABEL org.opencontainers.image.description="The HydePHP CLI, a static site generator that needs no PHP and no Composer."
LABEL org.opencontainers.image.source="https://github.com/hydephp/cli"

# The Linux artifacts are built against musl by static-php-cli, so no glibc shim is needed.
ARG ARTIFACT=hyde-linux-x86_64

COPY builds/${ARTIFACT} /usr/local/bin/hyde

RUN chmod +x /usr/local/bin/hyde

WORKDIR /site

ENTRYPOINT ["/usr/local/bin/hyde"]

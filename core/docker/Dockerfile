# syntax=docker/dockerfile:1
# Build from the repository root: docker build -f core/docker/Dockerfile -t temporal-php-core .

FROM rust:1-bookworm AS bridge
RUN apt-get update \
    && apt-get install -y --no-install-recommends protobuf-compiler libprotobuf-dev \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /src
COPY core/bridge ./
RUN --mount=type=cache,target=/usr/local/cargo/registry \
    --mount=type=cache,target=/usr/local/cargo/git \
    --mount=type=cache,target=/src/target \
    cargo build --release --lib \
    && cp target/release/libtemporal_php_bridge.so /libtemporal_php_bridge.so

FROM php:8.5-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libffi-dev git unzip \
    && docker-php-ext-install ffi pcntl sockets \
    && pecl install protobuf \
    && docker-php-ext-enable protobuf \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN COMPOSER_ROOT_VERSION=2.19.0 composer install --no-interaction --prefer-dist --no-progress
COPY --from=bridge /libtemporal_php_bridge.so core/bridge/target/release/libtemporal_php_bridge.so
CMD ["php", "bench/worker.php"]

FROM node:20-bookworm-slim

ARG UID=1000
ARG GID=1000

# Chromium de Debian en vez del que baja puppeteer: más liviano y con sus bibliotecas.
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    CHROMIUM_PATH=/usr/bin/chromium

RUN apt-get update && apt-get install -y --no-install-recommends \
      chromium fonts-liberation ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY whatsapp/package.json ./
COPY whatsapp/patches ./patches
# Parche de whatsapp-web.js 1.34.7 para envíos con archivo (ver patches/).
RUN npm install --omit=dev --no-audit --no-fund && node patches/fix-media-id.cjs

COPY whatsapp/src ./src
COPY whatsapp/test ./test

# Cada sesión vinculada vive en /data (volumen): sin él habría que escanear en cada arranque.
RUN mkdir -p /data && chown -R ${UID}:${GID} /app /data
USER ${UID}:${GID}

EXPOSE 3000
HEALTHCHECK --interval=20s --timeout=5s --start-period=30s --retries=5 \
  CMD curl -fsS http://127.0.0.1:3000/health || exit 1

CMD ["node", "src/index.js"]

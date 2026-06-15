FROM node:20-alpine

WORKDIR /app

# package.json монтируется через volume; зависимости ставятся в entrypoint,
# чтобы не пересобирать образ при изменении кода (dev-режим Vite).
COPY package*.json ./

EXPOSE 5173

CMD ["sh", "-c", "npm install && npm run dev -- --host 0.0.0.0"]

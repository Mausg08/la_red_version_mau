FROM node:20-alpine

WORKDIR /app

# Install dependencies
COPY package.json .
RUN npm install --production

# Copy server code
COPY server.js .

# Expose WebSocket port
EXPOSE 3001

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD wget -qO- http://localhost:3001/health || exit 1

CMD ["node", "server.js"]

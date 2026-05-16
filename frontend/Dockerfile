# ============================================================
# Frontend Dockerfile — Multi-stage build
# Stage 1: Build React production bundle
# Stage 2: Serve with Nginx (lightweight alpine)
# ============================================================

# ── Stage 1: Build ────────────────────────────────────────────
FROM node:20-alpine AS builder

WORKDIR /app

# Accept env vars at build time — baked into React JS bundle (not runtime-configurable).
# Both args must be declared here for docker-compose build args to take effect.
ARG REACT_APP_API_URL=/api
ARG REACT_APP_APP_NAME="Barcode Portal"
ENV REACT_APP_API_URL=$REACT_APP_API_URL
ENV REACT_APP_APP_NAME=$REACT_APP_APP_NAME

# Install dependencies first (better layer caching)
COPY package*.json ./
RUN npm ci --silent --no-audit --no-fund

# Copy source and build
COPY . .
RUN npm run build

# ── Stage 2: Serve ────────────────────────────────────────────
FROM nginx:1.25-alpine

# Remove default nginx config
RUN rm /etc/nginx/conf.d/default.conf

# Copy React build output
COPY --from=builder /app/build /usr/share/nginx/html

# Copy SPA nginx config (handles React Router client-side routes)
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Non-root: nginx master runs as root briefly then drops to nginx user
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD wget -qO- http://localhost/ || exit 1

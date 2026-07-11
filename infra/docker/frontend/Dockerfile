# syntax=docker/dockerfile:1

FROM node:22-alpine AS build

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .

# Vite inlines VITE_* variables into the static bundle at build time, so they
# must be supplied as build args (not runtime env) — there is no server-side
# process to read them from later. Defaults match frontend/.env.example.
ARG VITE_API_URL=http://localhost:8080/api/v1
ARG VITE_APP_URL=http://localhost:8080
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8085
ARG VITE_REVERB_SCHEME=http
ENV VITE_API_URL=${VITE_API_URL} \
    VITE_APP_URL=${VITE_APP_URL} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN npm run build

# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS production

# API_ORIGIN/WS_ORIGIN drive the CSP connect-src below and vary per
# deployment (dev vs. staging vs. prod), so the conf is a template —
# nginx's own entrypoint runs envsubst over it at container start using only
# defined env vars, leaving nginx's own $uri-style variables untouched.
ENV API_ORIGIN=http://localhost:8080 \
    WS_ORIGIN=ws://localhost:8085

RUN rm -f /etc/nginx/conf.d/default.conf
COPY infra/docker/frontend/frontend.conf.template /etc/nginx/templates/frontend.conf.template
COPY infra/docker/nginx/security-headers.conf /etc/nginx/frontend-security-headers.conf
COPY --from=build /app/dist /usr/share/nginx/html

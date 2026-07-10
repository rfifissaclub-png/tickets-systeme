FROM nginx:1.27-alpine

RUN rm -f /etc/nginx/conf.d/default.conf
COPY infra/docker/nginx/tms.conf /etc/nginx/conf.d/tms.conf
COPY infra/docker/nginx/security-headers.conf /etc/nginx/security-headers.conf

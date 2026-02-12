# Stage 1: Build frontend assets
FROM node:20-alpine as frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: Nginx
FROM nginx:alpine
COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/public /var/www/html/public

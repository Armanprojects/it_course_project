# Образ для Vite dev server.
FROM node:22-alpine

WORKDIR /var/www/frontend

# Код и node_modules монтируются томами в docker-compose (dev).
EXPOSE 5173

# --host обязателен: по умолчанию Vite слушает 127.0.0.1 внутри контейнера
# и снаружи (из nginx) недоступен.
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

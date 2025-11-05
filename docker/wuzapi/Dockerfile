# Dockerfile para WuzAPI (Backend + Frontend)
# IGUAL AO QUE ESTÁ FUNCIONANDO ONLINE
FROM golang:1.24-alpine AS builder

# Instalar dependências
RUN apk add --no-cache git

# Clonar repositório
WORKDIR /app
RUN git clone https://github.com/pedroherpeto/wuzapi.git .

# Compilar backend Go
RUN go mod download
RUN go build -o wuzapi .

# Imagem final
FROM alpine:latest

# Instalar dependências runtime (incluindo Node.js para frontend)
RUN apk add --no-cache ca-certificates tzdata nodejs npm

# Copiar binário e migrações
COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi
COPY --from=builder /app/migrations /usr/local/bin/migrations

# Copiar frontend
COPY --from=builder /app/frontend /app/frontend
COPY --from=builder /app/static /app/static

# Copiar arquivo de configuração do frontend (usa URL INTERNA do Docker)
COPY frontend.env /app/frontend/.env

# Build do frontend React (como root)
RUN cd /app/frontend && npm install && npm run build

# Criar usuário não-root e dar permissões
RUN adduser -D -s /bin/sh wuzapi
RUN chown -R wuzapi:wuzapi /app/frontend /app/static

# Expor portas (backend API:8080 e frontend:3000)
EXPOSE 8080 3000

# Comando de inicialização
# Backend API na porta 8080, Frontend na porta 3000
CMD ["sh", "-c", "wuzapi -port=8080 -address=0.0.0.0 -admintoken=${API_KEY:-admin123456} & cd /app/frontend && npx serve -s build -l 3000"]

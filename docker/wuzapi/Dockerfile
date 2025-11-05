# Dockerfile para WuzAPI (Backend + Frontend)
FROM golang:1.23-alpine AS builder

# Instalar dependências
RUN apk add --no-cache git

# Clonar repositório e fixar em commit antes do Go 1.24
WORKDIR /app
RUN git clone https://github.com/pedroherpeto/wuzapi.git . && \
    (git log --before="2024-10-01" --pretty=format:"%H" -1 | head -1 | xargs -I {} git checkout {} || \
    sed -i 's/go 1\.24/go 1.23/g' go.mod || true)

# Compilar backend Go
RUN go mod download
RUN go build -o wuzapi .

# Imagem final
FROM alpine:latest

# Instalar dependências runtime (incluindo Node.js para frontend)
RUN apk add --no-cache ca-certificates tzdata nodejs npm

# Copiar binário
COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi

# Copiar tudo e depois limpar o que não precisamos
COPY --from=builder /app /tmp/wuzapi-build

# Organizar arquivos
RUN mkdir -p /app/sessions /app/logs /app/data /usr/local/bin/migrations && \
    if [ -d /tmp/wuzapi-build/migrations ]; then cp -r /tmp/wuzapi-build/migrations/* /usr/local/bin/migrations/ 2>/dev/null || true; fi && \
    if [ -d /tmp/wuzapi-build/frontend ]; then cp -r /tmp/wuzapi-build/frontend /app/; fi && \
    if [ -d /tmp/wuzapi-build/static ]; then cp -r /tmp/wuzapi-build/static /app/; fi && \
    rm -rf /tmp/wuzapi-build

# Build do frontend se existir
RUN if [ -d /app/frontend ]; then \
        cd /app/frontend && \
        echo "REACT_APP_API_URL=http://localhost:8081" > .env && \
        npm install && \
        npm run build; \
    fi

# Criar usuário não-root
RUN adduser -D -s /bin/sh wuzapi && \
    chown -R wuzapi:wuzapi /app

# Expor portas
EXPOSE 8080 3000

# Comando de inicialização
CMD ["sh", "-c", "wuzapi -port=8080 -address=0.0.0.0 -admintoken=${WUZAPI_ADMIN_TOKEN:-admin123456} & if [ -d /app/frontend/build ]; then cd /app/frontend && npx serve -s build -l 3000; else sleep infinity; fi"]

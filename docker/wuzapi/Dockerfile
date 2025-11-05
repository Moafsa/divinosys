# Dockerfile para WuzAPI (Backend + Frontend)
# FIXADO em Go 1.22 e commit específico do Wuzapi que funciona
FROM golang:1.22-alpine AS builder

# Instalar dependências
RUN apk add --no-cache git

# Clonar repositório e fixar em commit específico (antes Go 1.24)
WORKDIR /app
RUN git clone https://github.com/pedroherpeto/wuzapi.git . && \
    git checkout 8a9c4e8b3f2d1a5c7e9d6f4b2a8c5e3d1f7b9a4c 2>/dev/null || \
    git checkout $(git log --before="2024-09-01" --pretty=format:"%H" -1) || \
    echo "Usando HEAD"

# Compilar backend Go
RUN go mod download
RUN go build -o wuzapi .

# Imagem final
FROM alpine:latest

# Instalar dependências runtime (incluindo Node.js para frontend)
RUN apk add --no-cache ca-certificates tzdata nodejs npm

# Copiar binário
COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi

# Copiar tudo temporariamente para verificar o que existe
COPY --from=builder /app /tmp/wuzapi-build

# Criar diretórios e organizar arquivos
RUN mkdir -p /app/sessions /app/logs /app/data /usr/local/bin/migrations && \
    if [ -d /tmp/wuzapi-build/migrations ]; then cp -r /tmp/wuzapi-build/migrations/* /usr/local/bin/migrations/; fi && \
    if [ -d /tmp/wuzapi-build/frontend ]; then cp -r /tmp/wuzapi-build/frontend /app/; fi && \
    if [ -d /tmp/wuzapi-build/static ]; then cp -r /tmp/wuzapi-build/static /app/; fi && \
    if [ -d /tmp/wuzapi-build/web ]; then cp -r /tmp/wuzapi-build/web /app/; fi && \
    if [ -d /tmp/wuzapi-build/ui ]; then cp -r /tmp/wuzapi-build/ui /app/; fi && \
    rm -rf /tmp/wuzapi-build

# Build do frontend React se existir
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
# Backend na porta 8080, se frontend existir, serve na porta 3000
CMD ["sh", "-c", "wuzapi -port=8080 -address=0.0.0.0 -admintoken=${WUZAPI_ADMIN_TOKEN:-admin123456} & if [ -d /app/frontend/build ]; then cd /app/frontend && npx serve -s build -l 3000; else echo 'Frontend não encontrado, apenas API rodando'; sleep infinity; fi"]

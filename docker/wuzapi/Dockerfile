# Dockerfile para WuzAPI (Backend + Frontend)
# FIXADO em Go 1.23 (versão que funcionava antes)
FROM golang:1.23-alpine AS builder

# Instalar dependências
RUN apk add --no-cache git

# Clonar repositório Wuzapi (repositório original do pedroherpeto)
WORKDIR /app
RUN git clone https://github.com/pedroherpeto/wuzapi.git .

# FIXAR em commit que usa Go 1.23 (antes da mudança para 1.24)
# Buscar último commit que ainda funcionava
RUN git log --all --oneline | head -20 && \
    git checkout $(git log --grep="1.23" --pretty=format:"%H" -1 2>/dev/null || \
    git log --before="2024-10-15" --pretty=format:"%H" -1 || \
    git rev-list --before="2024-10-01" HEAD -1)

# Se go.mod pedir 1.24, corrigir para 1.23
RUN if grep -q "go 1.24" go.mod 2>/dev/null; then \
        sed -i 's/go 1\.24/go 1.23/g' go.mod; \
    fi

# Compilar backend Go
RUN go mod tidy
RUN go mod download
RUN go build -o wuzapi .

# Imagem final
FROM alpine:latest

# Instalar dependências runtime (incluindo Node.js para frontend)
RUN apk add --no-cache ca-certificates tzdata nodejs npm

# Copiar binário
COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi

# Copiar tudo temporariamente
COPY --from=builder /app /tmp/wuzapi-build

# Criar diretórios e organizar arquivos
RUN mkdir -p /app/sessions /app/logs /app/data /usr/local/bin/migrations && \
    if [ -d /tmp/wuzapi-build/migrations ]; then cp -r /tmp/wuzapi-build/migrations/* /usr/local/bin/migrations/ || true; fi && \
    if [ -d /tmp/wuzapi-build/frontend ]; then cp -r /tmp/wuzapi-build/frontend /app/; fi && \
    if [ -d /tmp/wuzapi-build/static ]; then cp -r /tmp/wuzapi-build/static /app/; fi && \
    if [ -d /tmp/wuzapi-build/web ]; then cp -r /tmp/wuzapi-build/web /app/; fi && \
    if [ -d /tmp/wuzapi-build/ui ]; then cp -r /tmp/wuzapi-build/ui /app/; fi && \
    if [ -d /tmp/wuzapi-build/views ]; then cp -r /tmp/wuzapi-build/views /app/; fi && \
    if [ -d /tmp/wuzapi-build/public ]; then cp -r /tmp/wuzapi-build/public /app/; fi

# Listar o que foi copiado para debug
RUN echo "=== Estrutura copiada ===" && ls -la /app/ && \
    echo "=== Conteúdo completo ===" && find /app/ -type f 2>/dev/null | head -50

# Build do frontend se existir
RUN if [ -d /app/frontend ]; then \
        echo "Frontend encontrado, fazendo build..."; \
        cd /app/frontend && \
        npm install && \
        npm run build; \
    elif [ -d /app/web ]; then \
        echo "Web UI encontrado, fazendo build..."; \
        cd /app/web && \
        npm install && \
        npm run build 2>/dev/null || echo "Build do web falhou ou não necessário"; \
    else \
        echo "AVISO: Frontend não encontrado em /app/"; \
        ls -la /tmp/wuzapi-build/ || true; \
    fi

# Limpar temporários
RUN rm -rf /tmp/wuzapi-build

# Criar usuário não-root
RUN adduser -D -s /bin/sh wuzapi && \
    chown -R wuzapi:wuzapi /app

# Expor portas
EXPOSE 8080 3000

# Comando de inicialização
# O Wuzapi serve o frontend via /static diretamente na porta 8080
CMD ["sh", "-c", "wuzapi -port=8080 -address=0.0.0.0 -admintoken=${WUZAPI_ADMIN_TOKEN:-admin123456}"]

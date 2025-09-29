# Dockerfile para WuzAPI
FROM golang:1.24-alpine AS builder

# Instalar dependências
RUN apk add --no-cache git

# Clonar e compilar WuzAPI
WORKDIR /app
RUN git clone https://github.com/pedroherpeto/wuzapi.git .
RUN go mod download
RUN go build -o wuzapi .

# Imagem final
FROM alpine:latest

# Instalar dependências runtime
RUN apk add --no-cache ca-certificates tzdata

# Copiar binário e migrações
COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi
COPY --from=builder /app/migrations /usr/local/bin/migrations

# Criar usuário não-root
RUN adduser -D -s /bin/sh wuzapi
USER wuzapi

# Expor porta
EXPOSE 8080

# Comando de inicialização
CMD ["wuzapi"]

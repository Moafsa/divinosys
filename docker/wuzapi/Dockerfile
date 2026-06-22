# WuzAPI com whatsmeow atualizado (fix "Client outdated")
FROM golang:1.25-alpine AS builder

RUN apk add --no-cache git

WORKDIR /app
RUN git clone --depth 1 https://github.com/asternic/wuzapi.git .

# Atualizar whatsmeow para versão compatível com WhatsApp atual
RUN go get -u go.mau.fi/whatsmeow@latest && go mod tidy

# Correções do frontend para deploy atrás de proxy
RUN sed -i "s|'http://localhost:5555'|''|g" frontend/src/contexts/AuthContext.tsx || true
RUN sed -i "s|/api/validate-token|/api/admin/users|g" frontend/src/contexts/AuthContext.tsx || true
RUN sed -i "s|response\.data\.valid|response.data.instances !== undefined|g" frontend/src/contexts/AuthContext.tsx || true

RUN go mod download
RUN go build -o wuzapi .

FROM alpine:latest

RUN apk add --no-cache ca-certificates tzdata nodejs npm

COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi
COPY --from=builder /app/migrations /usr/local/bin/migrations
COPY --from=builder /app/frontend /app/frontend
COPY --from=builder /app/static /app/static

COPY frontend.env /app/frontend/.env

RUN cd /app/frontend && npm install && npm run build

RUN adduser -D -s /bin/sh wuzapi && \
    chown -R wuzapi:wuzapi /app/frontend /app/static

EXPOSE 8080 3000

CMD ["sh", "-c", "wuzapi -port=8080 -address=0.0.0.0 -admintoken=${API_KEY:-admin123456} & cd /app/frontend && npx serve -s build -l 3000"]

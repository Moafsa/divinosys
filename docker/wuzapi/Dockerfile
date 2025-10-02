FROM golang:1.21-alpine AS builder

# Install dependencies
RUN apk add --no-cache git

# Clone repository
WORKDIR /app
RUN git clone https://github.com/pedroherpeto/wuzapi.git .

# Build backend Go
RUN go mod download
RUN go build -o wuzapi .

# Final image
FROM alpine:latest

# Install runtime dependencies
RUN apk add --no-cache ca-certificates tzdata nodejs npm

# Copy binary and migrations
COPY --from=builder /app/wuzapi /usr/local/bin/wuzapi
COPY --from=builder /app/migrations /usr/local/bin/migrations

# Copy frontend
COPY --from=builder /app/frontend /app/frontend
COPY --from=builder /app/static /app/static

# Copy frontend configuration
COPY frontend.env /app/frontend/.env

# Create non-root user
RUN adduser -D -s /bin/sh wuzapi
RUN chown -R wuzapi:wuzapi /app/frontend /app/static
USER wuzapi

# Expose port
EXPOSE 8080

# Startup command
CMD ["sh", "-c", "wuzapi & cd /app/frontend && npm install && npm start"]

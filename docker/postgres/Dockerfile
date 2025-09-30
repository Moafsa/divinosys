FROM postgres:15-alpine

# Copiar script de entrada personalizado
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configurar variáveis de ambiente
ENV POSTGRES_DB=divino_lanches
ENV POSTGRES_USER=postgres
ENV POSTGRES_PASSWORD=divino_password
ENV POSTGRES_HOST_AUTH_METHOD=trust
ENV POSTGRES_INITDB_ARGS=--auth-host=trust --auth-local=trust

# Usar script personalizado como entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

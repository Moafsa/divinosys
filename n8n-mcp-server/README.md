# Divino Lanches MCP Server

Model Context Protocol (MCP) server for Divino Lanches AI integration with n8n.

## Overview

This server provides a standardized interface for AI agents to query the Divino Lanches database efficiently. Instead of sending all data in every request, the AI can make targeted queries only for the information it needs.

## Architecture

```
Divino Lanches System → n8n Workflow → MCP Server → PostgreSQL
                                    ↓
                              OpenAI API
```

## Features

- **Efficient Data Retrieval**: Only fetch data that's needed
- **Multiple Tools**: 8 specialized tools for different queries
- **Tenant Isolation**: Multi-tenant support built-in
- **Performance**: Connection pooling and optimized queries
- **Security**: Input validation and prepared statements

## Available Tools

### 1. `get_products`
Get products list with optional filters
```json
{
  "tool": "get_products",
  "parameters": {
    "query": "burger",
    "category_id": 1,
    "limit": 20
  },
  "context": {
    "tenant_id": 1,
    "filial_id": 1
  }
}
```

### 2. `get_ingredients`
Get ingredients list with optional type filter
```json
{
  "tool": "get_ingredients",
  "parameters": {
    "tipo": "proteina",
    "limit": 50
  }
}
```

### 3. `get_categories`
Get all categories
```json
{
  "tool": "get_categories",
  "parameters": {}
}
```

### 4. `get_orders`
Get orders with optional filters
```json
{
  "tool": "get_orders",
  "parameters": {
    "status": "Pendente",
    "mesa_id": "5",
    "limit": 10
  }
}
```

### 5. `get_tables`
Get tables with optional status filter
```json
{
  "tool": "get_tables",
  "parameters": {
    "status": "ocupada"
  }
}
```

### 6. `search_products`
Search products by name or description
```json
{
  "tool": "search_products",
  "parameters": {
    "term": "x-bacon",
    "limit": 10
  }
}
```

### 7. `get_product_details`
Get detailed information about a specific product
```json
{
  "tool": "get_product_details",
  "parameters": {
    "product_id": 123
  }
}
```

### 8. `get_order_details`
Get detailed information about a specific order
```json
{
  "tool": "get_order_details",
  "parameters": {
    "order_id": 456
  }
}
```

## Installation

### Local Development

1. Install dependencies:
```bash
cd n8n-mcp-server
npm install
```

2. Configure environment:
```bash
cp .env.example .env
# Edit .env with your database credentials
```

3. Start server:
```bash
npm start
```

4. Test health check:
```bash
curl http://localhost:3100/health
```

### Docker

1. Build image:
```bash
docker build -t divino-mcp-server .
```

2. Run container:
```bash
docker run -d \
  --name divino-mcp \
  -p 3100:3100 \
  -e DB_HOST=your_db_host \
  -e DB_PASSWORD=your_password \
  divino-mcp-server
```

### Docker Compose

Add to your `docker-compose.yml`:
```yaml
services:
  mcp-server:
    build: ./n8n-mcp-server
    container_name: divino-mcp-server
    ports:
      - "3100:3100"
    environment:
      - MCP_PORT=3100
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_NAME=divino_lanches
      - DB_USER=postgres
      - DB_PASSWORD=${DB_PASSWORD}
    depends_on:
      - postgres
    restart: unless-stopped
    networks:
      - divino-network
```

## API Endpoints

### GET /health
Health check endpoint
```bash
curl http://localhost:3100/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2025-01-08T10:30:00.000Z"
}
```

### GET /tools
List all available tools
```bash
curl http://localhost:3100/tools
```

### POST /execute
Execute a tool
```bash
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "get_products",
    "parameters": {"limit": 5},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

## Integration with n8n

### Setup Workflow

1. Create new workflow in n8n
2. Add **Webhook** trigger
3. Add **HTTP Request** node to call MCP server
4. Add **Code** node to process MCP response
5. Add **OpenAI** node with function calling
6. Add **HTTP Request** node to return response

See `n8n-workflow-example.json` for complete workflow.

## Security Considerations

- **Database Credentials**: Use environment variables, never hardcode
- **Input Validation**: All inputs are validated and sanitized
- **SQL Injection**: Uses parameterized queries
- **Rate Limiting**: Consider adding rate limiting for production
- **Authentication**: Add API key authentication for production

## Performance

- **Connection Pooling**: PostgreSQL connection pool (max 20 connections)
- **Query Optimization**: All queries use indexes
- **Caching**: Consider adding Redis cache for frequently accessed data
- **Monitoring**: Add APM tool (New Relic, DataDog) for production

## Troubleshooting

### Connection Issues
```bash
# Test database connection
docker exec -it divino-mcp-server node -e "
  const { Pool } = require('pg');
  const pool = new Pool({host: process.env.DB_HOST});
  pool.query('SELECT NOW()', (err, res) => {
    console.log(err ? err : res.rows);
    pool.end();
  });
"
```

### High Memory Usage
- Reduce connection pool size
- Add query result limits
- Implement pagination

## Monitoring

### Logs
```bash
# Docker
docker logs -f divino-mcp-server

# Local
npm run dev
```

### Metrics
Consider adding:
- Request count per tool
- Average response time
- Error rate
- Database query performance

## Next Steps

1. ✅ Basic MCP server implementation
2. ⏳ Add caching layer (Redis)
3. ⏳ Implement API key authentication
4. ⏳ Add rate limiting
5. ⏳ Implement vector search for semantic queries
6. ⏳ Add more specialized tools (reports, analytics)
7. ⏳ Implement WebSocket support for real-time updates

## License

Proprietary - Divino Lanches System

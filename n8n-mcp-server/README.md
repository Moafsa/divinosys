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
- **Dual Transport Support**: HTTP REST and Server Sent Events (SSE)

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
  "timestamp": "2025-01-08T10:30:00.000Z",
  "security": "enabled",
  "write_operations_protected": true
}
```

### GET /tools
List all available tools
```bash
curl http://localhost:3100/tools
```

### POST /execute (HTTP REST)
Execute a tool via HTTP REST
```bash
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: your_api_key" \
  -d '{
    "tool": "get_products",
    "parameters": {"limit": 5},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

### GET /sse (Server Sent Events)
Connect to SSE stream for real-time updates
```bash
curl -N http://localhost:3100/sse
```

This endpoint:
- Establishes a persistent connection using Server Sent Events
- Sends a heartbeat every 30 seconds to keep connection alive
- Returns connection status and available tools information

### POST /sse/execute (SSE Execute)
Execute a tool and get response via SSE
```bash
curl -X POST http://localhost:3100/sse/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: your_api_key" \
  -d '{
    "tool": "get_products",
    "parameters": {"limit": 5},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

**Note**: Both `/execute` (HTTP REST) and `/sse/execute` endpoints support the same tools and parameters. Choose based on your integration needs.

## Integration with n8n

### Method 1: Using MCP Client Node (Recommended)

The n8n MCP Client node supports **both** transport methods:

#### Option A: HTTP REST Transport
```
Endpoint: https://your-domain.com/execute
Server Transport: HTTP or REST
Authentication: Header Auth
  - Header Name: x-api-key
  - Header Value: your_mcp_api_key
```

#### Option B: Server Sent Events (SSE) Transport
```
Endpoint: https://your-domain.com/sse
Server Transport: Server Sent Events (SSE)
Authentication: Header Auth
  - Header Name: x-api-key
  - Header Value: your_mcp_api_key
```

**Both methods work identically** - choose based on your preference or n8n version compatibility.

### Method 2: Manual HTTP Request Node

If you prefer manual setup:

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

## Transport Methods Comparison

| Feature | HTTP REST (`/execute`) | SSE (`/sse`) |
|---------|------------------------|--------------|
| **Connection Type** | Request/Response | Persistent Stream |
| **Latency** | Standard | Low (persistent connection) |
| **Real-time Updates** | No | Yes (via heartbeats) |
| **Compatibility** | All n8n versions | n8n with SSE support |
| **Use Case** | Simple integrations | Real-time monitoring |
| **Recommended For** | Most scenarios | Advanced use cases |

**Recommendation**: Start with **HTTP REST** (`/execute`) for simplicity. Use **SSE** (`/sse`) if you need real-time capabilities or if required by your n8n configuration.

## Next Steps

1. ✅ Basic MCP server implementation
2. ✅ Server Sent Events (SSE) support
3. ✅ API key authentication for write operations
4. ⏳ Add caching layer (Redis)
5. ⏳ Add rate limiting
6. ⏳ Implement vector search for semantic queries
7. ⏳ Add more specialized tools (reports, analytics)

## License

Proprietary - Divino Lanches System

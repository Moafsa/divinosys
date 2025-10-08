# n8n Integration Setup Guide

This guide walks you through setting up the n8n integration with MCP server for Divino Lanches AI.

## Prerequisites

- Docker and Docker Compose installed
- n8n instance running (or will be set up)
- MCP Server deployed and accessible
- OpenAI API key

## Architecture Overview

```
┌─────────────────┐     ┌──────────────┐     ┌─────────────┐
│  Divino System  │────▶│  n8n Webhook │────▶│ MCP Server  │
│   (Frontend)    │     │   Workflow   │     │ (Database)  │
└─────────────────┘     └──────┬───────┘     └─────────────┘
                               │
                               ▼
                        ┌──────────────┐
                        │  OpenAI API  │
                        └──────────────┘
```

## Step 1: Deploy MCP Server

### Option A: Docker Compose (Recommended)

Add to your main `docker-compose.yml`:

```yaml
services:
  # ... existing services ...

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
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:3100/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 10s

  n8n:
    image: n8nio/n8n:latest
    container_name: divino-n8n
    ports:
      - "5678:5678"
    environment:
      - N8N_BASIC_AUTH_ACTIVE=true
      - N8N_BASIC_AUTH_USER=admin
      - N8N_BASIC_AUTH_PASSWORD=${N8N_PASSWORD}
      - N8N_HOST=${N8N_HOST:-localhost}
      - N8N_PORT=5678
      - N8N_PROTOCOL=http
      - NODE_ENV=production
      - WEBHOOK_URL=http://${N8N_HOST:-localhost}:5678/
      - GENERIC_TIMEZONE=America/Sao_Paulo
    volumes:
      - n8n_data:/home/node/.n8n
    restart: unless-stopped
    networks:
      - divino-network
    depends_on:
      - mcp-server

volumes:
  n8n_data:

networks:
  divino-network:
    driver: bridge
```

### Option B: Standalone Docker

```bash
cd n8n-mcp-server

# Build image
docker build -t divino-mcp-server .

# Run container
docker run -d \
  --name divino-mcp-server \
  -p 3100:3100 \
  -e DB_HOST=your_db_host \
  -e DB_PORT=5432 \
  -e DB_NAME=divino_lanches \
  -e DB_USER=postgres \
  -e DB_PASSWORD=your_password \
  --restart unless-stopped \
  divino-mcp-server
```

### Verify MCP Server

```bash
# Health check
curl http://localhost:3100/health

# List available tools
curl http://localhost:3100/tools

# Test query
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "get_products",
    "parameters": {"limit": 5},
    "context": {"tenant_id": 1, "filial_id": 1}
  }'
```

## Step 2: Setup n8n

### Deploy n8n

```bash
# Start services
docker-compose up -d n8n

# Access n8n
# Open http://localhost:5678 in browser
```

### Configure n8n Credentials

1. Login to n8n (admin / your_password)
2. Go to **Credentials** → **Add Credential**
3. Add **OpenAI** credential:
   - Name: `OpenAI API`
   - API Key: Your OpenAI API key
   - Save

## Step 3: Import Workflow

1. In n8n, go to **Workflows**
2. Click **Import from File**
3. Select `n8n-integration/workflow-example.json`
4. Click **Import**

### Configure Workflow Nodes

#### Webhook Node
- **Path**: `ai-chat`
- **Method**: `POST`
- Expected payload:
  ```json
  {
    "message": "Listar produtos",
    "tenant_id": 1,
    "filial_id": 1
  }
  ```

#### MCP Server HTTP Requests
- **URL**: `http://mcp-server:3100/execute`
  - If running locally: `http://localhost:3100/execute`
  - If on same Docker network: `http://mcp-server:3100/execute`

#### OpenAI Node
- **Credential**: Select the OpenAI credential you created
- **Model**: `gpt-4` (or `gpt-3.5-turbo` for faster/cheaper)

### Activate Workflow
1. Click **Active** toggle in top right
2. Note the webhook URL (will be something like `http://localhost:5678/webhook/ai-chat`)

## Step 4: Update Divino Lanches System

Update your AI chat handler to use n8n webhook instead of calling OpenAI directly.

### Modify `mvc/ajax/ai_chat.php`

```php
case 'send_message':
    $message = $_POST['message'] ?? '';
    
    if (empty($message)) {
        throw new Exception('Mensagem é obrigatória');
    }
    
    $session = \System\Session::getInstance();
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    // Call n8n webhook instead of OpenAI directly
    $n8nWebhook = getenv('N8N_WEBHOOK_URL') ?: 'http://localhost:5678/webhook/ai-chat';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $n8nWebhook,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'message' => $message,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Erro ao processar mensagem');
    }
    
    $data = json_decode($response, true);
    
    echo json_encode([
        'success' => true,
        'response' => $data['response']
    ]);
    break;
```

### Add to `.env`

```env
# n8n Integration
N8N_WEBHOOK_URL=http://localhost:5678/webhook/ai-chat
```

For production (Coolify):
```env
N8N_WEBHOOK_URL=https://your-n8n-domain.com/webhook/ai-chat
```

## Step 5: Test Integration

### Test from Terminal

```bash
# Test n8n webhook directly
curl -X POST http://localhost:5678/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Listar produtos de hamburguer",
    "tenant_id": 1,
    "filial_id": 1
  }'
```

### Test from Divino System

1. Open Divino Lanches dashboard
2. Open AI Chat
3. Send message: "Listar produtos"
4. Verify response

### Monitor n8n Workflow

1. Go to n8n → Workflows → Your workflow
2. Click on **Executions** tab
3. See real-time execution logs

## Step 6: Production Deployment (Coolify)

### Deploy MCP Server to Coolify

1. Push code to Git repository
2. In Coolify:
   - Create new service → Docker Compose
   - Point to repository
   - Set environment variables:
     - `DB_HOST`
     - `DB_PASSWORD`
     - etc.
   - Deploy

### Deploy n8n to Coolify

Option A: Use n8n Cloud (Recommended)
- Sign up at https://n8n.io
- Import workflow
- Configure webhook URL

Option B: Self-host on Coolify
- Create new service → Docker Image: `n8nio/n8n`
- Set environment variables
- Configure domain and SSL
- Deploy

### Update Webhook URL

Update `.env` or Coolify environment:
```env
N8N_WEBHOOK_URL=https://n8n.yourdomain.com/webhook/ai-chat
```

## Advanced Configuration

### Add Caching

Add Redis for caching frequent queries:

```yaml
services:
  redis:
    image: redis:7-alpine
    container_name: divino-redis
    ports:
      - "6379:6379"
    restart: unless-stopped
    networks:
      - divino-network
```

Update MCP server to use Redis cache.

### Add Rate Limiting

Protect your endpoints with rate limiting using nginx or API gateway.

### Add Authentication

Add API key authentication to MCP server:

```javascript
// In server.js
app.use((req, res, next) => {
  const apiKey = req.headers['x-api-key'];
  if (!apiKey || apiKey !== process.env.API_KEY) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
});
```

### Vector Search Integration

For semantic search, integrate Pinecone or pgvector:

```javascript
// Add to MCP server
async function semanticSearch(query, tenantId, filialId) {
  // Use embeddings + vector database
  const embedding = await getEmbedding(query);
  const results = await vectorDB.search(embedding, tenantId, filialId);
  return results;
}
```

## Monitoring & Observability

### MCP Server Logs

```bash
# Docker logs
docker logs -f divino-mcp-server

# Filter errors
docker logs divino-mcp-server 2>&1 | grep ERROR
```

### n8n Execution Logs

Check in n8n UI → Executions tab

### Performance Metrics

Add Prometheus + Grafana for monitoring:
- Request rate
- Response time
- Error rate
- Database query performance

## Troubleshooting

### MCP Server Not Responding

```bash
# Check if running
docker ps | grep mcp-server

# Check logs
docker logs divino-mcp-server

# Test health
curl http://localhost:3100/health
```

### n8n Webhook Timeout

- Increase timeout in workflow HTTP Request nodes
- Optimize database queries
- Add caching

### Database Connection Errors

- Verify database credentials
- Check network connectivity
- Verify connection pool settings

### OpenAI API Errors

- Check API key validity
- Verify rate limits
- Check quota/billing

## Cost Optimization

### Token Usage

With MCP architecture:
- **Before**: ~2000 tokens per request (sending all data)
- **After**: ~500 tokens per request (only relevant data)
- **Savings**: 75% reduction in API costs

### Database Queries

- Add proper indexes
- Implement query result caching
- Use connection pooling

## Next Steps

1. ✅ Basic MCP + n8n integration
2. ⏳ Add Redis caching layer
3. ⏳ Implement semantic search with embeddings
4. ⏳ Add monitoring and alerting
5. ⏳ Implement A/B testing for prompts
6. ⏳ Add conversation history tracking
7. ⏳ Multi-language support

## Support

For issues or questions:
1. Check MCP server logs
2. Check n8n execution logs
3. Verify network connectivity
4. Test each component independently

---

**Version**: 1.0  
**Last Updated**: January 2025

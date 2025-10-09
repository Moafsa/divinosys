const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const { Pool } = require('pg');
require('dotenv').config();

const app = express();
const PORT = process.env.MCP_PORT || 3100;

// Middleware
app.use(helmet());
app.use(cors());
app.use(express.json());

// PostgreSQL connection pool
const pool = new Pool({
  host: process.env.DB_HOST || 'localhost',
  port: process.env.DB_PORT || 5432,
  database: process.env.DB_NAME || 'divino_lanches',
  user: process.env.DB_USER || 'postgres',
  password: process.env.DB_PASSWORD,
  max: 20,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
});

// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Test endpoint for GET requests
app.get('/execute', (req, res) => {
  res.json({
    error: 'This endpoint requires POST method',
    message: 'Use POST with JSON body containing: { "tool": "tool_name", "parameters": {}, "context": { "tenant_id": 1, "filial_id": 1 } }',
    example: {
      tool: 'get_products',
      parameters: { limit: 5 },
      context: { tenant_id: 1, filial_id: 1 }
    }
  });
});

// MCP Protocol endpoint - List available tools
app.get('/tools', (req, res) => {
  res.json({
    tools: [
      {
        name: 'get_products',
        description: 'Get products list with optional filters',
        parameters: {
          query: 'Optional search term',
          category_id: 'Optional category filter',
          limit: 'Maximum number of results (default: 20)'
        }
      },
      {
        name: 'get_ingredients',
        description: 'Get ingredients list with optional type filter',
        parameters: {
          tipo: 'Optional type filter (proteina, vegetal, molho, etc)',
          limit: 'Maximum number of results (default: 50)'
        }
      },
      {
        name: 'get_categories',
        description: 'Get all categories',
        parameters: {}
      },
      {
        name: 'get_orders',
        description: 'Get orders with optional filters',
        parameters: {
          status: 'Optional status filter (Pendente, Preparando, Pronto, Finalizado)',
          mesa_id: 'Optional table ID filter',
          limit: 'Maximum number of results (default: 10)'
        }
      },
      {
        name: 'get_tables',
        description: 'Get tables with optional status filter',
        parameters: {
          status: 'Optional status filter (disponivel, ocupada, reservada)'
        }
      },
      {
        name: 'search_products',
        description: 'Search products by name or description',
        parameters: {
          term: 'Search term (required)',
          limit: 'Maximum number of results (default: 10)'
        }
      },
      {
        name: 'get_product_details',
        description: 'Get detailed information about a specific product',
        parameters: {
          product_id: 'Product ID (required)'
        }
      },
      {
        name: 'get_order_details',
        description: 'Get detailed information about a specific order',
        parameters: {
          order_id: 'Order ID (required)'
        }
      }
    ]
  });
});

// Execute MCP tool
app.post('/execute', async (req, res) => {
  const { tool, parameters, context } = req.body;
  
  if (!tool) {
    return res.status(400).json({ error: 'Tool name is required' });
  }

  const tenantId = context?.tenant_id || 1;
  const filialId = context?.filial_id || 1;

  try {
    let result;
    
    switch (tool) {
      case 'get_products':
        result = await getProducts(parameters, tenantId, filialId);
        break;
      case 'get_ingredients':
        result = await getIngredients(parameters, tenantId, filialId);
        break;
      case 'get_categories':
        result = await getCategories(tenantId, filialId);
        break;
      case 'get_orders':
        result = await getOrders(parameters, tenantId, filialId);
        break;
      case 'get_tables':
        result = await getTables(parameters, tenantId, filialId);
        break;
      case 'search_products':
        result = await searchProducts(parameters, tenantId, filialId);
        break;
      case 'get_product_details':
        result = await getProductDetails(parameters, tenantId, filialId);
        break;
      case 'get_order_details':
        result = await getOrderDetails(parameters, tenantId, filialId);
        break;
      default:
        return res.status(400).json({ error: `Unknown tool: ${tool}` });
    }

    res.json({
      success: true,
      tool,
      result,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error(`Error executing tool ${tool}:`, error);
    res.status(500).json({
      success: false,
      error: error.message,
      tool
    });
  }
});

// ============ Database Query Functions ============

async function getProducts(params = {}, tenantId, filialId) {
  const { query, category_id, limit = 20 } = params;
  
  let sql = `
    SELECT p.id, p.nome, p.preco_normal, p.preco_mini, p.descricao,
           c.nome as categoria, p.disponivel
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.tenant_id = $1 AND p.filial_id = $2
  `;
  
  const queryParams = [tenantId, filialId];
  let paramIndex = 3;
  
  if (query) {
    sql += ` AND (LOWER(p.nome) LIKE LOWER($${paramIndex}) OR LOWER(p.descricao) LIKE LOWER($${paramIndex}))`;
    queryParams.push(`%${query}%`);
    paramIndex++;
  }
  
  if (category_id) {
    sql += ` AND p.categoria_id = $${paramIndex}`;
    queryParams.push(category_id);
    paramIndex++;
  }
  
  sql += ` ORDER BY p.nome LIMIT $${paramIndex}`;
  queryParams.push(limit);
  
  const result = await pool.query(sql, queryParams);
  return {
    count: result.rows.length,
    products: result.rows
  };
}

async function getIngredients(params = {}, tenantId, filialId) {
  const { tipo, limit = 50 } = params;
  
  let sql = `
    SELECT id, nome, tipo, preco_adicional, disponivel
    FROM ingredientes
    WHERE tenant_id = $1 AND filial_id = $2
  `;
  
  const queryParams = [tenantId, filialId];
  let paramIndex = 3;
  
  if (tipo) {
    sql += ` AND tipo = $${paramIndex}`;
    queryParams.push(tipo);
    paramIndex++;
  }
  
  sql += ` ORDER BY tipo, nome LIMIT $${paramIndex}`;
  queryParams.push(limit);
  
  const result = await pool.query(sql, queryParams);
  return {
    count: result.rows.length,
    ingredients: result.rows
  };
}

async function getCategories(tenantId, filialId) {
  const sql = `
    SELECT id, nome
    FROM categorias
    WHERE tenant_id = $1 AND filial_id = $2
    ORDER BY nome
  `;
  
  const result = await pool.query(sql, [tenantId, filialId]);
  return {
    count: result.rows.length,
    categories: result.rows
  };
}

async function getOrders(params = {}, tenantId, filialId) {
  const { status, mesa_id, limit = 10 } = params;
  
  let sql = `
    SELECT idpedido, idmesa, cliente, status, valor_total, 
           data, hora_pedido, observacao, delivery
    FROM pedido
    WHERE tenant_id = $1 AND filial_id = $2
  `;
  
  const queryParams = [tenantId, filialId];
  let paramIndex = 3;
  
  if (status) {
    sql += ` AND status = $${paramIndex}`;
    queryParams.push(status);
    paramIndex++;
  }
  
  if (mesa_id) {
    sql += ` AND idmesa = $${paramIndex}`;
    queryParams.push(mesa_id);
    paramIndex++;
  }
  
  sql += ` ORDER BY data DESC, hora_pedido DESC LIMIT $${paramIndex}`;
  queryParams.push(limit);
  
  const result = await pool.query(sql, queryParams);
  return {
    count: result.rows.length,
    orders: result.rows
  };
}

async function getTables(params = {}, tenantId, filialId) {
  const { status } = params;
  
  let sql = `
    SELECT id_mesa, status
    FROM mesas
    WHERE tenant_id = $1 AND filial_id = $2
  `;
  
  const queryParams = [tenantId, filialId];
  
  if (status) {
    sql += ` AND status = $3`;
    queryParams.push(status);
  }
  
  sql += ` ORDER BY id_mesa::integer`;
  
  const result = await pool.query(sql, queryParams);
  return {
    count: result.rows.length,
    tables: result.rows
  };
}

async function searchProducts(params = {}, tenantId, filialId) {
  const { term, limit = 10 } = params;
  
  if (!term) {
    throw new Error('Search term is required');
  }
  
  const sql = `
    SELECT p.id, p.nome, p.preco_normal, p.descricao,
           c.nome as categoria, p.disponivel
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.tenant_id = $1 AND p.filial_id = $2
    AND (
      LOWER(p.nome) LIKE LOWER($3) 
      OR LOWER(p.descricao) LIKE LOWER($3)
    )
    ORDER BY 
      CASE 
        WHEN LOWER(p.nome) LIKE LOWER($4) THEN 1
        ELSE 2
      END,
      p.nome
    LIMIT $5
  `;
  
  const result = await pool.query(sql, [
    tenantId, 
    filialId, 
    `%${term}%`,
    `${term}%`,
    limit
  ]);
  
  return {
    count: result.rows.length,
    search_term: term,
    products: result.rows
  };
}

async function getProductDetails(params = {}, tenantId, filialId) {
  const { product_id } = params;
  
  if (!product_id) {
    throw new Error('Product ID is required');
  }
  
  // Get product basic info
  const productSql = `
    SELECT p.*, c.nome as categoria
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.id = $1 AND p.tenant_id = $2 AND p.filial_id = $3
  `;
  
  const productResult = await pool.query(productSql, [product_id, tenantId, filialId]);
  
  if (productResult.rows.length === 0) {
    throw new Error('Product not found');
  }
  
  // Get product ingredients
  const ingredientsSql = `
    SELECT i.id, i.nome, i.tipo, i.preco_adicional
    FROM produto_ingredientes pi
    JOIN ingredientes i ON pi.ingrediente_id = i.id
    WHERE pi.produto_id = $1
    ORDER BY i.tipo, i.nome
  `;
  
  const ingredientsResult = await pool.query(ingredientsSql, [product_id]);
  
  return {
    product: productResult.rows[0],
    ingredients: ingredientsResult.rows
  };
}

async function getOrderDetails(params = {}, tenantId, filialId) {
  const { order_id } = params;
  
  if (!order_id) {
    throw new Error('Order ID is required');
  }
  
  // Get order basic info
  const orderSql = `
    SELECT *
    FROM pedido
    WHERE idpedido = $1 AND tenant_id = $2 AND filial_id = $3
  `;
  
  const orderResult = await pool.query(orderSql, [order_id, tenantId, filialId]);
  
  if (orderResult.rows.length === 0) {
    throw new Error('Order not found');
  }
  
  // Get order items
  const itemsSql = `
    SELECT pi.*, p.nome as produto_nome
    FROM pedido_itens pi
    JOIN produtos p ON pi.produto_id = p.id
    WHERE pi.pedido_id = $1
    ORDER BY pi.id
  `;
  
  const itemsResult = await pool.query(itemsSql, [order_id]);
  
  return {
    order: orderResult.rows[0],
    items: itemsResult.rows,
    total_items: itemsResult.rows.length
  };
}

// Start server
app.listen(PORT, () => {
  console.log(`🚀 Divino Lanches MCP Server running on port ${PORT}`);
  console.log(`📊 Health check: http://localhost:${PORT}/health`);
  console.log(`🔧 Tools endpoint: http://localhost:${PORT}/tools`);
});

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM signal received: closing HTTP server');
  await pool.end();
  process.exit(0);
});

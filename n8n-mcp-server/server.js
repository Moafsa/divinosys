const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const { Pool } = require('pg');
require('dotenv').config();

const app = express();
const PORT = process.env.MCP_PORT || 3100;
const API_KEY = process.env.MCP_API_KEY;

// Validate MCP API Key
if (!API_KEY) {
  console.error('❌ Missing required MCP_API_KEY environment variable');
  console.error('   Set MCP_API_KEY in your .env file');
  process.exit(1);
}

// Middleware
app.use(helmet());
app.use(cors());
app.use(express.json());

// Middleware de autenticação para operações de escrita
app.use((req, res, next) => {
  if (req.method === 'POST' && req.body.tool) {
    const writeOperations = [
      'create_product', 'update_product', 'delete_product',
      'create_ingredient', 'update_ingredient', 'delete_ingredient',
      'create_category', 'update_category', 'delete_category',
      'create_financial_entry', 'update_order_status', 'create_payment'
    ];
    
    if (writeOperations.includes(req.body.tool)) {
      const apiKey = req.headers['x-api-key'];
      
      if (!apiKey || apiKey !== API_KEY) {
        return res.status(401).json({ 
          error: 'Unauthorized - API key required for write operations',
          required_operations: writeOperations,
          message: 'Include x-api-key header with valid API key'
        });
      }
    }
  }
  next();
});

// Validate required environment variables
if (!process.env.DB_HOST || !process.env.DB_PORT || !process.env.DB_NAME || !process.env.DB_USER || !process.env.DB_PASSWORD) {
  console.error('❌ Missing required database environment variables:');
  console.error('   DB_HOST:', process.env.DB_HOST ? '✓' : '❌ MISSING');
  console.error('   DB_PORT:', process.env.DB_PORT ? '✓' : '❌ MISSING');
  console.error('   DB_NAME:', process.env.DB_NAME ? '✓' : '❌ MISSING');
  console.error('   DB_USER:', process.env.DB_USER ? '✓' : '❌ MISSING');
  console.error('   DB_PASSWORD:', process.env.DB_PASSWORD ? '✓' : '❌ MISSING');
  process.exit(1);
}

// PostgreSQL connection pool
const pool = new Pool({
  host: process.env.DB_HOST,
  port: process.env.DB_PORT,
  database: process.env.DB_NAME,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  max: 20,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
});

// Health check
app.get('/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    timestamp: new Date().toISOString(),
    security: 'enabled',
    write_operations_protected: true
  });
});

// Test endpoint for GET requests
app.get('/execute', (req, res) => {
  res.json({
    error: 'This endpoint requires POST method',
    message: 'Use POST with JSON body containing: { "tool": "tool_name", "таrameters": {}, "context": { "tenant_id": "from_session", "filial_id": "from_session" } }',
    example: {
      tool: 'get_products',
      parameters: { limit: 5 },
      context: { tenant_id: '$session->getTenantId()', filial_id: '$session->getFilialId()' }
    },
    note: 'tenant_id and filial_id come from PHP session: $session->getTenantId() and $session->getFilialId()'
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

  const tenantId = context?.tenant_id;
  const filialId = context?.filial_id;
  
  if (!tenantId || !filialId) {
    return res.status(400).json({ 
      error: 'tenant_id and filial_id are required in context',
      message: 'Multi-tenant system requires tenant_id and filial_id from user session'
    });
  }

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
        
      // Operações de escrita (requerem autenticação)
      case 'create_product':
        result = await createProduct(parameters, tenantId, filialId);
        break;
      case 'update_product':
        result = await updateProduct(parameters, tenantId, filialId);
        break;
      case 'delete_product':
        result = await deleteProduct(parameters, tenantId, filialId);
        break;
      case 'create_ingredient':
        result = await createIngredient(parameters, tenantId, filialId);
        break;
      case 'update_ingredient':
        result = await updateIngredient(parameters, tenantId, filialId);
        break;
      case 'delete_ingredient':
        result = await deleteIngredient(parameters, tenantId, filialId);
        break;
      case 'create_category':
        result = await createCategory(parameters, tenantId, filialId);
        break;
      case 'update_category':
        result = await updateCategory(parameters, tenantId, filialId);
        break;
      case 'delete_category':
        result = await deleteCategory(parameters, tenantId, filialId);
        break;
      case 'create_financial_entry':
        result = await createFinancialEntry(parameters, tenantId, filialId);
        break;
      case 'update_order_status':
        result = await updateOrderStatus(parameters, tenantId, filialId);
        break;
      case 'create_payment':
        result = await createPayment(parameters, tenantId, filialId);
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

// ============ Operações de Escrita ============

async function createProduct(params, tenantId, filialId) {
  const { nome, categoria_id, preco_normal, preco_mini, descricao, disponivel } = params;
  
  if (!nome || !categoria_id || !preco_normal) {
    throw new Error('Nome, categoria_id e preco_normal são obrigatórios');
  }
  
  const sql = `
    INSERT INTO produtos (nome, categoria_id, preco_normal, preco_mini, descricao, disponivel, tenant_id, filial_id)
    VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
    RETURNING id, nome, preco_normal, preco_mini, descricao, disponivel
  `;
  
  const result = await pool.query(sql, [
    nome, categoria_id, preco_normal, preco_mini || null, descricao || '', 
    disponivel !== false, tenantId, filialId
  ]);
  
  return {
    success: true,
    message: 'Produto criado com sucesso!',
    product: result.rows[0]
  };
}

async function updateProduct(params, tenantId, filialId) {
  const { id, nome, categoria_id, preco_normal, preco_mini, descricao, disponivel } = params;
  
  if (!id) {
    throw new Error('ID do produto é obrigatório');
  }
  
  const updateFields = [];
  const values = [];
  let paramIndex = 1;
  
  if (nome !== undefined) {
    updateFields.push(`nome = $${paramIndex++}`);
    values.push(nome);
  }
  if (categoria_id !== undefined) {
    updateFields.push(`categoria_id = $${paramIndex++}`);
    values.push(categoria_id);
  }
  if (preco_normal !== undefined) {
    updateFields.push(`preco_normal = $${paramIndex++}`);
    values.push(preco_normal);
  }
  if (preco_mini !== undefined) {
    updateFields.push(`preco_mini = $${paramIndex++}`);
    values.push(preco_mini);
  }
  if (descricao !== undefined) {
    updateFields.push(`descricao = $${paramIndex++}`);
    values.push(descricao);
  }
  if (disponivel !== undefined) {
    updateFields.push(`disponivel = $${paramIndex++}`);
    values.push(disponivel);
  }
  
  if (updateFields.length === 0) {
    throw new Error('Pelo menos um campo deve ser fornecido para atualização');
  }
  
  values.push(id, tenantId, filialId);
  
  const sql = `
    UPDATE produtos 
    SET ${updateFields.join(', ')}
    WHERE id = $${paramIndex++} AND tenant_id = $${paramIndex++} AND filial_id = $${paramIndex++}
    RETURNING id, nome, preco_normal, preco_mini, descricao, disponivel
  `;
  
  const result = await pool.query(sql, values);
  
  if (result.rows.length === 0) {
    throw new Error('Produto não encontrado ou sem permissão para editar');
  }
  
  return {
    success: true,
    message: 'Produto atualizado com sucesso!',
    product: result.rows[0]
  };
}

async function deleteProduct(params, tenantId, filialId) {
  const { id } = params;
  
  if (!id) {
    throw new Error('ID do produto é obrigatório');
  }
  
  const sql = `
    DELETE FROM produtos 
    WHERE id = $1 AND tenant_id = $2 AND filial_id = $3
    RETURNING id, nome
  `;
  
  const result = await pool.query(sql, [id, tenantId, filialId]);
  
  if (result.rows.length === 0) {
    throw new Error('Produto não encontrado ou sem permissão para excluir');
  }
  
  return {
    success: true,
    message: 'Produto excluído com sucesso!',
    deleted_product: result.rows[0]
  };
}

async function createIngredient(params, tenantId, filialId) {
  const { nome, tipo, preco_adicional, disponivel } = params;
  
  if (!nome) {
    throw new Error('Nome do ingrediente é obrigatório');
  }
  
  const sql = `
    INSERT INTO ingredientes (nome, tipo, preco_adicional, disponivel, tenant_id, filial_id)
    VALUES ($1, $2, $3, $4, $5, $6)
    RETURNING id, nome, tipo, preco_adicional, disponivel
  `;
  
  const result = await pool.query(sql, [
    nome, tipo || 'complemento', preco_adicional || 0, disponivel !== false, tenantId, filialId
  ]);
  
  return {
    success: true,
    message: 'Ingrediente criado com sucesso!',
    ingredient: result.rows[0]
  };
}

async function updateIngredient(params, tenantId, filialId) {
  const { id, nome, tipo, preco_adicional, disponivel } = params;
  
  if (!id) {
    throw new Error('ID do ingrediente é obrigatório');
  }
  
  const updateFields = [];
  const values = [];
  let paramIndex = 1;
  
  if (nome !== undefined) {
    updateFields.push(`nome = $${paramIndex++}`);
    values.push(nome);
  }
  if (tipo !== undefined) {
    updateFields.push(`tipo = $${paramIndex++}`);
    values.push(tipo);
  }
  if (preco_adicional !== undefined) {
    updateFields.push(`preco_adicional = $${paramIndex++}`);
    values.push(preco_adicional);
  }
  if (disponivel !== undefined) {
    updateFields.push(`disponivel = $${paramIndex++}`);
    values.push(disponivel);
  }
  
  if (updateFields.length === 0) {
    throw new Error('Pelo menos um campo deve ser fornecido para atualização');
  }
  
  values.push(id, tenantId, filialId);
  
  const sql = `
    UPDATE ingredientes 
    SET ${updateFields.join(', ')}
    WHERE id = $${paramIndex++} AND tenant_id = $${paramIndex++} AND filial_id = $${paramIndex++}
    RETURNING id, nome, tipo, preco_adicional, disponivel
  `;
  
  const result = await pool.query(sql, values);
  
  if (result.rows.length === 0) {
    throw new Error('Ingrediente não encontrado ou sem permissão para editar');
  }
  
  return {
    success: true,
    message: 'Ingrediente atualizado com sucesso!',
    ingredient: result.rows[0]
  };
}

async function deleteIngredient(params, tenantId, filialId) {
  const { id } = params;
  
  if (!id) {
    throw new Error('ID do ingrediente é obrigatório');
  }
  
  const sql = `
    DELETE FROM ingredientes 
    WHERE id = $1 AND tenant_id = $2 AND filial_id = $3
    RETURNING id, nome
  `;
  
  const result = await pool.query(sql, [id, tenantId, filialId]);
  
  if (result.rows.length === 0) {
    throw new Error('Ingrediente não encontrado ou sem permissão para excluir');
  }
  
  return {
    success: true,
    message: 'Ingrediente excluído com sucesso!',
    deleted_ingredient: result.rows[0]
  };
}

async function createCategory(params, tenantId, filialId) {
  const { nome } = params;
  
  if (!nome) {
    throw new Error('Nome da categoria é obrigatório');
  }
  
  const sql = `
    INSERT INTO categorias (nome, tenant_id, filial_id)
    VALUES ($1, $2, $3)
    RETURNING id, nome
  `;
  
  const result = await pool.query(sql, [nome, tenantId, filialId]);
  
  return {
    success: true,
    message: 'Categoria criada com sucesso!',
    category: result.rows[0]
  };
}

async function updateCategory(params, tenantId, filialId) {
  const { id, nome } = params;
  
  if (!id || !nome) {
    throw new Error('ID e nome da categoria são obrigatórios');
  }
  
  const sql = `
    UPDATE categorias 
    SET nome = $1
    WHERE id = $2 AND tenant_id = $3 AND filial_id = $4
    RETURNING id, nome
  `;
  
  const result = await pool.query(sql, [nome, id, tenantId, filialId]);
  
  if (result.rows.length === 0) {
    throw new Error('Categoria não encontrada ou sem permissão para editar');
  }
  
  return {
    success: true,
    message: 'Categoria atualizada com sucesso!',
    category: result.rows[0]
  };
}

async function deleteCategory(params, tenantId, filialId) {
  const { id } = params;
  
  if (!id) {
    throw new Error('ID da categoria é obrigatório');
  }
  
  // Verificar se há produtos usando esta categoria
  const checkSql = `
    SELECT COUNT(*) as count 
    FROM produtos 
    WHERE categoria_id = $1 AND tenant_id = $2 AND filial_id = $3
  `;
  
  const checkResult = await pool.query(checkSql, [id, tenantId, filialId]);
  
  if (parseInt(checkResult.rows[0].count) > 0) {
    throw new Error('Não é possível excluir categoria que possui produtos associados');
  }
  
  const sql = `
    DELETE FROM categorias 
    WHERE id = $1 AND tenant_id = $2 AND filial_id = $3
    RETURNING id, nome
  `;
  
  const result = await pool.query(sql, [id, tenantId, filialId]);
  
  if (result.rows.length === 0) {
    throw new Error('Categoria não encontrada ou sem permissão para excluir');
  }
  
  return {
    success: true,
    message: 'Categoria excluída com sucesso!',
    deleted_category: result.rows[0]
  };
}

async function createFinancialEntry(params, tenantId, filialId) {
  const { tipo, valor, descricao, categoria } = params;
  
  if (!tipo || !valor || !descricao) {
    throw new Error('Tipo, valor e descrição são obrigatórios');
  }
  
  const sql = `
    INSERT INTO lancamentos_financeiros (tipo, valor, descricao, categoria, data, tenant_id, filial_id)
    VALUES ($1, $2, $3, $4, NOW(), $5, $6)
    RETURNING id, tipo, valor, descricao, categoria, data
  `;
  
  const result = await pool.query(sql, [
    tipo, valor, descricao, categoria || 'outros', tenantId, filialId
  ]);
  
  return {
    success: true,
    message: 'Lançamento financeiro criado com sucesso!',
    entry: result.rows[0]
  };
}

async function updateOrderStatus(params, tenantId, filialId) {
  const { order_id, status } = params;
  
  if (!order_id || !status) {
    throw new Error('ID do pedido e status são obrigatórios');
  }
  
  const validStatuses = ['Pendente', 'Preparando', 'Pronto', 'Finalizado', 'Cancelado'];
  if (!validStatuses.includes(status)) {
    throw new Error(`Status inválido. Use um dos seguintes: ${validStatuses.join(', ')}`);
  }
  
  const sql = `
    UPDATE pedido 
    SET status = $1
    WHERE idpedido = $2 AND tenant_id = $3 AND filial_id = $4
    RETURNING idpedido, status, valor_total, cliente
  `;
  
  const result = await pool.query(sql, [status, order_id, tenantId, filialId]);
  
  if (result.rows.length === 0) {
    throw new Error('Pedido não encontrado ou sem permissão para editar');
  }
  
  return {
    success: true,
    message: 'Status do pedido atualizado com sucesso!',
    order: result.rows[0]
  };
}

async function createPayment(params, tenantId, filialId) {
  const { pedido_id, valor, metodo_pagamento, observacao } = params;
  
  if (!pedido_id || !valor || !metodo_pagamento) {
    throw new Error('ID do pedido, valor e método de pagamento são obrigatórios');
  }
  
  const sql = `
    INSERT INTO pagamentos (pedido_id, valor, metodo_pagamento, observacao, data_pagamento, tenant_id, filial_id)
    VALUES ($1, $2, $3, $4, NOW(), $5, $6)
    RETURNING id, pedido_id, valor, metodo_pagamento, observacao, data_pagamento
  `;
  
  const result = await pool.query(sql, [
    pedido_id, valor, metodo_pagamento, observacao || '', tenantId, filialId
  ]);
  
  return {
    success: true,
    message: 'Pagamento registrado com sucesso!',
    payment: result.rows[0]
  };
}

// Start server
app.listen(PORT, () => {
  console.log(`🚀 Divino Lanches MCP Server running on port ${PORT}`);
  console.log(`🔒 Security enabled for write operations`);
  console.log(`📊 Health check: http://localhost:${PORT}/health`);
  console.log(`🔧 Tools endpoint: http://localhost:${PORT}/tools`);
});

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM signal received: closing HTTP server');
  await pool.end();
  process.exit(0);
});

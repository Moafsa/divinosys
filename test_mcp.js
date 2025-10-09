const axios = require('axios');

// Test MCP Server
async function testMCP() {
  const baseUrl = 'https://mcp.conext.click';
  
  console.log('🧪 Testing MCP Server...\n');
  
  try {
    // 1. Test health endpoint
    console.log('1️⃣ Testing /health...');
    const healthResponse = await axios.get(`${baseUrl}/health`);
    console.log('✅ Health:', healthResponse.data);
    console.log('');
    
    // 2. Test tools endpoint
    console.log('2️⃣ Testing /tools...');
    const toolsResponse = await axios.get(`${baseUrl}/tools`);
    console.log('✅ Tools available:', toolsResponse.data.tools.length);
    console.log('Tools:', toolsResponse.data.tools.map(t => t.name).join(', '));
    console.log('');
    
    // 3. Test execute endpoint with GET (should show error message)
    console.log('3️⃣ Testing /execute with GET...');
    try {
      const executeGetResponse = await axios.get(`${baseUrl}/execute`);
      console.log('✅ GET /execute response:', executeGetResponse.data);
    } catch (error) {
      console.log('❌ GET /execute error:', error.response?.data || error.message);
    }
    console.log('');
    
    // 4. Test execute endpoint with POST
    console.log('4️⃣ Testing /execute with POST...');
    const executeData = {
      tool: 'get_products',
      parameters: { limit: 3 },
      context: { tenant_id: 1, filial_id: 1 }
    };
    
    const executeResponse = await axios.post(`${baseUrl}/execute`, executeData);
    console.log('✅ POST /execute response:', JSON.stringify(executeResponse.data, null, 2));
    console.log('');
    
    // 5. Test another tool
    console.log('5️⃣ Testing get_categories...');
    const categoriesData = {
      tool: 'get_categories',
      parameters: {},
      context: { tenant_id: 1, filial_id: 1 }
    };
    
    const categoriesResponse = await axios.post(`${baseUrl}/execute`, categoriesData);
    console.log('✅ Categories response:', JSON.stringify(categoriesResponse.data, null, 2));
    
  } catch (error) {
    console.error('❌ Error testing MCP:', error.response?.data || error.message);
  }
}

// Run test
testMCP();

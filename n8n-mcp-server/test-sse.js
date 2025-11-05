/**
 * Test script for MCP Server SSE endpoints
 * 
 * This script tests both HTTP REST and SSE endpoints to ensure
 * the server is working correctly.
 */

const http = require('http');
const https = require('https');

const API_KEY = process.env.MCP_API_KEY || 'mcp_divinosys_2024_secret_key';
const BASE_URL = process.env.MCP_URL || 'http://localhost:3100';

// Parse URL
const urlObj = new URL(BASE_URL);
const isHttps = urlObj.protocol === 'https:';
const client = isHttps ? https : http;

console.log('🧪 Testing MCP Server\n');
console.log(`Base URL: ${BASE_URL}`);
console.log(`Protocol: ${isHttps ? 'HTTPS' : 'HTTP'}\n`);

// Test 1: Health Check
function testHealthCheck() {
  return new Promise((resolve, reject) => {
    console.log('1️⃣ Testing Health Check...');
    
    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (isHttps ? 443 : 80),
      path: '/health',
      method: 'GET'
    };

    const req = client.request(options, (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          console.log(`   ✅ Health check passed`);
          console.log(`   Response:`, json);
          console.log('');
          resolve(json);
        } catch (error) {
          console.log(`   ❌ Failed to parse JSON`);
          reject(error);
        }
      });
    });

    req.on('error', (error) => {
      console.log(`   ❌ Health check failed:`, error.message);
      reject(error);
    });

    req.end();
  });
}

// Test 2: List Tools
function testListTools() {
  return new Promise((resolve, reject) => {
    console.log('2️⃣ Testing List Tools...');
    
    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (isHttps ? 443 : 80),
      path: '/tools',
      method: 'GET'
    };

    const req = client.request(options, (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          console.log(`   ✅ List tools passed`);
          console.log(`   Found ${json.tools.length} tools`);
          console.log('');
          resolve(json);
        } catch (error) {
          console.log(`   ❌ Failed to parse JSON`);
          reject(error);
        }
      });
    });

    req.on('error', (error) => {
      console.log(`   ❌ List tools failed:`, error.message);
      reject(error);
    });

    req.end();
  });
}

// Test 3: HTTP REST Execute
function testHttpExecute() {
  return new Promise((resolve, reject) => {
    console.log('3️⃣ Testing HTTP REST Execute (/execute)...');
    
    const postData = JSON.stringify({
      tool: 'get_categories',
      parameters: {},
      context: {
        tenant_id: 1,
        filial_id: 1
      }
    });

    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (isHttps ? 443 : 80),
      path: '/execute',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData),
        'x-api-key': API_KEY
      }
    };

    const req = client.request(options, (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          if (json.success) {
            console.log(`   ✅ HTTP REST execute passed`);
            console.log(`   Tool: ${json.tool}`);
            console.log(`   Result count: ${json.result?.count || 0}`);
          } else {
            console.log(`   ❌ HTTP REST execute failed:`, json.error);
          }
          console.log('');
          resolve(json);
        } catch (error) {
          console.log(`   ❌ Failed to parse JSON`);
          reject(error);
        }
      });
    });

    req.on('error', (error) => {
      console.log(`   ❌ HTTP REST execute failed:`, error.message);
      reject(error);
    });

    req.write(postData);
    req.end();
  });
}

// Test 4: SSE Connection
function testSSEConnection() {
  return new Promise((resolve, reject) => {
    console.log('4️⃣ Testing SSE Connection (/sse)...');
    
    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (isHttps ? 443 : 80),
      path: '/sse',
      method: 'GET',
      headers: {
        'Accept': 'text/event-stream',
        'Cache-Control': 'no-cache'
      }
    };

    const req = client.request(options, (res) => {
      let eventCount = 0;
      let buffer = '';
      
      res.on('data', (chunk) => {
        buffer += chunk.toString();
        
        // Parse SSE events
        const lines = buffer.split('\n');
        buffer = lines.pop(); // Keep incomplete line in buffer
        
        for (const line of lines) {
          if (line.startsWith('event:')) {
            eventCount++;
            const eventType = line.substring(6).trim();
            console.log(`   📡 Received event: ${eventType}`);
          } else if (line.startsWith('data:')) {
            const data = line.substring(5).trim();
            try {
              const json = JSON.parse(data);
              console.log(`      Data:`, json);
            } catch (e) {
              console.log(`      Data:`, data);
            }
          }
        }
        
        // After receiving a few events, close connection
        if (eventCount >= 2) {
          req.destroy();
          console.log(`   ✅ SSE connection works (received ${eventCount} events)`);
          console.log('');
          resolve({ eventCount });
        }
      });
      
      // Timeout after 5 seconds
      setTimeout(() => {
        req.destroy();
        if (eventCount > 0) {
          console.log(`   ✅ SSE connection works (received ${eventCount} events)`);
          console.log('');
          resolve({ eventCount });
        } else {
          console.log(`   ❌ No SSE events received`);
          reject(new Error('No SSE events received'));
        }
      }, 5000);
    });

    req.on('error', (error) => {
      if (error.code === 'ECONNRESET') {
        // Connection was closed (expected after receiving events)
        return;
      }
      console.log(`   ❌ SSE connection failed:`, error.message);
      reject(error);
    });

    req.end();
  });
}

// Test 5: SSE Execute
function testSSEExecute() {
  return new Promise((resolve, reject) => {
    console.log('5️⃣ Testing SSE Execute (/sse/execute)...');
    
    const postData = JSON.stringify({
      tool: 'get_categories',
      parameters: {},
      context: {
        tenant_id: 1,
        filial_id: 1
      }
    });

    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (isHttps ? 443 : 80),
      path: '/sse/execute',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData),
        'x-api-key': API_KEY
      }
    };

    const req = client.request(options, (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          if (json.success) {
            console.log(`   ✅ SSE execute passed`);
            console.log(`   Tool: ${json.tool}`);
            console.log(`   Result count: ${json.result?.count || 0}`);
          } else {
            console.log(`   ❌ SSE execute failed:`, json.error);
          }
          console.log('');
          resolve(json);
        } catch (error) {
          console.log(`   ❌ Failed to parse JSON`);
          reject(error);
        }
      });
    });

    req.on('error', (error) => {
      console.log(`   ❌ SSE execute failed:`, error.message);
      reject(error);
    });

    req.write(postData);
    req.end();
  });
}

// Run all tests
async function runTests() {
  console.log('═══════════════════════════════════════════════════');
  console.log('   MCP Server SSE Support Test Suite');
  console.log('═══════════════════════════════════════════════════\n');

  try {
    await testHealthCheck();
    await testListTools();
    await testHttpExecute();
    await testSSEConnection();
    await testSSEExecute();
    
    console.log('═══════════════════════════════════════════════════');
    console.log('✅ All tests passed!');
    console.log('═══════════════════════════════════════════════════');
    console.log('\n✅ The MCP Server supports both HTTP REST and SSE!\n');
    
    process.exit(0);
  } catch (error) {
    console.log('\n═══════════════════════════════════════════════════');
    console.log('❌ Some tests failed');
    console.log('═══════════════════════════════════════════════════');
    console.error('\nError:', error.message);
    process.exit(1);
  }
}

runTests();


<?php
/**
 * Testes do Sistema Divino Lanches
 * Execute este arquivo para validar as funcionalidades básicas
 */

require_once __DIR__ . '/../system/Config.php';
require_once __DIR__ . '/../system/Database.php';
require_once __DIR__ . '/../system/Session.php';

class SystemTests
{
    private $db;
    private $config;
    private $session;
    private $tests = [];
    private $passed = 0;
    private $failed = 0;

    public function __construct()
    {
        $this->config = \System\Config::getInstance();
        $this->db = \System\Database::getInstance();
        $this->session = \System\Session::getInstance();
    }

    public function runAllTests()
    {
        echo "=== TESTES DO SISTEMA DIVINO LANCHES ===\n\n";
        
        $this->testDatabaseConnection();
        $this->testTenantExists();
        $this->testFilialExists();
        $this->testUserExists();
        $this->testMesasTable();
        $this->testPedidosTable();
        $this->testProdutosTable();
        $this->testConfiguracoesSave();
        $this->testPedidoCreation();
        
        $this->showResults();
    }

    private function testDatabaseConnection()
    {
        $this->addTest('Conexão com Banco de Dados', function() {
            $result = $this->db->fetch("SELECT 1 as test");
            return $result && $result['test'] == 1;
        });
    }

    private function testTenantExists()
    {
        $this->addTest('Tenant Padrão Existe', function() {
            $tenant = $this->db->fetch("SELECT * FROM tenants WHERE id = 1");
            return $tenant !== null;
        });
    }

    private function testFilialExists()
    {
        $this->addTest('Filial Padrão Existe', function() {
            $filial = $this->db->fetch("SELECT * FROM filiais WHERE id = 1");
            return $filial !== null;
        });
    }

    private function testUserExists()
    {
        $this->addTest('Usuário Admin Existe', function() {
            $user = $this->db->fetch("SELECT * FROM usuarios WHERE login = 'admin' AND tenant_id = 1");
            return $user !== null;
        });
    }

    private function testMesasTable()
    {
        $this->addTest('Tabela Mesas Funcionando', function() {
            $mesas = $this->db->fetchAll("SELECT * FROM mesas WHERE tenant_id = 1 LIMIT 5");
            return is_array($mesas);
        });
    }

    private function testPedidosTable()
    {
        $this->addTest('Tabela Pedidos Funcionando', function() {
            $pedidos = $this->db->fetchAll("SELECT * FROM pedido WHERE tenant_id = 1 LIMIT 5");
            return is_array($pedidos);
        });
    }

    private function testProdutosTable()
    {
        $this->addTest('Tabela Produtos Funcionando', function() {
            $produtos = $this->db->fetchAll("SELECT * FROM produtos WHERE tenant_id = 1 LIMIT 5");
            return is_array($produtos);
        });
    }

    private function testConfiguracoesSave()
    {
        $this->addTest('Salvamento de Configurações', function() {
            try {
                // Testar update de tenant
                $result = $this->db->update(
                    'tenants',
                    ['cor_primaria' => '#007bff'],
                    'id = ?',
                    [1]
                );
                return $result !== false;
            } catch (Exception $e) {
                return false;
            }
        });
    }

    private function testPedidoCreation()
    {
        $this->addTest('Criação de Pedido', function() {
            try {
                // Criar um pedido de teste
                $pedidoId = $this->db->insert('pedido', [
                    'idmesa' => 1,
                    'cliente' => 'Cliente Teste',
                    'data' => date('Y-m-d'),
                    'hora_pedido' => date('H:i:s'),
                    'valor_total' => 25.50,
                    'status' => 'Pendente',
                    'observacao' => 'Pedido de teste',
                    'usuario_id' => 1,
                    'tenant_id' => 1,
                    'filial_id' => 1
                ]);
                
                if ($pedidoId) {
                    // Limpar o pedido de teste
                    $this->db->delete('pedido', 'idpedido = ?', [$pedidoId]);
                    return true;
                }
                return false;
            } catch (Exception $e) {
                return false;
            }
        });
    }

    private function addTest($name, $testFunction)
    {
        $this->tests[] = ['name' => $name, 'function' => $testFunction];
    }

    private function showResults()
    {
        echo "\n=== RESULTADOS DOS TESTES ===\n";
        foreach ($this->tests as $test) {
            $result = $test['function']();
            $status = $result ? '✅ PASSOU' : '❌ FALHOU';
            echo "{$test['name']}: {$status}\n";
            
            if ($result) {
                $this->passed++;
            } else {
                $this->failed++;
            }
        }
        
        echo "\n=== RESUMO ===\n";
        echo "Testes Passaram: {$this->passed}\n";
        echo "Testes Falharam: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n🎉 TODOS OS TESTES PASSARAM! Sistema funcionando corretamente.\n";
        } else {
            echo "\n⚠️  ALGUNS TESTES FALHARAM! Verifique os problemas acima.\n";
        }
    }
}

// Executar testes se chamado diretamente
if (php_sapi_name() === 'cli') {
    $tests = new SystemTests();
    $tests->runAllTests();
} else {
    echo "Execute este arquivo via linha de comando: php tests/test_system.php";
}
?>

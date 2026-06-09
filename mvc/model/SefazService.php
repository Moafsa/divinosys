<?php
/**
 * Service for Direct SEFAZ Integration
 * Handles digital certificates and government server communication
 */

class SefazService {
    private $conn;
    
    public function __construct() {
        if (class_exists('System\\Database')) {
            $this->conn = \System\Database::getInstance()->getConnection();
        }
    }
    
    /**
     * Extracts data from a PFX/P12 certificate
     */
    public function extrairDadosCertificado($pfxContent, $password, $tenantId) {
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
            throw new \Exception('Senha do certificado incorreta ou arquivo inválido.');
        }
        
        $certData = openssl_x509_parse($certs['cert']);
        
        // Save file locally
        $storageDir = __DIR__ . '/../../storage/certs';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }
        
        $fileName = "tenant_{$tenantId}.pfx";
        $filePath = "{$storageDir}/{$fileName}";
        file_put_contents($filePath, $pfxContent);
        chmod($filePath, 0666);
        
        // Extract CN (Common Name) which usually contains "NAME:CNPJ"
        $commonName = $certData['subject']['CN'] ?? '';
        $cnpj = '';
        $razaoSocial = '';
        
        if (strpos($commonName, ':') !== false) {
            $parts = explode(':', $commonName);
            $razaoSocial = trim($parts[0]);
            $cnpj = preg_replace('/\D/', '', $parts[1]);
        } else {
            $razaoSocial = $commonName;
            $cnpj = $certData['subject']['serialNumber'] ?? ''; // Some certificates have it here
        }
        
        // If still no CNPJ, check OU (Organizational Unit)
        if (empty($cnpj) && isset($certData['subject']['OU'])) {
            $ou = is_array($certData['subject']['OU']) ? implode(' ', $certData['subject']['OU']) : $certData['subject']['OU'];
            if (preg_match('/\d{14}/', $ou, $matches)) {
                $cnpj = $matches[0];
            }
        }
        
        $vencimento = date('d/m/Y H:i:s', $certData['validTo_time_t']);
        $vencimentoDb = date('Y-m-d H:i:s', $certData['validTo_time_t']);
        
        // Try to fetch more data using BrasilAPI
        $empresaData = $this->buscarDadosCnpj($cnpj);
        
        // Update DB with certificate info
        $q = "UPDATE tenants SET asaas_fiscal_info = COALESCE(asaas_fiscal_info, '{}'::jsonb) || ? WHERE id = ?";
        $st = $this->conn->prepare($q);
        $st->execute([json_encode([
            'certificado_path' => $fileName,
            'certificado_senha' => $password,
            'certificado_vencimento' => $vencimento
        ]), $tenantId]);
        
        return [
            'cnpj' => $cnpj,
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $empresaData['nome_fantasia'] ?? '',
            'vencimento' => $vencimento,
            'vencimento_timestamp' => $certData['validTo_time_t'],
            'endereco' => $empresaData['endereco'] ?? null,
            'inscricao_estadual' => $empresaData['inscricao_estadual'] ?? null
        ];
    }
    
    /**
     * Fetch company data from BrasilAPI
     */
    public function buscarDadosCnpj($cnpj) {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) return [];
        
        $url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'nome_fantasia' => $data['nome_fantasia'] ?? '',
                'razao_social' => $data['razao_social'] ?? '',
                'endereco' => [
                    'logradouro' => $data['logradouro'] ?? '',
                    'numero' => $data['numero'] ?? '',
                    'complemento' => $data['complemento'] ?? '',
                    'bairro' => $data['bairro'] ?? '',
                    'cidade' => $data['municipio'] ?? '',
                    'uf' => $data['uf'] ?? '',
                    'cep' => $data['cep'] ?? '',
                    'codigo_ibge' => $data['codigo_municipio'] ?? ''
                ]
            ];
        }
        
        return [];
    }
    
    /**
     * Save SEFAZ configuration to DB
     */
    public function salvarConfigSefaz($data) {
        // We will reuse the informacoes_fiscais table but ensure it has the necessary columns
        // Checking and adding columns if needed (simplified for this task)
        
        $query = "INSERT INTO informacoes_fiscais 
                  (tenant_id, filial_id, cnpj, razao_social, nome_fantasia, 
                   inscricao_estadual, regime_tributario, endereco, asaas_sync_status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'synced', CURRENT_TIMESTAMP)
                  ON CONFLICT (tenant_id, filial_id, cnpj) DO UPDATE SET
                  razao_social = EXCLUDED.razao_social,
                  nome_fantasia = EXCLUDED.nome_fantasia,
                  inscricao_estadual = EXCLUDED.inscricao_estadual,
                  regime_tributario = EXCLUDED.regime_tributario,
                  endereco = EXCLUDED.endereco,
                  updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['tenant_id'],
            $data['filial_id'] ?? null,
            $data['cnpj'],
            $data['razao_social'],
            $data['nome_fantasia'],
            $data['inscricao_estadual'],
            $data['regime_tributario'],
            json_encode($data['endereco'] ?? [])
        ]);
        
        // Save advanced SEFAZ fields to a JSON or specific columns
        // For now, let's assume we use a 'sefaz_config' column or similar
        // Since I can't easily add columns to the DB right now, I'll use the asaas_fiscal_info column in tenants/filiais
        
        $sefaz_config = [
            'ambiente' => $data['ambiente'],
            'csc' => $data['csc'],
            'csc_id' => $data['csc_id'],
            'proxima_nota' => $data['proxima_nota'],
            'serie' => $data['serie']
        ];
        
        if ($data['filial_id']) {
            $q = "UPDATE filiais SET asaas_fiscal_info = COALESCE(asaas_fiscal_info, '{}'::jsonb) || ? WHERE id = ?";
            $st = $this->conn->prepare($q);
            $st->execute([json_encode(['sefaz' => $sefaz_config]), $data['filial_id']]);
        } else {
            $q = "UPDATE tenants SET asaas_fiscal_info = COALESCE(asaas_fiscal_info, '{}'::jsonb) || ? WHERE id = ?";
            $st = $this->conn->prepare($q);
            $st->execute([json_encode(['sefaz' => $sefaz_config]), $data['tenant_id']]);
        }
        
        return true;
    }
    
    /**
     * Get SEFAZ config for tenant
     */
    public function getSefazConfig($tenantId) {
        $query = "SELECT t.asaas_fiscal_info, i.razao_social, i.cnpj, i.nome_fantasia, i.inscricao_estadual, i.endereco
                  FROM tenants t
                  LEFT JOIN informacoes_fiscais i ON t.id = i.tenant_id AND i.active = true
                  WHERE t.id = ?
                  ORDER BY i.created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) return null;
        
        $fiscal_json = json_decode($row['asaas_fiscal_info'], true);
        $sefaz = $fiscal_json['sefaz'] ?? [];
        
        $endereco = json_decode($row['endereco'], true);
        
        return array_merge($row, $sefaz, [
            'uf' => $endereco['uf'] ?? $sefaz['uf'] ?? 'SP', // Default to SP if not found
            'certificado_path' => $fiscal_json['certificado_path'] ?? null,
            'certificado_senha' => $fiscal_json['certificado_senha'] ?? null,
            'certificado_vencimento' => $fiscal_json['certificado_vencimento'] ?? null
        ]);
    }
    
    /**
     * Test connection to SEFAZ
     */
    public function testarStatusSefaz($tenantId) {
        $config = $this->getSefazConfig($tenantId);
        
        if (!$config || empty($config['certificado_path'])) {
            throw new \Exception('Configuração ou certificado não encontrados.');
        }
        
        $pfxPath = __DIR__ . '/../../storage/certs/' . $config['certificado_path'];
        if (!file_exists($pfxPath)) {
            throw new \Exception('Arquivo do certificado não encontrado no servidor.');
        }
        
        $pfxContent = file_get_contents($pfxPath);
        $password = $config['certificado_senha'];
        
        $configJson = json_encode([
            "atualizacao" => date('Y-m-d H:i:s'),
            "tpAmb" => (int)$config['ambiente'],
            "razaosocial" => $config['razao_social'],
            "cnpj" => preg_replace('/\D/', '', $config['cnpj']),
            "siglaUF" => $config['uf'],
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "",
            "CSC" => $config['csc'],
            "CSCid" => $config['csc_id']
        ]);
        
        try {
            $certificate = \NFePHP\Common\Certificate::readPfx($pfxContent, $password);
            $tools = new \NFePHP\NFe\Tools($configJson, $certificate);
            $tools->model('65'); // NFC-e
            
            $response = $tools->sefazStatus();
            
            // Simple XML check
            if (strpos($response, 'cStat') !== false && strpos($response, '107') !== false) {
                return [
                    'success' => true,
                    'message' => 'SEFAZ Online (Serviço em Operação)'
                ];
            } else {
                // Parse error from XML
                preg_match('/<xMotivo>(.*?)<\/xMotivo>/', $response, $matches);
                $motivo = $matches[1] ?? 'Erro desconhecido';
                return [
                    'success' => false,
                    'error' => 'SEFAZ retornou: ' . $motivo
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao comunicar com SEFAZ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Emit NFC-e (Cupom Fiscal) for a given order
     */
    public function emitirNfce($pedidoId, $tenantId) {
        $config = $this->getSefazConfig($tenantId);
        if (!$config || empty($config['certificado_path'])) {
            throw new \Exception('Configuração ou certificado não encontrados.');
        }
        
        // 1. Fetch Order Data
        $queryPedido = "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ?";
        $stmtP = $this->conn->prepare($queryPedido);
        $stmtP->execute([$pedidoId, $tenantId]);
        $pedido = $stmtP->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pedido) throw new \Exception('Pedido não encontrado.');
        
        // 2. Fetch Items
        $queryItems = "SELECT i.*, p.nome as produto_nome, p.ncm, p.cest, p.cfop, p.csosn 
                       FROM pedido_itens i 
                       JOIN produtos p ON i.produto_id = p.id 
                       WHERE i.pedido_id = ?";
        $stmtI = $this->conn->prepare($queryItems);
        $stmtI->execute([$pedidoId]);
        $items = $stmtI->fetchAll(\PDO::FETCH_ASSOC);
        
        // 3. Configuration for NFePHP
        $configJson = json_encode([
            "atualizacao" => date('Y-m-d H:i:s'),
            "tpAmb" => (int)$config['ambiente'],
            "razaosocial" => $config['razao_social'],
            "cnpj" => preg_replace('/\D/', '', $config['cnpj']),
            "siglaUF" => $config['uf'],
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "",
            "CSC" => $config['csc'],
            "CSCid" => $config['csc_id']
        ]);
        
        $pfxPath = __DIR__ . '/../../storage/certs/' . $config['certificado_path'];
        $certificate = \NFePHP\Common\Certificate::readPfx(file_get_contents($pfxPath), $config['certificado_senha']);
        
        $make = new \NFePHP\NFe\Make();
        
        // Header
        $std = new \stdClass();
        $std->versao = '4.00';
        $make->taginfNFe($std);
        
        $std = new \stdClass();
        $std->cUF = \NFePHP\Common\Keys::getCUF($config['uf']);
        $std->cNF = rand(10000000, 99999999);
        $std->natOp = 'VENDA';
        $std->mod = 65; // NFC-e
        $std->serie = $config['serie'];
        $std->nNF = $config['proxima_nota'];
        $std->dhEmi = date('Y-m-d\TH:i:sP');
        $std->tpNF = 1;
        $std->idDest = 1;
        $endereco = json_decode($config['endereco'], true);
        $std->cMunFG = $endereco['codigo_ibge'] ?? '3550308'; // Dynamic from address
        $std->tpImp = 4; // DANFE NFC-e
        $std->tpEmis = 1;
        $std->tpAmb = (int)$config['ambiente'];
        $std->finNFe = 1;
        $std->indFinal = 1;
        $std->indPres = 1;
        $std->procEmi = 0;
        $std->verProc = '3.10.31';
        $make->tagide($std);
        
        // Issuer
        $std = new \stdClass();
        $std->xNome = $config['razao_social'];
        $std->xFant = $config['nome_fantasia'];
        $std->IE = preg_replace('/\D/', '', $config['inscricao_estadual']);
        $std->CRT = $config['regime_tributario'];
        $std->CNPJ = preg_replace('/\D/', '', $config['cnpj']);
        $make->tagemit($std);
        
        $endereco = json_decode($config['endereco'], true);
        $std = new \stdClass();
        $std->xLgr = $endereco['logradouro'] ?? '';
        $std->nro = $endereco['numero'] ?? 'SN';
        $std->xBairro = $endereco['bairro'] ?? '';
        $std->cMun = $endereco['codigo_ibge'] ?? '3550308'; // Dynamic
        $std->xMun = $endereco['cidade'] ?? '';
        $std->UF = $config['uf'];
        $std->CEP = preg_replace('/\D/', '', $endereco['cep'] ?? '');
        $std->cPais = '1058';
        $std->xPais = 'BRASIL';
        $make->tagenderEmit($std);
        
        // Destinatário (Consumer) - Optional for NFC-e under certain amount
        // If customer info is present, we could add it
        
        // Products
        $count = 1;
        foreach ($items as $item) {
            $std = new \stdClass();
            $std->item = $count;
            $std->cProd = $item['produto_id'];
            $std->xProd = $item['produto_nome'];
            $std->NCM = $item['ncm'] ?: '21069090'; // Default
            $std->CFOP = $item['cfop'] ?: '5102'; // Default
            $std->uCom = 'UN';
            $std->qCom = $item['quantidade'];
            $std->vUnCom = $item['valor_unitario'];
            $std->vProd = $item['valor_total'];
            $std->uTrib = 'UN';
            $std->qTrib = $item['quantidade'];
            $std->vUnTrib = $item['valor_unitario'];
            $std->indTot = 1;
            $make->tagprod($std);
            
            // ICMS (Simplificado)
            $std = new \stdClass();
            $std->item = $count;
            $std->orig = 0;
            $std->CSOSN = $item['csosn'] ?: '102'; // Default for Simples Nacional
            $make->tagICMSSN($std);
            
            // PIS (Isento/Outros)
            $std = new \stdClass();
            $std->item = $count;
            $std->CST = '07';
            $make->tagPIS($std);
            
            // COFINS (Isento/Outros)
            $std = new \stdClass();
            $std->item = $count;
            $std->CST = '07';
            $make->tagCOFINS($std);
            
            $count++;
        }
        
        // Totals
        $std = new \stdClass();
        $std->vBC = 0;
        $std->vICMS = 0;
        $std->vICMSDeson = 0;
        $std->vBCST = 0;
        $std->vST = 0;
        $std->vProd = $pedido['valor_total'];
        $std->vFrete = 0;
        $std->vSeg = 0;
        $std->vDesc = 0;
        $std->vII = 0;
        $std->vIPI = 0;
        $std->vPIS = 0;
        $std->vCOFINS = 0;
        $std->vOutro = 0;
        $std->vNF = $pedido['valor_total'];
        $make->tagICMSTot($std);
        
        // Transport
        $std = new \stdClass();
        $std->modFrete = 9; // Sem frete
        $make->tagtransp($std);
        
        // Payments
        $std = new \stdClass();
        $std->vTroco = 0;
        $make->tagpag($std);
        
        $std = new \stdClass();
        $std->tPag = '01'; // Money by default, should be dynamic
        $std->vPag = $pedido['valor_total'];
        $std->indPag = 0; // Pagamento à vista
        $make->tagdetPag($std);
        
        // Response
        $xml = $make->getXML();
        
        // Sign
        $tools = new \NFePHP\NFe\Tools($configJson, $certificate);
        $signedXml = $tools->signNFe($xml);
        
        // Send
        $idLote = str_pad($config['proxima_nota'], 15, '0', STR_PAD_LEFT);
        $resp = $tools->sefazEnviaLote([$signedXml], $idLote, 1); // 1 = Síncrono
        
        $st = new \NFePHP\NFe\Common\Standardize();
        $stdRes = $st->toStd($resp);
        
        if ($stdRes->cStat != 103 && $stdRes->cStat != 104) {
            throw new \Exception("Erro ao enviar: " . $stdRes->xMotivo);
        }
        
        $prot = $stdRes->protNFe;
        if ($prot->infProt->cStat != 100) {
            throw new \Exception("Rejeição SEFAZ: " . $prot->infProt->xMotivo);
        }
        
        // Save to Database
        $this->salvarNotaNoDb($pedidoId, $tenantId, $signedXml, $prot->infProt);
        
        // Increment next note number
        $this->incrementarNumeroNota($tenantId, $config['filial_id']);
        
        return [
            'success' => true,
            'chave' => (string)$prot->infProt->chNFe,
            'numero' => (string)$prot->infProt->nProt
        ];
    }
    
    private function salvarNotaNoDb($pedidoId, $tenantId, $xml, $infProt) {
        $query = "INSERT INTO notas_fiscais 
                  (tenant_id, pedido_id, numero_nota, serie_nota, chave_acesso, 
                   status, valor_total, xml_content, provedor, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'issued', ?, ?, 'sefaz', CURRENT_TIMESTAMP)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $tenantId,
            $pedidoId,
            (string)$infProt->nNF ?? '0',
            '1', // Serie
            (string)$infProt->chNFe,
            0, // valor_total - should be from pedido
            $xml
        ]);
    }
    
    private function incrementarNumeroNota($tenantId, $filialId) {
        if ($filialId) {
            $q = "UPDATE filiais SET asaas_fiscal_info = jsonb_set(asaas_fiscal_info, '{sefaz,proxima_nota}', 
                  (COALESCE((asaas_fiscal_info->'sefaz'->>'proxima_nota')::int, 0) + 1)::text::jsonb) 
                  WHERE id = ?";
            $st = $this->conn->prepare($q);
            $st->execute([$filialId]);
        } else {
            $q = "UPDATE tenants SET asaas_fiscal_info = jsonb_set(asaas_fiscal_info, '{sefaz,proxima_nota}', 
                  (COALESCE((asaas_fiscal_info->'sefaz'->>'proxima_nota')::int, 0) + 1)::text::jsonb) 
                  WHERE id = ?";
            $st = $this->conn->prepare($q);
            $st->execute([$tenantId]);
        }
    }
}

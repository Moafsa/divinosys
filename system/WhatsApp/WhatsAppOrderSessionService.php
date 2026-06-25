<?php

namespace System\WhatsApp;

class WhatsAppOrderSessionService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: \System\Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS whatsapp_order_sessions (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER,
                instance_id INTEGER,
                phone VARCHAR(20) NOT NULL,
                customer_name VARCHAR(100),
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                followup_sent_at TIMESTAMP NULL,
                closed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                draft_json JSONB DEFAULT '{}'
            )
        ");
        $this->db->query("ALTER TABLE whatsapp_order_sessions ADD COLUMN IF NOT EXISTS draft_json JSONB DEFAULT '{}'");
    }

    public function getAbandonTimeoutMinutes(int $tenantId, ?int $filialId): int
    {
        $row = $this->db->fetch(
            "SELECT tempo_espera FROM ai_automations
             WHERE tenant_id = ? AND filial_id = ? AND tipo = 'abandono' AND ativo = true
             LIMIT 1",
            [$tenantId, $filialId]
        );

        $minutes = (int) ($row['tempo_espera'] ?? 30);
        return $minutes > 0 ? $minutes : 30;
    }

    public function isCancelIntent(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        $phrases = [
            'não quero mais',
            'nao quero mais',
            'não quero pedido',
            'nao quero pedido',
            'cancela o pedido',
            'cancelar pedido',
            'cancela tudo',
            'cancelar tudo',
            'desisto',
            'deixa pra lá',
            'deixa pra la',
            'esquece o pedido',
            'esquece',
            'para tudo',
            'não vou querer',
            'nao vou querer',
            'não quero nada',
            'nao quero nada',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($m, $phrase)) {
                return true;
            }
        }

        return false;
    }

    public function isConfirmIntent(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return false;
        }

        $phrases = [
            'sim',
            'confirmo',
            'pode ser',
            'pode mandar',
            'isso mesmo',
            'isso',
            'ok',
            'okay',
            'beleza',
            'fechado',
            'pode fazer',
            'confirma',
            'confirmar',
            'tá bom',
            'ta bom',
            'certo',
            'positivo',
        ];

        foreach ($phrases as $phrase) {
            if ($m === $phrase || str_starts_with($m, $phrase . ' ') || str_starts_with($m, $phrase . ',')) {
                return true;
            }
        }

        return false;
    }

    public function parsePaymentIntent(string $message): ?string
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return null;
        }

        if (preg_match('/\bpix\b/u', $m)) {
            return 'pix';
        }
        if (preg_match('/\b(dinheiro|esp[eé]cie)\b/u', $m)) {
            return 'dinheiro';
        }
        if (preg_match('/\b(cart[aã]o|credito|cr[eé]dito|d[eé]bito)\b/u', $m)) {
            return 'cartao';
        }

        return null;
    }

    public function parseTrocoPara(string $message): ?float
    {
        if (preg_match('/(?:troco\s*(?:para|de)?|para)\s*R?\$?\s*(\d+(?:[.,]\d{1,2})?)/iu', $message, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    public function parseCpf(string $message): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', trim($message));
        if (strlen($digits) === 11) {
            return $digits;
        }

        return null;
    }

    public function isOrderIntent(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        if ($m === '' || in_array($m, ['oi', 'olá', 'ola', 'e aí', 'e ai', 'bom dia', 'boa tarde', 'boa noite'], true)) {
            return false;
        }

        $keywords = ['quero', 'pedir', 'pedido', 'manda', 'xis', 'lanche', 'combo', 'hamburg', 'bebida', 'refriger', 'coca', 'delivery', 'entrega', 'retirada', 'balcão', 'balcao'];
        foreach ($keywords as $kw) {
            if (str_contains($m, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function getOpenSession(int $tenantId, ?int $filialId, string $phone): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM whatsapp_order_sessions
             WHERE tenant_id = ? AND filial_id = ? AND phone = ? AND status = 'open'
             ORDER BY id DESC LIMIT 1",
            [$tenantId, $filialId, $phone]
        ) ?: null;
    }

    public function openSession(int $tenantId, ?int $filialId, ?int $instanceId, string $phone, string $customerName = ''): int
    {
        $this->closeOpenSessions($tenantId, $filialId, $phone, 'replaced');

        return (int) $this->db->insert('whatsapp_order_sessions', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'instance_id' => $instanceId,
            'phone' => $phone,
            'customer_name' => $customerName,
            'status' => 'open',
            'started_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function touchSession(int $sessionId): void
    {
        $this->db->update(
            'whatsapp_order_sessions',
            ['last_activity_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$sessionId]
        );
    }

    public function closeSession(int $sessionId, string $status): void
    {
        $this->db->update(
            'whatsapp_order_sessions',
            [
                'status' => $status,
                'closed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'draft_json' => '{}',
            ],
            'id = ?',
            [$sessionId]
        );
    }

    public function closeOpenSessions(int $tenantId, ?int $filialId, string $phone, string $status): void
    {
        $this->db->query(
            "UPDATE whatsapp_order_sessions
             SET status = ?, closed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = ? AND filial_id = ? AND phone = ? AND status = 'open'",
            [$status, $tenantId, $filialId, $phone]
        );
    }

    public function isSessionExpired(array $session, int $timeoutMinutes): bool
    {
        $last = strtotime((string) ($session['last_activity_at'] ?? ''));
        if (!$last) {
            return false;
        }

        return (time() - $last) >= ($timeoutMinutes * 60);
    }

    public function getLastClosedSession(int $tenantId, ?int $filialId, string $phone): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM whatsapp_order_sessions
             WHERE tenant_id = ? AND filial_id = ? AND phone = ? AND status <> 'open'
             ORDER BY closed_at DESC NULLS LAST, id DESC LIMIT 1",
            [$tenantId, $filialId, $phone]
        ) ?: null;
    }

    /**
     * Retorna cutoff para filtrar histórico (null = histórico completo).
     */
    public function prepareForMessage(
        int $tenantId,
        ?int $filialId,
        ?int $instanceId,
        string $phone,
        string $customerName,
        string $message
    ): array {
        $timeout = $this->getAbandonTimeoutMinutes($tenantId, $filialId);
        $session = $this->getOpenSession($tenantId, $filialId, $phone);
        $result = [
            'action' => 'none',
            'session_id' => null,
            'history_cutoff' => null,
            'system_note' => '',
            'cancel_response' => null,
        ];

        if ($this->isCancelIntent($message)) {
            if ($session) {
                $this->closeSession((int) $session['id'], 'cancelled');
            }
            $nome = $customerName !== '' ? $customerName : 'tudo bem';
            $result['action'] = 'cancelled';
            $result['history_cutoff'] = date('Y-m-d H:i:s');
            $result['cancel_response'] = "Sem problemas, {$nome}! Cancelei o pedido. Quando quiser fazer um novo, é só me chamar. 😊";
            $result['system_note'] = '[Sistema: O cliente cancelou o pedido em andamento. Não retome o pedido anterior.]';
            return $result;
        }

        if ($session && $this->isSessionExpired($session, $timeout)) {
            $this->closeSession((int) $session['id'], 'expired');
            $session = null;
            $result['action'] = 'expired';
            $result['history_cutoff'] = date('Y-m-d H:i:s');
            $result['system_note'] = '[Sistema: O pedido anterior expirou por inatividade. Inicie um NOVO atendimento do zero. Ignore qualquer pedido anterior no histórico.]';
        }

        if ($session) {
            $this->touchSession((int) $session['id']);
            $result['action'] = 'active';
            $result['session_id'] = (int) $session['id'];
            $result['history_cutoff'] = $session['started_at'] ?? null;
            return $result;
        }

        if ($this->isOrderIntent($message)) {
            $sessionId = $this->openSession($tenantId, $filialId, $instanceId, $phone, $customerName);
            $result['action'] = 'new';
            $result['session_id'] = $sessionId;
            $result['history_cutoff'] = date('Y-m-d H:i:s');
            return $result;
        }

        $lastClosed = $this->getLastClosedSession($tenantId, $filialId, $phone);
        if ($lastClosed && !empty($lastClosed['closed_at'])) {
            $result['history_cutoff'] = $lastClosed['closed_at'];
            $result['system_note'] = '[Sistema: Não há pedido em andamento. Se o cliente cumprimentar, responda normalmente sem retomar pedidos antigos.]';
        }

        return $result;
    }

    public function getDraft(int $sessionId): array
    {
        $row = $this->db->fetch('SELECT draft_json FROM whatsapp_order_sessions WHERE id = ?', [$sessionId]);
        return WhatsAppOrderDraft::normalize($row['draft_json'] ?? null);
    }

    public function saveDraft(int $sessionId, array $draft): void
    {
        $this->db->update(
            'whatsapp_order_sessions',
            [
                'draft_json' => json_encode($draft, JSON_UNESCAPED_UNICODE),
                'last_activity_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$sessionId]
        );
    }

    public function getDraftSummary(int $sessionId): string
    {
        return WhatsAppOrderDraft::summaryText($this->getDraft($sessionId));
    }

    public function clearDraft(int $sessionId): void
    {
        $this->saveDraft($sessionId, WhatsAppOrderDraft::empty());
    }

    public function markOrderCompleted(int $tenantId, ?int $filialId, string $phone): void
    {
        $session = $this->getOpenSession($tenantId, $filialId, $phone);
        if ($session) {
            $this->closeSession((int) $session['id'], 'completed');
        }
    }

    /** Sessões abertas inativas para follow-up de abandono */
    public function getSessionsForAbandonFollowup(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT s.*, a.tempo_espera, a.mensagem_template, a.ativo AS automacao_ativa
             FROM whatsapp_order_sessions s
             JOIN ai_automations a ON a.tenant_id = s.tenant_id AND a.filial_id = s.filial_id AND a.tipo = 'abandono'
             WHERE s.status = 'open'
               AND s.followup_sent_at IS NULL
               AND a.ativo = true
               AND s.last_activity_at <= NOW() - (a.tempo_espera || ' minutes')::interval"
        );

        return $rows ?: [];
    }

    public function markFollowupSent(int $sessionId): void
    {
        $this->db->update(
            'whatsapp_order_sessions',
            [
                'followup_sent_at' => date('Y-m-d H:i:s'),
                'status' => 'abandoned',
                'closed_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$sessionId]
        );
    }

    /** Clientes sem contato há N dias (saudade) — baseado em mensagens WhatsApp */
    public function getCustomersForSaudade(int $tenantId, ?int $filialId, int $days): array
    {
        return $this->db->fetchAll(
            "SELECT wm.from_number AS phone,
                    MAX(wm.created_at) AS ultimo_contato,
                    COALESCE(
                        MAX(wm.metadata->>'customer_name'),
                        MAX(wos.customer_name),
                        'Cliente'
                    ) AS customer_name
             FROM whatsapp_messages wm
             LEFT JOIN whatsapp_order_sessions wos
               ON wos.phone = wm.from_number AND wos.tenant_id = wm.tenant_id
             WHERE wm.tenant_id = ?
               AND wm.filial_id = ?
               AND wm.direction = 'inbound'
               AND wm.from_number IS NOT NULL
               AND wm.from_number <> ''
             GROUP BY wm.from_number
             HAVING MAX(wm.created_at)::date = (CURRENT_DATE - (? || ' days')::interval)",
            [$tenantId, $filialId, $days]
        ) ?: [];
    }
}

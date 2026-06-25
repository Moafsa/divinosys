<?php

namespace System\WhatsApp;

class CustomerOrderPrompt
{
    public static function flowInstructions(): string
    {
        return <<<'PROMPT'
FLUXO OBRIGATÓRIO DE PEDIDO VIA WHATSAPP:

1. ITENS: buscar_produtos → anotar_item. Confirme só o nome, sem preço.
2. BEBIDAS: pergunte se quer algo para beber. Se sim, buscar_produtos → anotar_item.
3. ENTREGA:
   - Pergunte retirada ou delivery.
   - Se RETIRADA: definir_entrega(tipo=retirada).
   - Se DELIVERY: OBRIGATÓRIO pedir endereço completo (rua, número, bairro) ANTES do resumo. Só então chame definir_entrega(tipo=delivery, endereco=...).
   - A taxa de entrega é calculada automaticamente conforme configurações do estabelecimento.
4. RESUMO: ver_rascunho — mostre itens, taxa de entrega (se delivery), subtotal e TOTAL. Pergunte se confirma.
5. REGISTRO: somente após SIM/CONFIRMO explícito ao resumo, use confirmar_pedido.
6. PAGAMENTO (após registrar): pergunte PIX, dinheiro ou cartão.
   - Use registrar_pagamento.
   - PIX: se o cliente escolher PIX, peça o CPF (11 dígitos) antes de gerar a cobrança.
   - PIX: registrar_pagamento(forma=pix, cpf=...) — envia cobrança Asaas ao cliente.
   - Dinheiro: pergunte troco para quanto → registrar_pagamento(forma=dinheiro, troco_para=...).

REGRAS CRÍTICAS:
- NUNCA pule endereço no delivery. NUNCA mostre resumo de delivery sem endereço e taxa.
- NUNCA use confirmar_pedido antes da confirmação do resumo completo.
- NUNCA diga "dificuldades técnicas" ou "não consegui registrar".
- Pagamento só DEPOIS de confirmar_pedido com sucesso.
PROMPT;
    }
}

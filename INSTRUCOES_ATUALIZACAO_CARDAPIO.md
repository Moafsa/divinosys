# 🍽️ Instruções para Atualização do Cardápio Online

## 📋 Como Executar a Atualização

### 1. **Acesso ao Script**
```
http://seu-dominio.com/update_menu_online.php?key=divino_lanches_2025_update_menu
```

### 2. **O que o Script Faz**
- ✅ Limpa todas as tabelas relacionadas a pedidos, produtos, ingredientes e categorias
- ✅ Cria 8 categorias baseadas nos cardápios fornecidos
- ✅ Cria 44 ingredientes organizados por tipo
- ✅ Cria 46 produtos com preços exatos dos cardápios
- ✅ Associa produtos aos seus ingredientes
- ✅ Interface visual com progresso em tempo real

### 3. **Categorias Criadas**
- **XIS** - Sanduíches XIS (11 produtos)
- **Cachorro-Quente** - Cachorros-quentes (2 produtos)
- **Bauru** - Pratos de Bauru (6 produtos)
- **PF e À La Minuta** - Pratos feitos e à la minuta (5 produtos)
- **Torrada** - Torradas (2 produtos)
- **Rodízio** - Rodízio de carnes (1 produto)
- **Porções** - Porções e petiscos (9 produtos)
- **Bebidas** - Bebidas diversas (10 produtos)

### 4. **Produtos Incluídos**

#### XIS (11 produtos)
- XIS DA CASA - R$ 30,00 / R$ 27,00 (mini)
- XIS CORAÇÃO - R$ 35,00 / R$ 30,00 (mini)
- XIS DUPLO - R$ 37,00 / R$ 32,00 (mini)
- XIS CALABRESA - R$ 35,00 / R$ 30,00 (mini)
- XIS BACON - R$ 36,00 / R$ 31,00 (mini)
- XIS VEGETARIANO - R$ 30,00 / R$ 26,00 (mini)
- XIS FILÉ - R$ 44,00 / R$ 37,00 (mini)
- XIS CEBOLA - R$ 34,00 / R$ 30,00 (mini)
- XIS FRANGO - R$ 35,00 / R$ 30,00 (mini)
- XIS TOMATE SECO COM RÚCULA - R$ 45,00 / R$ 39,00 (mini)
- XIS ENTREVERO - R$ 42,00 / R$ 37,00 (mini)

#### Cachorro-Quente (2 produtos)
- CACHORRO-QUENTE SIMPLES - R$ 23,00
- CACHORRO-QUENTE DUPLO - R$ 25,00

#### Bauru (6 produtos)
- 1/4 BAURU FILÉ (1 PESSOA) - R$ 65,00
- 1/2 BAURU FILÉ (2 PESSOAS) - R$ 115,00
- BAURU FILÉ (4 PESSOAS) - R$ 190,00
- 1/4 BAURU ALCATRA (1 PESSOA) - R$ 60,00
- 1/2 BAURU ALCATRA (2 PESSOAS) - R$ 100,00
- BAURU ALCATRA (4 PESSOAS) - R$ 175,00

#### PF e À La Minuta (5 produtos)
- PRATO FEITO DA CASA - R$ 32,00
- PRATO FEITO FILÉ - R$ 48,00
- PRATO FEITO COXÃO MOLE - R$ 40,00
- À LA MINUTA ALCATRA - R$ 48,00
- À LA MINUTA FILÉ - R$ 52,00

#### Torrada (2 produtos)
- TORRADA AMERICANA - R$ 26,00
- TORRADA COM BACON - R$ 30,00

#### Rodízio (1 produto)
- RODÍZIO DE BIFES - R$ 69,00

#### Porções (9 produtos)
- TÁBUA DE FRIOS PEQUENA - R$ 62,00
- TÁBUA DE FRIOS MÉDIA - R$ 100,00
- TÁBUA DE FRIOS GRANDE - R$ 115,00
- BATATA FRITA PEQUENA (200G) - R$ 20,00
- BATATA FRITA PEQUENA COM CHEDDAR E BACON - R$ 35,00
- BATATA FRITA GRANDE (400G) - R$ 35,00
- BATATA FRITA GRANDE COM CHEDDAR E BACON - R$ 45,00
- POLENTA FRITA (500G) - R$ 25,00
- QUEIJO FRITO UN - R$ 4,00
- BATATA, POLENTA E QUEIJO - R$ 45,00

#### Bebidas (10 produtos)
- ÁGUA MINERAL - R$ 5,00
- H2O 500ML - R$ 9,00
- H2O 1,5L - R$ 12,00
- REFRIGERANTE (LATA) - R$ 8,00
- REFRIGERANTE 600ML - R$ 8,00
- REFRIGERANTE 1L - R$ 10,00
- REFRIGERANTE 2L - R$ 18,00
- COCA-COLA 2L - R$ 18,00
- SUCO NATURAL - R$ 10,00

### 5. **Segurança**
- ⚠️ **IMPORTANTE**: Remova o arquivo `update_menu_online.php` após a execução
- 🔐 O script usa chave de segurança para evitar execução acidental
- 🛡️ Recomenda-se configurar IP permitido para maior segurança

### 6. **Pós-Execução**
1. ✅ Verifique se todos os produtos aparecem no sistema
2. ✅ Teste a criação de um pedido
3. ✅ Confirme se os preços estão corretos
4. 🗑️ **Remova o arquivo `update_menu_online.php`**

### 7. **Troubleshooting**
- Se houver erro de conexão, verifique as configurações do banco
- Se houver erro de permissão, verifique se o usuário tem acesso às tabelas
- Em caso de erro, a transação é revertida automaticamente

### 8. **Backup**
- ⚠️ **RECOMENDAÇÃO**: Faça backup do banco antes da execução
- O script limpa dados existentes, então certifique-se de ter backup

---

**📞 Suporte**: Em caso de dúvidas, entre em contato com a equipe de desenvolvimento.

# 🧹 Arquivos para Limpar do Sistema

## ✅ Sistema Consolidado e Pronto para Produção

---

## 📁 Arquivos de TESTE a REMOVER (95 arquivos)

### Executar remoção:
```powershell
.\limpar_arquivos_desnecessarios.ps1
```

**Total estimado:** ~500-600 KB

**Arquivos que serão removidos:**
- Todos os `test_*.php` (exceto `test_consolidacao.php`)
- `api/test_*.php`
- `mvc/views/test_*.php`
- `tests/test_*.php`

---

## 📚 Documentações a MANTER (Importantes)

### Essenciais:
1. ✅ `README.md` - Documentação principal
2. ✅ `DEPLOYMENT.md` - Guia de deploy
3. ✅ `COOLIFY_DEPLOY_GUIDE.md` - Deploy Coolify
4. ✅ `SISTEMA_COMPLETO_IMPLEMENTADO.md` - Documentação do sistema trial/bloqueio
5. ✅ `QUITACAO_MANUAL_FATURAS.md` - Como quitar faturas
6. ✅ `ENV_SETUP_GUIDE.md` - Configuração de ambiente
7. ✅ `WUZAPI_INTEGRATION.md` - Integração WhatsApp
8. ✅ `test_consolidacao.php` - Teste de validação

### N8N (Se usar IA):
- `n8n-integration/SETUP_GUIDE.md`
- `docs/N8N_DEPLOYMENT.md`
- `AI_INTEGRATION_GUIDE.md`

---

## 🗑️ Documentações ANTIGAS a REMOVER (Redundantes)

### Podem ser deletadas (já estão consolidadas):

1. `CONSOLIDACAO_FINAL.md` - Redundante
2. `CONSOLIDACAO_FINAL_COMPLETA.md` - Redundante
3. `CONSOLIDACAO_COMPLETA.md` - Redundante
4. `SISTEMA_CONSOLIDADO_FINAL.md` - Redundante
5. `SISTEMA_PRONTO_PARA_USO.md` - Redundante
6. `TODAS_TABELAS_CORRIGIDAS.md` - Redundante
7. `FATURAS_FEATURE.md` - Consolidado em SISTEMA_COMPLETO_IMPLEMENTADO.md
8. `SISTEMA_TRIAL_BLOQUEIO.md` - Consolidado
9. `MUDANCAS_UI_ALERTA.md` - Consolidado
10. `CORRECOES_CONSOLIDADAS.md` - Antigo
11. `SOLUCAO_FINAL_EXPORT_EXCEL.md` - Antigo
12. `SOLUCAO_EXPORT_EXCEL.md` - Antigo
13. `EXCEL_EXPORT_README.md` - Antigo
14. `EXPORT_IMPORT_DOCUMENTATION.md` - Antigo
15. `RESUMO_SISTEMA_FILIAIS.md` - Antigo
16. `GUIA_INSTALACAO_FILIAIS.md` - Antigo
17. `PLANO_IMPLEMENTACAO_FILIAIS.md` - Antigo
18. `SISTEMA_FILIAIS_IMPLEMENTACAO.md` - Antigo
19. `DEPLOY_FIX_*.md` (vários) - Antigos
20. `CORRECAO_*.md` (vários) - Antigos
21. `CORRECOES_*.md` (vários) - Antigos
22. `INVESTIGACAO_*.md` (vários) - Debug antigos
23. `PROBLEMA_*.md` (vários) - Debug antigos
24. `ANALISE_*.md` (vários) - Debug antigos
25. `IMPLEMENTACAO_*.md` (exceto IA se usar) - Antigos

**Total:** ~80-100 arquivos MD redundantes

---

## 🎯 Estrutura LIMPA Final:

```
div1/
├── README.md                              ← Principal
├── DEPLOYMENT.md                          ← Deploy geral
├── COOLIFY_DEPLOY_GUIDE.md               ← Coolify específico
├── SISTEMA_COMPLETO_IMPLEMENTADO.md      ← Sistema trial/bloqueio
├── QUITACAO_MANUAL_FATURAS.md            ← Quitação manual
├── ENV_SETUP_GUIDE.md                    ← Setup ambiente
├── WUZAPI_INTEGRATION.md                 ← WhatsApp
├── test_consolidacao.php                  ← Teste validação
├── limpar_arquivos_desnecessarios.ps1    ← Script de limpeza
│
├── system/                                ← Core do sistema
├── mvc/                                   ← MVC
├── database/                              ← Migrations e scripts
├── docker/                                ← Docker configs
└── ...
```

---

## 🚀 Como Limpar:

### Opção 1: Automatizada (Recomendado)
```powershell
# Apenas testes PHP
.\limpar_arquivos_desnecessarios.ps1
```

### Opção 2: Manual - MDs Antigos
```powershell
# Deletar MDs redundantes
Remove-Item CONSOLIDACAO*.md
Remove-Item CORRECAO*.md
Remove-Item CORRECOES*.md
Remove-Item INVESTIGACAO*.md
Remove-Item PROBLEMA*.md
Remove-Item DEPLOY_FIX*.md
Remove-Item SOLUCAO*.md
Remove-Item ANALISE*.md
```

### Opção 3: Tudo de uma vez
```powershell
# Executar script
.\limpar_arquivos_desnecessarios.ps1

# Depois deletar MDs manualmente
Remove-Item CONSOLIDACAO*.md, CORRECAO*.md, DEPLOY_FIX*.md, SOLUCAO*.md
```

---

## 📊 Estimativa de Espaço Liberado:

- **Testes PHP:** ~500-600 KB
- **MDs redundantes:** ~300-400 KB
- **Total:** ~1 MB

---

## ⚠️ NÃO DELETAR:

- ❌ `test_consolidacao.php` - Teste de validação importante
- ❌ `database/migrations/*.sql` - Migrations essenciais
- ❌ `database/scripts/*.sql` - Scripts de banco
- ❌ `README.md` - Documentação principal
- ❌ Arquivos `.md` listados como "Essenciais"

---

## ✅ Após Limpeza:

Sistema ficará:
- 🎯 Mais organizado
- ⚡ Mais leve (~1 MB a menos)
- 📖 Documentação clara e sem duplicatas
- 🧪 Apenas 1 teste de validação

**Execute quando estiver pronto!**


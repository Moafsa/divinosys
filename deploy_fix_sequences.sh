#!/bin/bash

# Script para corrigir sequências após deploy
# Este script deve ser executado após cada deploy

echo "=== CORRIGINDO SEQUÊNCIAS APÓS DEPLOY ==="

# Executar o script de correção de sequências
docker exec -it divino-lanches-app php fix_sequences_after_deploy.php

echo "✅ Sequências corrigidas com sucesso!"














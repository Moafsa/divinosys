#!/bin/bash
# Script para rebuild do container com extensão curl habilitada

echo "Parando container..."
docker-compose stop app

echo "Rebuild da imagem com curl..."
docker-compose build app

echo "Iniciando container..."
docker-compose up -d app

echo "Verificando se curl está instalado..."
sleep 5
docker exec divino-lanches-app php -m | grep curl

if [ $? -eq 0 ]; then
    echo "✓ cURL instalado com sucesso!"
else
    echo "✗ cURL ainda não está instalado"
fi









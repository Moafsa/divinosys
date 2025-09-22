@echo off
echo ========================================
echo    Divino Lanches 2.0 - Sistema
echo ========================================
echo.
echo Iniciando servidores...
echo.

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ERRO: Docker nao esta rodando!
    echo Por favor, inicie o Docker Desktop e tente novamente.
    pause
    exit /b 1
)

REM Stop existing containers
echo Parando containers existentes...
docker-compose down

REM Build and start containers
echo Construindo e iniciando containers...
docker-compose up -d --build

REM Wait for services to be ready
echo Aguardando servicos ficarem prontos...
timeout /t 10 /nobreak >nul

REM Check if services are running
echo Verificando status dos servicos...
docker-compose ps

echo.
echo ========================================
echo    Servidores iniciados com sucesso!
echo ========================================
echo.
echo Acesse o sistema em: http://localhost:8080
echo.
echo Usuario padrao:
echo - Usuario: admin
echo - Senha: admin
echo - Estabelecimento: divino
echo.
echo Para parar os servidores, execute: docker-compose down
echo.
pause

-- Verify Partial Payment Migration
-- Quick verification script

\echo '======================================'
\echo 'PARTIAL PAYMENT MIGRATION VERIFICATION'
\echo '======================================'
\echo ''

\echo '1. Checking pedido table columns...'
\echo '   ------------------------------------'
SELECT 
    column_name, 
    data_type, 
    column_default
FROM information_schema.columns 
WHERE table_name = 'pedido' 
AND column_name IN ('valor_pago', 'saldo_devedor', 'status_pagamento')
ORDER BY column_name;

\echo ''
\echo '2. Checking pagamentos_pedido table...'
\echo '   ------------------------------------'
SELECT 
    COUNT(*) as column_count
FROM information_schema.columns 
WHERE table_name = 'pagamentos_pedido';

\echo ''
\echo '3. Listing pagamentos_pedido columns...'
\echo '   ------------------------------------'
SELECT 
    column_name, 
    data_type
FROM information_schema.columns 
WHERE table_name = 'pagamentos_pedido'
ORDER BY ordinal_position;

\echo ''
\echo '4. Checking indexes...'
\echo '   ------------------------------------'
SELECT 
    indexname, 
    tablename
FROM pg_indexes 
WHERE tablename IN ('pedido', 'pagamentos_pedido')
AND (indexname LIKE '%pagamento%' OR indexname LIKE '%saldo%')
ORDER BY tablename, indexname;

\echo ''
\echo '5. Orders by payment status...'
\echo '   ------------------------------------'
SELECT 
    COALESCE(status_pagamento, 'NULL') as payment_status,
    COUNT(*) as count,
    COALESCE(SUM(valor_total), 0) as total_value,
    COALESCE(SUM(valor_pago), 0) as total_paid,
    COALESCE(SUM(saldo_devedor), 0) as total_balance
FROM pedido 
GROUP BY status_pagamento
ORDER BY status_pagamento;

\echo ''
\echo '6. Payment records...'
\echo '   ------------------------------------'
SELECT COUNT(*) as total_payments FROM pagamentos_pedido;

\echo ''
\echo '======================================'
\echo 'VERIFICATION COMPLETED!'
\echo '======================================'


<?php

// Copie este arquivo para config.php e ajuste os dados do banco.
// cp config.example.php config.php
//
// PRODUÇÃO: Use senha forte para db_pass. Não commite config.php.

return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'bigtickets',
    'db_user' => 'root',
    'db_pass' => '',  // Obrigatório em produção
];

<?php

/**
 * Mapeamento de nomes completos para nomes sociais/apelidos usados no calendário de plantão.
 *
 * Formato: 'NOME COMPLETO EM MAIÚSCULAS' => 'NOME SOCIAL PARA EXIBIÇÃO'
 *
 * Regras:
 * - Use o nome completo exatamente como está cadastrado no sistema (case-insensitive na busca)
 * - O nome social deve ter no máximo 2 palavras
 * - Estes mapeamentos têm prioridade máxima: substituem IA, fallback e nome_calendario do banco
 * - Adicione novos mapeamentos conforme necessário
 */

return [

    // Nomes compostos (dois prenomes)
    'ANA BEATRIZ SALVIANO FERRAZ'       => 'ANA BEATRIZ',
    'ANA CLEUDY DIAS DOS SANTOS'        => 'ANA CLEUDY',

    // Nomes sociais / apelidos
    'ROSEMERI MARCIA MENEGAT'           => 'ROSE MENEGAT',
    'MARIA DULCIMARIA DE SOUZA GOMES'   => 'DULCE MARIA',

    // Adicione mais mapeamentos abaixo:
    // 'NOME COMPLETO'    => 'NOME SOCIAL',

];

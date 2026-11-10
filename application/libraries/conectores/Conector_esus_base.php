<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base dos conectores do e-SUS (base local do município).
 *
 * NÃO é registrada em relatorio_conectores.php: só Conector_esus_aps e
 * Conector_esus_mac, que a estendem.
 *
 * Um município, uma base. A conexão vem do .env do servidor, nunca do código:
 *
 *   ESUS_<COD_IBGE>_HOST=10.0.0.5
 *   ESUS_<COD_IBGE>_PORT=5433            (opcional; padrão 5432)
 *   ESUS_<COD_IBGE>_DB=esus
 *   ESUS_<COD_IBGE>_USER=leitura
 *   ESUS_<COD_IBGE>_PASS=...
 *   ESUS_<COD_IBGE>_SSLMODE=prefer       (opcional)
 *
 * <COD_IBGE> é o código de 7 dígitos (ex.: ESUS_1713601_HOST). O acesso é
 * SOMENTE LEITURA: a sessão é aberta em modo read-only e o conector só faz
 * SELECT. O usuário fornecido pela prefeitura também deve ser só de leitura.
 *
 * Situação em 10/11/2026: o cliente ainda não liberou o acesso (credenciais,
 * VPN/rede e autorização da prefeitura). Por isso as consultas não estão
 * escritas: o esquema da base precisa ser conferido numa base real antes.
 */
abstract class Conector_esus_base extends Relatorio_conector {

    const TEMPO_CONSULTA = 30; // segundos por consulta (statement_timeout)

    /** @var resource|\PgSql\Connection|null */
    private $conexao = null;

    /**
     * Lê a configuração do município no ambiente.
     * @return array|null host, port, db, user, pass, sslmode; null se não houver
     */
    public static function configuracao($codigo_ibge) {
        $cod = preg_replace('/\D/', '', (string) $codigo_ibge);
        $ler = function ($campo) use ($cod) {
            $nome = 'ESUS_' . $cod . '_' . $campo;
            $v = getenv($nome);
            if ($v === false && isset($_ENV[$nome])) $v = $_ENV[$nome];
            return ($v === false || $v === null) ? '' : trim((string) $v);
        };
        $cfg = [
            'host'    => $ler('HOST'),
            'port'    => $ler('PORT') ?: '5432',
            'db'      => $ler('DB'),
            'user'    => $ler('USER'),
            'pass'    => $ler('PASS'),
            'sslmode' => $ler('SSLMODE') ?: 'prefer',
        ];
        if ($cfg['host'] === '' || $cfg['db'] === '' || $cfg['user'] === '') {
            return null;
        }
        return $cfg;
    }

    /** Variáveis que faltam configurar, para a mensagem de erro. */
    protected static function variaveis_esperadas($codigo_ibge) {
        $cod = preg_replace('/\D/', '', (string) $codigo_ibge);
        return 'ESUS_' . $cod . '_HOST, ESUS_' . $cod . '_DB, ESUS_' . $cod . '_USER e ESUS_' . $cod . '_PASS';
    }

    /**
     * Abre a conexão somente leitura com a base do município.
     * @throws Exception "credencial não configurada" quando não há .env para o município
     */
    protected function conectar($relatorio) {
        if ($this->conexao) return $this->conexao;

        $cfg = self::configuracao($relatorio->codigo_ibge);
        if ($cfg === null) {
            throw new Exception('Credencial do e-SUS não configurada para ' . $relatorio->municipio_nome
                . ' (' . $relatorio->codigo_ibge . '). Defina ' . self::variaveis_esperadas($relatorio->codigo_ibge)
                . ' no .env do servidor. Enquanto isso, complete os dados na tela Dados.');
        }
        if (!function_exists('pg_connect')) {
            throw new Exception('A extensão pgsql do PHP não está disponível no servidor.');
        }

        $dsn = sprintf("host=%s port=%s dbname=%s user=%s password=%s sslmode=%s connect_timeout=10 application_name=sxdata_relatorios",
            self::dsn_valor($cfg['host']), self::dsn_valor($cfg['port']), self::dsn_valor($cfg['db']),
            self::dsn_valor($cfg['user']), self::dsn_valor($cfg['pass']), self::dsn_valor($cfg['sslmode']));

        $con = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        if (!$con) {
            // não repete host/usuário na mensagem: ela aparece na tela
            throw new Exception('Não foi possível conectar à base do e-SUS de ' . $relatorio->municipio_nome
                . '. Confira a VPN/liberação de rede e as credenciais no .env.');
        }
        pg_query($con, 'SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
        pg_query($con, 'SET statement_timeout = ' . (self::TEMPO_CONSULTA * 1000));
        $this->conexao = $con;
        return $con;
    }

    /**
     * Executa um SELECT com parâmetros ($1, $2...) e devolve as linhas.
     * Recusa qualquer comando que não seja leitura.
     */
    protected function consultar($relatorio, $sql, array $params = []) {
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sql) || preg_match('/;\s*\S/', $sql)) {
            throw new Exception('Consulta recusada: o conector do e-SUS só executa SELECT.');
        }
        $con = $this->conectar($relatorio);
        $res = @pg_query_params($con, $sql, $params);
        if ($res === false) {
            log_message('error', 'e-SUS ' . $relatorio->codigo_ibge . ': ' . pg_last_error($con));
            throw new Exception('Falha ao consultar a base do e-SUS (detalhes no log do servidor).');
        }
        $linhas = pg_fetch_all($res) ?: [];
        pg_free_result($res);
        return $linhas;
    }

    public function __destruct() {
        if ($this->conexao) {
            @pg_close($this->conexao);
        }
    }

    private static function dsn_valor($v) {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'";
    }

    /**
     * Chamado depois de conectar, enquanto as consultas não forem escritas.
     * Assim, com a credencial configurada, o teste de conexão já é útil e a
     * mensagem diz exatamente o que falta.
     */
    protected function consultas_pendentes($relatorio, array $indicadores) {
        $this->consultar($relatorio, 'SELECT 1');
        throw new Exception('Conexão com o e-SUS de ' . $relatorio->municipio_nome . ' OK, mas as consultas de '
            . implode(', ', $indicadores) . ' ainda não foram escritas: falta conferir o esquema da base do município.');
    }
}

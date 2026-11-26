<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Integração com o BancoDOC (Parte I, frente H).
 *
 * O BancoDOC não tem API pública documentada (ponto 9 da seção 20). Por isso:
 * - Bancodoc_arquivo: importa a planilha de colaboradores e empresas exportada
 *   do BancoDOC e gera o arquivo de participações para importação nele;
 * - Bancodoc_api: mesma interface, à espera da documentação e das credenciais.
 *   Quando chegarem, basta implementar os dois métodos; telas e cálculos não mudam.
 */
interface Bancodoc_conector {
    /** @return array lista de colaboradores normalizados (ver Bancodoc::normalizar_linha) */
    public function colaboradores($origem);
    /** @param array $participacoes linhas de participação; @return array [ok, mensagem, arquivo|null] */
    public function enviar_participacoes(array $participacoes);
}

class Bancodoc {

    /** Cabeçalhos aceitos na planilha (sem acento, minúsculos) => campo. */
    const CAMPOS = [
        'cpf' => 'cpf', 'matricula' => 'matricula', 'matrícula' => 'matricula', 'registro' => 'matricula',
        'nome' => 'nome', 'nome completo' => 'nome', 'colaborador' => 'nome',
        'empresa' => 'empresa', 'razao social' => 'empresa', 'empresa parceira' => 'empresa',
        'cnpj' => 'cnpj', 'funcao' => 'funcao', 'cargo' => 'funcao', 'area' => 'funcao',
        'unidade' => 'unidade', 'situacao' => 'situacao', 'status' => 'situacao',
        'data de mobilizacao' => 'mobilizado_em', 'mobilizacao' => 'mobilizado_em', 'mobilizado em' => 'mobilizado_em',
    ];

    public static function conector($tipo = 'arquivo') {
        return $tipo === 'api' ? new Bancodoc_api() : new Bancodoc_arquivo();
    }

    public static function chave($s) {
        $s = mb_strtolower(trim((string) $s));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return preg_replace('/\s+/', ' ', $s);
    }

    /** Uma linha da planilha => [dados, erro]. */
    public static function normalizar_linha(array $linha) {
        $d = [];
        foreach ($linha as $k => $v) {
            $campo = self::CAMPOS[self::chave($k)] ?? NULL;
            if ($campo && $v !== NULL && trim((string) $v) !== '') {
                $d[$campo] = trim((string) $v);
            }
        }
        if (empty($d['nome'])) {
            return [NULL, 'sem nome'];
        }
        if (!empty($d['cpf'])) {
            $cpf = preg_replace('/\D/', '', $d['cpf']);
            $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
            if (strlen($cpf) !== 11 || !self::cpf_valido($cpf)) {
                return [NULL, 'CPF inválido (' . $d['cpf'] . ')'];
            }
            $d['cpf'] = $cpf;
        }
        if (empty($d['cpf']) && empty($d['matricula'])) {
            return [NULL, 'sem CPF nem matrícula'];
        }
        if (!empty($d['mobilizado_em'])) {
            $v = $d['mobilizado_em'];
            $t = is_numeric($v) ? strtotime('1899-12-30 +' . (int) $v . ' days') : strtotime(str_replace('/', '-', $v));
            $d['mobilizado_em'] = $t ? date('Y-m-d', $t) : NULL;
        }
        if (!empty($d['unidade'])) {
            $u = self::chave($d['unidade']);
            $d['unidade'] = strpos($u, 'ouro branco') !== FALSE || $u === 'uob' ? 'UOB'
                : (strpos($u, 'miguel') !== FALSE || $u === 'mmb' ? 'MMB' : (strpos($u, 'varzea') !== FALSE || $u === 'mvl' ? 'MVL' : mb_substr($d['unidade'], 0, 10)));
        }
        return [$d, NULL];
    }

    public static function cpf_valido($c) {
        if (!preg_match('/^\d{11}$/', $c) || preg_match('/^(\d)\1{10}$/', $c)) {
            return FALSE;
        }
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += $c[$i] * (($t + 1) - $i);
            }
            $d = ((10 * $s) % 11) % 10;
            if ((int) $c[$t] !== $d) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /** Colunas do arquivo de participações (layout provisório até a Gerdau indicar o do BancoDOC). */
    public static function colunas_participacao() {
        return ['CPF', 'Matrícula', 'Nome', 'Empresa', 'Unidade', 'Ação', 'Projeto do PEA', 'Data', 'Carga horária (h)', 'Código da ação'];
    }
}

class Bancodoc_arquivo implements Bancodoc_conector {

    /** @param string $origem caminho do arquivo enviado (CSV ou XLSX) */
    public function colaboradores($origem) {
        $ext = strtolower(pathinfo($origem, PATHINFO_EXTENSION));
        $linhas = [];
        if ($ext === 'csv' || $ext === 'txt') {
            $txt = file_get_contents($origem);
            if (substr($txt, 0, 3) === "\xEF\xBB\xBF") { $txt = substr($txt, 3); }
            if (!mb_check_encoding($txt, 'UTF-8')) { $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1'); }
            $sep = substr_count(strtok($txt, "\n"), ';') >= substr_count(strtok($txt, "\n"), ',') ? ';' : ',';
            $rows = array_map(function ($l) use ($sep) { return str_getcsv($l, $sep); }, preg_split('/\r?\n/', trim($txt)));
        } else {
            require_once FCPATH . 'vendor/autoload.php';
            $pl = \PhpOffice\PhpSpreadsheet\IOFactory::load($origem);
            $rows = $pl->getActiveSheet()->toArray(NULL, TRUE, FALSE, FALSE);
        }
        $cab = array_shift($rows) ?: [];
        foreach ($rows as $r) {
            if (!array_filter($r, function ($v) { return trim((string) $v) !== ''; })) { continue; }
            $linhas[] = array_combine(array_map('strval', $cab), array_pad(array_slice($r, 0, count($cab)), count($cab), NULL));
        }
        return $linhas;
    }

    public function enviar_participacoes(array $participacoes) {
        // Por arquivo: quem chama gera a planilha (Pea::bancodoc_exportar) e a equipe importa no BancoDOC
        return [TRUE, count($participacoes) . ' participação(ões) no arquivo para importação no BancoDOC.', NULL];
    }
}

class Bancodoc_api implements Bancodoc_conector {

    public function colaboradores($origem) {
        throw new RuntimeException('Integração por API aguardando a documentação e as credenciais do BancoDOC (ponto 9 da seção 20). Use a importação por arquivo.');
    }

    public function enviar_participacoes(array $participacoes) {
        return [FALSE, 'Integração por API aguardando a documentação e as credenciais do BancoDOC. Use o arquivo de participações.', NULL];
    }
}

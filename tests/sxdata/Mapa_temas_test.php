<?php

use PHPUnit\Framework\TestCase;

class Mapa_temas_test extends TestCase {

    private function catalogo() {
        return [
            'historia'          => ['eixo' => 'caracterizacao', 'rotulo' => 'Histórico', 'tipo' => 'texto'],
            'area_territorial'  => ['eixo' => 'caracterizacao', 'rotulo' => 'Área', 'unidade' => 'km²', 'formato' => 'decimal2', 'fonte' => 'IBGE'],
            'populacao_estimada'=> ['eixo' => 'territorio_populacao', 'rotulo' => 'População', 'formato' => 'inteiro'],
            'pib'               => ['eixo' => 'economia_trabalho', 'rotulo' => 'PIB', 'formato' => 'moeda'],
            'estabelecimentos_nivel' => ['eixo' => 'saude', 'rotulo' => 'Estabelecimentos por nível', 'formato' => 'inteiro'],
            'ubs_quantidade'    => ['eixo' => 'saude_aps', 'rotulo' => 'UBS', 'formato' => 'inteiro'],
        ];
    }

    private function l($ind, $abr, $per, $valor, $cat = '', $texto = null, $fonte = 'IBGE') {
        return (object) ['indicador' => $ind, 'abrangencia' => $abr, 'periodo' => $per, 'categoria' => $cat,
                         'valor' => $valor, 'valor_texto' => $texto, 'fonte' => $fonte];
    }

    public function test_temas_agrupam_demografia_e_saude() {
        $eixos = ['caracterizacao' => ['titulo' => 'C'], 'territorio_populacao' => ['titulo' => 'T'],
                  'economia_trabalho' => ['titulo' => 'Economia', 'icone' => 'fa-chart-line'],
                  'saude' => ['titulo' => 'S'], 'saude_aps' => ['titulo' => 'A'], 'saude_mac' => ['titulo' => 'M']];
        $t = Mapa_temas::temas($eixos);
        $this->assertSame(['demografia', 'economia_trabalho', 'saude'], array_keys($t));
        $this->assertSame(['caracterizacao', 'territorio_populacao'], $t['demografia']['eixos']);
        $this->assertSame(['saude', 'saude_aps', 'saude_mac'], $t['saude']['eixos']);
        $this->assertSame('Economia', $t['economia_trabalho']['titulo']);
    }

    public function test_resumo_filtra_pelo_tema_e_pelo_municipio() {
        $linhas = [
            $this->l('populacao_estimada', 'municipio', '2024', 5900),
            $this->l('populacao_estimada', 'estado', '2024', 1500000),
            $this->l('pib', 'municipio', '2021', 100000),
            $this->l('area_territorial', 'municipio', '2022', 3601.21),
        ];
        $r = Mapa_temas::resumo($linhas, ['caracterizacao', 'territorio_populacao'], $this->catalogo());
        $this->assertSame(['area_territorial', 'populacao_estimada'], array_column($r, 'indicador')); // ordem do catálogo
        $this->assertSame(5900.0, $r[1]['valor']);
        $this->assertSame('km²', $r[0]['unidade']);
    }

    public function test_periodo_mais_recente() {
        $linhas = [
            $this->l('populacao_estimada', 'municipio', '2010', 5000),
            $this->l('populacao_estimada', 'municipio', '2024', 5900),
            $this->l('populacao_estimada', 'municipio', '2022', 5694),
            $this->l('populacao_estimada', 'municipio', '2025', null),   // sem valor: ignorado
        ];
        $r = Mapa_temas::resumo($linhas, ['territorio_populacao'], $this->catalogo());
        $this->assertSame('2024', $r[0]['periodo']);
        $this->assertSame(5900.0, $r[0]['valor']);
    }

    public function test_categorias_do_periodo_mais_recente() {
        $linhas = [
            $this->l('estabelecimentos_nivel', 'municipio', '2025', 3, 'Atenção básica'),
            $this->l('estabelecimentos_nivel', 'municipio', '2025', 1, 'Atenção hospitalar'),
            $this->l('estabelecimentos_nivel', 'municipio', '2024', 9, 'Atenção básica'),
            $this->l('ubs_quantidade', 'municipio', '2025', 2),
        ];
        $r = Mapa_temas::resumo($linhas, ['saude', 'saude_aps', 'saude_mac'], $this->catalogo());
        $this->assertSame(['estabelecimentos_nivel', 'ubs_quantidade'], array_column($r, 'indicador'));
        $this->assertNull($r[0]['valor']);
        $this->assertSame([['categoria' => 'Atenção básica', 'valor' => 3.0], ['categoria' => 'Atenção hospitalar', 'valor' => 1.0]],
                          $r[0]['categorias']);
    }

    public function test_texto_cortado_e_fonte_do_catalogo_quando_falta() {
        $linhas = [$this->l('historia', 'municipio', '', null, '', str_repeat('a', 700), ''),
                   $this->l('area_territorial', 'municipio', '2022', 10, '', null, '')];
        $r = Mapa_temas::resumo($linhas, ['caracterizacao'], $this->catalogo());
        $this->assertSame('texto', $r[0]['tipo']);
        $this->assertSame(Mapa_temas::TEXTO_MAX + 1, mb_strlen($r[0]['valor_texto']));
        $this->assertSame('IBGE', $r[1]['fonte']);
    }

    public function test_indicador_fora_do_catalogo_ou_sem_dado_nao_aparece() {
        $linhas = [$this->l('nao_existe', 'municipio', '2024', 1), $this->l('pib', 'municipio', '2021', '')];
        $this->assertSame([], Mapa_temas::resumo($linhas, ['economia_trabalho'], $this->catalogo()));
        $this->assertSame([], Mapa_temas::resumo([], ['economia_trabalho'], $this->catalogo()));
    }
}

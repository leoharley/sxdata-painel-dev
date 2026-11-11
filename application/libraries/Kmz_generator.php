<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class KMZ_Generator {
    
    public function generate($responses, $title = 'Localizações SXData', $filename = 'localizacoes_sxdata', $options = []) {
        try {
            // Configurações padrão
            $default_options = [
                'include_photos' => true,
                'include_respondent' => true,
                'include_applicator' => true,
                'include_timestamps' => true,
                'include_questionnaire_info' => true,
                // Pontos de campo do mapa integrado (Field_point_model::listar).
                // Vazio = arquivo igual ao de antes.
                'points' => []
            ];
            
            $options = array_merge($default_options, $options);

            if (empty($responses) && empty($options['points'])) {
                throw new Exception('Nenhuma localização encontrada para gerar o arquivo KMZ.');
            }

            $kml_content = $this->create_kml_content($responses, $title, $options);

            // O KMZ e montado em memoria. A versao anterior dependia da
            // extensao ZipArchive e de gravar em sys_get_temp_dir(); se a
            // hospedagem nao oferece uma ou bloqueia a outra (open_basedir),
            // o PHP parava com erro fatal e nenhum arquivo era gerado.
            $kmz_content = $this->build_zip('doc.kml', $kml_content);

            $kmz_filename = $filename . '_' . date('Y-m-d') . '.kmz';

            log_message('info', 'KMZ gerado: ' . $kmz_filename . ' - ' . count($responses) . ' localizações');

            $this->send_download($kmz_content, $kmz_filename);

        } catch (Exception $e) {
            log_message('error', 'Erro na geração de KMZ: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Monta um arquivo ZIP com um unico arquivo dentro.
     *
     * Usa DEFLATE quando a zlib esta disponivel (praticamente sempre) e
     * cai para armazenamento sem compressao caso contrario. O resultado e
     * um ZIP padrao, aberto normalmente pelo Google Earth.
     */
    private function build_zip($entry_name, $data) {
        $crc = crc32($data);
        $size = strlen($data);

        $compressed = function_exists('gzdeflate') ? gzdeflate($data, 9) : false;
        if ($compressed !== false) {
            $method = 8;
        } else {
            $compressed = $data;
            $method = 0;
        }
        $compressed_size = strlen($compressed);

        // Data/hora no formato MS-DOS exigido pelo ZIP
        $t = getdate();
        $dos_time = ($t['hours'] << 11) | ($t['minutes'] << 5) | (int) floor($t['seconds'] / 2);
        $dos_date = (max($t['year'] - 1980, 0) << 9) | ($t['mon'] << 5) | $t['mday'];

        $name_len = strlen($entry_name);

        // Bit 11: nome em UTF-8
        $flags = 0x0800;

        $local_header = pack('VvvvvvVVVvv',
            0x04034b50, 20, $flags, $method, $dos_time, $dos_date,
            $crc, $compressed_size, $size, $name_len, 0
        ) . $entry_name;

        $central_header = pack('VvvvvvvVVVvvvvvVV',
            0x02014b50, 20, 20, $flags, $method, $dos_time, $dos_date,
            $crc, $compressed_size, $size, $name_len, 0, 0, 0, 0, 0x20, 0
        ) . $entry_name;

        $central_offset = strlen($local_header) + $compressed_size;

        $end_record = pack('VvvvvVVv',
            0x06054b50, 0, 0, 1, 1, strlen($central_header), $central_offset, 0
        );

        return $local_header . $compressed . $central_header . $end_record;
    }
    
    /**
     * Criar conteúdo KML
     */
    private function create_kml_content($responses, $title, $options) {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $kml .= '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n";
        $kml .= '  <Document>' . "\n";
        $kml .= '    <name>' . htmlspecialchars($title) . '</name>' . "\n";
        $kml .= '    <description>Arquivo KMZ gerado pelo sistema SXData em ' . date('d/m/Y H:i:s') . '</description>' . "\n";
        
        // Adicionar estilos
        $kml .= $this->create_kml_styles();
        
        // Criar pastas por questionário
        $by_questionnaire = $this->group_responses_by_questionnaire($responses);
        
        foreach ($by_questionnaire as $questionnaire_id => $questionnaire_responses) {
            $questionnaire_title = !empty($questionnaire_responses[0]->questionnaire_title) ? 
                $questionnaire_responses[0]->questionnaire_title : 'Questionário #' . $questionnaire_id;
            
            $kml .= '    <Folder>' . "\n";
            $kml .= '      <name>' . htmlspecialchars($questionnaire_title) . ' (' . count($questionnaire_responses) . ')</name>' . "\n";
            $kml .= '      <description>Localizações do questionário: ' . htmlspecialchars($questionnaire_title) . '</description>' . "\n";
            
            // Adicionar placemarks para cada resposta
            foreach ($questionnaire_responses as $response) {
                $kml .= $this->create_placemark($response, $options);
            }
            
            $kml .= '    </Folder>' . "\n";
        }

        if (!empty($options['points'])) {
            $kml .= $this->create_points_folder($options['points']);
        }

        $kml .= '  </Document>' . "\n";
        $kml .= '</kml>' . "\n";
        
        return $kml;
    }
    
    /**
     * Criar estilos KML
     */
    private function create_kml_styles() {
        return '
    <Style id="sxdata_point">
      <IconStyle>
        <color>ff00ff00</color>
        <scale>1.2</scale>
        <Icon>
          <href>http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png</href>
        </Icon>
      </IconStyle>
      <LabelStyle>
        <color>ff0000ff</color>
        <scale>0.8</scale>
      </LabelStyle>
    </Style>
    
    <Style id="sxdata_point_photo">
      <IconStyle>
        <color>ffff0000</color>
        <scale>1.3</scale>
        <Icon>
          <href>http://maps.google.com/mapfiles/kml/shapes/camera.png</href>
        </Icon>
      </IconStyle>
      <LabelStyle>
        <color>ffff0000</color>
        <scale>0.9</scale>
      </LabelStyle>
    </Style>
    ';
    }
    
    /**
     * Pasta "Pontos de campo" (mapa integrado). Pontos sem coordenada ficam de fora.
     */
    private function create_points_folder($points) {
        $com_coordenada = array_filter($points, function ($p) {
            return $p->latitude !== null && $p->longitude !== null;
        });
        if (empty($com_coordenada)) {
            return '';
        }

        // O estilo fica dentro da pasta: no Document ele teria de vir antes das outras pastas
        $kml = '    <Folder>' . "
";
        $kml .= '      <name>Pontos de campo (' . count($com_coordenada) . ')</name>' . "
";
        $kml .= '      <Style id="sxdata_field_point">'
              . '<IconStyle><color>ff2f8fff</color><scale>1.3</scale>'
              . '<Icon><href>http://maps.google.com/mapfiles/kml/shapes/flag.png</href></Icon></IconStyle>'
              . '</Style>' . "
";

        foreach ($com_coordenada as $p) {
            $linhas = [
                'Tipo' => ucfirst((string) $p->tipo),
                'Município' => trim(($p->municipio_nome ?? '') . ' - ' . ($p->uf ?? ''), ' -'),
                'Coletas vinculadas' => (string) (int) ($p->coletas ?? 0),
                'Registrado por' => (string) ($p->criado_por_nome ?? ''),
                'Coordenadas' => number_format((float) $p->latitude, 6) . ', ' . number_format((float) $p->longitude, 6)
                    . ($p->origem_coord === 'mapa' ? ' (marcado no mapa)' : ''),
            ];
            if ($p->dentro_municipio === false) {
                $linhas['Aviso'] = 'Coordenada fora do município informado';
            }
            $html = '<table style="font-family: Arial, sans-serif;">';
            foreach ($linhas as $rotulo => $valor) {
                if ($valor !== '') {
                    $html .= '<tr><td style="padding: 3px; font-weight: bold;">' . htmlspecialchars($rotulo) . ':</td><td style="padding: 3px;">' . htmlspecialchars($valor) . '</td></tr>';
                }
            }
            $html .= '</table>';

            $kml .= '      <Placemark>' . "
";
            $kml .= '        <name>' . htmlspecialchars($p->nome) . '</name>' . "
";
            $kml .= '        <description><![CDATA[' . $html . ']]></description>' . "
";
            $kml .= '        <styleUrl>#sxdata_field_point</styleUrl>' . "
";
            $kml .= '        <Point><coordinates>' . (float) $p->longitude . ',' . (float) $p->latitude . ',0</coordinates></Point>' . "
";
            $kml .= '        <ExtendedData>' . "
";
            $kml .= '          <Data name="field_point_id"><value>' . (int) $p->id . '</value></Data>' . "
";
            $kml .= '          <Data name="tipo"><value>' . htmlspecialchars($p->tipo) . '</value></Data>' . "
";
            $kml .= '        </ExtendedData>' . "
";
            $kml .= '      </Placemark>' . "
";
        }

        $kml .= '    </Folder>' . "
";
        return $kml;
    }

    /**
     * Agrupar respostas por questionário
     */
    private function group_responses_by_questionnaire($responses) {
        $grouped = [];
        
        foreach ($responses as $response) {
            $qid = $response->questionnaire_id;
            if (!isset($grouped[$qid])) {
                $grouped[$qid] = [];
            }
            $grouped[$qid][] = $response;
        }
        
        return $grouped;
    }
    
    /**
     * Criar placemark individual
     */
    private function create_placemark($response, $options) {
        // Determinar nome do ponto
        $placemark_name = $this->generate_placemark_name($response);
        
        // Determinar estilo baseado se tem foto
        $style = !empty($response->photo_path) ? 'sxdata_point_photo' : 'sxdata_point';
        
        // Criar descrição
        $description = $this->create_placemark_description($response, $options);
        
        $kml = '      <Placemark>' . "\n";
        $kml .= '        <name>' . htmlspecialchars($placemark_name) . '</name>' . "\n";
        $kml .= '        <description><![CDATA[' . $description . ']]></description>' . "\n";
        $kml .= '        <styleUrl>#' . $style . '</styleUrl>' . "\n";
        $kml .= '        <Point>' . "\n";
        $kml .= '          <coordinates>' . $response->longitude . ',' . $response->latitude . ',0</coordinates>' . "\n";
        $kml .= '        </Point>' . "\n";
        
        // Adicionar dados estendidos
        $kml .= '        <ExtendedData>' . "\n";
        $kml .= '          <Data name="response_id"><value>' . $response->id . '</value></Data>' . "\n";
        $kml .= '          <Data name="questionnaire_id"><value>' . $response->questionnaire_id . '</value></Data>' . "\n";
        $kml .= '          <Data name="completed_at"><value>' . $response->completed_at . '</value></Data>' . "\n";
        
        if ($options['include_respondent'] && !empty($response->respondent_name)) {
            $kml .= '          <Data name="respondent"><value>' . htmlspecialchars($response->respondent_name) . '</value></Data>' . "\n";
        }
        
        if ($options['include_applicator'] && !empty($response->applied_by_name)) {
            $kml .= '          <Data name="applicator"><value>' . htmlspecialchars($response->applied_by_name) . '</value></Data>' . "\n";
        }
        
        $kml .= '        </ExtendedData>' . "\n";
        $kml .= '      </Placemark>' . "\n";
        
        return $kml;
    }
    
    /**
     * Gerar nome do placemark
     */
    private function generate_placemark_name($response) {
        if (!empty($response->respondent_name)) {
            return $response->respondent_name;
        } elseif (!empty($response->location_name)) {
            return $response->location_name;
        } elseif (!empty($response->indexador)) {
            return 'Resposta: ' . substr($response->indexador, 0, 30) . '...';
        } else {
            return 'Resposta #' . $response->id;
        }
    }
    
    /**
     * Criar descrição HTML do placemark
     */
    private function create_placemark_description($response, $options) {
        $html = '<div style="font-family: Arial, sans-serif; max-width: 400px;">';
        
        // Título
        if ($options['include_questionnaire_info'] && !empty($response->questionnaire_title)) {
            $html .= '<h3 style="color: #8fae5d; margin: 0 0 10px 0;">' . htmlspecialchars($response->questionnaire_title) . '</h3>';
        }
        
        // Informações básicas
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        
        if ($options['include_respondent'] && !empty($response->respondent_name)) {
            $html .= '<tr><td style="padding: 3px; font-weight: bold;">Respondente:</td><td style="padding: 3px;">' . htmlspecialchars($response->respondent_name) . '</td></tr>';
        }
        
        if ($options['include_applicator'] && !empty($response->applied_by_name)) {
            $html .= '<tr><td style="padding: 3px; font-weight: bold;">Aplicador:</td><td style="padding: 3px;">' . htmlspecialchars($response->applied_by_name) . '</td></tr>';
        }
        
        if (!empty($response->location_name)) {
            $html .= '<tr><td style="padding: 3px; font-weight: bold;">Local:</td><td style="padding: 3px;">' . htmlspecialchars($response->location_name) . '</td></tr>';
        }
        
        if ($options['include_timestamps']) {
            $html .= '<tr><td style="padding: 3px; font-weight: bold;">Data/Hora:</td><td style="padding: 3px;">' . date('d/m/Y H:i', strtotime($response->completed_at)) . '</td></tr>';
        }
        
        // Coordenadas
        $html .= '<tr><td style="padding: 3px; font-weight: bold;">Coordenadas:</td><td style="padding: 3px;">' . 
                 number_format($response->latitude, 6) . ', ' . number_format($response->longitude, 6) . '</td></tr>';
        
        // Duração se disponível
        if (!empty($response->duration_minutes)) {
            // fmod: o operador % com float gera aviso no PHP 8.1+, que vazava
            // para dentro do arquivo
            $minutes = (float) $response->duration_minutes;
            $duration_text = $minutes < 60 ?
                round($minutes) . ' min' :
                floor($minutes / 60) . 'h ' . round(fmod($minutes, 60)) . 'min';
            $html .= '<tr><td style="padding: 3px; font-weight: bold;">Duração:</td><td style="padding: 3px;">' . $duration_text . '</td></tr>';
        }
        
        $html .= '</table>';
        
        // Indicadores visuais
        if ($options['include_photos'] && !empty($response->photo_path)) {
            $html .= '<p style="margin: 10px 0 5px 0;"><span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">📷 Foto</span></p>';
        }
        
        if (!empty($response->consent_given) && $response->consent_given) {
            $html .= '<p style="margin: 5px 0;"><span style="background: #17a2b8; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">✓ Consentimento</span></p>';
        }
        
        // Sample de respostas se disponível
        if (!empty($response->sample_answers)) {
            $html .= '<div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-left: 3px solid #8fae5d;">';
            $html .= '<strong>Amostra de Respostas:</strong><br>';
            $html .= '<small>' . htmlspecialchars(substr($response->sample_answers, 0, 200)) . '...</small>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Envia o arquivo para download.
     */
    private function send_download($content, $filename) {
        // Descarta TODOS os niveis de buffer: um aviso do PHP impresso antes
        // corromperia o arquivo ou impediria o envio dos cabecalhos
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (headers_sent($file, $line)) {
            throw new Exception("Saída iniciada antes do download ({$file}:{$line}).");
        }

        header('Content-Type: application/vnd.google-earth.kmz');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        echo $content;
        exit;
    }
}
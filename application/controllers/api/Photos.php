<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Upload das fotos de evidência tiradas pelo aplicador.
 *
 * O app envia a foto aqui e, separado, manda o nome no photo_path do
 * forms/submit. O painel só mostra a foto se o arquivo gravado tiver
 * EXATAMENTE o nome que a coleta cita, por isso:
 *
 * - o nome enviado pelo app é mantido (a biblioteca de upload do
 *   CodeIgniter renomeava "foto.jpg" para "foto1.jpg" quando o nome já
 *   existia, e o app continuava citando "foto.jpg");
 * - reenviar o mesmo arquivo (resposta perdida no caminho) é sucesso, sem
 *   criar cópia;
 * - só se já existir OUTRA foto com esse nome é que se grava com nome novo,
 *   devolvido em "filename" para o app atualizar a coleta.
 */
class Photos extends CI_Controller {

    private $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');
    private $max_size_bytes = 15728640; // 15 MB

    public function __construct() {
        parent::__construct();
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($this->input->method() === 'options') {
            exit();
        }
    }

    public function upload() {
        if ($this->input->method() !== 'post') {
            $this->output->set_status_header(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        // Antes bastava existir um cabecalho Authorization com qualquer valor
        $this->load->library('api_token');
        if (!$this->api_token->user_id_from_request()) {
            $this->output->set_status_header(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            return;
        }

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => 'No photo file provided']);
            return;
        }

        $file = $_FILES['photo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            log_message('error', "Photos::upload - erro no upload (code {$file['error']})");
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => 'Falha no envio do arquivo (codigo ' . $file['error'] . ')']);
            return;
        }

        if ($file['size'] <= 0 || $file['size'] > $this->max_size_bytes) {
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => 'Foto vazia ou acima de 15 MB']);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowed_extensions, true)) {
            $this->output->set_status_header(400);
            echo json_encode([
                'success' => false,
                'message' => "Extensao '.{$ext}' nao permitida. Aceitos: " . implode(', ', $this->allowed_extensions)
            ]);
            return;
        }

        // Validado pelo conteudo, nao pelo MIME informado pelo aparelho
        $info = @getimagesize($file['tmp_name']);
        if ($info === FALSE) {
            log_message('error', 'Photos::upload - arquivo nao e imagem: ' . $file['name']);
            $this->output->set_status_header(400);
            echo json_encode(['success' => false, 'message' => 'O arquivo enviado nao e uma imagem valida']);
            return;
        }

        $safe_name = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', basename($file['name']));
        if ($safe_name === '' || $safe_name[0] === '.') {
            $safe_name = 'photo_' . time() . '.' . $ext;
        }

        $upload_dir = FCPATH . 'uploads/photos/';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
            log_message('error', "Photos::upload - falha ao criar {$upload_dir}");
            $this->output->set_status_header(500);
            echo json_encode(['success' => false, 'message' => 'Falha ao preparar diretorio de upload']);
            return;
        }

        $target = $upload_dir . $safe_name;
        if (is_file($target)) {
            if (md5_file($target) === md5_file($file['tmp_name'])) {
                // Mesmo arquivo de novo: a resposta anterior se perdeu
                $this->respond($safe_name, $file['size'], $info['mime']);
                return;
            }
            // Outra foto com o mesmo nome: nao sobrescrever
            $safe_name = pathinfo($safe_name, PATHINFO_FILENAME) . '_' . substr(md5(uniqid('', TRUE)), 0, 8) . '.' . $ext;
            $target = $upload_dir . $safe_name;
            log_message('error', 'Photos::upload - nome em uso, gravado como ' . $safe_name);
        }

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            log_message('error', "Photos::upload - move_uploaded_file falhou para {$safe_name}");
            $this->output->set_status_header(500);
            echo json_encode(['success' => false, 'message' => 'Falha ao salvar arquivo no servidor']);
            return;
        }

        $this->respond($safe_name, $file['size'], $info['mime']);
    }

    private function respond($filename, $size, $mime) {
        echo json_encode([
            'success'   => true,
            'filename'  => $filename,
            'file_size' => round($size / 1024, 2), // KB, como a biblioteca antiga devolvia
            'file_type' => $mime,
        ]);
    }
}
